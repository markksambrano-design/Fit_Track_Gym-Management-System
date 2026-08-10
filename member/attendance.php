<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$page_title = "Attendance Logs";

include '../includes/auth.php';
requireMemberAuth();

include 'components/header.php';
include '../includes/db.php';

$member_id = getCurrentMemberId();

// Debug: Check if member_id is valid
if (!$member_id) {
    error_log("Member ID not found in session: " . print_r($_SESSION, true));
    header('Location: login.php');
    exit;
}

// Get member info for display
$member_sql = "SELECT first_name, last_name, member_id, membership_type, photo FROM members WHERE id = ?";
$member_stmt = $conn->prepare($member_sql);
if ($member_stmt) {
    $member_stmt->bind_param('i', $member_id);
    $member_stmt->execute();
    $member_info = $member_stmt->get_result()->fetch_assoc();
    $member_stmt->close();
} else {
    error_log("Database error in member info: " . $conn->error);
    $member_info = null;
}

// Get attendance records for the logged-in member
$attendance_records = [];

// Query to get attendance records from member_attendance table
$sql = "SELECT 
            a.id,
            a.date,
            a.time_in,
            a.time_out,
            'current' as source
        FROM member_attendance a 
        WHERE a.member_id = ? 
        ORDER BY a.date DESC, a.time_in DESC
        LIMIT 100";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param('i', $member_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $attendance_records[] = $row;
    }
    $stmt->close();
} else {
    error_log("Database error in member attendance: " . $conn->error);
}

// Check for archived records
$archive_tables = ['member_attendance_archive', 'attendance_archive', 'archived_attendance'];
$archive_records = [];

foreach ($archive_tables as $table_name) {
    $table_check = $conn->query("SHOW TABLES LIKE '$table_name'");
    if ($table_check && $table_check->num_rows > 0) {
        if ($table_name === 'attendance_archive') {
            $archive_sql = "SELECT 
                                aa.id,
                                aa.archive_date as date,
                                aa.time_in,
                                aa.time_out,
                                'archive' as source
                            FROM attendance_archive aa 
                            WHERE aa.member_id = ? 
                            ORDER BY aa.archive_date DESC, aa.time_in DESC
                            LIMIT 50";
        } else {
            $archive_sql = "SELECT 
                                aa.id,
                                aa.date,
                                aa.time_in,
                                aa.time_out,
                                'archive' as source
                            FROM archived_attendance aa 
                            WHERE aa.member_id = ? 
                            ORDER BY aa.date DESC, aa.time_in DESC
                            LIMIT 50";
        }

        $archive_stmt = $conn->prepare($archive_sql);
        if ($archive_stmt) {
            $archive_stmt->bind_param('i', $member_id);
            $archive_stmt->execute();
            $archive_result = $archive_stmt->get_result();

            while ($row = $archive_result->fetch_assoc()) {
                $archive_records[] = $row;
            }
            $archive_stmt->close();
            break;
        }
    }
}

// Merge and sort all records
$attendance_records = array_merge($attendance_records, $archive_records);
usort($attendance_records, function($a, $b) {
    $date_a = strtotime($a['date'] . ' ' . ($a['time_in'] ?? '00:00:00'));
    $date_b = strtotime($b['date'] . ' ' . ($b['time_in'] ?? '00:00:00'));
    return $date_b - $date_a;
});


// Check today's attendance status - Always show Workout Active
$today = date('Y-m-d');
$today_status = 'Workout Active';
$today_class = 'success';
$today_icon = 'fa-dumbbell';
$today_record = null;

// Check if member has checked in today but not checked out
foreach ($attendance_records as $record) {
    if ($record['date'] === $today && !empty($record['time_in']) && empty($record['time_out'])) {
        $today_record = $record;
        break;
    }
}

// Additional check: Query today's attendance directly from database
if (!$today_record) {
    $today_sql = "SELECT id, date, time_in, time_out FROM member_attendance WHERE member_id = ? AND date = ? AND time_in IS NOT NULL AND time_out IS NULL ORDER BY time_in DESC LIMIT 1";
    $today_stmt = $conn->prepare($today_sql);
    if ($today_stmt) {
        $today_stmt->bind_param('is', $member_id, $today);
        $today_stmt->execute();
        $today_result = $today_stmt->get_result();
        if ($today_result->num_rows > 0) {
            $today_record = $today_result->fetch_assoc();
        }
        $today_stmt->close();
    }
}

// Always show "Currently Active" status - no fallback to "Not Checked In"
?>

<!-- Modern Header Section - Admin Design Pattern -->
 
<!-- Member Profile Card - Admin Style -->
<div class="member-profile-card">
    <div class="profile-content">
        <div class="profile-left">
            <div class="profile-avatar">
                <?php 
                $profileImage = '';
                if (!empty($member_info['photo'])) {
                    $photoPath = '../uploads/member_photos/' . $member_info['photo'];
                    if (file_exists($photoPath)) {
                        $profileImage = $photoPath;
                    }
                }
                
                if (empty($profileImage)) {
                    $firstName = $member_info['first_name'] ?? '';
                    $lastName = $member_info['last_name'] ?? '';
                    $profileImage = 'https://ui-avatars.com/api/?name=' . urlencode($firstName . '+' . $lastName) . '&background=3b82f6&color=fff&size=200&font-size=0.4';
                }
                ?>
                <img src="<?= htmlspecialchars($profileImage) ?>" 
                     alt="Profile Picture" 
                     class="profile-avatar-img"
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <div class="profile-avatar-fallback" style="display: none;">
                    <i class="fas fa-user"></i>
                </div>
            </div>
            <div class="profile-info">
                <h4 class="member-name">
                    <?= htmlspecialchars($member_info['first_name'] . ' ' . $member_info['last_name']) ?>
                </h4>
                <div class="member-details">
                    <span class="member-id">
                        <i class="fas fa-id-card"></i>
                        Member ID: <?= htmlspecialchars($member_info['member_id']) ?>
                    </span>
                    <span class="membership-type">
                        <i class="fas fa-crown"></i>
                        <?= htmlspecialchars(ucfirst($member_info['membership_type'])) ?> Member
                    </span>
                </div>
            </div>
        </div>
        <div class="profile-right">
            <div class="today-status">
                <div class="status-time">
                    <small class="time-info">
                        <i class="fas fa-clock"></i>
                        <?= date('M d, Y h:i A') ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>



<!-- Attendance Records Section - Admin Style -->
<div class="attendance-records-section">
    <div class="attendance-records-card">
        <div class="records-header">
            <div class="header-content">
                <h3 class="records-title">
                    <i class="fas fa-calendar-check"></i>
                    Attendance Records
                </h3>
                <p class="records-subtitle">Your complete attendance history and session details</p>
            </div>
        </div>
        <div class="records-content">
            <?php if (empty($attendance_records)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-calendar-times"></i>
                    </div>
                    <h3 class="empty-title">No attendance records found</h3>
                    <p class="empty-description">Attendance records will appear here once you start checking in.</p>
                </div>
            <?php else: ?>
                <div class="attendance-table-container">
                    <table class="attendance-table" id="paymentTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time In</th>
                                <th>Time Out</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                                    <?php 
                                    $total_payment = 0;
                                    $hourly_rate = 62.50;
                                    foreach ($attendance_records as $record): 
                                        $date = date('M d, Y', strtotime($record['date']));
                                        $time_in = $record['time_in'] ? date('h:i A', strtotime($record['time_in'])) : '-';
                                        $time_out = $record['time_out'] ? date('h:i A', strtotime($record['time_out'])) : '-';
                                        
                                        // Calculate hours worked and payment
                                        $hours_worked = 0;
                                        $payment = 0;
                                        $status = 'Incomplete';
                                        $status_class = 'warning';
                                        
                                        if ($record['time_in'] && $record['time_out']) {
                                            $time_in_obj = new DateTime($record['time_in']);
                                            $time_out_obj = new DateTime($record['time_out']);
                                            $interval = $time_in_obj->diff($time_out_obj);
                                            $hours_worked = $interval->h + ($interval->i / 60);
                                            $payment = $hours_worked * $hourly_rate;
                                            $total_payment += $payment;
                                            $status = 'Completed';
                                            $status_class = 'success';
                                        } elseif ($record['time_in']) {
                                            $status = 'In Progress';
                                            $status_class = 'info';
                                        }
                                        
                                        // Check if it's today's record
                                        $is_today = $record['date'] === $today;
                                    ?>
                            <tr class="attendance-row <?= $is_today ? 'today-row' : '' ?>">
                                <td class="date-cell">
                                    <div class="date-content">
                                        <strong><?= $date ?></strong>
                                        <?php if ($is_today): ?>
                                            <span class="today-badge">Today</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="time-cell time-in">
                                    <span class="time-value"><?= $time_in ?></span>
                                </td>
                                <td class="time-cell time-out">
                                    <span class="time-value"><?= $time_out ?></span>
                                </td>
                                <td class="status-cell">
                                    <span class="status-badge status-<?= $status_class ?>">
                                        <i class="fas fa-<?= $status === 'Completed' ? 'check-circle' : ($status === 'In Progress' ? 'clock' : 'exclamation-triangle') ?>"></i>
                                        <?= $status ?>
                                    </span>
                                </td>
                            </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                </div>
                
                <div class="table-footer">
                    <div class="footer-info">
                        <span class="info-item">
                            <i class="fas fa-info-circle"></i>
                            Attendance tracking system
                        </span>
                        <span class="info-item">
                            <i class="fas fa-clock"></i>
                            Last updated: <?= date('M d, Y h:i A') ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>


<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Attendance Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailsModalBody">
                <!-- Content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php include 'components/footer.php'; ?>

<style>
/* Modern Member Attendance Styles - Admin Design Pattern */

/* Header Styles - Matching Admin Design */
.member-attendance-header {
    background: linear-gradient(135deg, rgba(30, 41, 59, 0.9), rgba(15, 23, 42, 0.9));
    -webkit-backdrop-filter: blur(20px);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
}

.member-attendance-header .header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.member-attendance-header .header-left {
    flex: 1;
    min-width: 300px;
}

.member-attendance-header .page-title-section {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.member-attendance-header .page-title {
    font-size: 2.5rem;
    font-weight: 700;
    color: #f8f9fc;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 15px;
}

.member-attendance-header .page-title i {
    color: #3b82f6;
    font-size: 2.2rem;
}

.member-attendance-header .page-subtitle {
    color: rgba(255, 255, 255, 0.7);
    font-size: 1.1rem;
    margin: 0;
    font-weight: 400;
}

.member-attendance-header .header-right {
    display: flex;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
}

.member-attendance-header .current-time {
    font-size: 1.2rem;
    font-weight: 600;
    color: #3b82f6;
    font-family: 'Courier New', monospace;
}

.member-attendance-header .current-date {
    display: flex;
    align-items: center;
    gap: 8px;
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.95rem;
}

/* Member Profile Card - Admin Style */
.member-profile-card {
    background: rgba(30, 41, 59, 0.7);
    -webkit-backdrop-filter: blur(15px);
    backdrop-filter: blur(15px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    color: white;
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}

.member-profile-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #3b82f6, #1d4ed8);
    pointer-events: none;
}

.member-profile-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
    background: rgba(30, 41, 59, 0.9);
}

.profile-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.profile-left {
    display: flex;
    align-items: center;
    gap: 20px;
    flex: 1;
    min-width: 300px;
}

.profile-avatar {
    width: 80px;
    height: 80px;
    background: rgba(59, 130, 246, 0.1);
    border: 2px solid #3b82f6;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #3b82f6;
    font-size: 2rem;
    flex-shrink: 0;
    position: relative;
    overflow: hidden;
}

.profile-avatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
}

.profile-avatar-fallback {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
    font-size: 2rem;
}

.profile-info {
    flex: 1;
}

.member-name {
    font-size: 1.8rem;
    font-weight: 700;
    color: #f8f9fc;
    margin: 0 0 10px 0;
}

.member-details {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.member-id, .membership-type {
    display: flex;
    align-items: center;
    gap: 8px;
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.9rem;
}

.member-id i, .membership-type i {
    color: #3b82f6;
    width: 16px;
    text-align: center;
}

.profile-right {
    display: flex;
    align-items: center;
    gap: 20px;
}

.today-status {
    text-align: right;
}

.status-label {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.9rem;
    margin-bottom: 8px;
    font-weight: 500;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-badge.status-success {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.status-badge.status-warning {
    background: rgba(251, 191, 36, 0.2);
    color: #fbbf24;
    border: 1px solid rgba(251, 191, 36, 0.3);
}

.status-badge.status-secondary {
    background: rgba(107, 114, 128, 0.2);
    color: #6b7280;
    border: 1px solid rgba(107, 114, 128, 0.3);
}

.status-time {
    margin-top: 8px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.time-info {
    display: flex;
    align-items: center;
    gap: 6px;
    color: rgba(255, 255, 255, 0.7);
    font-size: 1.2rem;
    font-weight: 600;
}

.time-info i {
    color: #3b82f6;
    width: 12px;
    text-align: center;
}


/* Attendance Records Section - Admin Style */
.attendance-records-section {
    margin-bottom: 30px;
}

.attendance-records-card {
    background: rgba(30, 41, 59, 0.7);
    -webkit-backdrop-filter: blur(15px);
    backdrop-filter: blur(15px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    color: white;
    overflow: hidden;
    transition: all 0.3s ease;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

.attendance-records-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
    border-color: rgba(255, 255, 255, 0.2);
    background: rgba(30, 41, 59, 0.9);
}

.records-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 25px 30px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    flex-wrap: wrap;
    gap: 20px;
}

.header-content {
    flex: 1;
    min-width: 300px;
}

.records-title {
    font-size: 1.8rem;
    font-weight: 600;
    color: #f8f9fc;
    margin: 0 0 8px 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.records-title i {
    color: #3b82f6;
    font-size: 1.5rem;
}

.records-subtitle {
    color: rgba(255, 255, 255, 0.7);
    font-size: 1rem;
    margin: 0;
    font-weight: 400;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.btn-action {
    background: rgba(59, 130, 246, 0.1);
    border: 1px solid rgba(59, 130, 246, 0.3);
    color: #3b82f6;
    padding: 10px 20px;
    border-radius: 12px;
    font-weight: 500;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    text-decoration: none;
    font-size: 0.9rem;
}

.btn-action:hover {
    background: rgba(59, 130, 246, 0.2);
    border-color: #3b82f6;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(59, 130, 246, 0.3);
    color: #3b82f6;
}

.btn-action.btn-export {
    background: rgba(16, 185, 129, 0.1);
    border-color: rgba(16, 185, 129, 0.3);
    color: #10b981;
}

.btn-action.btn-export:hover {
    background: rgba(16, 185, 129, 0.2);
    border-color: #10b981;
    box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);
    color: #10b981;
}

.btn-action.btn-print {
    background: rgba(107, 114, 128, 0.1);
    border-color: rgba(107, 114, 128, 0.3);
    color: #6b7280;
}

.btn-action.btn-print:hover {
    background: rgba(107, 114, 128, 0.2);
    border-color: #6b7280;
    box-shadow: 0 5px 15px rgba(107, 114, 128, 0.3);
    color: #6b7280;
}

.records-content {
    padding: 30px;
}

/* Attendance Table - Admin Style */
.attendance-table-container {
    overflow-x: auto;
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.attendance-table {
    width: 100%;
    border-collapse: collapse;
    background: rgba(15, 23, 42, 0.8);
    -webkit-backdrop-filter: blur(10px);
    backdrop-filter: blur(10px);
}

.attendance-table thead {
    background: rgba(59, 130, 246, 0.1);
    border-bottom: 2px solid rgba(59, 130, 246, 0.3);
}

.attendance-table th {
    padding: 20px 15px;
    text-align: left;
    font-weight: 600;
    color: #f8f9fc;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: none;
}

.attendance-table td {
    padding: 20px 15px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.9rem;
}

.attendance-row:hover {
    background: rgba(59, 130, 246, 0.05);
    transform: translateX(5px);
    transition: all 0.3s ease;
}

.attendance-row.today-row {
    background: rgba(251, 191, 36, 0.1);
    border-left: 4px solid #fbbf24;
}

.date-cell {
    font-weight: 600;
}

.date-content {
    display: flex;
    align-items: center;
    gap: 10px;
}

.today-badge {
    background: linear-gradient(45deg, #fbbf24, #f59e0b);
    color: #1f2937;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.time-cell {
    font-family: 'Courier New', monospace;
    font-weight: 500;
}

.time-in .time-value {
    color: #3b82f6;
}

.time-out .time-value {
    color: #10b981;
}

.status-cell {
    text-align: center;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-badge.status-success {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.status-badge.status-info {
    background: rgba(59, 130, 246, 0.2);
    color: #3b82f6;
    border: 1px solid rgba(59, 130, 246, 0.3);
}

.status-badge.status-warning {
    background: rgba(251, 191, 36, 0.2);
    color: #fbbf24;
    border: 1px solid rgba(251, 191, 36, 0.3);
}

.table-footer {
    padding: 20px 30px;
    background: rgba(255, 255, 255, 0.02);
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.footer-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.info-item {
    display: flex;
    align-items: center;
    gap: 8px;
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.85rem;
}

.info-item i {
    color: #3b82f6;
    width: 14px;
    text-align: center;
}

/* Empty State - Admin Style */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: rgba(255, 255, 255, 0.6);
}

.empty-state .empty-icon {
    font-size: 4rem;
    margin-bottom: 20px;
    color: rgba(255, 255, 255, 0.3);
}

.empty-state .empty-title {
    color: #f8f9fc;
    margin-bottom: 10px;
    font-weight: 600;
    font-size: 1.5rem;
}

.empty-state .empty-description {
    color: rgba(255, 255, 255, 0.6);
    font-size: 1rem;
    margin: 0;
}


/* Responsive Design */
@media (max-width: 768px) {
    .member-attendance-header .header-content {
        flex-direction: column;
        text-align: center;
    }
    
    .member-attendance-header .header-right {
        justify-content: center;
    }
    
    .profile-content {
        flex-direction: column;
        text-align: center;
    }
    
    .profile-left {
        flex-direction: column;
        text-align: center;
    }
    
    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
    }
    
    .records-header {
        flex-direction: column;
        align-items: stretch;
    }
    
    .header-actions {
        justify-content: center;
    }
    
    .status-info {
        flex-direction: column;
        align-items: stretch;
    }
    
    .status-actions {
        align-items: stretch;
    }
    
    .time-details {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .attendance-table-container {
        overflow-x: auto;
    }
    
    .attendance-table th,
    .attendance-table td {
        padding: 15px 10px;
        font-size: 0.85rem;
    }
}

/* Print Styles */
@media print {
    .header-actions, .status-actions, .modal {
        display: none !important;
    }
    
    .attendance-records-card,
    .today-status-card,
    .member-profile-card {
        box-shadow: none !important;
        border: 1px solid #dee2e6 !important;
        background: white !important;
        color: black !important;
    }
    
    .attendance-table {
        background: white !important;
    }
    
    .attendance-table th,
    .attendance-table td {
        color: black !important;
        border: 1px solid #dee2e6 !important;
    }
}
</style>

<script>
let isProcessing = false;

// Show loading state
function showLoading(buttonId, loadingText) {
    const button = document.getElementById(buttonId);
    if (button) {
        button.innerHTML = `<i class="fas fa-spinner fa-spin me-1"></i>${loadingText}`;
        button.disabled = true;
        isProcessing = true;
    }
}

// Reset button state
function resetButton(buttonId, originalText) {
    const button = document.getElementById(buttonId);
    if (button) {
        button.innerHTML = originalText;
        button.disabled = false;
        isProcessing = false;
    }
}

// Show notification
function showNotification(message, type = 'success') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, type === 'success' ? 5000 : 8000);
}

// Refresh attendance
function refreshAttendance() {
    if (isProcessing) return;
    
    showLoading('refreshBtn', 'Refreshing...');
    setTimeout(() => {
        location.reload();
    }, 500);
}

// Complete today's session
function completeTodaySession() {
    if (isProcessing) return;
    
    if (confirm('Are you sure you want to complete today\'s session? This will set your time out to now.')) {
        showLoading('completeBtn', 'Completing...');
        
        fetch('complete_session.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'complete_today'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Session completed successfully!');
                location.reload();
            } else {
                showNotification(data.message || 'Failed to complete session', 'danger');
                resetButton('completeBtn', '<i class="fas fa-check me-1"></i>Complete Session');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('An error occurred while completing session', 'danger');
            resetButton('completeBtn', '<i class="fas fa-check me-1"></i>Complete Session');
        });
    }
}

// View attendance details
function viewDetails(recordId) {
    // For now, just show a simple modal with basic info
    const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
    document.getElementById('detailsModalBody').innerHTML = `
        <div class="text-center">
            <i class="fas fa-info-circle fa-3x text-info mb-3"></i>
            <p>Detailed view for attendance record #${recordId}</p>
            <p class="text-muted">This feature will show more detailed information about the attendance record.</p>
        </div>
    `;
    modal.show();
}

// Export attendance data
function exportAttendance() {
    if (isProcessing) return;
    
    showLoading('exportBtn', 'Exporting...');
    
    // Create CSV content
    const table = document.getElementById('attendanceTable');
    const rows = table.querySelectorAll('tbody tr');
    let csv = 'Date,Time In,Time Out,Duration,Status\n';
    
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        const date = cells[0].textContent.trim().split('Today')[0].trim();
        const timeIn = cells[1].textContent.trim();
        const timeOut = cells[2].textContent.trim();
        const duration = cells[3].textContent.trim();
        const status = cells[4].textContent.trim();
        
        csv += `"${date}","${timeIn}","${timeOut}","${duration}","${status}"\n`;
    });
    
    // Download CSV file
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `attendance_${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
    
    resetButton('exportBtn', '<i class="fas fa-download me-1"></i>Export');
    showNotification('Attendance data exported successfully!');
}

// Print attendance
function printAttendance() {
    if (isProcessing) return;
    
    showLoading('printBtn', 'Preparing...');
    
    setTimeout(() => {
        window.print();
        resetButton('printBtn', '<i class="fas fa-print me-1"></i>Print');
    }, 500);
}

// Export payment data
function exportPayments() {
    if (isProcessing) return;
    
    showLoading('exportPaymentsBtn', 'Exporting...');
    
    // Create CSV content for payments
    const table = document.getElementById('paymentTable');
    const rows = table.querySelectorAll('tbody tr');
    let csv = 'Date,Time In,Time Out,Status\n';
    
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        const date = cells[0].textContent.trim().split('Today')[0].trim();
        const timeIn = cells[1].textContent.trim();
        const timeOut = cells[2].textContent.trim();
        const status = cells[3].textContent.trim();
        
        csv += `"${date}","${timeIn}","${timeOut}","${status}"\n`;
    });
    
    // Download CSV file
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `attendance_records_${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
    
    resetButton('exportPaymentsBtn', '<i class="fas fa-download me-1"></i>Export Records');
    showNotification('Attendance records exported successfully!');
}

// Print payment data
function printPayments() {
    if (isProcessing) return;
    
    showLoading('printPaymentsBtn', 'Preparing...');
    
    setTimeout(() => {
        // Create a new window for printing payment table only
        const printWindow = window.open('', '_blank');
        const table = document.getElementById('paymentTable');
        const tableHTML = table.outerHTML;
        
        printWindow.document.write(`
            <html>
                <head>
                    <title>Attendance Records - ${new Date().toLocaleDateString()}</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f2f2f2; font-weight: bold; }
                        .text-success { color: #28a745; }
                        .text-primary { color: #007bff; }
                        .badge { padding: 2px 6px; border-radius: 3px; font-size: 0.8em; }
                        .bg-info { background-color: #17a2b8; color: white; }
                        .bg-success { background-color: #28a745; color: white; }
                        .bg-warning { background-color: #ffc107; color: #212529; }
                        .bg-info { background-color: #17a2b8; color: white; }
                        @media print { body { margin: 0; } }
                    </style>
                </head>
                <body>
                    <h2>Attendance Records</h2>
                    <p>Generated on: ${new Date().toLocaleString()}</p>
                    ${tableHTML}
                </body>
            </html>
        `);
        
        printWindow.document.close();
        printWindow.print();
        printWindow.close();
        
        resetButton('printPaymentsBtn', '<i class="fas fa-print me-1"></i>Print');
    }, 500);
}

// Keyboard shortcuts
document.addEventListener('keydown', function(event) {
    if ((event.ctrlKey && event.key === 'r') || event.key === 'F5') {
        event.preventDefault();
        refreshAttendance();
    }
});

// Auto-refresh every 5 minutes
setInterval(() => {
    if (!isProcessing) {
        location.reload();
    }
}, 300000); // 5 minutes

// Real-time clock
function updateClock() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', { 
        hour12: true, 
        hour: '2-digit', 
        minute: '2-digit', 
        second: '2-digit' 
    });
    
    const clockElement = document.getElementById('currentTime');
    if (clockElement) {
        clockElement.textContent = timeString;
    }
}

// Update clock every second
setInterval(updateClock, 1000);
updateClock(); // Initial call

// Enhanced card animations
document.addEventListener('DOMContentLoaded', function() {
    // Add smooth animations to cards
    const cards = document.querySelectorAll('.stat-card, .attendance-records-card, .today-status-card, .member-profile-card');
    
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        card.style.transition = 'all 0.6s ease';
        
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
    
    // Add hover effects to table rows
    const tableRows = document.querySelectorAll('.attendance-row');
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.transform = 'translateX(5px)';
            this.style.background = 'rgba(59, 130, 246, 0.05)';
        });
        
        row.addEventListener('mouseleave', function() {
            this.style.transform = 'translateX(0)';
            this.style.background = '';
        });
    });
    
    // Enhanced button interactions
    const actionButtons = document.querySelectorAll('.btn-action');
    actionButtons.forEach(button => {
        button.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
        });
        
        button.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
});

// Initialize tooltips
if (typeof bootstrap !== 'undefined') {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
}
</script>
