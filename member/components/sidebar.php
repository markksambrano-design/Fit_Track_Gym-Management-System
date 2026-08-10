<!-- Sidebar -->
<link rel="stylesheet" href="../assets/css/admin/sidebar.css">
<link rel="stylesheet" href="../assets/css/member/sidebar.css">

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
            <li><a href="profile.php"><i class="fas fa-user"></i><span class="menu-text">My Profile</span></a></li>
            <li><a href="attendance.php"><i class="fas fa-calendar-check"></i><span class="menu-text">Gym Visit Logs</span></a></li>
            <li><a href="trainers.php"><i class="fas fa-dumbbell"></i><span class="menu-text">Personal Training</span></a></li>
            <li><a href="feedback.php"><i class="fas fa-comment-dots"></i><span class="menu-text">Feedback</span></a></li>
        </ul>
    </div>

    <div class="sidebar-footer">
        <div class="user-profile">
            <?php
            // Get member data from session
            $member_data = $_SESSION['member_data'] ?? [];
            $member_name = $_SESSION['member_name'] ?? 'Member';
            $member_photo = $member_data['photo'] ?? '';
            
            // Determine profile image
            if (!empty($member_photo) && file_exists('../uploads/member_photos/' . $member_photo)) {
                $profile_image = '../uploads/member_photos/' . $member_photo;
            } else {
                // Use default avatar based on member name
                $default_avatar = 'https://ui-avatars.com/api/?name=' . urlencode($member_name) . '&background=6C5CE7&color=fff&size=44';
                $profile_image = $default_avatar;
            }
            
            // Get membership status
            $membership_status = ucfirst($member_data['membership_type'] ?? 'Regular');
            $status_color = '#00B894';
            $status_text = 'Active';
            
            // Check if member is currently checked in
            if (isset($_SESSION['member_id'])) {
                include '../includes/db.php';
                $member_id = $_SESSION['member_id'];
                $today = date('Y-m-d');
                
                $check_query = "SELECT * FROM attendance WHERE member_id = ? AND DATE(date) = ? ORDER BY time_in DESC LIMIT 1";
                $stmt = $conn->prepare($check_query);
                if ($stmt) {
                    $stmt->bind_param("is", $member_id, $today);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result && $result->num_rows > 0) {
                        $attendance_record = $result->fetch_assoc();
                        if (empty($attendance_record['time_out'])) {
                            $status_text = 'In Gym';
                            $status_color = '#00CEC9';
                        }
                    }
                    $stmt->close();
                }
            }
            ?>
            <img src="<?= htmlspecialchars($profile_image) ?>" alt="<?= htmlspecialchars($member_name) ?>" class="user-avatar">
            <div class="user-info">
                <h4><?= htmlspecialchars($member_name) ?></h4>
                <p><?= htmlspecialchars($status_text) ?> <span class="user-status" style="background: <?= $status_color ?>;"></span></p>
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
<script src="../assets/js/member/sidebar.js"></script>
