<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

include '../includes/auth.php';
requireMemberAuth();
include '../includes/db.php';

$member_id = getCurrentMemberId();

if (!$member_id) {
    echo json_encode(['success' => false, 'message' => 'Member not authenticated']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

switch ($action) {
    case 'archive_attendance':
        $attendance_id = $input['attendance_id'] ?? 0;
        
        if (!$attendance_id) {
            echo json_encode(['success' => false, 'message' => 'Attendance ID required']);
            exit;
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Get attendance record
            $get_record = "SELECT * FROM member_attendance WHERE id = ? AND member_id = ?";
            $stmt = $conn->prepare($get_record);
            $stmt->bind_param('ii', $attendance_id, $member_id);
            $stmt->execute();
            $record = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$record) {
                throw new Exception('Attendance record not found');
            }
            
            // Insert into archive table
            $archive_sql = "INSERT INTO member_attendance_archive 
                           (member_id, date, time_in, time_out, archive_date) 
                           VALUES (?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($archive_sql);
            $stmt->bind_param('isss', 
                $record['member_id'], 
                $record['date'], 
                $record['time_in'], 
                $record['time_out']
            );
            $stmt->execute();
            $stmt->close();
            
            // Delete from main table
            $delete_sql = "DELETE FROM member_attendance WHERE id = ? AND member_id = ?";
            $stmt = $conn->prepare($delete_sql);
            $stmt->bind_param('ii', $attendance_id, $member_id);
            $stmt->execute();
            $stmt->close();
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Attendance record archived successfully']);
            
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Failed to archive: ' . $e->getMessage()]);
        }
        break;
        
    case 'restore_attendance':
        $archive_id = $input['archive_id'] ?? 0;
        
        if (!$archive_id) {
            echo json_encode(['success' => false, 'message' => 'Archive ID required']);
            exit;
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Get archived record
            $get_archive = "SELECT * FROM member_attendance_archive WHERE id = ? AND member_id = ?";
            $stmt = $conn->prepare($get_archive);
            $stmt->bind_param('ii', $archive_id, $member_id);
            $stmt->execute();
            $archive_record = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$archive_record) {
                throw new Exception('Archived record not found');
            }
            
            // Insert back to main table
            $restore_sql = "INSERT INTO member_attendance 
                           (member_id, date, time_in, time_out) 
                           VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($restore_sql);
            $stmt->bind_param('isss', 
                $archive_record['member_id'], 
                $archive_record['date'], 
                $archive_record['time_in'], 
                $archive_record['time_out']
            );
            $stmt->execute();
            $stmt->close();
            
            // Delete from archive
            $delete_archive = "DELETE FROM member_attendance_archive WHERE id = ? AND member_id = ?";
            $stmt = $conn->prepare($delete_archive);
            $stmt->bind_param('ii', $archive_id, $member_id);
            $stmt->execute();
            $stmt->close();
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Attendance record restored successfully']);
            
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Failed to restore: ' . $e->getMessage()]);
        }
        break;
        
    case 'get_archived_records':
        $page = $input['page'] ?? 1;
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $count_sql = "SELECT COUNT(*) as total FROM member_attendance_archive WHERE member_id = ?";
        $stmt = $conn->prepare($count_sql);
        $stmt->bind_param('i', $member_id);
        $stmt->execute();
        $total_records = $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();
        
        $records_sql = "SELECT * FROM member_attendance_archive 
                       WHERE member_id = ? 
                       ORDER BY archive_date DESC 
                       LIMIT ? OFFSET ?";
        $stmt = $conn->prepare($records_sql);
        $stmt->bind_param('iii', $member_id, $limit, $offset);
        $stmt->execute();
        $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'records' => $records,
            'total' => $total_records,
            'page' => $page,
            'total_pages' => ceil($total_records / $limit)
        ]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>
