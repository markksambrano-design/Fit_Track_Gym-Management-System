<?php
session_start();
include '../includes/db.php';

// Check if member is logged in
if (!isset($_SESSION['member_id'])) {
    header('Location: login.php');
    exit;
}

$member_id = $_SESSION['member_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $trainer_id = (int)$_POST['trainer_id'];
    $session_date = $_POST['session_date'];
    $session_time = $_POST['session_time'];
    $notes = trim($_POST['notes'] ?? '');

    $errors = [];

    // Check if member has selected a training package
    $package_check = $conn->query("SHOW COLUMNS FROM members LIKE 'training_package'");
    if ($package_check->num_rows == 0) {
        $conn->query("ALTER TABLE members ADD COLUMN training_package INT(11) DEFAULT NULL AFTER with_trainees");
    }
    
    $package_stmt = $conn->prepare("SELECT training_package FROM members WHERE id = ?");
    $package_stmt->bind_param("i", $member_id);
    $package_stmt->execute();
    $package_result = $package_stmt->get_result();
    $member_package = $package_result->fetch_assoc();
    $selected_package = $member_package['training_package'] ?? null;
    $package_stmt->close();
    
    if (!$selected_package) {
        $errors[] = "Please select your training package first before booking a session.";
    }

    // Validate inputs
    if (empty($trainer_id) || empty($session_date) || empty($session_time)) {
        $errors[] = "All required fields must be filled.";
    }

    // Validate date is not in the past
    if (strtotime($session_date) < strtotime(date('Y-m-d'))) {
        $errors[] = "Session date cannot be in the past.";
    }

    // Validate time format
    if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $session_time)) {
        $errors[] = "Invalid time format.";
    }

    // Check if trainer exists and is available (treat all staff as trainers)
    $trainer_check = $conn->prepare("SELECT id, first_name, last_name FROM staff WHERE id = ?");
    $trainer_check->bind_param("i", $trainer_id);
    $trainer_check->execute();
    $trainer_result = $trainer_check->get_result();

    if ($trainer_result->num_rows === 0) {
        $errors[] = "Selected trainer is not available.";
    }
    $trainer_check->close();

    // Check for scheduling conflicts
    if (empty($errors)) {
        // Check if trainer is already booked at this time
        $conflict_check = $conn->prepare("SELECT id FROM training_sessions
                                         WHERE trainer_id = ? AND session_date = ? AND session_time = ? AND status = 'booked'");
        $conflict_check->bind_param("iss", $trainer_id, $session_date, $session_time);
        $conflict_check->execute();
        $conflict_result = $conflict_check->get_result();

        if ($conflict_result->num_rows > 0) {
            $errors[] = "This trainer is already booked at the selected time.";
        }
        $conflict_check->close();

        // Check if member already has a booking at this time
        $member_conflict_check = $conn->prepare("SELECT id FROM training_sessions
                                                WHERE member_id = ? AND session_date = ? AND session_time = ? AND status = 'booked'");
        $member_conflict_check->bind_param("iss", $member_id, $session_date, $session_time);
        $member_conflict_check->execute();
        $member_conflict_result = $member_conflict_check->get_result();

        if ($member_conflict_result->num_rows > 0) {
            $errors[] = "You already have a training session booked at this time.";
        }
        $member_conflict_check->close();
    }

    if (empty($errors)) {
        // Insert the booking
        $insert_stmt = $conn->prepare("INSERT INTO training_sessions (member_id, trainer_id, session_date, session_time, notes, status, created_at)
                                      VALUES (?, ?, ?, ?, ?, 'booked', NOW())");
        $insert_stmt->bind_param("iisss", $member_id, $trainer_id, $session_date, $session_time, $notes);

        if ($insert_stmt->execute()) {
            $_SESSION['success_message'] = "Training session booked successfully!";
            header('Location: trainers.php');
            exit;
        } else {
            $errors[] = "Failed to book the session. Please try again.";
        }
        $insert_stmt->close();
    }

    // If there are errors, redirect back with error messages
    if (!empty($errors)) {
        $_SESSION['error_message'] = implode('<br>', $errors);
        header('Location: trainers.php');
        exit;
    }
} else {
    // Invalid request method
    header('Location: trainers.php');
    exit;
}
?>