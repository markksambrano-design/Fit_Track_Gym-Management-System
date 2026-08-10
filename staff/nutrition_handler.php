<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['staff_logged_in']) || !$_SESSION['staff_logged_in']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$staff_id = $_SESSION['staff_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'assign_nutrition_plan') {
        // Get raw POST data first to handle JSON
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Check if data is from JSON or form-data
        if ($input === null) {
            // Use regular POST data
            $input = $_POST;
        }
        
        // Extract data
        $member_id = intval($input['member_id'] ?? 0);
        $plan_name = trim($input['plan_name'] ?? '');
        $log_date = trim($input['log_date'] ?? '');
        $meals = isset($input['meals']) ? 
                 (is_string($input['meals']) ? json_decode($input['meals'], true) : $input['meals']) 
                 : [];

        // Validate inputs
        if ($member_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid member ID']);
            exit;
        }
        if (empty($plan_name)) {
            echo json_encode(['success' => false, 'message' => 'Plan name is required']);
            exit;
        }
        if (empty($log_date)) {
            echo json_encode(['success' => false, 'message' => 'Date is required']);
            exit;
        }
        if (!is_array($meals) || empty($meals)) {
            echo json_encode(['success' => false, 'message' => 'Please add at least one meal']);
            exit;
        }

        // Validate member is VIP and assigned to this trainer
        $check_query = "SELECT membership_type FROM members WHERE id = ? AND membership_type = 'vip' 
                       AND (trainer_id = ? OR EXISTS(SELECT 1 FROM training_sessions ts WHERE ts.member_id = members.id AND ts.trainer_id = ?))";
        $check_stmt = $conn->prepare($check_query);
        $is_valid = false;
        if ($check_stmt) {
            $check_stmt->bind_param("iii", $member_id, $staff_id, $staff_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            $is_valid = $result->num_rows > 0;
            $check_stmt->close();
        }

        if (!$is_valid) {
            echo json_encode(['success' => false, 'message' => 'Invalid member or not authorized']);
            exit;
        }

        // Start transaction
        $conn->begin_transaction();

        try {
            // Delete existing plans for this date
            $delete_query = "DELETE FROM member_nutrition_logs WHERE member_id = ? AND log_date = ? AND is_plan = 1";
            $delete_stmt = $conn->prepare($delete_query);
            if ($delete_stmt) {
                $delete_stmt->bind_param("is", $member_id, $log_date);
                $delete_stmt->execute();
                $delete_stmt->close();
            }

            // Prepare insert statement
            $insert_query = "INSERT INTO member_nutrition_logs 
                            (member_id, trainer_id, plan_name, log_date, meal_type, food_name, calories, protein, carbs, fat, quantity, notes, is_plan, assigned_by, assigned_date) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW())";

            $stmt = $conn->prepare($insert_query);
            if (!$stmt) {
                throw new Exception("Database error: " . $conn->error);
            }

            $success_count = 0;
            $error_messages = [];
            $current_date = date('Y-m-d H:i:s');
            
            foreach ($meals as $index => $meal) {
                // Ensure numeric values are properly converted
                $calories = isset($meal['calories']) ? (float)$meal['calories'] : 0;
                $protein = isset($meal['protein']) ? (float)$meal['protein'] : 0;
                $carbs = isset($meal['carbs']) ? (float)$meal['carbs'] : 0;
                $fat = isset($meal['fat']) ? (float)$meal['fat'] : 0;
                $meal_type = trim($meal['meal_type'] ?? '');
                $food_name = trim($meal['food_name'] ?? '');
                $quantity = trim($meal['quantity'] ?? '');
                $notes = trim($meal['notes'] ?? '');
                
                // Validate meal data
                if (empty($meal_type)) {
                    $error_messages[] = "Meal " . ($index + 1) . ": Meal type is required";
                    continue;
                }
                if (empty($food_name)) {
                    $error_messages[] = "Meal " . ($index + 1) . ": Food name is required";
                    continue;
                }

                // Bind parameters
                if (!$stmt->bind_param("iissssddddsis", 
                    $member_id, 
                    $staff_id,
                    $plan_name,
                    $log_date,
                    $meal_type,
                    $food_name,
                    $calories,
                    $protein,
                    $carbs,
                    $fat,
                    $quantity,
                    $notes,
                    $staff_id
                )) {
                    $error_messages[] = "Meal " . ($index + 1) . ": Bind error - " . $stmt->error;
                    continue;
                }
                
                if (!$stmt->execute()) {
                    $error_messages[] = "Meal " . ($index + 1) . ": " . $stmt->error;
                    continue;
                }
                
                $success_count++;
            }

            $stmt->close();

            if ($success_count > 0) {
                $conn->commit();
                $message = "Nutrition plan assigned successfully with $success_count meals";
                if (!empty($error_messages)) {
                    $message .= ". Issues: " . implode("; ", $error_messages);
                }
                echo json_encode(['success' => true, 'message' => $message]);
            } else {
                $conn->rollback();
                $error_msg = !empty($error_messages) ? implode("; ", $error_messages) : 'Failed to assign nutrition plan';
                echo json_encode(['success' => false, 'message' => $error_msg]);
            }

        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }

    } elseif ($action === 'get_member_plans') {
        // Check if data is from JSON or form-data
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input === null) {
            $input = $_POST;
        }
        
        $member_id = intval($input['member_id'] ?? 0);

        // Check authorization
        $check_query = "SELECT id FROM members WHERE id = ? AND membership_type = 'vip' 
                       AND (trainer_id = ? OR EXISTS(SELECT 1 FROM training_sessions ts WHERE ts.member_id = members.id AND ts.trainer_id = ?))";
        $check_stmt = $conn->prepare($check_query);
        $authorized = false;
        if ($check_stmt) {
            $check_stmt->bind_param("iii", $member_id, $staff_id, $staff_id);
            $check_stmt->execute();
            $authorized = $check_stmt->get_result()->num_rows > 0;
            $check_stmt->close();
        }

        if (!$authorized) {
            echo json_encode(['success' => false, 'message' => 'Not authorized']);
            exit;
        }

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
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>