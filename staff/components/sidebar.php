<!-- Sidebar -->
<link rel="stylesheet" href="../assets/css/admin/sidebar.css">
<link rel="stylesheet" href="../assets/css/staff/sidebar.css">

<div class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <a href="dashboard.php" class="logo">
            <img src="../assets/img/FIT.png" alt="RVG Power Build" style="max-width: 60px; height: auto;" />
            <span class="logo-text" style="font-size: 20px; font-weight: bold;">RVG POWER BUILD</span>
        </a>
    </div>

    <div class="sidebar-menu">
        <ul>
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i><span class="menu-text">Dashboard</span></a></li>
            <li><a href="attendance.php"><i class="fas fa-clipboard-check"></i><span class="menu-text">Attendance</span></a></li>
            <li><a href="training_schedule.php"><i class="fas fa-calendar-alt"></i><span class="menu-text">Training Schedule</span></a></li>
            <li><a href="salary.php"><i class="fas fa-money-bill-wave"></i><span class="menu-text">Salary</span></a></li>
            <li><a href="profile.php"><i class="fas fa-user-edit"></i><span class="menu-text">Profile</span></a></li>
        </ul>
    </div>

    <div class="sidebar-footer">
        <div class="user-profile">
            <?php
            // Get staff data from session
            $staff_data = $_SESSION['staff_data'] ?? [];
            $staff_name = $_SESSION['staff_name'] ?? 'Staff Member';
            $staff_photo = $staff_data['photo'] ?? '';
            
            // Determine profile image
            if (!empty($staff_photo) && file_exists('../uploads/staff_photos/' . $staff_photo)) {
                $profile_image = '../uploads/staff_photos/' . $staff_photo;
            } else {
                // Use default avatar based on staff name
                $default_avatar = 'https://ui-avatars.com/api/?name=' . urlencode($staff_name) . '&background=6C5CE7&color=fff&size=44';
                $profile_image = $default_avatar;
            }
            
            // Get staff position/role
            $staff_position = 'Staff';
            $status_color = '#00B894'; // Green for active
            $status_text = 'Active';
            
            // Check if staff is currently working (has attendance today)
            if (isset($_SESSION['staff_id'])) {
                include '../includes/db.php';
                $staff_id = $_SESSION['staff_id'];
                $today = date('Y-m-d');
                
                $check_query = "SELECT * FROM staff_attendance WHERE staff_id = ? AND DATE(date) = ? ORDER BY time_in DESC LIMIT 1";
                $stmt = $conn->prepare($check_query);
                if ($stmt) {
                    $stmt->bind_param("is", $staff_id, $today);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result && $result->num_rows > 0) {
                        $attendance_record = $result->fetch_assoc();
                        if (empty($attendance_record['time_out'])) {
                            $status_text = 'On Duty';
                            $status_color = '#00CEC9'; // Blue for on duty
                        }
                    }
                    $stmt->close();
                }
            }
            ?>
            <img src="<?= htmlspecialchars($profile_image) ?>" alt="<?= htmlspecialchars($staff_name) ?>" class="user-avatar">
            <div class="user-info">
                <h4><?= htmlspecialchars($staff_name) ?></h4>
                <p><?= htmlspecialchars($staff_position) ?> <span class="user-status" style="background: <?= $status_color ?>;"></span></p>
            </div>
        </div>
    </div>
</div>

<!-- Toggle Button -->
<div class="toggle-btn-container">
    <button id="sidebarToggle" class="toggle-btn">
        <i class="fas fa-chevron-left"></i>
    </button>
</div>

<!-- JS Connection -->
<script src="../assets/js/staff/sidebar.js"></script>
