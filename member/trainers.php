<?php
$page_title = "Personal Training";

// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../includes/db.php';

// Check if member is logged in (additional check beyond header.php)
if (!isset($_SESSION['member_id'])) {
    header('Location: login.php');
    exit;
}

$member_id = $_SESSION['member_id'];
$member_data = $_SESSION['member_data'] ?? [];
$member_name = $_SESSION['member_name'] ?? 'Member';

// Get member data including membership_type and with_trainees
$member_stmt = $conn->prepare("SELECT membership_type, training_package, package_selected_date, with_trainees, trainer_id FROM members WHERE id = ?");
$member_stmt->bind_param("i", $member_id);
$member_stmt->execute();
$member_result = $member_stmt->get_result();
$member_info = $member_result->fetch_assoc();
$membership_type = $member_info['membership_type'];
$selected_package = $member_info['training_package'];
$package_selected_date = $member_info['package_selected_date'];
$with_trainees = $member_info['with_trainees'];
$assigned_trainer_id = $member_info['trainer_id'];
$member_stmt->close();

// Ensure trainer_id is set in members table if assigned in sessions
if (!$member_info['trainer_id']) {
    // Check if has assigned trainer in sessions
    $check_trainer = $conn->prepare("SELECT trainer_id FROM training_sessions WHERE member_id = ? AND trainer_id > 0 ORDER BY id DESC LIMIT 1");
    $check_trainer->bind_param("i", $member_id);
    $check_trainer->execute();
    $check_result = $check_trainer->get_result();
    if ($check_row = $check_result->fetch_assoc()) {
        // Update members table
        $update_trainer = $conn->prepare("UPDATE members SET trainer_id = ? WHERE id = ?");
        $update_trainer->bind_param("ii", $check_row['trainer_id'], $member_id);
        $update_trainer->execute();
        $update_trainer->close();
        $member_info['trainer_id'] = $check_row['trainer_id'];
    }
    $check_trainer->close();
}

// Update assigned_trainer_id after sync
$assigned_trainer_id = $member_info['trainer_id'];

// Removed automatic setting of with_trainees to enforce strict access control
// Only members with with_trainees = 'with' can access personal training features

// Check if member has a valid membership that includes training (with trainees)
$access_denied = false;
if ($with_trainees !== 'with') {
    $access_denied = true;
}

// Get assigned trainer for this member (from members table first, then from sessions)
$assigned_trainer = null;

if ($assigned_trainer_id) {
    // Get trainer info from assigned_trainer_id
    $trainer_stmt = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) as trainer_name, photo as trainer_photo FROM staff WHERE id = ?");
    $trainer_stmt->bind_param("i", $assigned_trainer_id);
    $trainer_stmt->execute();
    $trainer_result = $trainer_stmt->get_result();
    $assigned_trainer = $trainer_result->fetch_assoc();
    $trainer_stmt->close();
} elseif ($with_trainees === 'with') {
    // Fallback: try to find from training sessions
    $assigned_trainer_query = "SELECT DISTINCT CONCAT(s.first_name, ' ', s.last_name) as trainer_name, s.photo as trainer_photo, ts.trainer_id
                              FROM training_sessions ts
                              LEFT JOIN staff s ON ts.trainer_id = s.id
                              WHERE ts.member_id = ? AND ts.trainer_id > 0
                              ORDER BY ts.id DESC
                              LIMIT 1";
    $trainer_stmt = $conn->prepare($assigned_trainer_query);
    $trainer_stmt->bind_param("i", $member_id);
    $trainer_stmt->execute();
    $trainer_result = $trainer_stmt->get_result();
    $assigned_trainer = $trainer_result->fetch_assoc();
    $trainer_stmt->close();
    
    // If trainer not found in staff table, show trainer ID
    if ($assigned_trainer && empty($assigned_trainer['trainer_name'])) {
        $assigned_trainer['trainer_name'] = 'Trainer ID: ' . $assigned_trainer['trainer_id'];
        $assigned_trainer['trainer_photo'] = null;
    }
}

// Debug: Check if member has any training sessions
$debug_query = "SELECT COUNT(*) as total_sessions, COUNT(CASE WHEN trainer_id > 0 THEN 1 END) as sessions_with_trainer FROM training_sessions WHERE member_id = ?";
$debug_stmt = $conn->prepare($debug_query);
$debug_stmt->bind_param("i", $member_id);
$debug_stmt->execute();
$debug_result = $debug_stmt->get_result();
$debug_data = $debug_result->fetch_assoc();
$debug_stmt->close();

include 'components/header.php';

// Initialize variables
$total_sessions = 8;
$duration_months = 1;

// Automatically set training_package based on membership_type if not set or update if membership changed
if ($membership_type) {
    // Map membership_type to total sessions
    $total_sessions = match($membership_type) {
        'regular' => 8,    // 1 month
        'student' => 18,   // 3 months
        'vip' => 24,       // 6 months
        'premium' => 32,   // 1 year
        default => 8
    };
    
    // Calculate duration and sessions per month
    $duration_months = match($membership_type) {
        'regular' => 1,
        'student' => 3,
        'vip' => 6,
        'premium' => 12,   // 1 year
        default => 1
    };
    
    $sessions_per_month = ceil($total_sessions / $duration_months);
    
    // Update the member's training_package if different
    if ($selected_package != $sessions_per_month) {
        $update_stmt = $conn->prepare("UPDATE members SET training_package = ?, package_selected_date = CURDATE() WHERE id = ?");
        $update_stmt->bind_param("ii", $sessions_per_month, $member_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        $selected_package = $sessions_per_month;
        $package_selected_date = date('Y-m-d');
    }
} elseif (!$selected_package) {
    // Default to regular if no membership type and no package
    $selected_package = 8;
    $membership_type = 'regular';
}

// Infer membership type and totals from selected_package if membership_type is null
if (!$membership_type && $selected_package) {
    if ($selected_package == 8) {
        $membership_type = 'regular';
        $total_sessions = 8;
        $duration_months = 1;
    } elseif ($selected_package == 6) {
        $membership_type = 'student';
        $total_sessions = 18;
        $duration_months = 3;
    } elseif ($selected_package == 4) {
        $membership_type = 'vip';
        $total_sessions = 24;
        $duration_months = 6;
    } elseif ($selected_package == 3) {
        $membership_type = 'premium';
        $total_sessions = 32;
        $duration_months = 12;
    } else {
        // Custom package, assume regular-like
        $membership_type = 'regular';
        $total_sessions = $selected_package;
        $duration_months = 1;
    }
}

// Calculate session start date (next day after package selection for regular/student, same day for VIP)
$session_start_date = $package_selected_date ? 
    ($membership_type === 'vip' ? $package_selected_date : date('Y-m-d', strtotime($package_selected_date . ' +1 day'))) 
    : null;

// Handle booking cancellation
if (isset($_POST['cancel_booking']) && isset($_POST['booking_id'])) {
    $booking_id = (int)$_POST['booking_id'];

    // Verify the booking belongs to this member
    $stmt = $conn->prepare("SELECT id FROM training_sessions WHERE id = ? AND member_id = ? AND status = 'booked'");
    $stmt->bind_param("ii", $booking_id, $member_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Cancel the booking
        $update_stmt = $conn->prepare("UPDATE training_sessions SET status = 'cancelled' WHERE id = ?");
        $update_stmt->bind_param("i", $booking_id);
        $update_stmt->execute();
        $update_stmt->close();

        $success_message = "Training session cancelled successfully.";
    } else {
        $error_message = "Unable to cancel this booking.";
    }
    $stmt->close();
}

// Get available trainers
$trainers_query = "SELECT s.id, s.staff_id, s.first_name, s.last_name, s.photo, s.schedule,
                          GROUP_CONCAT(DISTINCT sp.specialty SEPARATOR ', ') as specialties
                   FROM staff s
                   LEFT JOIN staff_specialties sp ON s.id = sp.staff_id
                   WHERE s.position = 'Trainer' OR s.position = 'Personal Trainer'
                   GROUP BY s.id
                   ORDER BY s.first_name";
$trainers_result = $conn->query($trainers_query);

// Get monthly session requirement
$current_year = date('Y');
$current_month = date('n');
$monthly_sessions_required = 0;

if ($selected_package) {
    // Check if member_monthly_sessions table exists
    $check_table = $conn->query("SHOW TABLES LIKE 'member_monthly_sessions'");
    if ($check_table->num_rows > 0) {
        $monthly_stmt = $conn->prepare("SELECT package_sessions FROM member_monthly_sessions WHERE member_id = ? AND year = ? AND month = ?");
        $monthly_stmt->bind_param("iii", $member_id, $current_year, $current_month);
        $monthly_stmt->execute();
        $monthly_result = $monthly_stmt->get_result();
        if ($monthly_row = $monthly_result->fetch_assoc()) {
            $monthly_sessions_required = $monthly_row['package_sessions'];
        } else {
            // Create monthly record if package is selected but no monthly record exists
            $monthly_sessions_required = $selected_package;
            $insert_monthly = $conn->prepare("INSERT INTO member_monthly_sessions (member_id, year, month, package_sessions) VALUES (?, ?, ?, ?)");
            $insert_monthly->bind_param("iiii", $member_id, $current_year, $current_month, $selected_package);
            $insert_monthly->execute();
            $insert_monthly->close();
        }
        $monthly_stmt->close();
    } else {
        $monthly_sessions_required = $selected_package;
    }
}

// Get member's booked sessions for current month
$bookings_query = "SELECT ts.id, ts.session_date, ts.session_time, ts.status, ts.notes,
                          s.first_name, s.last_name, s.photo
                   FROM training_sessions ts
                   LEFT JOIN staff s ON ts.trainer_id = s.id
                   JOIN members m ON ts.member_id = m.id
                   WHERE ts.member_id = ? 
                   AND YEAR(ts.session_date) = ? 
                   AND MONTH(ts.session_date) = ?
                   AND ts.status IN ('booked', 'available')
                   AND ts.session_date <= m.expired_date
                   AND ts.session_date >= ?
                   ORDER BY ts.session_date ASC, ts.session_time ASC";
$bookings_stmt = $conn->prepare($bookings_query);
$bookings_stmt->bind_param("iiis", $member_id, $current_year, $current_month, $session_start_date);
$bookings_stmt->execute();
$bookings_result = $bookings_stmt->get_result();

// Count only booked sessions for progress
$booked_sessions_this_month = 0;

// Store booked sessions in array for later use
$booked_sessions_array = [];
while ($row = $bookings_result->fetch_assoc()) {
    $booked_sessions_array[] = $row;
    if ($row['status'] === 'booked') {
        $booked_sessions_this_month++;
    }
}
$bookings_stmt->close();

// Ensure workout tables exist before querying
$check_workout_table = $conn->query("SHOW TABLES LIKE 'workout_plans'");
if ($check_workout_table->num_rows == 0) {
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    $create_workout_table = "CREATE TABLE IF NOT EXISTS workout_plans (
        id INT AUTO_INCREMENT PRIMARY KEY,
        package_sessions INT NOT NULL,
        session_number INT NOT NULL,
        workout_name VARCHAR(255) NOT NULL,
        workout_description TEXT,
        exercises TEXT,
        duration_minutes INT DEFAULT 60,
        difficulty ENUM('beginner','intermediate','advanced') DEFAULT 'beginner',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_package_session (package_sessions, session_number),
        INDEX idx_package (package_sessions)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($create_workout_table);
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
} else {
    // Get existing columns
    $existing_columns = [];
    $cols_result = $conn->query("SHOW COLUMNS FROM workout_plans");
    while ($col = $cols_result->fetch_assoc()) {
        $existing_columns[] = $col['Field'];
    }
    
    // Add missing columns one by one, checking if they exist first
    // Add in order: workout_name, workout_description, exercises, duration_minutes, difficulty
    // Use FIRST or AFTER based on what columns exist
    // Find the last existing column to use as anchor
    $last_column = 'session_number'; // Default anchor column
    if (!in_array('session_number', $existing_columns)) {
        // If session_number doesn't exist, try package_sessions
        if (in_array('package_sessions', $existing_columns)) {
            $last_column = 'package_sessions';
        } else {
            // If neither exists, just add at the end
            $last_column = null;
        }
    }
    
    if (!in_array('workout_name', $existing_columns)) {
        if ($last_column) {
            $conn->query("ALTER TABLE workout_plans ADD COLUMN workout_name VARCHAR(255) NOT NULL DEFAULT '' AFTER $last_column");
        } else {
            $conn->query("ALTER TABLE workout_plans ADD COLUMN workout_name VARCHAR(255) NOT NULL DEFAULT ''");
        }
        $last_column = 'workout_name';
    } else {
        $last_column = 'workout_name';
    }
    
    if (!in_array('workout_description', $existing_columns)) {
        if ($last_column) {
            $conn->query("ALTER TABLE workout_plans ADD COLUMN workout_description TEXT AFTER $last_column");
        } else {
            $conn->query("ALTER TABLE workout_plans ADD COLUMN workout_description TEXT");
        }
        $last_column = 'workout_description';
    } else {
        $last_column = 'workout_description';
    }
    
    if (!in_array('exercises', $existing_columns)) {
        if ($last_column) {
            $conn->query("ALTER TABLE workout_plans ADD COLUMN exercises TEXT AFTER $last_column");
        } else {
            $conn->query("ALTER TABLE workout_plans ADD COLUMN exercises TEXT");
        }
        $last_column = 'exercises';
    } else {
        $last_column = 'exercises';
    }
    
    if (!in_array('duration_minutes', $existing_columns)) {
        if ($last_column) {
            $conn->query("ALTER TABLE workout_plans ADD COLUMN duration_minutes INT DEFAULT 60 AFTER $last_column");
        } else {
            $conn->query("ALTER TABLE workout_plans ADD COLUMN duration_minutes INT DEFAULT 60");
        }
        $last_column = 'duration_minutes';
    } else {
        $last_column = 'duration_minutes';
    }
    
    if (!in_array('difficulty', $existing_columns)) {
        if ($last_column) {
            $conn->query("ALTER TABLE workout_plans ADD COLUMN difficulty ENUM('beginner','intermediate','advanced') DEFAULT 'beginner' AFTER $last_column");
        } else {
            $conn->query("ALTER TABLE workout_plans ADD COLUMN difficulty ENUM('beginner','intermediate','advanced') DEFAULT 'beginner'");
        }
    }
}

$check_member_workout = $conn->query("SHOW TABLES LIKE 'member_workout_sessions'");
if ($check_member_workout->num_rows == 0) {
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    $create_member_workout = "CREATE TABLE IF NOT EXISTS member_workout_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NOT NULL,
        trainer_id INT,
        monthly_session_id INT,
        workout_plan_id INT,
        session_number INT NOT NULL,
        workout_name VARCHAR(255),
        exercises TEXT,
        status ENUM('pending','in_progress','completed','skipped') DEFAULT 'pending',
        session_date DATE NOT NULL,
        session_time TIME NOT NULL,
        completed_date DATE NULL,
        notes TEXT,
        duration_minutes INT DEFAULT 60,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
        FOREIGN KEY (trainer_id) REFERENCES staff(id) ON DELETE SET NULL,
        FOREIGN KEY (monthly_session_id) REFERENCES member_monthly_sessions(id) ON DELETE CASCADE,
        FOREIGN KEY (workout_plan_id) REFERENCES workout_plans(id) ON DELETE SET NULL,
        INDEX idx_member_monthly (member_id, monthly_session_id),
        INDEX idx_status (status),
        INDEX idx_session_date (session_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($create_member_workout);
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
} else {
    // Check and add missing columns
    $existing_columns = [];
    $cols_result = $conn->query("SHOW COLUMNS FROM member_workout_sessions");
    while ($col = $cols_result->fetch_assoc()) {
        $existing_columns[] = $col['Field'];
    }
    
    if (!in_array('duration_minutes', $existing_columns)) {
        $conn->query("ALTER TABLE member_workout_sessions ADD COLUMN duration_minutes INT DEFAULT 60 AFTER notes");
    }
    
    if (!in_array('workout_plan_id', $existing_columns)) {
        $conn->query("ALTER TABLE member_workout_sessions ADD COLUMN workout_plan_id INT AFTER monthly_session_id");
        $conn->query("ALTER TABLE member_workout_sessions ADD FOREIGN KEY (workout_plan_id) REFERENCES workout_plans(id) ON DELETE SET NULL");
    }
    
    if (!in_array('trainer_id', $existing_columns)) {
        $conn->query("ALTER TABLE member_workout_sessions ADD COLUMN trainer_id INT AFTER member_id");
        $conn->query("ALTER TABLE member_workout_sessions ADD FOREIGN KEY (trainer_id) REFERENCES staff(id) ON DELETE SET NULL");
    }
    
    if (!in_array('session_date', $existing_columns)) {
        $conn->query("ALTER TABLE member_workout_sessions ADD COLUMN session_date DATE AFTER status");
    }
    
    if (!in_array('session_time', $existing_columns)) {
        $conn->query("ALTER TABLE member_workout_sessions ADD COLUMN session_time TIME AFTER session_date");
    }
}

// Get generated workout sessions for this month
$workout_sessions = [];
$monthly_session_id = null;
$all_sessions_completed = false;
$has_current_month_record = false;

if ($selected_package && $monthly_sessions_required > 0) {
    // Get monthly session ID
    $get_monthly_stmt = $conn->prepare("SELECT id FROM member_monthly_sessions WHERE member_id = ? AND year = ? AND month = ?");
    $get_monthly_stmt->bind_param("iii", $member_id, $current_year, $current_month);
    $get_monthly_stmt->execute();
    $monthly_result = $get_monthly_stmt->get_result();
    if ($monthly_row = $monthly_result->fetch_assoc()) {
        $has_current_month_record = true;
        $monthly_session_id = $monthly_row['id'];
        
        // Check if workout_plans columns exist
        $check_desc = $conn->query("SHOW COLUMNS FROM workout_plans LIKE 'workout_description'");
        $has_description = $check_desc->num_rows > 0;
        $check_duration = $conn->query("SHOW COLUMNS FROM workout_plans LIKE 'duration_minutes'");
        $has_duration = $check_duration->num_rows > 0;
        
        // Build query based on available columns
        if ($has_description && $has_duration) {
            $workout_query = "SELECT mws.id, mws.session_number, mws.workout_name, mws.exercises, mws.status, 
                                     mws.completed_date, mws.notes, 
                                     wp.workout_description, wp.duration_minutes
                              FROM member_workout_sessions mws
                              LEFT JOIN workout_plans wp ON mws.workout_plan_id = wp.id
                              WHERE mws.member_id = ? AND mws.monthly_session_id = ?
                              ORDER BY mws.session_number ASC";
        } else {
            // Fallback query without workout_plans join if columns don't exist
            $workout_query = "SELECT mws.id, mws.session_number, mws.workout_name, mws.exercises, mws.status, 
                                     mws.completed_date, mws.notes, 
                                     '' as workout_description, 60 as duration_minutes
                              FROM member_workout_sessions mws
                              WHERE mws.member_id = ? AND mws.monthly_session_id = ?
                              ORDER BY mws.session_number ASC";
        }
        
        $workout_stmt = $conn->prepare($workout_query);
        if ($workout_stmt) {
            $workout_stmt->bind_param("ii", $member_id, $monthly_session_id);
            $workout_stmt->execute();
            $workout_result = $workout_stmt->get_result();
            
            $total_sessions = 0;
            $completed_count = 0;
            
            while ($workout = $workout_result->fetch_assoc()) {
                $workout_sessions[] = $workout;
                $total_sessions++;
                if ($workout['status'] === 'completed') {
                    $completed_count++;
                }
            }
            
            // If no workouts exist or wrong number of sessions, generate them from workout_plans
            // DISABLED: Automatic workout generation - workouts should be manually assigned by trainers
            /*
            if ($total_sessions == 0 || $total_sessions != $selected_package) {
                // Delete existing workouts for this month if wrong number
                if ($total_sessions > 0 && $total_sessions != $selected_package) {
                    $delete_stmt = $conn->prepare("DELETE FROM member_workout_sessions WHERE member_id = ? AND monthly_session_id = ?");
                    $delete_stmt->bind_param("ii", $member_id, $monthly_session_id);
                    $delete_stmt->execute();
                    $delete_stmt->close();
                }
                
                // Get workout plans for the member's package
                $plans_stmt = $conn->prepare("SELECT id, session_number, workout_name, workout_description, exercises, duration_minutes, difficulty 
                                             FROM workout_plans 
                                             WHERE package_sessions = ? 
                                             ORDER BY session_number ASC");
                $plans_stmt->bind_param("i", $selected_package);
                $plans_stmt->execute();
                $plans_result = $plans_stmt->get_result();
                
                if ($plans_result->num_rows > 0) {
                    while ($plan = $plans_result->fetch_assoc()) {
                        // Insert into member_workout_sessions
                        $insert_stmt = $conn->prepare("INSERT INTO member_workout_sessions 
                            (member_id, monthly_session_id, workout_plan_id, session_number, workout_name, exercises, status, duration_minutes) 
                            VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)");
                        $insert_stmt->bind_param("iiiissi", 
                            $member_id, 
                            $monthly_session_id, 
                            $plan['id'],
                            $plan['session_number'], 
                            $plan['workout_name'], 
                            $plan['exercises'], 
                            $plan['duration_minutes']
                        );
                        $insert_stmt->execute();
                        $total_sessions++;
                    }
                    $insert_stmt->close();
                    
                    // Re-query workouts after generation
                    $workout_stmt->bind_param("ii", $member_id, $monthly_session_id);
                    $workout_stmt->execute();
                    $workout_result = $workout_stmt->get_result();
                    
                    $workout_sessions = [];
                    $completed_count = 0;
                    while ($workout = $workout_result->fetch_assoc()) {
                        $workout_sessions[] = $workout;
                        if ($workout['status'] === 'completed') {
                            $completed_count++;
                        }
                    }
                }
                $plans_stmt->close();
            }
            */
            
            // Check if all sessions are completed
            if ($total_sessions > 0 && $completed_count === $total_sessions) {
                $all_sessions_completed = true;
            }
            
            $workout_stmt->close();
            
            // Reset all workout statuses to pending for this member to ensure consistency
            // DISABLED: Don't reset workout statuses automatically
            /*
            $reset_stmt = $conn->prepare("UPDATE member_workout_sessions SET status = 'pending', completed_date = NULL, notes = NULL WHERE member_id = ?");
            $reset_stmt->bind_param("i", $member_id);
            $reset_stmt->execute();
            $reset_stmt->close();
            
            // Re-query workouts after reset
            $workout_stmt = $conn->prepare($workout_query);
            if ($workout_stmt) {
                $workout_stmt->bind_param("ii", $member_id, $monthly_session_id);
                $workout_stmt->execute();
                $workout_result = $workout_stmt->get_result();
                
                $workout_sessions = [];
                $total_sessions = 0;
                $completed_count = 0;
                
                while ($workout = $workout_result->fetch_assoc()) {
                    $workout_sessions[] = $workout;
                    $total_sessions++;
                    if ($workout['status'] === 'completed') {
                        $completed_count++;
                    }
                }
                
                // Check if all sessions are completed
                if ($total_sessions > 0 && $completed_count === $total_sessions) {
                    $all_sessions_completed = true;
                }
                
                $workout_stmt->close();
            }
            */
        }
    }
    $get_monthly_stmt->close();
}

// Check if no monthly record exists for current month (new membership scenario)
if ($selected_package && !$has_current_month_record) {
    // This means it's a new month or new membership, show package selection again
    $all_sessions_completed = true; // Treat as completed to show selection
}

// Get all booked sessions (for display in table)
$all_bookings_query = "SELECT ts.id, ts.session_date, ts.session_time, ts.status, ts.notes,
                          s.first_name, s.last_name, s.photo
                   FROM training_sessions ts
                   LEFT JOIN staff s ON ts.trainer_id = s.id
                   JOIN members m ON ts.member_id = m.id
                   WHERE ts.member_id = ?
                   AND ts.status IN ('booked', 'available')
                   AND ts.session_date <= m.expired_date
                   AND ts.session_date >= ?
                   ORDER BY ts.session_date ASC, ts.session_time ASC";
$all_bookings_stmt = $conn->prepare($all_bookings_query);
$all_bookings_stmt->bind_param("is", $member_id, $session_start_date);
$all_bookings_stmt->execute();
$all_bookings_result = $all_bookings_stmt->get_result();
$all_bookings_stmt->close();

// Store all bookings in array for display
$all_bookings = [];
$all_bookings_result->data_seek(0); // Reset result pointer
while ($row = $all_bookings_result->fetch_assoc()) {
    $all_bookings[] = $row;
}

// Get assigned workout sessions from training_schedule.php system (all, not just current month)
$assigned_workouts_query = "SELECT mws.id, mws.session_number, mws.workout_name, mws.exercises, 
                                  mws.status, mws.session_date, mws.session_time, mws.completed_date, 
                                  mws.created_at, mws.notes, mms.package_sessions, mms.year, mms.month,
                                  CONCAT(s.first_name, ' ', s.last_name) as trainer_name
                           FROM member_workout_sessions mws
                           LEFT JOIN member_monthly_sessions mms ON mws.monthly_session_id = mms.id
                           LEFT JOIN staff s ON mws.trainer_id = s.id
                           WHERE mws.member_id = ?
                           ORDER BY mws.session_number ASC";
$assigned_workouts_stmt = $conn->prepare($assigned_workouts_query);
$assigned_workouts_stmt->bind_param("i", $member_id);
$assigned_workouts_stmt->execute();
$assigned_workouts_result = $assigned_workouts_stmt->get_result();
$assigned_workouts_stmt->close();

// Get available time slots for booking
$time_slots = [
    '07:00', '08:00', '09:00', '10:00', '11:00', '12:00',
    '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00'
];
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-dumbbell me-2"></i>Personal Training
        </h1>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <?php if ($access_denied): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Your account does not have personal training access enabled. Please contact administration to enable training features.
                </div>
            </div>
        </div>
    <?php else: ?>

    <!-- Assigned Trainer Section -->
    <?php if ($assigned_trainer): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card assigned-trainer-card">
                    <div class="card-header assigned-trainer-header">
                        <h6 class="m-0 font-weight-bold">
                            <i class="fas fa-user-check me-2"></i>My Assigned Trainer
                        </h6>
                    </div>
                    <div class="card-body assigned-trainer-body">
                        <div class="row align-items-center">
                            <div class="col-md-3 text-center">
                                <?php if ($assigned_trainer['trainer_photo']): ?>
                                    <img src="../uploads/staff_photos/<?php echo htmlspecialchars($assigned_trainer['trainer_photo']); ?>" 
                                         alt="Trainer Photo" class="trainer-photo rounded-circle" style="width: 100px; height: 100px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="trainer-photo rounded-circle bg-secondary d-flex align-items-center justify-content-center" 
                                         style="width: 100px; height: 100px;">
                                        <i class="fas fa-user fa-2x text-white"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-9">
                                <h5 class="text-white mb-2"><?php echo htmlspecialchars($assigned_trainer['trainer_name']); ?></h5>
                                <p class="text-white-50 mb-3">Your personal trainer assigned by the administration.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- No assigned trainer message -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>No Assigned Trainer:</strong> You can still book sessions with any available trainer below, or contact administration to assign you a personal trainer.
                </div>
            </div>
        </div>
    <?php endif; ?>

    

    <!-- Assigned Workouts Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card assigned-workouts-card">
                <div class="card-header assigned-workouts-header">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-dumbbell me-2"></i>My Assigned Workouts
                    </h6>
                </div>
                <div class="card-body assigned-workouts-body">
                    <?php if ($assigned_workouts_result->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table assigned-workouts-table">
                                <thead>
                                    <tr>
                                            <th>Session #</th>
                                            <th>Workout Name</th>
                                            <th>Trainer</th>
                                            <th>Scheduled Date</th>
                                            <th>Exercises</th>
                                            <th>Status</th>
                                        </tr>
                                </thead>
                                <tbody>
                                    <?php while ($workout = $assigned_workouts_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($workout['session_number']); ?></td>
                                            <td><?php echo htmlspecialchars($workout['workout_name']); ?></td>
                                            <td><?php 
                                                $display_trainer = $workout['trainer_name'] ?? '';
                                                if (empty($display_trainer) && !empty($assigned_trainer['trainer_name'])) {
                                                    $display_trainer = $assigned_trainer['trainer_name'];
                                                }
                                                echo htmlspecialchars($display_trainer ?: 'Not assigned');
                                            ?></td>
                                            <td>
                                                <?php if ($workout['session_date']): ?>
                                                    <?php echo date('M d, Y', strtotime($workout['session_date'])); ?>
                                                    <?php if ($workout['session_time']): ?>
                                                        <br><small class="text-muted"><?php echo date('g:i A', strtotime($workout['session_time'])); ?></small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Not scheduled</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $exercises = json_decode($workout['exercises'], true);
                                                if ($exercises && is_array($exercises)): ?>
                                                    <div class="exercise-list">
                                                        <table class="table table-sm mb-0 exercise-details-table">
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
                                                                <?php foreach ($exercises as $exercise): ?>
                                                                    <tr>
                                                                        <td class="small"><?php echo htmlspecialchars($exercise['exercise'] ?? ''); ?></td>
                                                                        <td class="small"><?php echo htmlspecialchars($exercise['sets'] ?? ''); ?></td>
                                                                        <td class="small"><?php echo htmlspecialchars($exercise['reps'] ?? ''); ?></td>
                                                                        <td class="small"><?php echo htmlspecialchars($exercise['rest'] ?? ''); ?></td>
                                                                        <td class="small"><?php echo htmlspecialchars($exercise['equipment'] ?? ''); ?></td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">No exercises</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $workout['status'] === 'completed' ? 'success' : 
                                                         ($workout['status'] === 'in_progress' ? 'primary' : 
                                                         ($workout['status'] === 'pending' ? 'warning' : 'secondary')); 
                                                ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $workout['status'])); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-dumbbell fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No assigned workouts</h5>
                            <p class="text-muted">Your trainer hasn't assigned any workouts yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Booking Modal -->
<div class="modal fade" id="bookingModal" tabindex="-1" aria-labelledby="bookingModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bookingModalLabel">Book Training Session</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="bookingForm" method="POST" action="book_training.php">
                <div class="modal-body">
                    <input type="hidden" name="trainer_id" id="modalTrainerId">

                    <div class="mb-3">
                        <label for="trainerName" class="form-label">Trainer</label>
                        <input type="text" class="form-control" id="trainerName" readonly>
                    </div>

                    <div class="mb-3">
                        <label for="sessionDate" class="form-label">Session Date</label>
                        <input type="date" class="form-control" id="sessionDate" name="session_date" min="<?php echo $session_start_date; ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="sessionTime" class="form-label">Session Time</label>
                        <select class="form-select" id="sessionTime" name="session_time" required>
                            <option value="">Select Time</option>
                            <?php foreach ($time_slots as $slot): ?>
                                <option value="<?php echo $slot; ?>:00"><?php echo date('g:i A', strtotime($slot . ':00')); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="sessionsPerMonth" class="form-label">Sessions per Month</label>
                        <input type="text" class="form-control" id="sessionsPerMonthDisplay" 
                               value="<?php echo $selected_package ? ($total_sessions ?? $selected_package) . ' sessions for ' . ($duration_months == 12 ? '1 year' : $duration_months . ' month' . ($duration_months > 1 ? 's' : '')) . ' (' . ucfirst($membership_type) . ' membership)' : 'No package selected'; ?>" 
                               readonly>
                        <small class="text-muted">Your membership type determines your total training sessions and duration.</small>
                    </div>

                    <div class="mb-3">
                        <label for="sessionNotes" class="form-label">Notes (Optional)</label>
                        <textarea class="form-control" id="sessionNotes" name="notes" rows="3" placeholder="Any special requests or goals for this session..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Book Session</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openBookingModal(trainerId, trainerName) {
    document.getElementById('modalTrainerId').value = trainerId;
    document.getElementById('trainerName').value = trainerName;
    document.getElementById('bookingModalLabel').textContent = 'Book Session with ' + trainerName;

    // Reset form
    document.getElementById('bookingForm').reset();
    
    // Reset trainer fields
    document.getElementById('modalTrainerId').value = trainerId;
    document.getElementById('trainerName').value = trainerName;

    // Show modal
    new bootstrap.Modal(document.getElementById('bookingModal')).show();
}

function scrollToTrainers() {
    document.getElementById('availableTrainers').scrollIntoView({ 
        behavior: 'smooth',
        block: 'start'
    });
}
</script>

<style>
.trainer-card {
    transition: transform 0.2s;
}

.trainer-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.trainer-photo {
    border: 3px solid #e9ecef;
}

.badge {
    font-size: 0.75em;
}

.training-sessions-card {
    background: rgba(30, 41, 59, 0.7) !important;
    -webkit-backdrop-filter: blur(15px);
    backdrop-filter: blur(15px);
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    border-radius: 16px;
}

.training-sessions-header {
    background: rgba(15, 23, 42, 0.8) !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
}

.training-sessions-header h6 {
    color: white !important;
}

.training-sessions-header i {
    color: #3b82f6;
}

.training-sessions-body {
    background: transparent !important;
    padding: 1.5rem;
}

.training-sessions-table {
    background: transparent !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
    color: white;
}

.training-sessions-table thead {
    background: rgba(59, 130, 246, 0.1) !important;
    border-bottom: 2px solid rgba(59, 130, 246, 0.3) !important;
}

.training-sessions-table th {
    color: rgba(255, 255, 255, 0.9) !important;
    font-weight: 600;
    border-color: rgba(255, 255, 255, 0.1) !important;
    padding: 1rem;
    background: rgba(59, 130, 246, 0.1) !important;
}

.training-sessions-table tbody {
    background: transparent !important;
}

.training-sessions-table td {
    color: rgba(255, 255, 255, 0.8) !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
    padding: 1rem;
    vertical-align: middle;
    background: transparent !important;
}

.training-sessions-table tbody tr {
    background: transparent !important;
}

.training-sessions-table tbody tr:hover {
    background: rgba(59, 130, 246, 0.05) !important;
}

.training-sessions-table tbody tr:hover td {
    background: rgba(59, 130, 246, 0.05) !important;
}

.training-sessions-table .fw-bold {
    color: rgba(255, 255, 255, 0.9) !important;
}

.session-slot-card {
    transition: all 0.3s ease;
}

.session-slot-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.session-slot-card.border-success {
    background: rgba(40, 167, 69, 0.05);
}

.session-slot-card.border-warning {
    background: rgba(255, 193, 7, 0.05);
}

.session-slot-card {
    cursor: pointer;
}

.workout-plan-card {
    background: rgba(30, 41, 59, 0.7) !important;
    -webkit-backdrop-filter: blur(15px);
    backdrop-filter: blur(15px);
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    border-radius: 16px;
}

.workout-plan-header {
    background: rgba(15, 23, 42, 0.8) !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
}

.workout-plan-header h6 {
    color: white !important;
}

.workout-plan-header i {
    color: #10b981;
}

.workout-plan-body {
    background: transparent !important;
    padding: 1.5rem;
}

.workout-plan-table {
    background: transparent !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
    color: white;
}

.workout-plan-table thead {
    background: rgba(16, 185, 129, 0.1) !important;
    border-bottom: 2px solid rgba(16, 185, 129, 0.3) !important;
}

.workout-plan-table th {
    color: rgba(255, 255, 255, 0.9) !important;
    font-weight: 600;
    border-color: rgba(255, 255, 255, 0.1) !important;
    padding: 0.75rem;
    background: rgba(16, 185, 129, 0.1) !important;
}

.workout-plan-table tbody {
    background: transparent !important;
}

.workout-plan-table td {
    color: rgba(255, 255, 255, 0.8) !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
    padding: 0.75rem;
    vertical-align: middle;
    background: transparent !important;
}

.workout-plan-table tbody tr {
    background: transparent !important;
}

.workout-plan-table tbody tr:hover {
    background: rgba(16, 185, 129, 0.05) !important;
}

.workout-plan-table tbody tr:hover td {
    background: rgba(16, 185, 129, 0.05) !important;
}

.exercise-list {
    max-height: 120px;
    overflow-y: auto;
}

.exercise-item {
    display: flex;
    align-items: center;
    padding: 0.25rem 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.exercise-item:last-child {
    border-bottom: none;
}

.assigned-trainer-card {
    background: rgba(30, 41, 59, 0.7) !important;
    -webkit-backdrop-filter: blur(15px);
    backdrop-filter: blur(15px);
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    border-radius: 16px;
}

.assigned-trainer-header {
    background: rgba(15, 23, 42, 0.8) !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
}

.assigned-trainer-header h6 {
    color: white !important;
}

.assigned-trainer-header i {
    color: #10b981;
}

.assigned-trainer-body {
    background: transparent !important;
    padding: 1.5rem;
}

/* Assigned Workouts Table Styles - Match modern table background */
.assigned-workouts-table {
    background: rgba(15, 23, 42, 0.8);
    -webkit-backdrop-filter: blur(10px);
    backdrop-filter: blur(10px);
    color: rgba(255, 255, 255, 0.9);
    border-collapse: collapse;
    font-size: 0.95rem;
}

.assigned-workouts-table thead {
    background: rgba(15, 23, 42, 0.95);
}

.assigned-workouts-table th,
.assigned-workouts-table td {
    background: rgba(15, 23, 42, 0.8);
    border: 1px solid rgba(255, 255, 255, 0.1);
    padding: 12px 16px;
    color: rgba(255, 255, 255, 0.9);
}

.assigned-workouts-table th {
    font-weight: 600;
    color: #f8f9fc;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Exercise Details Table Styles - Match modern table background */
.exercise-details-table {
    background: rgba(15, 23, 42, 0.8);
    -webkit-backdrop-filter: blur(10px);
    backdrop-filter: blur(10px);
    color: rgba(255, 255, 255, 0.9);
    border-collapse: collapse;
}

.exercise-details-table thead {
    background: rgba(15, 23, 42, 0.95);
}

.exercise-details-table th,
.exercise-details-table td {
    background: rgba(15, 23, 42, 0.8);
    border: 1px solid rgba(255, 255, 255, 0.1);
    padding: 4px 8px;
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.8rem;
}

.exercise-details-table th {
    font-weight: 600;
    color: #f8f9fc;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
</style>

<!-- Workout Details Modal -->
<div class="modal fade" id="workoutDetailsModal" tabindex="-1" aria-labelledby="workoutDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="background: rgba(30, 41, 59, 0.95); border: 1px solid rgba(255, 255, 255, 0.1);">
            <div class="modal-header" style="border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                <h5 class="modal-title text-white" id="workoutDetailsModalLabel">
                    <i class="fas fa-dumbbell me-2 text-primary"></i>
                    <span id="modalWorkoutName">Workout Details</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-white">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="d-flex align-items-center mb-2">
                            <i class="fas fa-calendar-alt text-primary me-2"></i>
                            <strong>Session:</strong>
                            <span class="ms-2" id="modalSessionNumber">-</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-center mb-2">
                            <i class="fas fa-clock text-warning me-2"></i>
                            <strong>Duration:</strong>
                            <span class="ms-2" id="modalDuration">-</span>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="d-flex align-items-center mb-2">
                        <i class="fas fa-info-circle text-info me-2"></i>
                        <strong>Status:</strong>
                        <span class="ms-2 badge" id="modalStatus">-</span>
                    </div>
                </div>
                
                <div class="mb-3" id="modalDescriptionSection" style="display: none;">
                    <h6 class="text-primary mb-2">
                        <i class="fas fa-file-alt me-2"></i>Description
                    </h6>
                    <p class="text-muted" id="modalDescription">-</p>
                </div>
                
                <div class="mb-3">
                    <h6 class="text-primary mb-2">
                        <i class="fas fa-dumbbell me-2"></i>Equipment Needed
                    </h6>
                    <div class="p-3 rounded" style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3);">
                        <p class="mb-0" id="modalEquipment">-</p>
                    </div>
                </div>
                
                <div class="mb-3" id="modalCompletedDateSection" style="display: none;">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-calendar-check text-success me-2"></i>
                        <strong>Completed Date:</strong>
                        <span class="ms-2 text-success" id="modalCompletedDate">-</span>
                    </div>
                </div>
                
                <div class="mb-3" id="modalNotesSection" style="display: none;">
                    <h6 class="text-primary mb-2">
                        <i class="fas fa-sticky-note me-2"></i>Notes
                    </h6>
                    <p class="text-muted" id="modalNotes">-</p>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid rgba(255, 255, 255, 0.1);">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function showWorkoutDetails(element) {
    // Get workout data from data attribute
    const workoutData = element.getAttribute('data-workout');
    if (!workoutData) return;
    
    let workout;
    try {
        workout = JSON.parse(workoutData);
    } catch (e) {
        console.error('Error parsing workout data:', e);
        return;
    }
    
    // Populate modal with workout data
    document.getElementById('modalWorkoutName').textContent = workout.workout_name || 'Workout Details';
    document.getElementById('modalSessionNumber').textContent = 'Session ' + (workout.session_number || '-');
    document.getElementById('modalDuration').textContent = (workout.duration_minutes || 60) + ' minutes';
    
    // Status badge
    const statusBadge = document.getElementById('modalStatus');
    const status = workout.status || 'pending';
    statusBadge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
    statusBadge.className = 'badge bg-' + (
        status === 'completed' ? 'success' :
        status === 'in_progress' ? 'info' :
        status === 'skipped' ? 'danger' : 'warning'
    );
    
    // Description
    const descSection = document.getElementById('modalDescriptionSection');
    const descText = document.getElementById('modalDescription');
    if (workout.workout_description && workout.workout_description.trim() !== '') {
        descText.textContent = workout.workout_description;
        descSection.style.display = 'block';
    } else {
        descSection.style.display = 'none';
    }
    
    // Equipment
    document.getElementById('modalEquipment').textContent = workout.exercises || 'No equipment specified';
    
    // Completed date
    const completedSection = document.getElementById('modalCompletedDateSection');
    const completedDate = document.getElementById('modalCompletedDate');
    if (workout.completed_date) {
        const date = new Date(workout.completed_date);
        completedDate.textContent = date.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
        completedSection.style.display = 'block';
    } else {
        completedSection.style.display = 'none';
    }
    
    // Notes
    const notesSection = document.getElementById('modalNotesSection');
    const notesText = document.getElementById('modalNotes');
    if (workout.notes && workout.notes.trim() !== '') {
        notesText.textContent = workout.notes;
        notesSection.style.display = 'block';
    } else {
        notesSection.style.display = 'none';
    }
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('workoutDetailsModal'));
    modal.show();
}
</script>

<?php endif; ?>

<?php include 'components/footer.php'; ?>

<!-- DataTables for better table functionality -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>

<script>
$(document).ready(function() {
    $('#assignedWorkoutsTable').DataTable({
        "order": [[0, "asc"]], // Sort by session number
        "pageLength": 25,
        "responsive": true,
        "lengthChange": false
    });
});
</script></content>
<parameter name="filePath"> 