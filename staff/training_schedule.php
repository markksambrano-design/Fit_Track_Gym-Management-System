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
$staff_id = $_SESSION['staff_id'] ?? 0;

// Handle workout assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_workout'])) {
    $member_id = intval($_POST['member_id']);
    $workout_name = trim($_POST['workout_name']);
    $session_number = intval($_POST['session_number']);
    $workout_date = $_POST['workout_date'];
    $workout_time = $_POST['workout_time'];
    $exercises_data = $_POST['exercises'] ?? [];

    // Filter out empty exercises
    $exercises = [];
    foreach ($exercises_data as $ex) {
        if (!empty(trim($ex['exercise'] ?? ''))) {
            $exercises[] = [
                'exercise' => trim($ex['exercise']),
                'sets' => trim($ex['sets'] ?? ''),
                'reps' => trim($ex['reps'] ?? ''),
                'rest' => trim($ex['rest'] ?? ''),
                'equipment' => trim($ex['equipment'] ?? '')
            ];
        }
    }

    if (empty($exercises)) {
        $_SESSION['error_message'] = "Please add at least one exercise.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    if ($session_number < 1) {
        $_SESSION['error_message'] = "Please select a valid session number.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // Check if session number already exists for this member
    $check_query = "SELECT id FROM member_workout_sessions WHERE member_id = ? AND session_number = ?";
    $check_stmt = $conn->prepare($check_query);
    $exists = false;
    if ($check_stmt) {
        $check_stmt->bind_param("ii", $member_id, $session_number);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $exists = $result->num_rows > 0;
        $check_stmt->close();
    }

    if ($exists) {
        $_SESSION['error_message'] = "Session number $session_number already exists for this member.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // Check if session number is within member's package limit
    $package_query = "SELECT training_package FROM members WHERE id = ?";
    $package_stmt = $conn->prepare($package_query);
    $training_package = 8; // default
    if ($package_stmt) {
        $package_stmt->bind_param("i", $member_id);
        $package_stmt->execute();
        $result = $package_stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $training_package = $row['training_package'] ?? 8;
        }
        $package_stmt->close();
    }

    if ($session_number > $training_package) {
        $_SESSION['error_message'] = "Session number $session_number exceeds member's package limit of $training_package sessions.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // Insert into member_workout_sessions
    $insert_query = "INSERT INTO member_workout_sessions (member_id, trainer_id, workout_plan_id, session_number, workout_name, exercises, status, session_date, session_time, created_at, monthly_session_id) VALUES (?, ?, NULL, ?, ?, ?, 'pending', ?, ?, NOW(), NULL)";
    $insert_stmt = $conn->prepare($insert_query);
    if ($insert_stmt) {
        $staff_id = $_SESSION['staff_id'] ?? 0;
        $exercises_json = json_encode($exercises);
        $insert_stmt->bind_param("iiissss", $member_id, $staff_id, $session_number, $workout_name, $exercises_json, $workout_date, $workout_time);
        if ($insert_stmt->execute()) {
            $_SESSION['success_message'] = "Workout session assigned successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to assign workout session.";
        }
        $insert_stmt->close();
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle session scheduling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_session'])) {
    $member_id = intval($_POST['member_id']);
    $session_date = $_POST['session_date'];
    $session_time = $_POST['session_time'];
    $notes = trim($_POST['notes'] ?? '');

    // Validate inputs
    if (empty($member_id) || empty($session_date) || empty($session_time)) {
        $_SESSION['error_message'] = "All fields are required.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // Check if member is assigned to this trainer
    $check_member_query = "SELECT id FROM members WHERE id = ? AND trainer_id = ?";
    $check_member_stmt = $conn->prepare($check_member_query);
    $is_assigned = false;
    if ($check_member_stmt) {
        $check_member_stmt->bind_param("ii", $member_id, $staff_id);
        $check_member_stmt->execute();
        $result = $check_member_stmt->get_result();
        $is_assigned = $result->num_rows > 0;
        $check_member_stmt->close();
    }

    if (!$is_assigned) {
        $_SESSION['error_message'] = "You can only schedule sessions for your assigned members.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // Check if session slot is available (not already booked by another trainer)
    $check_slot_query = "SELECT id FROM training_sessions WHERE session_date = ? AND session_time = ? AND status = 'booked'";
    $check_slot_stmt = $conn->prepare($check_slot_query);
    $slot_taken = false;
    if ($check_slot_stmt) {
        $check_slot_stmt->bind_param("ss", $session_date, $session_time);
        $check_slot_stmt->execute();
        $result = $check_slot_stmt->get_result();
        $slot_taken = $result->num_rows > 0;
        $check_slot_stmt->close();
    }

    if ($slot_taken) {
        $_SESSION['error_message'] = "This time slot is already booked. Please choose a different time.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // Insert the session
    $insert_session_query = "INSERT INTO training_sessions (member_id, trainer_id, session_date, session_time, status, notes, created_at) VALUES (?, ?, ?, ?, 'booked', ?, NOW())";
    $insert_session_stmt = $conn->prepare($insert_session_query);
    if ($insert_session_stmt) {
        $insert_session_stmt->bind_param("iisss", $member_id, $staff_id, $session_date, $session_time, $notes);
        if ($insert_session_stmt->execute()) {
            $_SESSION['success_message'] = "Training session scheduled successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to schedule training session.";
        }
        $insert_session_stmt->close();
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle marking a workout as completed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_workout'])) {
    $workout_id = intval($_POST['workout_id']);
    if ($workout_id <= 0) {
        $_SESSION['error_message'] = "Invalid workout selected.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // Verify this workout belongs to a member assigned to this trainer
    // Allow completion if either the workout's trainer_id matches the staff OR the member's assigned trainer matches
    // or if this trainer has any scheduled training_sessions for the member
    $check_query = "SELECT mws.trainer_id AS mws_trainer_id, m.trainer_id AS member_trainer_id, mws.member_id,
                           EXISTS(SELECT 1 FROM training_sessions ts WHERE ts.member_id = m.id AND ts.trainer_id = ? LIMIT 1) AS has_schedule
                    FROM member_workout_sessions mws
                    JOIN members m ON mws.member_id = m.id
                    WHERE mws.id = ?";
    $check_stmt = $conn->prepare($check_query);
    $is_owner = false;
    if ($check_stmt) {
        $check_stmt->bind_param("ii", $staff_id, $workout_id);
        $check_stmt->execute();
        $res = $check_stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $is_owner = ((int)($row['mws_trainer_id'] ?? 0) === (int)$staff_id)
                        || ((int)($row['member_trainer_id'] ?? 0) === (int)$staff_id)
                        || ((int)($row['has_schedule'] ?? 0) === 1);
        }
        $check_stmt->close();
    }

    if (!$is_owner) {
        $_SESSION['error_message'] = "You are not authorized to complete this workout.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    $update_query = "UPDATE member_workout_sessions SET status = 'completed', completed_date = NOW() WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    if ($update_stmt) {
        $update_stmt->bind_param("i", $workout_id);
        if ($update_stmt->execute()) {
            $_SESSION['success_message'] = "Workout marked as completed.";
        } else {
            $_SESSION['error_message'] = "Failed to mark workout as completed.";
        }
        $update_stmt->close();
    } else {
        $_SESSION['error_message'] = "Failed to prepare database statement.";
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

$page_title = "Assigned Members & Training Schedules";
include 'components/header.php';

// Get staff information
$staff_id = $_SESSION['staff_id'] ?? 0;
$staff_name = $_SESSION['staff_name'] ?? 'Staff';

// Get training schedule - show unique members assigned to this trainer
$schedule_query = "
    SELECT m.id, m.first_name, m.last_name, m.member_id, m.photo, m.email, m.phone, m.training_package,
           COUNT(ts.id) as total_sessions,
           COUNT(CASE WHEN ts.status IN ('booked', 'in_progress') THEN 1 END) as active_sessions,
           COUNT(CASE WHEN ts.status = 'completed' THEN 1 END) as completed_sessions,
           COUNT(CASE WHEN ts.status = 'available' THEN 1 END) as available_sessions,
           MIN(ts.session_date) as first_session_date,
           MAX(ts.session_date) as last_session_date,
           GROUP_CONCAT(DISTINCT DATE_FORMAT(ts.session_date, '%M %e, %Y') ORDER BY ts.session_date ASC SEPARATOR '; ') as session_dates,
           GROUP_CONCAT(DISTINCT DATE_FORMAT(ts.session_time, '%h:%i %p') ORDER BY ts.session_time ASC SEPARATOR ', ') as session_times,
           COALESCE(MAX(mws.session_number), 0) as current_workout_sessions
    FROM members m
    INNER JOIN training_sessions ts ON m.id = ts.member_id
    LEFT JOIN member_workout_sessions mws ON m.id = mws.member_id
    WHERE ts.trainer_id = ?
    GROUP BY m.id, m.first_name, m.last_name, m.member_id, m.photo, m.email, m.phone, m.training_package
    ORDER BY m.first_name ASC
";

$schedule_stmt = $conn->prepare($schedule_query);
$training_sessions = [];
if ($schedule_stmt) {
    $schedule_stmt->bind_param("i", $staff_id);
    $schedule_stmt->execute();
    $result = $schedule_stmt->get_result();
    $training_sessions = $result->fetch_all(MYSQLI_ASSOC);
    $schedule_stmt->close();
}

// Get member IDs for workout schedules
$member_ids = array_column($training_sessions, 'id');

// Get current month/year for filtering
$current_year = date('Y');
$current_month = date('n');

// Get workout schedules for assigned members (current month only)
$workout_schedules = [];
if (!empty($member_ids)) {
    $placeholders = str_repeat('?,', count($member_ids) - 1) . '?';
    $workout_query = "
        SELECT mws.id, mws.member_id, mws.session_number, mws.workout_name, mws.exercises, mws.status,
               mws.completed_date, mws.created_at, mws.session_date, mws.session_time,
               m.first_name, m.last_name, m.member_id as member_code, m.photo
        FROM member_workout_sessions mws
        JOIN members m ON mws.member_id = m.id
        LEFT JOIN member_monthly_sessions mms ON mws.monthly_session_id = mms.id
        WHERE mws.member_id IN ($placeholders) AND (mms.year = ? AND mms.month = ? OR mms.id IS NULL)
        ORDER BY m.first_name ASC, mws.session_number ASC
    ";

    $workout_stmt = $conn->prepare($workout_query);
    if ($workout_stmt) {
        $types = str_repeat('i', count($member_ids)) . 'ii';
        $params = array_merge($member_ids, [$current_year, $current_month]);
        $workout_stmt->bind_param($types, ...$params);
        $workout_stmt->execute();
        $result = $workout_stmt->get_result();
        $workout_schedules = $result->fetch_all(MYSQLI_ASSOC);
        $workout_stmt->close();
    }
}

// Get all workout sessions for session dropdown
$all_sessions_query = "SELECT member_id, session_number, status, workout_name FROM member_workout_sessions ORDER BY member_id, session_number";
$all_sessions_stmt = $conn->prepare($all_sessions_query);
$all_sessions = [];
if ($all_sessions_stmt) {
    $all_sessions_stmt->execute();
    $result = $all_sessions_stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $all_sessions[$row['member_id']][] = $row;
    }
    $all_sessions_stmt->close();
}

// Get available workout plans
$workouts_query = "SELECT id, workout_name FROM workout_plans ORDER BY workout_name ASC";
$workouts_stmt = $conn->prepare($workouts_query);
$workouts = [];
if ($workouts_stmt) {
    $workouts_stmt->execute();
    $result = $workouts_stmt->get_result();
    $workouts = $result->fetch_all(MYSQLI_ASSOC);
    $workouts_stmt->close();
}
?>

<div class="container-fluid">
    <!-- Page Header with Modern Design -->
    <div class="page-header-section mb-4">
        <div class="header-content">
            <h1 class="page-title">
                <i class="fas fa-users"></i>Assigned Members & Training Schedules
            </h1>
            <p class="page-subtitle">Manage your members and their workout schedules</p>
        </div>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show modern-alert" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show modern-alert" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Training Sessions - Modern Design -->
    <div class="modern-card mb-4">
        <div class="card-header-modern">
            <div class="header-content-modern">
                <h3 class="card-title-modern">
                    <i class="fas fa-users"></i>
                    Member Management
                </h3>
                <p class="card-subtitle-modern">Manage workouts and nutrition for your assigned members</p>
            </div>
        </div>
        
        <!-- Tabs -->
        <ul class="nav nav-tabs" id="memberManagementTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="workouts-tab" data-bs-toggle="tab" data-bs-target="#workouts" type="button" role="tab" aria-controls="workouts" aria-selected="true">
                    <i class="fas fa-dumbbell"></i> Workouts
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="nutrition-tab" data-bs-toggle="tab" data-bs-target="#nutrition" type="button" role="tab" aria-controls="nutrition" aria-selected="false">
                    <i class="fas fa-utensils"></i> Nutrition (VIP)
                </button>
            </li>
        </ul>
        
        <div class="tab-content" id="memberManagementTabsContent">
            <!-- Workouts Tab -->
            <div class="tab-pane fade show active" id="workouts" role="tabpanel" aria-labelledby="workouts-tab">
                <div class="card-body-modern">
                    <div class="search-input-container mb-3">
                        <input type="text" id="trainingScheduleSearch" class="form-control search-input-modern" placeholder="Search members...">
                        <i class="fas fa-search search-icon"></i>
                    </div>
                    
                    <?php if (empty($training_sessions)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <h3 class="empty-title">No Assigned Members</h3>
                            <p class="empty-description">You don't have any members assigned to you yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive-modern">
                            <table class="modern-table" id="trainingScheduleTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Member</th>
                                        <th>Package</th>
                                        <th>Total Sessions</th>
                                        <th>Active Sessions</th>
                                        <th>Completed Sessions</th>
                                        <th>Available Slots</th>
                                        <th>Session Period</th>
                                        <th>Session Times</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($training_sessions as $member): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?php echo !empty($member['photo']) && file_exists('../uploads/member_photos/' . $member['photo'])
                                                             ? '../uploads/member_photos/' . $member['photo']
                                                             : 'https://ui-avatars.com/api/?name=' . urlencode($member['first_name'] . ' ' . $member['last_name']) . '&background=6C5CE7&color=fff&size=32'; ?>"
                                                         alt="<?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>"
                                                         class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;">
                                                    <div>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></div>
                                                        <small class="text-muted"><?php echo htmlspecialchars($member['member_id']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo $member['training_package'] ?? 8; ?> sessions/month</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo $member['total_sessions']; ?> total</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-warning"><?php echo $member['active_sessions']; ?> active</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-success"><?php echo $member['completed_sessions']; ?> completed</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $member['available_sessions']; ?> available</span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php
                                                    if (!empty($member['first_session_date']) && !empty($member['last_session_date'])) {
                                                        if ($member['first_session_date'] === $member['last_session_date']) {
                                                            echo date('M j, Y', strtotime($member['first_session_date']));
                                                        } else {
                                                            echo date('M j', strtotime($member['first_session_date'])) . ' - ' . date('M j, Y', strtotime($member['last_session_date']));
                                                        }
                                                    } else {
                                                        echo 'No sessions';
                                                    }
                                                    ?>
                                                </small>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php 
                                                    if (!empty($member['session_times'])) {
                                                        $times = explode(', ', $member['session_times']);
                                                        echo implode('<br>', array_slice($times, 0, 3));
                                                        if (count($times) > 3) {
                                                            echo '<br><em>...and ' . (count($times) - 3) . ' more</em>';
                                                        }
                                                    } else {
                                                        echo 'No sessions';
                                                    }
                                                    ?>
                                                </small>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-primary btn-sm" onclick="openScheduleModal(<?php echo $member['id']; ?>, '<?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>')">
                                                    <i class="fas fa-calendar-plus me-1"></i>Schedule Session
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Nutrition Tab -->
            <div class="tab-pane fade" id="nutrition" role="tabpanel" aria-labelledby="nutrition-tab">
                <div class="card-body-modern">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="search-input-container">
                            <input type="text" id="nutritionSearch" class="form-control search-input-modern" placeholder="Search VIP members...">
                            <i class="fas fa-search search-icon"></i>
                        </div>
                        <button class="btn btn-primary" onclick="createNutritionPlan()">
                            <i class="fas fa-plus"></i> Create Nutrition Plan
                        </button>
                    </div>
                    
                    <?php
                    // Get VIP members assigned to this trainer
                    $vip_members_query = "SELECT m.id, m.member_id, m.first_name, m.last_name, m.photo, m.membership_type,
                                                 COUNT(DISTINCT CASE WHEN mn.is_plan = 1 THEN mn.id END) as nutrition_plans_count,
                                                 MAX(CASE WHEN mn.is_plan = 1 THEN mn.assigned_date END) as last_plan_date
                                          FROM members m
                                          LEFT JOIN member_nutrition_logs mn ON m.id = mn.member_id
                                          WHERE m.membership_type = 'vip' 
                                          AND (m.trainer_id = ? OR EXISTS(SELECT 1 FROM training_sessions ts WHERE ts.member_id = m.id AND ts.trainer_id = ?))
                                          GROUP BY m.id
                                          ORDER BY m.first_name, m.last_name";
                    $vip_stmt = $conn->prepare($vip_members_query);
                    $vip_members = [];
                    if ($vip_stmt) {
                        $vip_stmt->bind_param("ii", $staff_id, $staff_id);
                        $vip_stmt->execute();
                        $result = $vip_stmt->get_result();
                        while ($row = $result->fetch_assoc()) {
                            $vip_members[] = $row;
                        }
                        $vip_stmt->close();
                    }
                    ?>
                    
                    <?php if (empty($vip_members)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="fas fa-crown"></i>
                            </div>
                            <h3 class="empty-title">No VIP Members</h3>
                            <p class="empty-description">You don't have any VIP members assigned to you yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive-modern">
                            <table class="modern-table" id="nutritionTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Member</th>
                                        <th>Membership</th>
                                        <th>Nutrition Plans</th>
                                        <th>Last Plan Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($vip_members as $member): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?php echo !empty($member['photo']) && file_exists('../uploads/member_photos/' . $member['photo'])
                                                             ? '../uploads/member_photos/' . $member['photo']
                                                             : 'https://ui-avatars.com/api/?name=' . urlencode($member['first_name'] . ' ' . $member['last_name']) . '&background=6C5CE7&color=fff&size=32'; ?>"
                                                         alt="<?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>"
                                                         class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;">
                                                    <div>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></div>
                                                        <small class="text-muted"><?php echo htmlspecialchars($member['member_id']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-warning text-dark">
                                                    <i class="fas fa-crown"></i> <?php echo htmlspecialchars(ucfirst($member['membership_type'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $member['nutrition_plans_count']; ?> plans</td>
                                            <td><?php echo $member['last_plan_date'] ? date('M d, Y', strtotime($member['last_plan_date'])) : 'Never'; ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-sm btn-outline-primary" onclick="assignNutritionPlan(<?php echo $member['id']; ?>, '<?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>')">
                                                        <i class="fas fa-plus"></i> Assign Plan
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-info" onclick="viewNutritionPlans(<?php echo $member['id']; ?>)">
                                                        <i class="fas fa-eye"></i> View Plans
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Member Workout Schedules - Modern Design -->
    <div class="modern-card mb-4">
        <div class="card-header-modern">
            <div class="header-content-modern">
                <h3 class="card-title-modern">
                    <i class="fas fa-dumbbell"></i>
                    Member Workout Schedules
                </h3>
                <p class="card-subtitle-modern">Current month's workout assignments and progress</p>
            </div>
        </div>
        <div class="card-body-modern">
            <?php if (empty($workout_schedules)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-dumbbell"></i>
                    </div>
                    <h3 class="empty-title">No Workout Schedules</h3>
                    <p class="empty-description">Your assigned members don't have workout schedules yet.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive-modern">
                    <table class="modern-table" id="workoutScheduleTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Session #</th>
                                <th>Workout Name</th>
                                <th>Exercises</th>
                                <th>Status</th>
                                <th>Date & Time</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($workout_schedules as $workout): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="<?php echo !empty($workout['photo']) && file_exists('../uploads/member_photos/' . $workout['photo'])
                                                     ? '../uploads/member_photos/' . $workout['photo']
                                                     : 'https://ui-avatars.com/api/?name=' . urlencode($workout['first_name'] . ' ' . $workout['last_name']) . '&background=6C5CE7&color=fff&size=32'; ?>"
                                                 alt="<?php echo htmlspecialchars($workout['first_name'] . ' ' . $workout['last_name']); ?>"
                                                 class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;">
                                            <div>
                                                <div class="fw-bold"><?php echo htmlspecialchars($workout['first_name'] . ' ' . $workout['last_name']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($workout['member_code']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $workout['session_number']; ?></span>
                                    </td>
                                    <td>
                                        <strong>🔹 SESSION <?php echo $workout['session_number']; ?> – <?php echo htmlspecialchars($workout['workout_name'] ?? 'Unnamed Workout'); ?></strong>
                                    </td>
                                    <td>
                                        <?php
                                        if (!empty($workout['exercises'])) {
                                            $exercises = json_decode($workout['exercises'], true);
                                            if (is_array($exercises) && !empty($exercises)) {
                                                // Check if exercises are structured (array of objects) or simple array
                                                $is_structured = is_array($exercises[0] ?? null) && isset($exercises[0]['exercise']);
                                                if ($is_structured) {
                                                    echo '<div class="exercises-container">';
                                                    $exercise_count = 0;
                                                    foreach ($exercises as $ex) {
                                                        if (!empty(trim($ex['exercise'] ?? ''))) {
                                                            $exercise_count++;
                                                            echo '<div class="exercise-item">';
                                                            echo '<div class="exercise-name"><i class="fas fa-dumbbell"></i> ' . htmlspecialchars($ex['exercise'] ?? '') . '</div>';
                                                            echo '<div class="exercise-details">';
                                                            if (!empty($ex['sets'])) echo '<span class="detail-badge"><strong>Sets:</strong> ' . htmlspecialchars($ex['sets']) . '</span>';
                                                            if (!empty($ex['reps'])) echo '<span class="detail-badge"><strong>Reps:</strong> ' . htmlspecialchars($ex['reps']) . '</span>';
                                                            if (!empty($ex['rest'])) echo '<span class="detail-badge"><strong>Rest:</strong> ' . htmlspecialchars($ex['rest']) . '</span>';
                                                            if (!empty($ex['equipment'])) echo '<span class="detail-badge"><strong>Equipment:</strong> ' . htmlspecialchars($ex['equipment']) . '</span>';
                                                            echo '</div>';
                                                            echo '</div>';
                                                        }
                                                    }
                                                    echo '</div>';
                                                    if ($exercise_count === 0) {
                                                        echo '<small class="text-muted">No exercises listed</small>';
                                                    }
                                                } else {
                                                    // Fallback to list if not structured
                                                    echo '<div class="exercises-list">';
                                                    $exercise_count = 0;
                                                    foreach ($exercises as $ex) {
                                                        if (!empty($ex)) {
                                                            $exercise_count++;
                                                            if ($exercise_count <= 5) {
                                                                $name = is_array($ex) ? ($ex['exercise'] ?? $ex[0] ?? 'Unknown') : (string)$ex;
                                                                echo '<div class="exercise-badge"><i class="fas fa-arrow-right"></i> ' . htmlspecialchars($name) . '</div>';
                                                            }
                                                        }
                                                    }
                                                    if ($exercise_count > 5) {
                                                        echo '<div class="exercise-badge more-badge">... +' . ($exercise_count - 5) . ' more</div>';
                                                    }
                                                    echo '</div>';
                                                }
                                            } else {
                                                echo '<small class="text-muted">Unable to parse exercises</small>';
                                            }
                                        } else {
                                            echo '<small class="text-muted">No exercises listed</small>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = match($workout['status']) {
                                            'completed' => 'bg-success',
                                            'in_progress' => 'bg-warning',
                                            'pending' => 'bg-secondary',
                                            'skipped' => 'bg-danger',
                                            default => 'bg-light'
                                        };
                                        $status_text = match($workout['status']) {
                                            'completed' => 'Completed',
                                            'in_progress' => 'In Progress',
                                            'pending' => 'Pending',
                                            'skipped' => 'Skipped',
                                            default => ucfirst($workout['status'])
                                        };
                                        ?>
                                        <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php if (!empty($workout['session_date']) && !empty($workout['session_time'])): ?>
                                                <?php echo date('M j, Y', strtotime($workout['session_date'])); ?><br>
                                                <?php echo date('g:i A', strtotime($workout['session_time'])); ?>
                                        
                                                <?php else: ?>
                                                Not scheduled
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php if ($workout['status'] !== 'completed'): ?>
                                        <form method="post" style="display: inline;" onsubmit="return confirm('Mark this workout as complete?');">
                                            <input type="hidden" name="workout_id" value="<?php echo $workout['id']; ?>">
                                            <button type="submit" name="complete_workout" class="btn btn-sm btn-success">
                                                <i class="fas fa-check"></i> Complete
                                            </button>
                                        </form>
                                        <?php endif; ?>

                                        <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this workout session?');">
                                            <input type="hidden" name="workout_id" value="<?php echo $workout['id']; ?>">
                                            <button type="submit" name="delete_workout" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Assign Workout Sessions - Modern Design -->
    <div class="modern-card mb-4">
        <div class="card-header-modern">
            <div class="header-content-modern">
                <h3 class="card-title-modern">
                    <i class="fas fa-plus"></i>
                    Assign Workout Sessions
                </h3>
                <p class="card-subtitle-modern">Create and assign new workout sessions to your members</p>
            </div>
        </div>
        <div class="card-body-modern">
            <form method="post" action="">
                <div class="row">
                    <div class="col-md-6">
                        <label for="member_id" class="form-label">Select Member</label>
                        <select class="form-control" name="member_id" id="member_id" required>
                            <option value="">Choose a member...</option>
                            <?php foreach ($training_sessions as $member): ?>
                                <option value="<?php echo $member['id']; ?>"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?> (<?php echo htmlspecialchars($member['member_id']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="workout_name" class="form-label">Workout Name</label>
                        <select class="form-control" name="workout_name" required>
                            <option value="">Choose workout type...</option>
                            <option value="FULL BODY (INTRO)">FULL BODY (INTRO)</option>
                            <option value="FULL BODY (BEGINNER)">FULL BODY (BEGINNER)</option>
                            <option value="FULL BODY (INTERMEDIATE)">FULL BODY (INTERMEDIATE)</option>
                            <option value="FULL BODY (ADVANCED)">FULL BODY (ADVANCED)</option>
                            <option value="UPPER BODY FOCUS">UPPER BODY FOCUS</option>
                            <option value="LOWER BODY FOCUS">LOWER BODY FOCUS</option>
                            <option value="PUSH DAY">PUSH DAY</option>
                            <option value="PULL DAY">PULL DAY</option>
                            <option value="LEG DAY">LEG DAY</option>
                            <option value="CORE WORKOUT">CORE WORKOUT</option>
                            <option value="CARDIO CIRCUIT">CARDIO CIRCUIT</option>
                            <option value="STRENGTH TRAINING">STRENGTH TRAINING</option>
                            <option value="HIIT WORKOUT">HIIT WORKOUT</option>
                            <option value="FLEXIBILITY & MOBILITY">FLEXIBILITY & MOBILITY</option>
                            <option value="CROSSFIT STYLE">CROSSFIT STYLE</option>
                            <option value="BODYWEIGHT ONLY">BODYWEIGHT ONLY</option>
                            <option value="WEIGHT TRAINING">WEIGHT TRAINING</option>
                            <option value="CIRCUIT TRAINING">CIRCUIT TRAINING</option>
                            <option value="FUNCTIONAL TRAINING">FUNCTIONAL TRAINING</option>
                        </select>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <label for="session_number" class="form-label">Session Number</label>
                        <select class="form-control" name="session_number" id="session_number" required>
                            <option value="">Select session...</option>
                            <!-- Options will be populated by JavaScript based on member selection -->
                        </select>
                        <small class="text-muted" id="session_info">Select a member to see available sessions</small>
                    </div>
                    <div class="col-md-3">
                        <label for="workout_date" class="form-label">Workout Date</label>
                        <input type="date" class="form-control" name="workout_date" id="workout_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="workout_time" class="form-label">Workout Time</label>
                        <input type="time" class="form-control" name="workout_time" id="workout_time" value="09:00" required>
                    </div>
                </div>
                <div class="mt-3">
                    <h6>Exercises</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered exercises-table">
                            <thead>
                                <tr>
                                    <th>Exercise</th>
                                    <th>Sets</th>
                                    <th>Reps</th>
                                    <th>Rest</th>
                                    <th>Equipment</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $common_exercises = [
                                    'Push-ups', 'Pull-ups', 'Squats', 'Lunges', 'Planks', 'Burpees', 
                                    'Bench Press', 'Deadlift', 'Shoulder Press', 'Bicep Curls', 'Tricep Dips',
                                    'Treadmill', 'Jump Rope', 'Cycling', 'Rowing', 'Elliptical',
                                    'Lat Pulldown', 'Chest Fly', 'Leg Press', 'Calf Raises', 'Russian Twists'
                                ];
                                $sets_options = ['1', '2', '3', '4', '5', '6', '8', '10', '12'];
                                $reps_options = ['5', '8', '10', '12', '15', '20', '25', '30', '45', '60', '90', '120'];
                                $rest_options = ['30 sec', '45 sec', '60 sec', '90 sec', '2 min', '3 min', '5 min'];
                                $equipment_options = [
                                    'Bodyweight', 'Dumbbells', 'Barbell', 'Resistance Bands', 'Kettlebell',
                                    'Treadmill', 'Jump Rope', 'Stationary Bike', 'Rowing Machine', 'Elliptical',
                                    'Bench', 'Pull-up Bar', 'Smith Machine', 'Cable Machine', 'Leg Press Machine'
                                ];
                                for ($i = 0; $i < 6; $i++): 
                                ?>
                                <tr>
                                    <td>
                                        <select class="form-control" name="exercises[<?php echo $i; ?>][exercise]">
                                            <option value="">Choose exercise...</option>
                                            <?php foreach ($common_exercises as $exercise): ?>
                                                <option value="<?php echo htmlspecialchars($exercise); ?>"><?php echo htmlspecialchars($exercise); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <select class="form-control" name="exercises[<?php echo $i; ?>][sets]">
                                            <option value="">Sets</option>
                                            <?php foreach ($sets_options as $sets): ?>
                                                <option value="<?php echo htmlspecialchars($sets); ?>"><?php echo htmlspecialchars($sets); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <select class="form-control" name="exercises[<?php echo $i; ?>][reps]">
                                            <option value="">Reps</option>
                                            <?php foreach ($reps_options as $reps): ?>
                                                <option value="<?php echo htmlspecialchars($reps); ?>"><?php echo htmlspecialchars($reps); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <select class="form-control" name="exercises[<?php echo $i; ?>][rest]">
                                            <option value="">Rest</option>
                                            <?php foreach ($rest_options as $rest): ?>
                                                <option value="<?php echo htmlspecialchars($rest); ?>"><?php echo htmlspecialchars($rest); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <select class="form-control" name="exercises[<?php echo $i; ?>][equipment]">
                                            <option value="">Equipment</option>
                                            <?php foreach ($equipment_options as $equipment): ?>
                                                <option value="<?php echo htmlspecialchars($equipment); ?>"><?php echo htmlspecialchars($equipment); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                    <small class="text-muted">Leave exercise fields empty if not needed. At least one exercise is required.</small>
                </div>
                <div class="mt-3">
                    <button type="submit" name="assign_workout" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Assign Workout Session
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Prepare member data for JavaScript
$member_data = [];
foreach ($training_sessions as $member) {
    $member_sessions = $all_sessions[$member['id']] ?? [];
    $sessions_obj = [];
    foreach ($member_sessions as $session) {
        if (is_array($session) && isset($session['session_number'], $session['status'], $session['workout_name'])) {
            $session_num = $session['session_number'];
            $status = $session['status'];
            $name = $session['workout_name'];
            if (is_scalar($session_num) && is_scalar($status) && is_scalar($name)) {
                $sessions_obj[(string)$session_num] = [
                    'status' => (string)$status,
                    'name' => (string)$name
                ];
            }
        }
    }
    $member_data[(string)$member['id']] = [
        'id' => (string)$member['id'],
        'name' => $member['first_name'] . ' ' . $member['last_name'],
        'currentSessions' => (int)($member['current_workout_sessions'] ?? 0),
        'trainingPackage' => (int)($member['training_package'] ?? 8),
        'sessions' => $sessions_obj
    ];
}
?>

<!-- DataTables for better table functionality -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>

<script>
$(document).ready(function() {
    var trainingTable = $('#trainingScheduleTable').DataTable({
        "order": [[1, "asc"], [2, "asc"]],
        "paging": false,
        "responsive": true,
        "lengthChange": false,
        "searching": false
    });

    // Add search functionality
    $('#trainingScheduleSearch').on('keyup', function() {
        trainingTable.search($(this).val()).draw();
    });

    $('#workoutScheduleTable').DataTable({
        "order": [[0, "asc"], [1, "asc"]],
        "paging": false,
        "responsive": true,
        "lengthChange": false,
        "searching": false
    });
});

var memberData = <?php echo json_encode($member_data); ?>;

// Handle member selection to populate session dropdown
$('#member_id').change(function() {
    var memberId = $(this).val();
    var sessionSelect = $('#session_number');
    var sessionInfo = $('#session_info');
    
    if (memberId && memberData[memberId]) {
        var selectedMember = memberData[memberId];
        var memberSessions = selectedMember.sessions;
        
        var currentSessions = selectedMember.currentSessions;
        var trainingPackage = selectedMember.trainingPackage;
        var nextSession = currentSessions + 1;
        
        // Clear existing options
        sessionSelect.empty();
        sessionSelect.append('<option value="">Select session...</option>');
        
        // Show existing sessions with their status
        var existingSessions = Object.keys(memberSessions).length;
        if (existingSessions > 0) {
            sessionSelect.append('<optgroup label="Existing Sessions">');
            for (var sessionNum in memberSessions) {
                var session = memberSessions[sessionNum];
                var statusText = session.status.charAt(0).toUpperCase() + session.status.slice(1).replace('_', ' ');
                var displayText = 'Session ' + sessionNum + ' - ' + statusText + ' (' + session.name + ')';
                sessionSelect.append('<option value="' + sessionNum + '" disabled>' + displayText + '</option>');
            }
            sessionSelect.append('</optgroup>');
        }
        
        // Add available sessions within package limit
        sessionSelect.append('<optgroup label="Available Sessions">');
        var firstAvailable = null;
        for (var i = 1; i <= trainingPackage; i++) {
            if (!memberSessions.hasOwnProperty(i.toString())) {
                var label = (i == nextSession) ? ' (Recommended)' : '';
                var selected = (i == nextSession && i <= trainingPackage) ? ' selected' : '';
                if (!firstAvailable) firstAvailable = i;
                sessionSelect.append('<option value="' + i + '"' + selected + '>Session ' + i + label + '</option>');
            }
        }
        sessionSelect.append('</optgroup>');
        
        // Auto-select the recommended session
        if (firstAvailable) {
            sessionSelect.val(firstAvailable);
        }
        
        sessionInfo.html('Member package: <strong>' + trainingPackage + ' sessions</strong>. Has <strong>' + existingSessions + '</strong> assigned. Next available: <strong>Session ' + (firstAvailable || 'None') + '</strong>');
    } else {
        sessionSelect.empty();
        sessionSelect.append('<option value="">Select session...</option>');
        sessionInfo.text('Select a member to see available sessions');
    }
});

// Handle exercise selection to auto-populate equipment (only if not already set)
$(document).on('change', 'select[name*="exercise"]', function() {
    var exerciseSelect = $(this);
    var exerciseValue = exerciseSelect.val();
    var row = exerciseSelect.closest('tr');
    var equipmentSelect = row.find('select[name*="equipment"]');
    
    // Only auto-populate if equipment is empty or default
    if (equipmentSelect.val() === '' || equipmentSelect.val() === 'Bodyweight') {
        // Define exercise to equipment mapping
        var exerciseEquipmentMap = {
            'Push-ups': 'Bodyweight',
            'Pull-ups': 'Pull-up Bar',
            'Squats': 'Bodyweight',
            'Lunges': 'Bodyweight',
            'Planks': 'Bodyweight',
            'Burpees': 'Bodyweight',
            'Bench Press': 'Bench',
            'Deadlift': 'Barbell',
            'Shoulder Press': 'Dumbbells',
            'Bicep Curls': 'Dumbbells',
            'Tricep Dips': 'Bench',
            'Treadmill': 'Treadmill',
            'Jump Rope': 'Jump Rope',
            'Cycling': 'Stationary Bike',
            'Rowing': 'Rowing Machine',
            'Elliptical': 'Elliptical',
            'Lat Pulldown': 'Cable Machine',
            'Chest Fly': 'Dumbbells',
            'Leg Press': 'Leg Press Machine',
            'Calf Raises': 'Bodyweight',
            'Russian Twists': 'Bodyweight'
        };
        
        if (exerciseValue && exerciseEquipmentMap[exerciseValue]) {
            equipmentSelect.val(exerciseEquipmentMap[exerciseValue]);
        } else if (exerciseValue) {
            // Default to Bodyweight for unknown exercises
            equipmentSelect.val('Bodyweight');
        }
    }
});
</script>

<!-- Schedule Session Modal -->
<div class="modal fade" id="scheduleModal" tabindex="-1" aria-labelledby="scheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="scheduleModalLabel">Schedule Training Session</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="schedule_session" value="1">
                    <input type="hidden" name="member_id" id="scheduleMemberId">

                    <div class="mb-3">
                        <label for="scheduleMemberName" class="form-label">Member</label>
                        <input type="text" class="form-control" id="scheduleMemberName" readonly>
                    </div>

                    <div class="mb-3">
                        <label for="sessionDate" class="form-label">Session Date</label>
                        <input type="date" class="form-control" id="sessionDate" name="session_date" min="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="sessionTime" class="form-label">Session Time</label>
                        <select class="form-select" id="sessionTime" name="session_time" required>
                            <option value="">Select Time</option>
                            <option value="07:00:00">7:00 AM</option>
                            <option value="08:00:00">8:00 AM</option>
                            <option value="09:00:00">9:00 AM</option>
                            <option value="10:00:00">10:00 AM</option>
                            <option value="11:00:00">11:00 AM</option>
                            <option value="12:00:00">12:00 PM</option>
                            <option value="13:00:00">1:00 PM</option>
                            <option value="14:00:00">2:00 PM</option>
                            <option value="15:00:00">3:00 PM</option>
                            <option value="16:00:00">4:00 PM</option>
                            <option value="17:00:00">5:00 PM</option>
                            <option value="18:00:00">6:00 PM</option>
                            <option value="19:00:00">7:00 PM</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="sessionNotes" class="form-label">Notes (Optional)</label>
                        <textarea class="form-control" id="sessionNotes" name="notes" rows="3" placeholder="Any special instructions or goals for this session..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Schedule Session</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openScheduleModal(memberId, memberName) {
    document.getElementById('scheduleMemberId').value = memberId;
    document.getElementById('scheduleMemberName').value = memberName;
    document.getElementById('scheduleModalLabel').textContent = 'Schedule Session for ' + memberName;

    // Reset form
    document.getElementById('scheduleModal').querySelector('form').reset();

    // Set member info
    document.getElementById('scheduleMemberId').value = memberId;
    document.getElementById('scheduleMemberName').value = memberName;

    // Show modal
    new bootstrap.Modal(document.getElementById('scheduleModal')).show();
}
</script>

<!-- Modern Styles for Training Schedule -->
<style>
/* Exercises Display Styles */
.exercises-container {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.exercise-item {
    background: rgba(59, 130, 246, 0.1);
    border-left: 3px solid #3b82f6;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 0;
}

.exercise-name {
    font-weight: 600;
    color: #f8f9fc;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    font-size: 0.95rem;
}

.exercise-name i {
    color: #3b82f6;
    font-size: 0.9rem;
}

.exercise-details {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.detail-badge {
    background: rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.85);
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 500;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.detail-badge strong {
    color: #3b82f6;
    font-weight: 600;
}

.exercises-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.exercise-badge {
    background: rgba(59, 130, 246, 0.15);
    color: #60a5fa;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 0.85rem;
    border-left: 2px solid #3b82f6;
    display: flex;
    align-items: center;
    gap: 6px;
    font-weight: 500;
}

.exercise-badge i {
    font-size: 0.7rem;
}

.more-badge {
    background: rgba(251, 191, 36, 0.1);
    color: #fbbf24;
    border-left-color: #fbbf24;
    font-style: italic;
}

/* Search Input Styles */
.search-input-container {
    position: relative;
    width: 100%;
    max-width: 300px;
    min-width: 250px;
}

.search-input-modern {
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: #f8f9fc;
    padding: 10px 15px 10px 35px;
    border-radius: 10px;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    width: 100%;
}

.search-input-modern::placeholder {
    color: rgba(255, 255, 255, 0.5);
}

.search-input-modern:focus {
    outline: none;
    background: rgba(255, 255, 255, 0.12);
    border-color: rgba(59, 130, 246, 0.5);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.9rem;
    pointer-events: none;
}

/* Page Header Section */
.page-header-section {
    margin-bottom: 40px;
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.page-title {
    font-size: 2.5rem;
    font-weight: 700;
    color: #f8f9fc;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 15px;
}

.page-title i {
    color: #3b82f6;
    font-size: 2rem;
}

.page-subtitle {
    color: rgba(255, 255, 255, 0.7);
    font-size: 1rem;
    margin: 0;
}

/* Modern Alert Styles */
.modern-alert {
    background: rgba(30, 41, 59, 0.7);
    -webkit-backdrop-filter: blur(15px);
    backdrop-filter: blur(15px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 15px;
    color: white;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    padding: 16px 20px;
}

.modern-alert.alert-success {
    border-left: 4px solid #10b981;
    background: rgba(16, 185, 129, 0.1);
}

.modern-alert.alert-danger {
    border-left: 4px solid #ef4444;
    background: rgba(239, 68, 68, 0.1);
}

/* Modern Card Styles */
.modern-card {
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

.modern-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
    border-color: rgba(255, 255, 255, 0.2);
    background: rgba(30, 41, 59, 0.9);
}

.card-header-modern {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 25px 30px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    flex-wrap: wrap;
    gap: 20px;
}

.header-content-modern {
    flex: 1;
    min-width: 300px;
}

.card-title-modern {
    font-size: 1.8rem;
    font-weight: 600;
    color: #f8f9fc;
    margin: 0 0 8px 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.card-title-modern i {
    color: #3b82f6;
    font-size: 1.5rem;
}

.card-subtitle-modern {
    color: rgba(255, 255, 255, 0.7);
    font-size: 1rem;
    margin: 0;
    font-weight: 400;
}

.card-body-modern {
    padding: 30px;
}

/* Modern Table Styles */
    .table-responsive-modern {
    overflow-x: hidden; /* prevent horizontal scroll */
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    max-height: 500px;
    overflow-y: auto;
    position: relative;
}

.modern-table {
    width: 100%;
    border-collapse: collapse;
    background: rgba(15, 23, 42, 0.8);
    -webkit-backdrop-filter: blur(10px);
    backdrop-filter: blur(10px);
    position: relative;
}

.modern-table thead {
    background: rgba(15, 23, 42, 0.95);
    border-bottom: 2px solid #3b82f6;
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.6);
}

.modern-table th {
    padding: 20px 15px;
    text-align: left;
    font-weight: 600;
    color: #f8f9fc;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: none;
    background: rgba(15, 23, 42, 0.95);
    position: relative;
}

.modern-table td {
    padding: 20px 15px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.9rem;
}

.table-row-modern:hover {
    background: rgba(59, 130, 246, 0.05);
    transform: translateX(5px);
    transition: all 0.3s ease;
}

/* Member Cell */
.member-cell {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.member-code {
    color: rgba(255, 255, 255, 0.6);
    display: block;
}

/* Badge Styles */
.badge-modern {
    background: rgba(59, 130, 246, 0.2);
    color: #3b82f6;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    border: 1px solid rgba(59, 130, 246, 0.3);
    display: inline-block;
}

.badge-info {
    background: rgba(59, 130, 246, 0.2);
    color: #3b82f6;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    border: 1px solid rgba(59, 130, 246, 0.3);
    display: inline-block;
}

.badge-success {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    border: 1px solid rgba(16, 185, 129, 0.3);
    display: inline-block;
}

/* Button Styles */
.btn-schedule {
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    border: none;
    color: white;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 600;
    transition: all 0.3s ease;
    cursor: pointer;
}

.btn-schedule:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 15px rgba(59, 130, 246, 0.3);
    background: linear-gradient(135deg, #1d4ed8, #1e40af);
    color: white;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: rgba(255, 255, 255, 0.6);
}

.empty-icon {
    font-size: 4rem;
    margin-bottom: 20px;
    color: rgba(255, 255, 255, 0.3);
}

.empty-title {
    color: #f8f9fc;
    margin-bottom: 10px;
    font-weight: 600;
    font-size: 1.5rem;
}

.empty-description {
    color: rgba(255, 255, 255, 0.6);
    font-size: 1rem;
    margin: 0;
}

/* Form Styles */
.form-label {
    color: rgba(255, 255, 255, 0.9);
    font-weight: 600;
    margin-bottom: 8px;
}

.form-control,
.form-select {
    background: rgba(15, 23, 42, 0.8);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: white;
    border-radius: 8px;
}

.form-control:focus,
.form-select:focus {
    background: rgba(15, 23, 42, 0.95);
    border-color: #3b82f6;
    box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
    color: white;
}

.form-control::placeholder {
    color: rgba(255, 255, 255, 0.5);
}

/* Responsive Design */
@media (max-width: 768px) {
    .page-title {
        font-size: 1.8rem;
    }
    
    .header-content {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .card-header-modern {
        flex-direction: column;
        align-items: stretch;
    }
    
    .modern-table th,
    .modern-table td {
        padding: 15px 10px;
        font-size: 0.85rem;
    }
    
    .table-responsive-modern {
        overflow-x: auto;
    }
}

/* Print Styles */
@media print {
    .modern-card {
        box-shadow: none !important;
        border: 1px solid #dee2e6 !important;
        background: white !important;
        color: black !important;
    }
    
    .modern-table {
        background: white !important;
    }
    
    .modern-table th,
    .modern-table td {
        color: black !important;
        border: 1px solid #dee2e6 !important;
    }
}

/* Text Contrast Improvements */
.modern-card .text-muted,
.table-responsive-modern .text-muted,
.card-body-modern .text-muted,
.header-content .page-subtitle,
.modern-table .dataTables_empty,
small {
    color: rgba(255, 255, 255, 0.78) !important;
}

/* Specific fix for session times in table */
.modern-table td small {
    color: rgba(255, 255, 255, 0.9) !important;
}

/* Ensure table badges and inline labels are readable */
.modern-table .badge,
.modern-card .badge {
    color: rgba(255,255,255,0.95) !important;
}

/* Exercises Table Styles - Match modern table background */
.exercises-table {
    background: rgba(15, 23, 42, 0.8);
    -webkit-backdrop-filter: blur(10px);
    backdrop-filter: blur(10px);
    color: rgba(255, 255, 255, 0.9);
    border-collapse: collapse;
}

.exercises-table thead {
    background: rgba(15, 23, 42, 0.95);
}

.exercises-table th,
.exercises-table td {
    background: rgba(15, 23, 42, 0.8);
    border: 1px solid rgba(255, 255, 255, 0.1);
    padding: 8px 12px;
    color: rgba(255, 255, 255, 0.9);
}

.exercises-table th {
    font-weight: 600;
    color: #f8f9fc;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
</style>

<style>
/* Nutrition Plan Modal - Modern Glassmorphism Design */
#nutritionPlanModal .modal-content {
    background: rgba(30, 39, 46, 0.95);
    -webkit-backdrop-filter: blur(20px);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
    color: white;
}

#nutritionPlanModal .modal-header {
    background: linear-gradient(135deg, rgba(108, 92, 231, 0.2), rgba(253, 121, 168, 0.2));
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px 20px 0 0;
    padding: 20px 30px;
}

#nutritionPlanModal .modal-title {
    color: white;
    font-weight: 600;
    font-size: 1.25rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

#nutritionPlanModal .modal-title i {
    color: #A29BFE;
    font-size: 1.4rem;
}

#nutritionPlanModal .btn-close {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    opacity: 0.8;
    transition: all 0.3s ease;
}

#nutritionPlanModal .btn-close:hover {
    background: rgba(255, 255, 255, 0.2);
    opacity: 1;
    transform: scale(1.1);
}

#nutritionPlanModal .modal-body {
    padding: 30px;
    background: rgba(255, 255, 255, 0.02);
}

#nutritionPlanModal .form-label {
    color: rgba(255, 255, 255, 0.9);
    font-weight: 500;
    margin-bottom: 8px;
    font-size: 0.9rem;
}

#nutritionPlanModal .form-control,
#nutritionPlanModal .form-select {
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 12px;
    color: white;
    padding: 12px 16px;
    transition: all 0.3s ease;
}

#nutritionPlanModal .form-control:focus,
#nutritionPlanModal .form-select:focus {
    background: rgba(255, 255, 255, 0.12);
    border-color: #A29BFE;
    box-shadow: 0 0 0 0.2rem rgba(162, 155, 254, 0.25);
    color: white;
}

#nutritionPlanModal .form-control::placeholder {
    color: rgba(255, 255, 255, 0.5);
}

/* Fix select option text visibility */
#nutritionPlanModal .form-select option {
    background: rgba(30, 39, 46, 0.95);
    color: white;
    padding: 8px;
}

/* Ensure selected text in select is visible */
#nutritionPlanModal .form-select {
    color: white;
}

#nutritionPlanModal .meal-item {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 20px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

#nutritionPlanModal .meal-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, #6C5CE7, #FD79A8, #00CEC9);
    border-radius: 15px 15px 0 0;
}

#nutritionPlanModal .meal-item:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(162, 155, 254, 0.3);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
}

#nutritionPlanModal .btn-outline-primary {
    background: linear-gradient(135deg, #6C5CE7, #A29BFE);
    border: 1px solid rgba(162, 155, 254, 0.3);
    color: white;
    border-radius: 12px;
    padding: 12px 24px;
    font-weight: 500;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

#nutritionPlanModal .btn-outline-primary:hover {
    background: linear-gradient(135deg, #A29BFE, #6C5CE7);
    border-color: #A29BFE;
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(108, 92, 231, 0.3);
    color: white;
}

#nutritionPlanModal .modal-footer {
    background: rgba(255, 255, 255, 0.05);
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 0 0 20px 20px;
    padding: 20px 30px;
}

#nutritionPlanModal .btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: rgba(255, 255, 255, 0.9);
    border-radius: 12px;
    padding: 12px 24px;
    font-weight: 500;
    transition: all 0.3s ease;
}

#nutritionPlanModal .btn-secondary:hover {
    background: rgba(255, 255, 255, 0.2);
    border-color: rgba(255, 255, 255, 0.3);
    color: white;
    transform: translateY(-1px);
}

#nutritionPlanModal .btn-primary {
    background: linear-gradient(135deg, #6C5CE7, #A29BFE);
    border: none;
    border-radius: 12px;
    padding: 12px 24px;
    font-weight: 500;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(108, 92, 231, 0.3);
}

#nutritionPlanModal .btn-primary:hover {
    background: linear-gradient(135deg, #A29BFE, #6C5CE7);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(108, 92, 231, 0.4);
    color: white;
}

#nutritionPlanModal .btn-danger {
    background: linear-gradient(135deg, #e74a3b, #ff6b6b);
    border: none;
    border-radius: 8px;
    transition: all 0.3s ease;
}

#nutritionPlanModal .btn-danger:hover {
    background: linear-gradient(135deg, #ff6b6b, #e74a3b);
    transform: scale(1.05);
}

/* View Nutrition Plans Modal */
#viewNutritionPlansModal .modal-content {
    background: rgba(30, 39, 46, 0.95);
    -webkit-backdrop-filter: blur(20px);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
    color: white;
}

#viewNutritionPlansModal .modal-header {
    background: linear-gradient(135deg, rgba(108, 92, 231, 0.2), rgba(253, 121, 168, 0.2));
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px 20px 0 0;
}

#viewNutritionPlansModal .modal-title {
    color: white;
    font-weight: 600;
}

#viewNutritionPlansModal .modal-title i {
    color: #A29BFE;
}

#viewNutritionPlansModal .modal-body {
    background: rgba(255, 255, 255, 0.02);
    padding: 20px;
}
</style>

<!-- Nutrition Plan Modal -->
<div class="modal fade" id="nutritionPlanModal" tabindex="-1" aria-labelledby="nutritionPlanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="nutritionPlanModalLabel"><i class="fas fa-utensils"></i> Assign Nutrition Plan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="nutritionPlanForm">
                    <input type="hidden" id="planMemberId" name="member_id">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="planName" class="form-label">Plan Name</label>
                            <input type="text" class="form-control" id="planName" name="plan_name" placeholder="e.g., Weight Loss Plan" required>
                        </div>
                        <div class="col-md-6">
                            <label for="planDate" class="form-label">Date</label>
                            <input type="date" class="form-control" id="planDate" name="log_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    
                    <div id="mealsContainer">
                        <div class="meal-item mb-3 p-3 border rounded">
                            <div class="row">
                                <div class="col-md-2">
                                    <label class="form-label">Meal Type</label>
                                    <select class="form-select meal-type" name="meals[0][meal_type]" required>
                                        <option value="breakfast">Breakfast</option>
                                        <option value="lunch">Lunch</option>
                                        <option value="dinner">Dinner</option>
                                        <option value="snack">Snack</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Food Name</label>
                                    <input type="text" class="form-control" name="meals[0][food_name]" placeholder="e.g., Grilled Chicken Breast" required>
                                </div>
                                <div class="col-md-1">
                                    <label class="form-label">Calories</label>
                                    <input type="number" class="form-control" name="meals[0][calories]" placeholder="0" min="0" required>
                                </div>
                                <div class="col-md-1">
                                    <label class="form-label">Protein (g)</label>
                                    <input type="number" step="0.1" class="form-control" name="meals[0][protein]" placeholder="0.0" min="0" required>
                                </div>
                                <div class="col-md-1">
                                    <label class="form-label">Carbs (g)</label>
                                    <input type="number" step="0.1" class="form-control" name="meals[0][carbs]" placeholder="0.0" min="0" required>
                                </div>
                                <div class="col-md-1">
                                    <label class="form-label">Fat (g)</label>
                                    <input type="number" step="0.1" class="form-control" name="meals[0][fat]" placeholder="0.0" min="0" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Quantity</label>
                                    <input type="text" class="form-control" name="meals[0][quantity]" placeholder="e.g., 100g, 1 cup">
                                </div>
                                <div class="col-md-1">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="button" class="btn btn-danger btn-sm w-100 remove-meal" style="display: none;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12">
                                    <label class="form-label">Notes (Optional)</label>
                                    <textarea class="form-control" name="meals[0][notes]" rows="1" placeholder="Any special instructions..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <button type="button" class="btn btn-outline-primary" id="addMealBtn">
                        <i class="fas fa-plus"></i> Add Another Meal
                    </button>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveNutritionPlan">Save Plan</button>
            </div>
        </div>
    </div>
</div>

<!-- View Nutrition Plans Modal -->
<div class="modal fade" id="viewNutritionPlansModal" tabindex="-1" aria-labelledby="viewNutritionPlansModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewNutritionPlansModalLabel"><i class="fas fa-eye"></i> Nutrition Plans</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="nutritionPlansList">
                    <!-- Plans will be loaded here -->
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include 'components/footer.php';
?>

<script>
// Nutrition plan management functions
let mealCount = 1;

function createNutritionPlan() {
    document.getElementById('planMemberId').value = '';
    document.getElementById('planName').value = '';
    document.getElementById('planDate').value = '<?php echo date('Y-m-d'); ?>';
    
    // Reset meals container
    document.getElementById('mealsContainer').innerHTML = `
        <div class="meal-item mb-3 p-3 border rounded">
            <div class="row">
                <div class="col-md-2">
                    <label class="form-label">Meal Type</label>
                    <select class="form-select meal-type" name="meals[0][meal_type]" required>
                        <option value="breakfast">Breakfast</option>
                        <option value="lunch">Lunch</option>
                        <option value="dinner">Dinner</option>
                        <option value="snack">Snack</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Food Name</label>
                    <input type="text" class="form-control" name="meals[0][food_name]" placeholder="e.g., Grilled Chicken Breast" required>
                </div>
                <div class="col-md-1">
                    <label class="form-label">Calories</label>
                    <input type="number" class="form-control" name="meals[0][calories]" placeholder="0" min="0" required>
                </div>
                <div class="col-md-1">
                    <label class="form-label">Protein (g)</label>
                    <input type="number" step="0.1" class="form-control" name="meals[0][protein]" placeholder="0.0" min="0" required>
                </div>
                <div class="col-md-1">
                    <label class="form-label">Carbs (g)</label>
                    <input type="number" step="0.1" class="form-control" name="meals[0][carbs]" placeholder="0.0" min="0" required>
                </div>
                <div class="col-md-1">
                    <label class="form-label">Fat (g)</label>
                    <input type="number" step="0.1" class="form-control" name="meals[0][fat]" placeholder="0.0" min="0" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Quantity</label>
                    <input type="text" class="form-control" name="meals[0][quantity]" placeholder="e.g., 100g, 1 cup">
                </div>
                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <button type="button" class="btn btn-danger btn-sm w-100 remove-meal" style="display: none;">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <label class="form-label">Notes (Optional)</label>
                    <textarea class="form-control" name="meals[0][notes]" rows="1" placeholder="Any special instructions..."></textarea>
                </div>
            </div>
        </div>
    `;
    mealCount = 1;
    
    const modal = new bootstrap.Modal(document.getElementById('nutritionPlanModal'));
    modal.show();
}

function assignNutritionPlan(memberId, memberName) {
    document.getElementById('planMemberId').value = memberId;
    document.getElementById('nutritionPlanModalLabel').innerHTML = '<i class="fas fa-utensils"></i> Assign Nutrition Plan - ' + memberName;
    
    // Reset form
    document.getElementById('planName').value = '';
    document.getElementById('planDate').value = '<?php echo date('Y-m-d'); ?>';
    
    // Reset meals
    document.getElementById('mealsContainer').innerHTML = `
        <div class="meal-item mb-3 p-3 border rounded">
            <div class="row">
                <div class="col-md-2">
                    <label class="form-label">Meal Type</label>
                    <select class="form-select meal-type" name="meals[0][meal_type]" required>
                        <option value="breakfast">Breakfast</option>
                        <option value="lunch">Lunch</option>
                        <option value="dinner">Dinner</option>
                        <option value="snack">Snack</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Food Name</label>
                    <input type="text" class="form-control" name="meals[0][food_name]" placeholder="e.g., Grilled Chicken Breast" required>
                </div>
                <div class="col-md-1">
                    <label class="form-label">Calories</label>
                    <input type="number" class="form-control" name="meals[0][calories]" placeholder="0" min="0" required>
                </div>
                <div class="col-md-1">
                    <label class="form-label">Protein (g)</label>
                    <input type="number" step="0.1" class="form-control" name="meals[0][protein]" placeholder="0.0" min="0" required>
                </div>
                <div class="col-md-1">
                    <label class="form-label">Carbs (g)</label>
                    <input type="number" step="0.1" class="form-control" name="meals[0][carbs]" placeholder="0.0" min="0" required>
                </div>
                <div class="col-md-1">
                    <label class="form-label">Fat (g)</label>
                    <input type="number" step="0.1" class="form-control" name="meals[0][fat]" placeholder="0.0" min="0" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Quantity</label>
                    <input type="text" class="form-control" name="meals[0][quantity]" placeholder="e.g., 100g, 1 cup">
                </div>
                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <button type="button" class="btn btn-danger btn-sm w-100 remove-meal" style="display: none;">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <label class="form-label">Notes (Optional)</label>
                    <textarea class="form-control" name="meals[0][notes]" rows="1" placeholder="Any special instructions..."></textarea>
                </div>
            </div>
        </div>
    `;
    mealCount = 1;
    
    const modal = new bootstrap.Modal(document.getElementById('nutritionPlanModal'));
    modal.show();
}

function viewNutritionPlans(memberId) {
    const modal = new bootstrap.Modal(document.getElementById('viewNutritionPlansModal'));
    modal.show();
    
    // Load nutrition plans
    const formData = new FormData();
    formData.append('action', 'get_member_plans');
    formData.append('member_id', memberId);
    
    fetch('nutrition_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let html = '<div class="nutrition-plans-list">';
            if (data.plans.length === 0) {
                html += '<div class="empty-state"><i class="fas fa-utensils"></i><h6>No nutrition plans assigned yet</h6></div>';
            } else {
                // Group by date
                const groupedPlans = data.plans.reduce((groups, plan) => {
                    const date = plan.log_date;
                    if (!groups[date]) groups[date] = [];
                    groups[date].push(plan);
                    return groups;
                }, {});
                
                Object.keys(groupedPlans).sort().reverse().forEach(date => {
                    const plans = groupedPlans[date];
                    html += `<div class="plan-date-group mb-4">
                        <h6 class="plan-date text-primary">${new Date(date).toLocaleDateString()}</h6>
                        <div class="plan-name-badge mb-2">
                            <span class="badge bg-info">${plans[0].plan_name || 'Nutrition Plan'}</span>
                        </div>`;
                    
                    plans.forEach(plan => {
                        html += `
                            <div class="nutrition-plan-item mb-2 p-3 border rounded">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">${plan.meal_type.charAt(0).toUpperCase() + plan.meal_type.slice(1)}</h6>
                                        <p class="mb-1 food-name">${plan.food_name}</p>
                                        ${plan.quantity ? `<small class="text-muted d-block">Quantity: ${plan.quantity}</small>` : ''}
                                        <div class="macros mt-2">
                                            <small>
                                                <span class="badge bg-light text-dark me-1">${plan.calories} cal</span>
                                                <span class="badge bg-success me-1">P: ${parseFloat(plan.protein).toFixed(1)}g</span>
                                                <span class="badge bg-warning me-1">C: ${parseFloat(plan.carbs).toFixed(1)}g</span>
                                                <span class="badge bg-danger">F: ${parseFloat(plan.fat).toFixed(1)}g</span>
                                            </small>
                                        </div>
                                        ${plan.notes ? `<p class="mt-2 mb-0"><small class="text-muted">${plan.notes}</small></p>` : ''}
                                    </div>
                                </div>
                            </div>`;
                    });
                    
                    html += '</div>';
                });
            }
            html += '</div>';
            document.getElementById('nutritionPlansList').innerHTML = html;
        } else {
            document.getElementById('nutritionPlansList').innerHTML = '<div class="alert alert-danger">Error loading plans: ' + data.message + '</div>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('nutritionPlansList').innerHTML = '<div class="alert alert-danger">An error occurred while loading nutrition plans.</div>';
    });
}

// Add meal functionality
document.getElementById('addMealBtn').addEventListener('click', function() {
    const container = document.getElementById('mealsContainer');
    const mealHtml = `
        <div class="meal-item mb-3 p-3 border rounded">
            <div class="row">
                <div class="col-md-2">
                    <label class="form-label">Meal Type</label>
                    <select class="form-select meal-type" name="meals[${mealCount}][meal_type]" required>
                        <option value="breakfast">Breakfast</option>
                        <option value="lunch">Lunch</option>
                        <option value="dinner">Dinner</option>
                        <option value="snack">Snack</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Food Name</label>
                    <input type="text" class="form-control" name="meals[${mealCount}][food_name]" placeholder="e.g., Grilled Chicken Breast" required>
                </div>
                <div class="col-md-1">
                    <label class="form-label">Calories</label>
                    <input type="number" class="form-control" name="meals[${mealCount}][calories]" placeholder="0" min="0" required>
                </div>
                <div class="col-md-1">
                    <label class="form-label">Protein (g)</label>
                    <input type="number" step="0.1" class="form-control" name="meals[${mealCount}][protein]" placeholder="0.0" min="0" required>
                </div>
                <div class="col-md-1">
                    <label class="form-label">Carbs (g)</label>
                    <input type="number" step="0.1" class="form-control" name="meals[${mealCount}][carbs]" placeholder="0.0" min="0" required>
                </div>
                <div class="col-md-1">
                    <label class="form-label">Fat (g)</label>
                    <input type="number" step="0.1" class="form-control" name="meals[${mealCount}][fat]" placeholder="0.0" min="0" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Quantity</label>
                    <input type="text" class="form-control" name="meals[${mealCount}][quantity]" placeholder="e.g., 100g, 1 cup">
                </div>
                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <button type="button" class="btn btn-danger btn-sm w-100 remove-meal">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <label class="form-label">Notes (Optional)</label>
                    <textarea class="form-control" name="meals[${mealCount}][notes]" rows="1" placeholder="Any special instructions..."></textarea>
                </div>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', mealHtml);
    mealCount++;
    
    // Show remove buttons if more than 1 meal
    document.querySelectorAll('.remove-meal').forEach(btn => btn.style.display = 'block');
});

// Remove meal functionality
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('remove-meal')) {
        e.target.closest('.meal-item').remove();
        mealCount--;
        
        // Hide remove buttons if only 1 meal left
        if (mealCount <= 1) {
            document.querySelectorAll('.remove-meal').forEach(btn => btn.style.display = 'none');
        }
    }
});

// Save nutrition plan
document.getElementById('saveNutritionPlan').addEventListener('click', function() {
    const form = document.getElementById('nutritionPlanForm');
    const formData = new FormData(form);
    formData.append('action', 'assign_nutrition_plan');
    
    // Convert meals to JSON
    const meals = [];
    const mealItems = document.querySelectorAll('.meal-item');
    mealItems.forEach((item, index) => {
        const mealData = {
            meal_type: item.querySelector(`select[name="meals[${index}][meal_type]"]`).value,
            food_name: item.querySelector(`input[name="meals[${index}][food_name]"]`).value,
            calories: parseFloat(item.querySelector(`input[name="meals[${index}][calories]"]`).value) || 0,
            protein: parseFloat(item.querySelector(`input[name="meals[${index}][protein]"]`).value) || 0,
            carbs: parseFloat(item.querySelector(`input[name="meals[${index}][carbs]"]`).value) || 0,
            fat: parseFloat(item.querySelector(`input[name="meals[${index}][fat]"]`).value) || 0,
            quantity: item.querySelector(`input[name="meals[${index}][quantity]"]`).value,
            notes: item.querySelector(`textarea[name="meals[${index}][notes]"]`).value
        };
        meals.push(mealData);
    });
    
    formData.append('meals', JSON.stringify(meals));
    
    fetch('nutrition_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('nutritionPlanModal'));
            modal.hide();
            
            // Show success message
            alert(data.message);
            
            // Refresh page
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while saving the nutrition plan.');
    });
});
</script>