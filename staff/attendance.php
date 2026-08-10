<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if logged in as staff
if (!isset($_SESSION['staff_logged_in']) || !$_SESSION['staff_logged_in']) {
    header('Location: login.php');
    exit;
}

$page_title = "My Attendance";
include 'components/header.php';
include '../includes/db.php';

// Get current staff ID
$currentStaffId = $_SESSION['staff_id'] ?? null;
$currentStaffName = $_SESSION['staff_name'] ?? 'Staff Member';

// Get staff info for display
$staff_sql = "SELECT first_name, last_name, staff_id, photo FROM staff WHERE id = ?";
$staff_stmt = $conn->prepare($staff_sql);
if ($staff_stmt) {
    $staff_stmt->bind_param('i', $currentStaffId);
    $staff_stmt->execute();
    $staff_info = $staff_stmt->get_result()->fetch_assoc();
    $staff_stmt->close();
} else {
    error_log("Database error in staff info: " . $conn->error);
    $staff_info = null;
}

// Get parameters
$view = $_GET['view'] ?? 'today';
$dateParam = $_GET['date'] ?? date('Y-m-d');

// Get staff attendance data
$staffAttendance = [];
$stats = [
    'total' => 0,
    'active' => 0,
    'completed' => 0
];

if ($view === 'archive') {
    // Check staff_attendance_archive table first, then fallback to attendance_archive
    $table_check = $conn->query("SHOW TABLES LIKE 'staff_attendance_archive'");
    if ($table_check && $table_check->num_rows > 0) {
        $sql = "SELECT sa.id AS attendance_id,
                sa.staff_id AS member_pk,
                sa.staff_code,
                sa.time_in,
                sa.time_out,
                sa.date,
                s.first_name,
                s.last_name,
                'Staff' as membership_type,
                'staff' as user_type
            FROM staff_attendance_archive sa
            JOIN staff s ON s.id = sa.staff_id
            WHERE sa.date = ? AND sa.staff_id = ?
            ORDER BY COALESCE(sa.time_out, sa.time_in) DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('si', $dateParam, $currentStaffId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) { $staffAttendance[] = $row; }
        $stmt->close();
    }
    
    // If no data found, check staff_attendance table
    if (empty($staffAttendance)) {
        $sql = "SELECT sa.id AS attendance_id,
                sa.staff_id AS member_pk,
                sa.staff_code,
                sa.time_in,
                sa.time_out,
                sa.date,
                s.first_name,
                s.last_name,
                'Staff' as membership_type,
                'staff' as user_type
            FROM staff_attendance sa
            JOIN staff s ON s.id = sa.staff_id
            WHERE sa.date = ? AND sa.staff_id = ?
            ORDER BY COALESCE(sa.time_out, sa.time_in) DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('si', $dateParam, $currentStaffId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) { $staffAttendance[] = $row; }
        $stmt->close();
    }
} else {
    // Today's staff data from staff_attendance table
    $sql = "SELECT sa.id AS attendance_id,
            sa.staff_id AS member_pk,
            sa.staff_code,
            sa.time_in,
            sa.time_out,
            sa.date,
            s.first_name,
            s.last_name,
            'Staff' as membership_type,
            'staff' as user_type
        FROM staff_attendance sa
        JOIN staff s ON s.id = sa.staff_id
        WHERE sa.date = CURDATE() AND sa.staff_id = ?
        ORDER BY COALESCE(sa.time_out, sa.time_in) DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $currentStaffId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $staffAttendance[] = $row; }
    $stmt->close();
}

// Calculate statistics
$stats['total'] = count($staffAttendance);
foreach ($staffAttendance as $record) {
    if ($record['time_in'] && !$record['time_out']) {
        $stats['active']++;
    } elseif ($record['time_in'] && $record['time_out']) {
        $stats['completed']++;
    }
}

// Include attendance-specific CSS
echo '<link rel="stylesheet" href="../assets/css/staff/attendance.css">';
echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">';
?>

<!-- Date Picker -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<!-- Staff Profile Card - Admin Style -->
<div class="staff-profile-card">
    <div class="profile-content">
        <div class="profile-left">
            <div class="profile-avatar">
                <?php 
                $profileImage = '';
                if (!empty($staff_info['photo'])) {
                    $photoPath = '../uploads/staff_photos/' . $staff_info['photo'];
                    if (file_exists($photoPath)) {
                        $profileImage = $photoPath;
                    }
                }
                
                if (empty($profileImage)) {
                    $firstName = $staff_info['first_name'] ?? '';
                    $lastName = $staff_info['last_name'] ?? '';
                    $profileImage = 'https://ui-avatars.com/api/?name=' . urlencode($firstName . '+' . $lastName) . '&background=3b82f6&color=fff&size=200&font-size=0.4';
                }
                ?>
                <img src="<?= htmlspecialchars($profileImage) ?>" 
                     alt="Profile Picture" 
                     class="profile-avatar-img"
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <div class="profile-avatar-fallback" style="display: none;">
                    <i class="fas fa-user-tie"></i>
                </div>
            </div>
            <div class="profile-info">
                <h4 class="staff-name">
                    <?= htmlspecialchars($staff_info['first_name'] . ' ' . $staff_info['last_name']) ?>
                </h4>
                <div class="staff-details">
                    <span class="staff-code">
                        <i class="fas fa-id-card"></i>
                        Staff ID: <?= htmlspecialchars($staff_info['staff_id']) ?>
                    </span>
                    <span class="staff-position">
                        <i class="fas fa-briefcase"></i>
                        Staff Member
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

<!-- Date Controls -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card bg-dark border-light">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div class="input-group" style="width: 200px;">
                        <input type="text" id="datePicker" class="form-control" placeholder="Select Date" value="<?= $dateParam ?>">
                        <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="?view=today&date=<?= date('Y-m-d') ?>" 
                           class="btn <?= $view === 'today' ? 'btn-primary' : 'btn-outline-light' ?>">
                            <i class="fas fa-calendar-day"></i> Today
                        </a>
                        <a href="?view=archive&date=<?= $dateParam ?>" 
                           class="btn <?= $view === 'archive' ? 'btn-primary' : 'btn-outline-light' ?>">
                            <i class="fas fa-archive"></i> Archive
                        </a>
                    </div>
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
                    My Attendance Records
                    <?php if ($view === 'today'): ?>
                        <span class="badge bg-primary ms-2">Today</span>
                    <?php else: ?>
                        <span class="badge bg-info ms-2"><?= date('M d, Y', strtotime($dateParam)) ?></span>
                    <?php endif; ?>
                </h3>
                <p class="records-subtitle">Your complete attendance history and working hours</p>
            </div>
        </div>
        <div class="records-content">
            <?php if (!empty($staffAttendance)): ?>
                <div class="attendance-table-container">
                    <table class="attendance-table" id="attendanceTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time In</th>
                                <th>Time Out</th>
                                <th>Duration</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($staffAttendance as $record): 
                                $date = date('M d, Y', strtotime($record['date']));
                                $time_in = $record['time_in'] ? date('h:i A', strtotime($record['time_in'])) : '-';
                                $time_out = $record['time_out'] ? date('h:i A', strtotime($record['time_out'])) : '-';
                                
                                // Calculate duration
                                $duration = '-';
                                if ($record['time_in'] && $record['time_out']) {
                                    $timeIn = new DateTime($record['time_in']);
                                    $timeOut = new DateTime($record['time_out']);
                                    $duration = $timeIn->diff($timeOut)->format('%H:%I');
                                }
                                
                                // Determine status
                                $status = 'Pending';
                                $status_class = 'warning';
                                if ($record['time_in'] && !$record['time_out']) {
                                    $status = 'Active';
                                    $status_class = 'info';
                                } elseif ($record['time_in'] && $record['time_out']) {
                                    $status = 'Completed';
                                    $status_class = 'success';
                                }
                                
                                // Check if it's today's record
                                $is_today = date('Y-m-d', strtotime($record['date'])) === date('Y-m-d');
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
                                <td class="duration-cell">
                                    <span class="duration-value"><?= $duration ?></span>
                                </td>
                                <td class="status-cell">
                                    <span class="status-badge status-<?= $status_class ?>">
                                        <i class="fas fa-<?= $status === 'Completed' ? 'check-circle' : ($status === 'Active' ? 'clock' : 'exclamation-triangle') ?>"></i>
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
                            Staff attendance tracking system
                        </span>
                        <span class="info-item">
                            <i class="fas fa-clock"></i>
                            Last updated: <?= date('M d, Y h:i A') ?>
                        </span>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-calendar-times"></i>
                    </div>
                    <h3 class="empty-title">No attendance records found</h3>
                    <p class="empty-description">
                        <?php if ($view === 'today'): ?>
                            No attendance records found for today. Make sure to scan your QR code when you arrive.
                        <?php else: ?>
                            No attendance records found for <?= date('M d, Y', strtotime($dateParam)) ?>
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>


<script>
    // Initialize date picker
    flatpickr("#datePicker", {
        dateFormat: "Y-m-d",
        defaultDate: "<?= $dateParam ?>",
        onChange: function(selectedDates, dateStr) {
            window.location.href = `?view=archive&date=${dateStr}`;
        }
    });

    // Auto-refresh every 30 seconds for today's view
    <?php if ($view === 'today'): ?>
    setInterval(function() {
        location.reload();
    }, 30000);
    <?php endif; ?>
</script>

<?php include 'components/footer.php'; ?>

<style>
/* Modern Staff Attendance Styles - Admin Design Pattern */

/* Staff Profile Card - Admin Style */
.staff-profile-card {
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

.staff-profile-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #3b82f6, #1d4ed8);
    pointer-events: none;
}

.staff-profile-card:hover {
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

.staff-name {
    font-size: 1.8rem;
    font-weight: 700;
    color: #f8f9fc;
    margin: 0 0 10px 0;
}

.staff-details {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.staff-code, .staff-position {
    display: flex;
    align-items: center;
    gap: 8px;
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.9rem;
}

.staff-code i, .staff-position i {
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

.attendance-table th:last-child {
    text-align: center;
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

.duration-cell {
    font-family: 'Courier New', monospace;
    font-weight: 500;
    color: #fbbf24;
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
    .profile-content {
        flex-direction: column;
        text-align: center;
    }
    
    .profile-left {
        flex-direction: column;
        text-align: center;
    }
    
    .records-header {
        flex-direction: column;
        align-items: stretch;
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
    .attendance-records-card,
    .staff-profile-card {
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
