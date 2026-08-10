<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

// Check if user is logged in as admin
if (!isAdminLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            createAnnouncement($conn, $_POST);
            break;
        case 'update':
            updateAnnouncement($conn, $_POST);
            break;
        case 'delete':
            deleteAnnouncement($conn, $_POST);
            break;
        case 'toggle_status':
            toggleAnnouncementStatus($conn, $_POST);
            break;
        case 'toggle_pin':
            toggleAnnouncementPin($conn, $_POST);
            break;
        case 'mark_viewed':
            markAnnouncementViewed($conn, $_POST);
            break;
        default:
            $response['message'] = 'Invalid action';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_announcements':
            getAnnouncements($conn, $_GET);
            break;
        case 'get_announcement':
            getAnnouncement($conn, $_GET);
            break;
        default:
            $response['message'] = 'Invalid action';
    }
}

// Create new announcement
function createAnnouncement($conn, $data) {
    global $response;
    
    try {
        $title = trim($data['title'] ?? '');
        $message = trim($data['message'] ?? '');
        $priority = $data['priority'] ?? 'medium';
        $target_audience = $data['target_audience'] ?? 'all';
        $expires_at = $data['expires_at'] ?? null;
        $is_pinned = isset($data['is_pinned']) ? 1 : 0;
        
        if (empty($title) || empty($message)) {
            $response['message'] = 'Title and message are required';
            return;
        }
        
        $admin_id = $_SESSION['admin_id'] ?? 1;
        
        $sql = "INSERT INTO announcements (title, message, priority, target_audience, created_by, expires_at, is_pinned) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssi", $title, $message, $priority, $target_audience, $admin_id, $expires_at, $is_pinned);
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Announcement created successfully';
            $response['announcement_id'] = $conn->insert_id;
        } else {
            $response['message'] = 'Failed to create announcement';
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }
}

// Update announcement
function updateAnnouncement($conn, $data) {
    global $response;
    
    try {
        $id = $data['id'] ?? 0;
        $title = trim($data['title'] ?? '');
        $message = trim($data['message'] ?? '');
        $priority = $data['priority'] ?? 'medium';
        $target_audience = $data['target_audience'] ?? 'all';
        $expires_at = $data['expires_at'] ?? null;
        $is_pinned = isset($data['is_pinned']) ? 1 : 0;
        
        if (empty($id) || empty($title) || empty($message)) {
            $response['message'] = 'ID, title and message are required';
            return;
        }
        
        $sql = "UPDATE announcements SET title = ?, message = ?, priority = ?, target_audience = ?, expires_at = ?, is_pinned = ? WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssi", $title, $message, $priority, $target_audience, $expires_at, $is_pinned, $id);
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Announcement updated successfully';
        } else {
            $response['message'] = 'Failed to update announcement';
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }
}

// Delete announcement
function deleteAnnouncement($conn, $data) {
    global $response;
    
    try {
        $id = $data['id'] ?? 0;
        
        if (empty($id)) {
            $response['message'] = 'Announcement ID is required';
            return;
        }
        
        $sql = "DELETE FROM announcements WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Announcement deleted successfully';
        } else {
            $response['message'] = 'Failed to delete announcement';
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }
}

// Toggle announcement status
function toggleAnnouncementStatus($conn, $data) {
    global $response;
    
    try {
        $id = $data['id'] ?? 0;
        
        if (empty($id)) {
            $response['message'] = 'Announcement ID is required';
            return;
        }
        
        $sql = "UPDATE announcements SET status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Announcement status toggled successfully';
        } else {
            $response['message'] = 'Failed to toggle announcement status';
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }
}

// Toggle announcement pin
function toggleAnnouncementPin($conn, $data) {
    global $response;
    
    try {
        $id = $data['id'] ?? 0;
        
        if (empty($id)) {
            $response['message'] = 'Announcement ID is required';
            return;
        }
        
        $sql = "UPDATE announcements SET is_pinned = NOT is_pinned WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Announcement pin toggled successfully';
        } else {
            $response['message'] = 'Failed to toggle announcement pin';
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }
}

// Mark announcement as viewed
function markAnnouncementViewed($conn, $data) {
    global $response;
    
    try {
        $announcement_id = $data['announcement_id'] ?? 0;
        $user_id = $data['user_id'] ?? 0;
        $user_type = $data['user_type'] ?? '';
        
        if (empty($announcement_id) || empty($user_id) || empty($user_type)) {
            $response['message'] = 'Announcement ID, user ID, and user type are required';
            return;
        }
        
        // Insert view record (will fail silently if already exists due to UNIQUE constraint)
        $sql = "INSERT IGNORE INTO announcement_views (announcement_id, user_id, user_type) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iis", $announcement_id, $user_id, $user_type);
        $stmt->execute();
        $stmt->close();
        
        // Update views count
        $sql = "UPDATE announcements SET views_count = (SELECT COUNT(*) FROM announcement_views WHERE announcement_id = ?) WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $announcement_id, $announcement_id);
        $stmt->execute();
        $stmt->close();
        
        $response['success'] = true;
        $response['message'] = 'Announcement marked as viewed';
        
    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }
}

// Get all announcements
function getAnnouncements($conn, $data) {
    global $response;
    
    try {
        $status = $data['status'] ?? 'active';
        $target_audience = $data['target_audience'] ?? '';
        $limit = $data['limit'] ?? 50;
        $offset = $data['offset'] ?? 0;
        
        $where_conditions = ["1=1"];
        $params = [];
        $types = "";
        
        if ($status !== 'all') {
            $where_conditions[] = "status = ?";
            $params[] = $status;
            $types .= "s";
        }
        
        if (!empty($target_audience) && $target_audience !== 'all') {
            $where_conditions[] = "(target_audience = ? OR target_audience = 'all')";
            $params[] = $target_audience;
            $types .= "s";
        }
        
        $where_clause = implode(" AND ", $where_conditions);
        
        $sql = "SELECT a.*, 
                       COALESCE(adm.name, 'System') as created_by_name
                FROM announcements a
                LEFT JOIN admins adm ON a.created_by = adm.id
                WHERE $where_clause
                ORDER BY a.is_pinned DESC, a.priority DESC, a.created_at DESC
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $announcements = [];
        while ($row = $result->fetch_assoc()) {
            $announcements[] = $row;
        }
        
        $response['success'] = true;
        $response['announcements'] = $announcements;
        $response['total'] = count($announcements);
        
        $stmt->close();
        
    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }
}

// Get single announcement
function getAnnouncement($conn, $data) {
    global $response;
    
    try {
        $id = $data['id'] ?? 0;
        
        if (empty($id)) {
            $response['message'] = 'Announcement ID is required';
            return;
        }
        
        $sql = "SELECT a.*, 
                       COALESCE(adm.name, 'System') as created_by_name
                FROM announcements a
                LEFT JOIN admins adm ON a.created_by = adm.id
                WHERE a.id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($announcement = $result->fetch_assoc()) {
            $response['success'] = true;
            $response['announcement'] = $announcement;
        } else {
            $response['message'] = 'Announcement not found';
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>
