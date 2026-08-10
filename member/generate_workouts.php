<?php
include '../includes/db.php';
session_start();

if (!isset($_SESSION['member_id'])) {
    // TEMPORARILY BYPASS LOGIN FOR TESTING
    $_SESSION['member_id'] = 1; // Use member ID 1 for testing
}

$member_id = $_SESSION['member_id'];
$current_year = date('Y');
$current_month = date('n');

// Check if member has selected package
$stmt = $conn->prepare("SELECT training_package FROM members WHERE id = ?");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$result = $stmt->get_result();
$member = $result->fetch_assoc();

if (!$member['training_package']) {
    echo "<div style='text-align: center; padding: 50px;'>";
    echo "<h3>No Training Package Selected</h3>";
    echo "<p>You need to select a training package first before you can generate workout sessions.</p>";
    echo "<a href='select_package.php' class='btn btn-primary' style='margin: 10px;'>Select Training Package</a><br>";
    echo "<a href='trainers.php'>← Back to Trainers Page</a>";
    echo "</div>";
    exit;
}

$selected_package = $member['training_package'];
echo "Member ID: $member_id<br>";
echo "Selected Package: $selected_package sessions/month<br>";
echo "Current Month: $current_month/$current_year<br><br>";

// Check if monthly record exists
$stmt = $conn->prepare("SELECT id FROM member_monthly_sessions WHERE member_id = ? AND year = ? AND month = ?");
$stmt->bind_param("iii", $member_id, $current_year, $current_month);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    // Create monthly record
    echo "Creating monthly record...<br>";
    $stmt = $conn->prepare("INSERT INTO member_monthly_sessions (member_id, year, month, package_sessions) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiii", $member_id, $current_year, $current_month, $selected_package);
    $stmt->execute();
    $monthly_session_id = $conn->insert_id;
    echo "Monthly record created with ID: $monthly_session_id<br>";
} else {
    $monthly_session_id = $result->fetch_assoc()['id'];
    echo "Monthly record exists with ID: $monthly_session_id<br>";
}

// Check if workout sessions already exist
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM member_workout_sessions WHERE member_id = ? AND monthly_session_id = ?");
$stmt->bind_param("ii", $member_id, $monthly_session_id);
$stmt->execute();
$result = $stmt->get_result();
$count = $result->fetch_assoc()['count'];

if ($count > 0) {
    echo "Workout sessions already exist ($count sessions)<br>";
} else {
    // Create workout sessions from workout_plans table
    echo "Creating workout sessions from workout plans...<br>";

    // Get workout plans for the member's package
    $stmt = $conn->prepare("SELECT session_number, workout_name, workout_description, exercises, duration_minutes, difficulty 
                           FROM workout_plans 
                           WHERE package_sessions = ? 
                           ORDER BY session_number ASC");
    $stmt->bind_param("i", $selected_package);
    $stmt->execute();
    $workout_plans_result = $stmt->get_result();

    if ($workout_plans_result->num_rows > 0) {
        $sessions_created = 0;
        while ($workout_plan = $workout_plans_result->fetch_assoc()) {
            // Get the workout_plan_id
            $stmt_id = $conn->prepare("SELECT id FROM workout_plans WHERE package_sessions = ? AND session_number = ?");
            $stmt_id->bind_param("ii", $selected_package, $workout_plan['session_number']);
            $stmt_id->execute();
            $id_result = $stmt_id->get_result();
            $workout_plan_id = $id_result->fetch_assoc()['id'];
            $stmt_id->close();
            
            // Insert into member_workout_sessions with reference to workout_plan
            $stmt_insert = $conn->prepare("INSERT INTO member_workout_sessions 
                (member_id, monthly_session_id, workout_plan_id, session_number, workout_name, exercises, status, duration_minutes) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)");
            $stmt_insert->bind_param("iiiiissi", 
                $member_id, 
                $monthly_session_id, 
                $workout_plan_id,
                $workout_plan['session_number'], 
                $workout_plan['workout_name'], 
                $workout_plan['exercises'], 
                $workout_plan['duration_minutes']
            );
            $stmt_insert->execute();
            echo "Created session {$workout_plan['session_number']}: {$workout_plan['workout_name']}<br>";
            $sessions_created++;
        }
        $stmt_insert->close();
        
        echo "<br><strong>Workout sessions created successfully from workout plans!</strong><br>";
        echo "Created $sessions_created workout sessions for this month.<br>";
    } else {
        // Fallback to sample workouts if no workout plans exist
        echo "No workout plans found for {$selected_package} sessions/month. Creating sample workouts...<br>";
        
        $sample_workouts = [
            [
                'session_number' => 1,
                'workout_name' => 'Upper Body Strength',
                'exercises' => 'Push-ups|3|10|60\nDumbbell Shoulder Press|3|12|45\nBent-over Rows|3|10|60',
                'duration_minutes' => 45
            ],
            [
                'session_number' => 2,
                'workout_name' => 'Lower Body Power',
                'exercises' => 'Squats|4|15|90\nLunges|3|10|60\nCalf Raises|3|20|30',
                'duration_minutes' => 50
            ],
            [
                'session_number' => 3,
                'workout_name' => 'Core & Cardio',
                'exercises' => 'Planks|3|30|0\nMountain Climbers|3|20|30\nBurpees|3|8|45',
                'duration_minutes' => 40
            ],
            [
                'session_number' => 4,
                'workout_name' => 'Full Body Circuit',
                'exercises' => 'Deadlifts|3|8|60\nPull-ups|3|6|45\nPush Press|3|10|45',
                'duration_minutes' => 55
            ]
        ];

        // Limit to selected package sessions
        $sessions_to_create = min($selected_package, count($sample_workouts));

        for ($i = 0; $i < $sessions_to_create; $i++) {
            $workout = $sample_workouts[$i];
            $stmt = $conn->prepare("INSERT INTO member_workout_sessions (member_id, monthly_session_id, session_number, workout_name, exercises, status, duration_minutes) VALUES (?, ?, ?, ?, ?, 'pending', ?)");
            $stmt->bind_param("iiisss", $member_id, $monthly_session_id, $workout['session_number'], $workout['workout_name'], $workout['exercises'], $workout['duration_minutes']);
            $stmt->execute();
            echo "Created session {$workout['session_number']}: {$workout['workout_name']}<br>";
        }

        echo "<br><strong>Sample workout sessions created successfully!</strong><br>";
        echo "Created $sessions_to_create workout sessions for this month.<br>";
    }
    
    $stmt->close();

echo "<br><a href='trainers.php'>← Back to Trainers Page</a>";
?>