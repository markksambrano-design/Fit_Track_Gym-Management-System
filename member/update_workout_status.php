<?php
session_start();
include '../includes/db.php';

// Check if member is logged in
if (!isset($_SESSION['member_id'])) {
    // TEMPORARILY BYPASS LOGIN FOR TESTING
    $_SESSION['member_id'] = 1; // Use member ID 1 for testing
}

$member_id = $_SESSION['member_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['session_id']) && isset($_POST['status'])) {
    $session_id = (int)$_POST['session_id'];
    $status = $_POST['status'];

    // Validate status
    $valid_statuses = ['pending', 'in_progress', 'completed', 'skipped'];
    if (!in_array($status, $valid_statuses)) {
        $_SESSION['error_message'] = 'Invalid status provided.';
        header('Location: trainers.php');
        exit;
    }

    // Verify the session belongs to this member
    $stmt = $conn->prepare("SELECT id FROM member_workout_sessions WHERE id = ? AND member_id = ?");
    $stmt->bind_param("ii", $session_id, $member_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Update the workout session status
        $update_data = ['status' => $status];
        $update_fields = ['status = ?'];
        $update_values = [$status];
        $update_types = 's';

        // If marking as completed, set completed_date
        if ($status === 'completed') {
            $update_fields[] = 'completed_date = CURDATE()';
        }

        $update_query = "UPDATE member_workout_sessions SET " . implode(', ', $update_fields) . " WHERE id = ? AND member_id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param($update_types . 'ii', ...array_merge($update_values, [$session_id, $member_id]));

        if ($update_stmt->execute()) {
            $_SESSION['success_message'] = 'Workout session status updated successfully.';
        } else {
            $_SESSION['error_message'] = 'Failed to update workout session status.';
        }
        $update_stmt->close();
    } else {
        $_SESSION['error_message'] = 'Workout session not found or access denied.';
    }
    $stmt->close();
} else {
    $_SESSION['error_message'] = 'Invalid request.';
}

header('Location: trainers.php');
exit;
?>