<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication check
if (!isset($_SESSION['staff_logged_in']) || !$_SESSION['staff_logged_in']) {
    header('Location: login.php');
    exit;
}

require_once '../includes/db.php';
require_once '../includes/functions.php';

$page_title = "Staff Dashboard";
include 'components/header.php';

// Get staff information
$staff_id = $_SESSION['staff_id'] ?? 0;
$staff_name = $_SESSION['staff_name'] ?? 'Staff';

// Get personalized greeting
$current_time = date('H');
$greeting = '';
if ($current_time < 12) {
    $greeting = 'Good Morning';
} elseif ($current_time < 17) {
    $greeting = 'Good Afternoon';
} else {
    $greeting = 'Good Evening';
}

// Get staff attendance statistics
$today = date('Y-m-d');
$staff_stats = [
    'today_attendance' => 0,
    'this_week_attendance' => 0,
    'this_month_attendance' => 0,
    'total_attendance' => 0,
    'current_status' => 'Not Checked In',
    'status_class' => 'secondary',
    'status_icon' => 'fa-calendar-times'
];

// Check today's attendance
$today_query = "SELECT * FROM staff_attendance WHERE staff_id = ? AND DATE(date) = ? ORDER BY time_in DESC LIMIT 1";
$stmt = $conn->prepare($today_query);
if ($stmt) {
    $stmt->bind_param("is", $staff_id, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $today_record = $result->fetch_assoc();
        if (empty($today_record['time_out'])) {
            $staff_stats['current_status'] = 'Currently Active';
            $staff_stats['status_class'] = 'warning';
            $staff_stats['status_icon'] = 'fa-clock';
        } else {
            $staff_stats['current_status'] = 'Completed Today';
            $staff_stats['status_class'] = 'success';
            $staff_stats['status_icon'] = 'fa-check-circle';
        }
    }
    $stmt->close();
}

// Get weekly attendance
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_query = "SELECT COUNT(*) as count FROM staff_attendance WHERE staff_id = ? AND DATE(date) >= ?";
$stmt = $conn->prepare($week_query);
if ($stmt) {
    $stmt->bind_param("is", $staff_id, $week_start);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $week_data = $result->fetch_assoc();
        $staff_stats['this_week_attendance'] = $week_data['count'];
    }
    $stmt->close();
}

// Get monthly attendance
$month_start = date('Y-m-01');
$month_query = "SELECT COUNT(*) as count FROM staff_attendance WHERE staff_id = ? AND DATE(date) >= ?";
$stmt = $conn->prepare($month_query);
if ($stmt) {
    $stmt->bind_param("is", $staff_id, $month_start);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $month_data = $result->fetch_assoc();
        $staff_stats['this_month_attendance'] = $month_data['count'];
    }
    $stmt->close();
}

// Get total attendance
$total_query = "SELECT COUNT(*) as count FROM staff_attendance WHERE staff_id = ?";
$stmt = $conn->prepare($total_query);
if ($stmt) {
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $total_data = $result->fetch_assoc();
        $staff_stats['total_attendance'] = $total_data['count'];
    }
    $stmt->close();
}

// Get gym statistics
$gym_stats = [
    'active_members' => 0,
    'today_visits' => 0,
    'total_members' => 0
];

// Get active members now
$active_query = "SELECT COUNT(*) as count FROM attendance WHERE DATE(date) = ? AND time_out IS NULL";
$stmt = $conn->prepare($active_query);
if ($stmt) {
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $active_data = $result->fetch_assoc();
        $gym_stats['active_members'] = $active_data['count'];
    }
    $stmt->close();
}

// Get today's total visits
$today_total_query = "SELECT COUNT(*) as count FROM attendance WHERE DATE(date) = ?";
$stmt = $conn->prepare($today_total_query);
if ($stmt) {
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $today_data = $result->fetch_assoc();
        $gym_stats['today_visits'] = $today_data['count'];
    }
    $stmt->close();
}

// Get total members
$total_members_query = "SELECT COUNT(*) as count FROM members";
$stmt = $conn->prepare($total_members_query);
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $members_data = $result->fetch_assoc();
        $gym_stats['total_members'] = $members_data['count'];
    }
    $stmt->close();
}

// Get announcements for staff
$announcements_query = "SELECT a.*, 
                       COALESCE(adm.name, 'System') as created_by_name
                       FROM announcements a
                       LEFT JOIN admins adm ON a.created_by = adm.id
                       WHERE a.status = 'active' 
                       AND (a.target_audience = 'all' OR a.target_audience = 'staff')
                       ORDER BY a.is_pinned DESC, a.priority DESC, a.created_at DESC
                       LIMIT 5";

$announcements_result = $conn->query($announcements_query);
$announcements = [];
if ($announcements_result) {
    while ($row = $announcements_result->fetch_assoc()) {
        $announcements[] = $row;
    }
}

// Get recent attendance history
$recent_attendance_query = "SELECT * FROM staff_attendance WHERE staff_id = ? ORDER BY date DESC, time_in DESC LIMIT 5";
$stmt = $conn->prepare($recent_attendance_query);
$recent_attendance = [];
if ($stmt) {
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recent_attendance[] = $row;
        }
    }
    $stmt->close();
}
?>

<!-- Modern Dashboard Container -->
<div class="container-fluid px-4">
    <!-- Welcome Section -->
    <div class="welcome-section">
        <div class="welcome-content">
            <div class="welcome-left">
                <div class="welcome-header">
                    <div class="welcome-icon">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="welcome-text">
                        <h1 class="welcome-title"><?php echo $greeting; ?>, <span class="staff-name"><?php echo htmlspecialchars($staff_name); ?></span>!</h1>
                        <p class="welcome-subtitle">Here's your staff management overview.</p>
                    </div>
                </div>
            </div>
            <div class="welcome-right">
                <div class="welcome-time">
                    <div class="current-time" id="currentTime"><?php echo date('H:i:s A'); ?></div>
                <div class="current-date">
                        <i class="fas fa-calendar"></i>
                        <span><?php echo date('F d, Y'); ?></span>
                    </div>
                </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="content-grid">
        <!-- Left Column -->
        <div class="content-left">

            <!-- Recent Activity -->
            <div class="content-card">
                <div class="card-header">
                    <h5><i class="fas fa-history"></i>Recent Activity</h5>
                    <a href="attendance.php" class="view-all">View All</a>
                </div>
                <div class="card-body">
                    <?php if (!empty($recent_attendance)): ?>
                        <div class="activity-list">
                            <?php foreach ($recent_attendance as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-<?php echo empty($activity['time_out']) ? 'clock' : 'check-circle'; ?>"></i>
                                    </div>
                                    <div class="activity-content">
                                        <h6><?php echo empty($activity['time_out']) ? 'Checked In' : 'Checked Out'; ?></h6>
                                        <p><?php echo date('M d, Y', strtotime($activity['date'])); ?> at <?php echo date('g:i A', strtotime($activity['time_in'])); ?></p>
                                    </div>
                                    <div class="activity-status <?php echo empty($activity['time_out']) ? 'active' : 'completed'; ?>">
                                        <?php echo empty($activity['time_out']) ? 'Active' : 'Completed'; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <h6>No recent activity</h6>
                            <p>Start your work day by checking in!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Announcements -->
            <div class="content-card">
                <div class="card-header">
                    <h5><i class="fas fa-bullhorn"></i>Latest Announcements</h5>
                    <span class="badge"><?php echo count($announcements); ?> new</span>
                </div>
                <div class="card-body">
                    <?php if (!empty($announcements)): ?>
                        <div class="announcement-list">
                            <?php foreach ($announcements as $announcement): ?>
                                <div class="announcement-item">
                                    <div class="announcement-header">
                                        <h6>
                                            <?php if ($announcement['is_pinned']): ?>
                                                <i class="fas fa-thumbtack text-danger"></i>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($announcement['title']); ?>
                                        </h6>
                                        <span class="priority-badge priority-<?php echo $announcement['priority']; ?>">
                                            <?php echo ucfirst($announcement['priority']); ?>
                                        </span>
                                    </div>
                                    <p><?php echo htmlspecialchars($announcement['message']); ?></p>
                                    <div class="announcement-meta">
                                        <small><i class="fas fa-user"></i> <?php echo htmlspecialchars($announcement['created_by_name']); ?></small>
                                        <small><i class="fas fa-clock"></i> <?php echo date('M d, Y', strtotime($announcement['created_at'])); ?></small>
                                        <small><i class="fas fa-users"></i> 
                                            <?php 
                                            if ($announcement['target_audience'] == 'all') {
                                                echo 'Everyone';
                                            } else {
                                                echo 'Staff Only';
                                            }
                                            ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-bullhorn"></i>
                            <h6>No announcements</h6>
                            <p>Check back later for updates.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- Right Column -->
        <div class="content-right">
        <!-- Gym Information -->
            <div class="content-card">
                <div class="card-header">
                    <h5><i class="fas fa-dumbbell"></i>Gym Information</h5>
                </div>
                <div class="card-body">
                    <div class="info-list">
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="info-content">
                                <h6>Operating Hours</h6>
                                <p>Monday - Sunday: 5:00 AM - 10:00 PM</p>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-phone"></i>
                            </div>
                            <div class="info-content">
                                <h6>Contact Info</h6>
                                <p>+63 912 345 6789</p>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div class="info-content">
                                <h6>Location</h6>
                                <p>RVG Power Build Gym, Your City</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Training Programs -->
            <div class="content-card">
                <div class="card-header">
                    <h5><i class="fas fa-dumbbell"></i>Training Programs</h5>
                    <span class="badge">Available</span>
                </div>
                <div class="card-body">
                    <div class="programs-carousel">
                        <div class="program-card active">
                            <div class="program-image">
                                <img src="../assets/img/training_programs/G.png" alt="Beginner Full-Body Program">
                                <div class="program-badge">Beginner</div>
                            </div>
                            <div class="program-content">
                                <h6>Beginner Full-Body Program</h6>
                                <p>3 days/week program designed to build overall strength and learn proper form. Perfect for beginners starting their fitness journey.</p>
                                <div class="program-features">
                                    <span class="feature-tag">
                                        <i class="fas fa-clock"></i> 3 Days/Week
                                    </span>
                                    <span class="feature-tag">
                                        <i class="fas fa-users"></i> Beginner
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="program-card">
                            <div class="program-image">
                                <img src="../assets/img/training_programs/R.png" alt="Intermediate Upper/Lower Split">
                                <div class="program-badge">Intermediate</div>
                            </div>
                            <div class="program-content">
                                <h6>Intermediate Upper/Lower Split</h6>
                                <p>4 days/week program for strength and muscle gain. Advanced training split for intermediate fitness enthusiasts.</p>
                                <div class="program-features">
                                    <span class="feature-tag">
                                        <i class="fas fa-clock"></i> 4 Days/Week
                                    </span>
                                    <span class="feature-tag">
                                        <i class="fas fa-users"></i> Intermediate
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="program-card">
                            <div class="program-image">
                                <img src="../assets/img/training_programs/V.png" alt="Advanced Training Program">
                                <div class="program-badge">Advanced</div>
                            </div>
                            <div class="program-content">
                                <h6>Advanced Training Program</h6>
                                <p>High-intensity training program for experienced athletes. Focus on power, strength, and advanced techniques.</p>
                                <div class="program-features">
                                    <span class="feature-tag">
                                        <i class="fas fa-clock"></i> 5-6 Days/Week
                                    </span>
                                    <span class="feature-tag">
                                        <i class="fas fa-users"></i> Advanced
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Carousel Navigation -->
                    <div class="carousel-nav">
                        <button class="nav-btn prev-btn" onclick="changeProgram(-1)">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <div class="carousel-dots">
                            <span class="dot active" onclick="currentProgram(1)"></span>
                            <span class="dot" onclick="currentProgram(2)"></span>
                            <span class="dot" onclick="currentProgram(3)"></span>
                        </div>
                        <button class="nav-btn next-btn" onclick="changeProgram(1)">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include 'components/footer.php'; ?>

<style>
/* Modern Dashboard Styles - Staff Design Colors */
:root {
    --primary-color: #4e73df;
    --secondary-color: #858796;
    --success-color: #1cc88a;
    --info-color: #36b9cc;
    --warning-color: #f6c23e;
    --danger-color: #e74a3b;
    --light-color: #f8f9fc;
    --dark-color: #5a5c69;
    --border-radius: 12px;
    --box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    --transition: all 0.3s ease;
    --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --gradient-success: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    --gradient-warning: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
    --gradient-info: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);
}

.container-fluid {
    transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1.2);
}

/* Sidebar toggle adjustments - Fixed positioning */
body.sidebar-hidden .content-grid {
    grid-template-columns: 2fr 1fr;
    gap: 30px;
    transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1.2);
}

body.sidebar-hidden .stats-grid {
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1.2);
}

body.sidebar-hidden .welcome-section {
    padding: 25px 30px;
    transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1.2);
}

body.sidebar-hidden .welcome-content {
    flex-direction: row;
    text-align: left;
    gap: 15px;
    transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1.2);
}

body.sidebar-hidden .welcome-visual {
    width: 50px;
    height: 50px;
    transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1.2);
}

/* Enhanced sidebar toggle effects - Maintain layout */
body.sidebar-hidden .container-fluid {
    max-width: 100%;
    padding-left: 20px;
    padding-right: 20px;
    transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1.2);
}

body.sidebar-hidden .content-card {
    transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1.2);
    margin-bottom: 30px;
}

body.sidebar-hidden .programs-carousel {
    transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1.2);
}

body.sidebar-hidden .announcement-list {
    transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1.2);
}

/* Ensure content maintains proper spacing */
body.sidebar-hidden .content-left {
    flex: 2;
}

body.sidebar-hidden .content-right {
    flex: 1;
}

/* Maintain card proportions */
body.sidebar-hidden .card-body {
    padding: 25px;
}

body.sidebar-hidden .card-header {
    padding: 20px 25px;
}

/* Welcome Section */
.welcome-section {
    background: #2d3748;
    border-radius: 15px;
    padding: 25px 30px;
    margin-bottom: 30px;
    color: white;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.welcome-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.welcome-left {
    flex: 1;
}

.welcome-header {
    display: flex;
    align-items: center;
    gap: 15px;
}

.welcome-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    background: linear-gradient(135deg, #4299e1, #3182ce);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    box-shadow: 0 4px 15px rgba(66, 153, 225, 0.3);
}

.welcome-text {
    flex: 1;
}

.welcome-title {
    font-size: 1.8rem;
    font-weight: 700;
    margin: 0 0 5px 0;
    color: white;
    line-height: 1.2;
}

.welcome-subtitle {
    font-size: 0.95rem;
    color: #a0aec0;
    margin: 0;
    line-height: 1.4;
}

.staff-name {
    color: #4299e1;
    font-weight: 600;
}

.welcome-right {
    display: flex;
    align-items: center;
    gap: 20px;
}

.welcome-time {
    display: flex;
    align-items: center;
    gap: 20px;
}

.current-time {
    font-size: 1.5rem;
    font-weight: 700;
    color: white;
    font-family: 'Courier New', monospace;
}

.current-date {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #4a5568;
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 0.9rem;
    color: white;
    font-weight: 500;
}

.current-date i {
    font-size: 0.8rem;
    opacity: 0.8;
}

/* Content Grid */
.content-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 30px;
    transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1.2);
    position: relative;
    width: 100%;
}

.content-card {
    background: rgba(255, 255, 255, 0.08);
    -webkit-backdrop-filter: blur(20px);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 20px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    margin-bottom: 30px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.card-header {
    padding: 20px 25px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: rgba(255, 255, 255, 0.05);
}

.card-header h5 {
    margin: 0;
    font-weight: 600;
    color: white;
    display: flex;
    align-items: center;
    gap: 10px;
}

.badge {
    background: var(--gradient-warning);
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.card-body {
    padding: 25px;
}

/* Actions Grid */
.actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
}

.action-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 20px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    text-decoration: none;
    color: white;
    transition: all 0.3s ease;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.action-item:hover {
    background: rgba(255, 255, 255, 0.1);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
    text-decoration: none;
    color: white;
}

.action-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
}

.action-content {
    flex: 1;
}

.action-content h6 {
    margin: 0 0 5px 0;
    font-weight: 600;
    color: white;
}

.action-content p {
    margin: 0;
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.8);
}

.action-arrow {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.9rem;
}

/* Progress Stats */
.progress-stats {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.progress-item {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.progress-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.progress-label {
    font-weight: 600;
    color: white;
}

.progress-value {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.8);
}

.progress-bar {
    height: 8px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 4px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: var(--gradient-primary);
    border-radius: 4px;
    transition: width 0.3s ease;
}

/* Activity List */
.activity-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.activity-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 10px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1rem;
}

.activity-content {
    flex: 1;
}

.activity-content h6 {
    margin: 0 0 5px 0;
    font-weight: 600;
    color: white;
}

.activity-content p {
    margin: 0;
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.8);
}

.activity-status {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.activity-status.active {
    background: #fff3cd;
    color: #856404;
}

.activity-status.completed {
    background: #d4edda;
    color: #155724;
}

/* Info List */
.info-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.info-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 10px;
    border: 1px solid #e9ecef;
}

.info-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1rem;
}

.info-content h6 {
    margin: 0 0 5px 0;
    font-weight: 600;
    color: #2c3e50;
}

.info-content p {
    margin: 0;
    font-size: 0.9rem;
    color: #6c757d;
}

/* Announcement List - Admin Style */
.announcement-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.announcement-item {
    background: rgba(15, 23, 42, 0.8);
    -webkit-backdrop-filter: blur(10px);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    overflow: hidden;
    transition: all 0.3s ease;
    position: relative;
    padding: 15px;
}

.announcement-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
    background: rgba(15, 23, 42, 0.9);
}

.announcement-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
    position: relative;
}

.announcement-header h6 {
    margin: 0;
    font-weight: 600;
    color: #f8f9fc;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 1.1rem;
    line-height: 1.3;
}

.priority-badge {
    position: absolute;
    top: 0;
    right: 0;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 500;
    text-transform: uppercase;
    z-index: 2;
}

.priority-badge.priority-high {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
}

.priority-badge.priority-medium {
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
    color: white;
}

.priority-badge.priority-low {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.priority-badge.priority-urgent {
    background: linear-gradient(135deg, #dc2626, #991b1b);
    color: white;
}

.announcement-item p {
    margin: 0 0 15px 0;
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.8);
    line-height: 1.5;
}

.announcement-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.7);
    background: rgba(255, 255, 255, 0.02);
    padding: 8px 12px;
    border-radius: 6px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.announcement-meta small {
    display: flex;
    align-items: center;
    gap: 4px;
}

.announcement-meta small i {
    color: #3b82f6;
    width: 12px;
    text-align: center;
}

/* Programs Carousel */
.programs-carousel {
    position: relative;
    overflow: hidden;
    border-radius: 12px;
    min-height: 600px;
}

.program-card {
    background: #f8f9fa;
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid #e9ecef;
    display: none;
    transition: all 0.3s ease;
    min-height: 600px;
}

.program-card.active {
    display: block;
    animation: fadeIn 0.5s ease-in-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateX(20px); }
    to { opacity: 1; transform: translateX(0); }
}

.program-image {
    position: relative;
    height: 550px;
    overflow: hidden;
}

.program-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.program-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
    padding: 6px 15px;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
}

.program-content {
    padding: 30px;
    min-height: 320px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.program-content h6 {
    margin: 0 0 10px 0;
    font-weight: 600;
    color: #2c3e50;
}

.program-content p {
    margin: 0 0 15px 0;
    font-size: 0.9rem;
    color: #6c757d;
    line-height: 1.5;
}

.program-features {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.feature-tag {
    background: white;
    color: #6c757d;
    padding: 4px 8px;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 500;
    border: 1px solid #e9ecef;
    display: flex;
    align-items: center;
    gap: 4px;
}

/* Carousel Navigation */
.carousel-nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 20px;
    padding: 0 10px;
}

.nav-btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    border: none;
    color: white;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.nav-btn:hover {
    background: linear-gradient(135deg, #5a6fd8, #6a4190);
    transform: scale(1.1);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
}

.nav-btn:active {
    transform: scale(0.95);
}

.carousel-dots {
    display: flex;
    gap: 8px;
}

.dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #dee2e6;
    cursor: pointer;
    transition: all 0.3s ease;
}

.dot.active {
    background: linear-gradient(135deg, #667eea, #764ba2);
    transform: scale(1.2);
}

.dot:hover {
    background: #adb5bd;
    transform: scale(1.1);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #6c757d;
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 15px;
    opacity: 0.5;
}

.empty-state h6 {
    margin: 0 0 10px 0;
    font-weight: 600;
    color: #2c3e50;
}

.empty-state p {
    margin: 0;
    font-size: 0.9rem;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
}

/* Sidebar toggle responsive adjustments */
@media (max-width: 1400px) {
    body.sidebar-hidden .content-grid {
        grid-template-columns: 1fr;
    }
    
    body.sidebar-hidden .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    }
}

@media (max-width: 768px) {
    body.sidebar-hidden .welcome-section {
        padding: 20px 15px;
    }
    
    body.sidebar-hidden .welcome-title {
        font-size: 1.8rem;
    }
}

@media (max-width: 768px) {
    .container-fluid {
        padding: 15px;
    }
    
    .welcome-section {
        padding: 30px 20px;
    }
    
    .welcome-content {
        flex-direction: column;
        text-align: center;
        gap: 20px;
    }
    
    .welcome-title {
        font-size: 2rem;
    }
    
    .welcome-visual {
        width: 150px;
        height: 150px;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .actions-grid {
        grid-template-columns: 1fr;
    }
    
    .welcome-actions {
        flex-direction: column;
        width: 100%;
    }
    
    .action-btn {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 576px) {
    .welcome-title {
        font-size: 1.5rem;
    }
    
    .stat-card {
        padding: 20px;
    }
    
    .card-body {
        padding: 20px;
    }
    
    .action-item {
        padding: 15px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add smooth animations to stat cards
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
        card.classList.add('fade-in-up');
    });
    
    // Add hover effects to action items
    const actionItems = document.querySelectorAll('.action-item');
    actionItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-3px)';
        });
        
        item.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
    
    // Animate progress bars
    const progressBars = document.querySelectorAll('.progress-fill');
    progressBars.forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0%';
        setTimeout(() => {
            bar.style.width = width;
        }, 500);
    });
});

// Update current time and greeting
function updateTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', {
        hour12: true,
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
    const dateString = now.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
    
    // Update time and date
    const timeElement = document.getElementById('currentTime');
    const dateElement = document.querySelector('.current-date span');
    
    if (timeElement) {
        timeElement.textContent = timeString;
    }
    if (dateElement) {
        dateElement.textContent = dateString;
    }
    
    // Update greeting based on time
    updateGreeting();
}

// Update greeting based on current time
function updateGreeting() {
    const now = new Date();
    const hour = now.getHours();
    let greeting = '';
    
    if (hour < 12) {
        greeting = 'Good Morning';
    } else if (hour < 17) {
        greeting = 'Good Afternoon';
    } else {
        greeting = 'Good Evening';
    }
    
    // Update the greeting in the main title
    const title = document.querySelector('.welcome-title');
    if (title) {
        const staffName = document.querySelector('.staff-name').textContent;
        title.innerHTML = `${greeting}, <span class="staff-name">${staffName}</span>!`;
    }
}

// Initialize time updates
document.addEventListener('DOMContentLoaded', function() {
    // Update time every second
    updateTime();
    setInterval(updateTime, 1000);
});

// Carousel functionality
let currentProgramIndex = 1;
const totalPrograms = 3;
let autoAdvanceInterval;
let isUserInteracting = false;

function showProgram(index) {
    const programs = document.querySelectorAll('.program-card');
    const dots = document.querySelectorAll('.dot');
    
    // Hide all programs
    programs.forEach(program => program.classList.remove('active'));
    dots.forEach(dot => dot.classList.remove('active'));
    
    // Show current program
    if (programs[index - 1]) {
        programs[index - 1].classList.add('active');
    }
    if (dots[index - 1]) {
        dots[index - 1].classList.add('active');
    }
}

function changeProgram(direction) {
    currentProgramIndex += direction;
    
    if (currentProgramIndex > totalPrograms) {
        currentProgramIndex = 1;
    } else if (currentProgramIndex < 1) {
        currentProgramIndex = totalPrograms;
    }
    
    showProgram(currentProgramIndex);
    pauseAutoAdvance();
}

function currentProgram(index) {
    currentProgramIndex = index;
    showProgram(currentProgramIndex);
    pauseAutoAdvance();
}

function startAutoAdvance() {
    // Clear any existing interval
    if (autoAdvanceInterval) {
        clearInterval(autoAdvanceInterval);
    }
    
    // Start auto-advance every 5 seconds
    autoAdvanceInterval = setInterval(() => {
        if (!isUserInteracting) {
            currentProgramIndex++;
            if (currentProgramIndex > totalPrograms) {
                currentProgramIndex = 1;
            }
            showProgram(currentProgramIndex);
        }
    }, 5000);
}

function pauseAutoAdvance() {
    isUserInteracting = true;
    
    // Clear the interval
    if (autoAdvanceInterval) {
        clearInterval(autoAdvanceInterval);
    }
    
    // Resume auto-advance after 10 seconds of no interaction
    setTimeout(() => {
        isUserInteracting = false;
        startAutoAdvance();
    }, 10000);
}

// Initialize carousel when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Start auto-advance after a short delay
    setTimeout(() => {
        startAutoAdvance();
    }, 2000);
});

// Add CSS for fade-in animation
const style = document.createElement('style');
style.textContent = `
    .fade-in-up {
        animation: fadeInUp 0.6s ease-out forwards;
        opacity: 0;
        transform: translateY(20px);
    }
    
    @keyframes fadeInUp {
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Fixed positioning for sidebar toggle */
    .content-left,
    .content-right {
        transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1.2);
        position: relative;
    }

    /* Ensure content doesn't shift when sidebar toggles */
    body.sidebar-hidden .content-left {
        flex: 2;
        min-width: 0;
    }

    body.sidebar-hidden .content-right {
        flex: 1;
        min-width: 0;
    }

    /* Maintain consistent spacing */
    body.sidebar-hidden .welcome-section {
        margin-bottom: 30px;
    }

    body.sidebar-hidden .content-card {
        margin-bottom: 30px;
    }

    /* Prevent content jumping */
    .content-grid {
        will-change: auto;
    }

    .content-card {
        will-change: auto;
    }
`;
document.head.appendChild(style);

// Enhanced sidebar toggle functionality for staff
document.addEventListener('DOMContentLoaded', function() {
    // Get sidebar toggle elements
    const toggleBtn = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const body = document.body;
    const contentGrid = document.querySelector('.content-grid');
    const containerFluid = document.querySelector('.container-fluid');
    
    if (toggleBtn && sidebar) {
        console.log('Staff sidebar elements found, initializing toggle functionality');
        
        // Enhanced toggle with content stretching
        toggleBtn.addEventListener('click', function() {
            console.log('Staff sidebar toggle clicked');
            
            // Toggle sidebar classes
            sidebar.classList.toggle('hidden');
            body.classList.toggle('sidebar-hidden');
            
            // Add visual feedback to toggle button
            this.style.transform = 'scale(0.95)';
            setTimeout(() => {
                this.style.transform = '';
            }, 150);
            
            // Store sidebar state in localStorage
            const isHidden = sidebar.classList.contains('hidden');
            localStorage.setItem('staff-sidebar-hidden', isHidden);
            
            // Maintain content layout without shifting
            if (contentGrid) {
                contentGrid.style.transition = 'all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1.2)';
                // Ensure grid maintains proper proportions
                if (isHidden) {
                    contentGrid.style.gridTemplateColumns = '2fr 1fr';
                } else {
                    contentGrid.style.gridTemplateColumns = '2fr 1fr';
                }
            }
            
            if (containerFluid) {
                containerFluid.style.transition = 'all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1.2)';
                // Maintain consistent padding
                if (isHidden) {
                    containerFluid.style.paddingLeft = '20px';
                    containerFluid.style.paddingRight = '20px';
                } else {
                    containerFluid.style.paddingLeft = '';
                    containerFluid.style.paddingRight = '';
                }
            }
            
            // Trigger content layout update after transition
            setTimeout(() => {
                window.dispatchEvent(new Event('resize'));
            }, 400);
        });
        
        // Restore sidebar state from localStorage
        const sidebarHidden = localStorage.getItem('staff-sidebar-hidden');
        if (sidebarHidden === 'true') {
            sidebar.classList.add('hidden');
            body.classList.add('sidebar-hidden');
        }
        
        // Handle window resize for responsive behavior
        window.addEventListener('resize', function() {
            if (window.innerWidth <= 992) {
                // On mobile, ensure sidebar starts hidden
                if (!sidebar.classList.contains('hidden')) {
                    sidebar.classList.add('hidden');
                    body.classList.add('sidebar-hidden');
                }
            } else {
                // On desktop, restore sidebar state
                const sidebarHidden = localStorage.getItem('staff-sidebar-hidden');
                if (sidebarHidden === 'true') {
                    sidebar.classList.add('hidden');
                    body.classList.add('sidebar-hidden');
                } else {
                    sidebar.classList.remove('hidden');
                    body.classList.remove('sidebar-hidden');
                }
            }
        });
        
        // Initialize sidebar state based on screen size
        if (window.innerWidth <= 992) {
            sidebar.classList.add('hidden');
            body.classList.add('sidebar-hidden');
        }
        
        console.log('Staff sidebar toggle functionality initialized successfully');
    } else {
        console.error('Staff sidebar elements not found!');
        console.log('Toggle button found:', !!toggleBtn);
        console.log('Sidebar found:', !!sidebar);
    }
});
</script>
