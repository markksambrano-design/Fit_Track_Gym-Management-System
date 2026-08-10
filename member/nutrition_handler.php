<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['member_logged_in']) || !$_SESSION['member_logged_in']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$member_id = $_SESSION['member_id'] ?? 0;

// Check if member is VIP
$stmt = $conn->prepare("SELECT membership_type FROM members WHERE id = ?");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$result = $stmt->get_result();
if (!$result || $result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Member not found']);
    exit;
}
$member = $result->fetch_assoc();
if ($member['membership_type'] !== 'vip') {
    echo json_encode(['success' => false, 'message' => 'VIP membership required']);
    exit;
}
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_plans') {
    // Get nutrition plans for the member
    $plans_query = "SELECT * FROM member_nutrition_logs 
                   WHERE member_id = ? AND is_plan = 1 
                   ORDER BY log_date DESC, meal_type ASC";
    $stmt = $conn->prepare($plans_query);
    $plans = [];
    if ($stmt) {
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $plans[] = $row;
        }
        $stmt->close();
    }
    
    echo json_encode(['success' => true, 'plans' => $plans]);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'log_nutrition') {
    $meal_type = trim($_POST['meal_type'] ?? '');
    $log_date = trim($_POST['log_date'] ?? '');
    $food_name = trim($_POST['food_name'] ?? '');
    $calories = floatval($_POST['calories'] ?? 0);
    $protein = floatval($_POST['protein'] ?? 0);
    $carbs = floatval($_POST['carbs'] ?? 0);
    $fat = floatval($_POST['fat'] ?? 0);
    $quantity = trim($_POST['quantity'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    // Validation
    if (empty($meal_type) || empty($log_date) || empty($food_name)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
        exit;
    }

    if (!in_array($meal_type, ['breakfast', 'lunch', 'dinner', 'snack'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid meal type']);
        exit;
    }

    // Insert nutrition log
    $stmt = $conn->prepare("INSERT INTO member_nutrition_logs 
        (member_id, log_date, meal_type, food_name, calories, protein, carbs, fat, quantity, notes) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    if ($stmt) {
        $stmt->bind_param("isssddddss", 
            $member_id, 
            $log_date, 
            $meal_type, 
            $food_name, 
            $calories, 
            $protein, 
            $carbs, 
            $fat, 
            $quantity, 
            $notes
        );
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Nutrition logged successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save nutrition data']);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>