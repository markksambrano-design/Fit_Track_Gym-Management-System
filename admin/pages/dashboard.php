<?php
// Set timezone to Philippines for correct time display
date_default_timezone_set('Asia/Manila');

$page_title = "Dashboard";
include '../includes/functions.php';
include 'components/header.php';

// Ensure attendance table exists
ensureAttendanceTable();

// Get dashboard data
$totalMembers = getTotalMembers();
$totalStaff = getTotalStaff();
$todayAttendance = getTodayAttendance();
$activeNow = getActiveNow();
$activeStaffNow = getActiveStaffNow();
$todayWalkIns = getTodayWalkIns();
$activeWalkIns = getActiveWalkIns();
$todayWalkInRevenue = getTodayWalkInRevenue();
$weeklyData = getWeeklyAttendance();
$membershipData = getMembershipTypes();
$recentCheckins = getRecentCheckins(5);
$recentStaffCheckins = getRecentStaffCheckins(5);
$recentWalkIns = getRecentWalkIns(5);
$recentAnnouncements = getRecentAnnouncements(5);

// Calculate additional metrics
$totalActiveUsers = $activeNow + $activeStaffNow;
$weeklyTotal = array_sum(array_column($weeklyData, 'total'));
$weeklyAverage = round($weeklyTotal / 7, 1);
?>

<div class="dashboard-container">
    <!-- Header Section -->
    <div class="dashboard-header">
        <div class="header-content">
            <div class="header-left">
                <h1 class="dashboard-title gym-brand">
                    <i class="fas fa-dumbbell gym-icon"></i>
                    RVG Power Build Dashboard
                </h1>
                <p class="dashboard-subtitle gym-subtitle">Welcome back! Here's your gym management overview.</p>
            </div>
            <div class="header-right">
                <div class="current-time" id="currentTime"></div>
                <div class="current-date">
                    <i class="fas fa-calendar-alt"></i>
                    <?php echo date('F d, Y'); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row mb-4" style="display: flex; flex-wrap: nowrap;">
        <div class="col mb-4" style="flex: 1; margin-right: 15px;">
            <div class="card stats-card border-left-primary">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Gym Members
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($totalMembers); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col mb-4" style="flex: 1; margin-right: 15px;">
            <div class="card stats-card border-left-success">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Gym Staff
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($totalStaff); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-tie fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col mb-4" style="flex: 1; margin-right: 15px;">
            <div class="card stats-card border-left-warning">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Today's Visits
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($todayAttendance); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col mb-4" style="flex: 1; margin-right: 15px;">
            <div class="card stats-card border-left-info">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Active Now
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($totalActiveUsers); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-check fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col mb-4" style="flex: 1;">
            <div class="card stats-card border-left-warning">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Today's Walk-ins
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($todayWalkIns); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-walking fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Dashboard Content -->
    <div class="dashboard-content">
        <!-- Left Column -->
        <div class="dashboard-left">
            <!-- Weekly Attendance Chart -->
            <div class="card main-content-card">
                <div class="card-header">
                    <h6 class="gym-brand">
                        <i class="fas fa-chart-line gym-icon"></i>Weekly Attendance Overview
                    </h6>
                    <div class="chart-controls">
                        <button class="refresh-btn" onclick="refreshChart()">
                            <i class="fas fa-sync-alt"></i>
                            Refresh
                        </button>
                        <select id="chartPeriod" class="period-selector">
                            <option value="week">This Week</option>
                            <option value="month">This Month</option>
                        </select>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="weeklyChart"></canvas>
                </div>
                <div class="chart-summary">
                    <div class="summary-item">
                        <span class="summary-label">Total This Week:</span>
                        <span class="summary-value"><?php echo $weeklyTotal; ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Average Daily:</span>
                        <span class="summary-value"><?php echo $weeklyAverage; ?></span>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="card main-content-card">
                <div class="card-header">
                    <h6 class="gym-brand">
                        <i class="fas fa-history gym-icon"></i>Recent Activity
                    </h6>
                    <a href="index.php?page=attendance" class="view-all-btn">
                        <i class="fas fa-eye"></i>
                        View All
                    </a>
                </div>
                
                <div class="activity-tabs">
                    <div class="tab-buttons">
                        <button class="tab-btn active" data-tab="members">
                            <i class="fas fa-users"></i>
                            Members
                        </button>
                        <button class="tab-btn" data-tab="staff">
                            <i class="fas fa-user-tie"></i>
                            Staff
                        </button>
                        <button class="tab-btn" data-tab="walkins">
                            <i class="fas fa-walking"></i>
                            Walk-ins
                        </button>
                    </div>
                    
                    <div class="tab-content">
                        <!-- Members Tab -->
                        <div class="tab-pane active" id="members-tab">
                            <div class="activity-list">
                                <?php if (empty($recentCheckins)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-users"></i>
                                        <p>No recent member check-ins</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($recentCheckins as $checkin): ?>
                                        <div class="activity-item">
                                            <div class="activity-avatar">
                                                <img src="<?php echo getUserPhoto($checkin['photo'], $checkin['first_name'] . ' ' . $checkin['last_name']); ?>" alt="Avatar">
                                            </div>
                                            <div class="activity-details">
                                                <div class="activity-name"><?php echo htmlspecialchars($checkin['first_name'] . ' ' . $checkin['last_name']); ?></div>
                                                <div class="activity-info">
                                                    <span class="membership-badge <?php echo $checkin['membership_type'] == 'premium' ? 'premium' : 'regular'; ?>">
                                                        <?php echo ucfirst($checkin['membership_type']); ?>
                                                    </span>
                                                    <span class="activity-time"><?php echo formatRecentTime($checkin['time_in']); ?></span>
                                                </div>
                                            </div>
                                            <div class="activity-status">
                                                <?php echo getAttendanceStatus($checkin['time_out']); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Staff Tab -->
                        <div class="tab-pane" id="staff-tab">
                            <div class="activity-list">
                                <?php if (empty($recentStaffCheckins)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-user-tie"></i>
                                        <p>No recent staff check-ins</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($recentStaffCheckins as $checkin): ?>
                                        <div class="activity-item">
                                            <div class="activity-avatar">
                                                <img src="<?php echo getUserPhoto($checkin['photo'], $checkin['first_name'] . ' ' . $checkin['last_name']); ?>" alt="Avatar">
                                            </div>
                                            <div class="activity-details">
                                                <div class="activity-name"><?php echo htmlspecialchars($checkin['first_name'] . ' ' . $checkin['last_name']); ?></div>
                                                <div class="activity-info">
                                                    <span class="position-badge"><?php echo htmlspecialchars($checkin['position'] ?? 'Staff'); ?></span>
                                                    <span class="activity-time"><?php echo formatRecentTime($checkin['time_in']); ?></span>
                                                </div>
                                            </div>
                                            <div class="activity-status">
                                                <?php echo getAttendanceStatus($checkin['time_out']); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Walk-ins Tab -->
                        <div class="tab-pane" id="walkins-tab">
                            <div class="activity-list">
                                <?php if (empty($recentWalkIns)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-walking"></i>
                                        <p>No recent walk-ins</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($recentWalkIns as $walkIn): ?>
                                        <div class="activity-item">
                                            <div class="activity-avatar walkin-avatar">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <div class="activity-details">
                                                <div class="activity-name"><?php echo htmlspecialchars($walkIn['first_name'] . ' ' . $walkIn['last_name']); ?></div>
                                                <div class="activity-info">
                                                    <?php echo getWalkInPurposeBadge($walkIn['purpose']); ?>
                                                    <span class="activity-time"><?php echo formatRecentTime($walkIn['time_in']); ?></span>
                                                </div>
                                            </div>
                                            <div class="activity-status">
                                                <?php echo getWalkInPurposeBadge($walkIn['purpose']); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Announcements -->
            <div class="card main-content-card">
                <div class="card-header">
                    <h6 class="gym-brand">
                        <i class="fas fa-bullhorn gym-icon"></i>Recent Announcements
                    </h6>
                    <a href="index.php?page=announcement" class="view-all-btn">
                        <i class="fas fa-eye"></i>
                        View All
                    </a>
                </div>
                <div class="announcements-list">
                    <?php if (empty($recentAnnouncements)): ?>
                        <div class="empty-state">
                            <i class="fas fa-bullhorn"></i>
                            <p>No announcements yet</p>
                            <a href="index.php?page=announcement" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Create First
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentAnnouncements as $announcement): ?>
                            <div class="announcement-item <?php echo $announcement['is_pinned'] ? 'pinned' : ''; ?>">
                                <?php if ($announcement['is_pinned']): ?>
                                    <div class="pin-indicator">
                                        <i class="fas fa-thumbtack"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="announcement-content">
                                    <div class="announcement-header">
                                        <h4 class="announcement-title"><?php echo htmlspecialchars($announcement['title']); ?></h4>
                                        <div class="announcement-badges">
                                            <?php echo getAnnouncementPriorityBadge($announcement['priority']); ?>
                                            <?php echo getAnnouncementAudienceBadge($announcement['target_audience']); ?>
                                        </div>
                                    </div>
                                    <p class="announcement-message"><?php echo htmlspecialchars(substr($announcement['message'], 0, 100)) . (strlen($announcement['message']) > 100 ? '...' : ''); ?></p>
                                    <div class="announcement-meta">
                                        <span class="announcement-author">
                                            <i class="fas fa-user"></i>
                                            <?php echo htmlspecialchars($announcement['created_by_name']); ?>
                                        </span>
                                        <span class="announcement-date">
                                            <i class="fas fa-clock"></i>
                                            <?php echo formatRecentTime($announcement['created_at']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="dashboard-right">
            <!-- Membership Distribution -->
            <div class="card main-content-card">
                <div class="card-header">
                    <h6 class="gym-brand">
                        <i class="fas fa-chart-pie gym-icon"></i>Membership Distribution
                    </h6>
                    <div class="legend-toggle">
                        <i class="fas fa-info-circle"></i>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="membershipChart"></canvas>
                </div>
                <div class="membership-legend">
                    <div class="legend-item">
                        <span class="legend-dot regular"></span>
                        <span>Regular (<?php echo isset($membershipData['regular']) ? $membershipData['regular'] : 0; ?>)</span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-dot student"></span>
                        <span>Student (<?php echo isset($membershipData['student']) ? $membershipData['student'] : 0; ?>)</span>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card main-content-card">
                <div class="card-header">
                    <h6 class="gym-brand">
                        <i class="fas fa-bolt gym-icon"></i>Quick Actions
                    </h6>
                    <span class="card-badge">Fast Access</span>
                </div>
                <div class="quick-actions-grid">
                    <a href="index.php?page=members" class="action-item">
                        <div class="action-icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="action-text">
                            <h4>Add Member</h4>
                            <p>Register new member</p>
                        </div>
                    </a>
                    
                    <a href="index.php?page=walk_in" class="action-item">
                        <div class="action-icon">
                            <i class="fas fa-walking"></i>
                        </div>
                        <div class="action-text">
                            <h4>Walk-in Entry</h4>
                            <p>Process walk-in</p>
                        </div>
                    </a>
                    
                    <a href="index.php?page=announcement" class="action-item">
                        <div class="action-icon">
                            <i class="fas fa-bullhorn"></i>
                        </div>
                        <div class="action-text">
                            <h4>Announcement</h4>
                            <p>Create new post</p>
                        </div>
                    </a>
                    
                    <a href="index.php?page=attendance" class="action-item">
                        <div class="action-icon">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <div class="action-text">
                            <h4>Attendance</h4>
                            <p>View records</p>
                        </div>
                    </a>
                </div>
            </div>

            <!-- System Status -->
            <div class="card main-content-card">
                <div class="card-header">
                    <h6 class="gym-brand">
                        <i class="fas fa-server gym-icon"></i>System Status
                    </h6>
                    <span class="status-indicator online">Online</span>
                </div>
                <div class="system-status">
                    <div class="status-item">
                        <div class="status-icon">
                            <i class="fas fa-database"></i>
                        </div>
                        <div class="status-info">
                            <span class="status-label">Database</span>
                            <span class="status-value">Connected</span>
                        </div>
                    </div>
                    <div class="status-item">
                        <div class="status-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="status-info">
                            <span class="status-label">Last Sync</span>
                            <span class="status-value"><?php echo date('H:i'); ?></span>
                        </div>
                    </div>
                    <div class="status-item">
                        <div class="status-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="status-info">
                            <span class="status-label">Active Users</span>
                            <span class="status-value"><?php echo $totalActiveUsers; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include CSS -->
<link href="../assets/css/admin/dashboard.css" rel="stylesheet">

<!-- Include JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Pass PHP data to JavaScript
const weeklyData = <?php echo json_encode($weeklyData); ?>;
const membershipData = <?php echo json_encode($membershipData); ?>;
const serverTime = <?php echo time(); ?>;
const serverTimezone = '<?php echo date_default_timezone_get(); ?>';

// Debug logging
console.log('Weekly Data:', weeklyData);
console.log('Membership Data:', membershipData);

// Update current time
function updateTime() {
    const now = new Date();
    
    // Use local time
    let timeString = now.toLocaleTimeString('en-US', { 
        hour12: true, 
        hour: '2-digit', 
        minute: '2-digit', 
        second: '2-digit'
    });
    
    document.getElementById('currentTime').textContent = timeString;
    
    // Debug info
    console.log('Server timezone:', serverTimezone);
    console.log('Current time:', timeString);
}

// Update time every second
setInterval(updateTime, 1000);
updateTime();

// Tab functionality
document.addEventListener('DOMContentLoaded', function() {
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabPanes = document.querySelectorAll('.tab-pane');

    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            const targetTab = button.getAttribute('data-tab');
            
            // Remove active class from all buttons and panes
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabPanes.forEach(pane => pane.classList.remove('active'));
            
            // Add active class to clicked button and corresponding pane
            button.classList.add('active');
            document.getElementById(targetTab + '-tab').classList.add('active');
        });
    });

    // Initialize charts
    initializeCharts();
});

// Initialize all charts
function initializeCharts() {
    // Weekly Attendance Chart
    const weeklyCtx = document.getElementById('weeklyChart');
    if (weeklyCtx) {
        const weeklyChart = new Chart(weeklyCtx, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Members',
                    data: [
                        weeklyData.Mon?.members || 0,
                        weeklyData.Tue?.members || 0,
                        weeklyData.Wed?.members || 0,
                        weeklyData.Thu?.members || 0,
                        weeklyData.Fri?.members || 0,
                        weeklyData.Sat?.members || 0,
                        weeklyData.Sun?.members || 0
                    ],
                    borderColor: '#4e73df',
                    backgroundColor: 'rgba(78, 115, 223, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Staff',
                    data: [
                        weeklyData.Mon?.staff || 0,
                        weeklyData.Tue?.staff || 0,
                        weeklyData.Wed?.staff || 0,
                        weeklyData.Thu?.staff || 0,
                        weeklyData.Fri?.staff || 0,
                        weeklyData.Sat?.staff || 0,
                        weeklyData.Sun?.staff || 0
                    ],
                    borderColor: '#1cc88a',
                    backgroundColor: 'rgba(28, 200, 138, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Walk-ins',
                    data: [
                        weeklyData.Mon?.walkins || 0,
                        weeklyData.Tue?.walkins || 0,
                        weeklyData.Wed?.walkins || 0,
                        weeklyData.Thu?.walkins || 0,
                        weeklyData.Fri?.walkins || 0,
                        weeklyData.Sat?.walkins || 0,
                        weeklyData.Sun?.walkins || 0
                    ],
                    borderColor: '#f6c23e',
                    backgroundColor: 'rgba(246, 194, 62, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        },
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.7)'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        },
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.7)'
                        }
                    }
                }
            }
        });
    }

    // Membership Chart
    const membershipCtx = document.getElementById('membershipChart');
    if (membershipCtx) {
        const membershipChart = new Chart(membershipCtx, {
            type: 'doughnut',
            data: {
                labels: ['Regular', 'Student'],
                datasets: [{
                    data: [
                        membershipData.regular || 0,
                        membershipData.student || 0
                    ],
                    backgroundColor: [
                        'rgba(78, 115, 223, 0.8)',
                        'rgba(28, 200, 138, 0.8)'
                    ],
                    borderColor: [
                        'rgba(78, 115, 223, 1)',
                        'rgba(28, 200, 138, 1)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                cutout: '60%'
            }
        });
    }
}

// Refresh chart function
function refreshChart() {
    console.log('Refreshing chart...');
    location.reload();
}

// Chart period change handler
document.getElementById('chartPeriod')?.addEventListener('change', function() {
    console.log('Chart period changed to:', this.value);
    // Add logic to change chart data based on period
});
</script>
<script src="../assets/js/admin/dashboard.js"></script>

<?php include 'components/footer.php'; ?>