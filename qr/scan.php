<?php
header('Content-Type: application/json');

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

require_once '../includes/db.php'; // provides $conn (MySQLi)

// Check and reconnect if needed
$conn = checkConnection();

// Enable error logging for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/qr_scan_errors.log');

// Create logs directory if it doesn't exist
if (!is_dir('../logs')) {
    mkdir('../logs', 0755, true);
}

function respond($data, $statusCode = 200) {
	http_response_code($statusCode);
	echo json_encode($data);
	exit;
}

// Log function for debugging
function debugLog($message, $data = null) {
    $log = date('Y-m-d H:i:s') . " - " . $message;
    if ($data) {
        $log .= " - " . json_encode($data);
    }
    error_log($log . "\n", 3, '../logs/qr_scan_debug.log');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	debugLog("Invalid request method", $_SERVER['REQUEST_METHOD']);
	respond(['success' => false, 'message' => 'Invalid request method'], 405);
}

$qrData = trim($_POST['qr_data'] ?? '');
$scannerType = trim($_POST['type'] ?? 'members'); // Get scanner type (members or staff)
debugLog("Received QR data", $qrData);
debugLog("Scanner type", $scannerType);

// Log all POST data for debugging
debugLog("All POST data received", $_POST);

if ($qrData === '') {
	debugLog("Missing QR data");
	respond(['success' => false, 'message' => 'Missing QR data'], 400);
}

if ($scannerType === '') {
	debugLog("Missing scanner type");
	respond(['success' => false, 'message' => 'Missing scanner type'], 400);
}

// Expected formats:
// 1) FIT_TRACK_MEMBER_ID:MEM-YYYY-XXXX
// 2) FIT_TRACK_STAFF_ID:STAFF-YYYY-XXXX
// 3) Raw member code like MEM-YYYY-XXXX
// 4) Raw staff code like STAFF-YYYY-XXXX
$memberCode = $qrData;
$staffCode = $qrData;
$isStaff = false;

$memberPrefix = 'FIT_TRACK_MEMBER_ID:';
$staffPrefix = 'FIT_TRACK_STAFF_ID:';

if (stripos($qrData, $memberPrefix) === 0) {
	$memberCode = substr($qrData, strlen($memberPrefix));
	$isStaff = false;
	debugLog("Detected member QR with prefix", $memberCode);
} elseif (stripos($qrData, $staffPrefix) === 0) {
	$staffCode = substr($qrData, strlen($staffPrefix));
	$isStaff = true;
	debugLog("Detected staff QR with prefix", $staffCode);
} else {
	// Check if it's a raw staff code
	if (stripos($qrData, 'STAFF-') === 0) {
		$staffCode = $qrData;
		$isStaff = true;
		debugLog("Detected raw staff code", $staffCode);
	} else {
		$memberCode = $qrData;
		$isStaff = false;
		debugLog("Detected raw member code", $memberCode);
	}
}

$memberCode = trim($memberCode);
$staffCode = trim($staffCode);

// Validate QR code type against scanner type
if ($scannerType === 'members' && $isStaff) {
	debugLog("Type mismatch: Scanner is in members mode but staff QR was scanned", ['scanner_type' => $scannerType, 'qr_type' => 'staff', 'qr_data' => $qrData]);
	respond(['success' => false, 'message' => 'This is a staff QR code. Please use the staff scanner or switch to staff mode.'], 400);
} elseif ($scannerType === 'staff' && !$isStaff) {
	debugLog("Type mismatch: Scanner is in staff mode but member QR was scanned", ['scanner_type' => $scannerType, 'qr_type' => 'member', 'qr_data' => $qrData]);
	respond(['success' => false, 'message' => 'This is a member QR code. Please use the member scanner or switch to member mode.'], 400);
}

debugLog("QR code type validation passed", ['scanner_type' => $scannerType, 'qr_type' => $isStaff ? 'staff' : 'member']);

// Check if separate attendance tables exist and create if needed
$memberTableExists = $conn->query("SHOW TABLES LIKE 'member_attendance'");
$staffTableExists = $conn->query("SHOW TABLES LIKE 'staff_attendance'");

if ($memberTableExists->num_rows == 0) {
	debugLog("Member attendance table does not exist, creating it");
	$createMemberSql = "CREATE TABLE IF NOT EXISTS member_attendance (
		id INT AUTO_INCREMENT PRIMARY KEY,
		member_id INT NOT NULL,
		member_code VARCHAR(50) NOT NULL,
		date DATE NOT NULL,
		time_in DATETIME DEFAULT NULL,
		time_out DATETIME DEFAULT NULL,
		created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		KEY member_id (member_id),
		KEY date (date),
		KEY idx_member_date (member_id, date),
		CONSTRAINT member_attendance_ibfk_1 FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

	if (!$conn->query($createMemberSql)) {
		debugLog("Failed to create member_attendance table", $conn->error);
		respond(['success' => false, 'message' => 'Failed to create member_attendance table: ' . $conn->error], 500);
	}
	debugLog("Member attendance table created successfully");
} else {
	debugLog("Member attendance table exists");
}

if ($staffTableExists->num_rows == 0) {
	debugLog("Staff attendance table does not exist, creating it");
	$createStaffSql = "CREATE TABLE IF NOT EXISTS staff_attendance (
		id INT AUTO_INCREMENT PRIMARY KEY,
		staff_id INT NOT NULL,
		staff_code VARCHAR(50) NOT NULL,
		date DATE NOT NULL,
		time_in DATETIME DEFAULT NULL,
		time_out DATETIME DEFAULT NULL,
		created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		KEY staff_id (staff_id),
		KEY date (date),
		CONSTRAINT staff_attendance_ibfk_1 FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

	if (!$conn->query($createStaffSql)) {
		debugLog("Failed to create staff_attendance table", $conn->error);
		respond(['success' => false, 'message' => 'Failed to create staff_attendance table: ' . $conn->error], 500);
	}
	debugLog("Staff attendance table created successfully");
} else {
	debugLog("Staff attendance table exists");
}

// Find member or staff by ID or qr_code_data
if ($isStaff) {
	debugLog("Searching for staff", ['staff_code' => $staffCode]);
	$stmt = $conn->prepare("SELECT id, staff_id, first_name, last_name, schedule, 'Staff' as membership_type FROM staff WHERE staff_id = ? OR qr_code_data = ? LIMIT 1");
	if (!$stmt) {
		debugLog("DB error preparing staff query", $conn->error);
		respond(['success' => false, 'message' => 'DB error: ' . $conn->error], 500);
	}
	$stmt->bind_param('ss', $staffCode, $staffCode);
	$stmt->execute();
	$result = $stmt->get_result();
	$member = $result->fetch_assoc();
	$stmt->close();
	
	if (!$member) {
		debugLog("Staff not found", $staffCode);
		respond(['success' => false, 'message' => 'Staff not found for code: ' . $staffCode], 404);
	}
	debugLog("Staff found", $member);
	
} else {
	debugLog("Searching for member", ['member_code' => $memberCode]);
	$stmt = $conn->prepare("SELECT id, member_id, first_name, last_name, membership_type, membership_duration, join_date, expired_date FROM members WHERE member_id = ? OR qr_code_data = ? LIMIT 1");
	if (!$stmt) {
		debugLog("DB error preparing member query", $conn->error);
		respond(['success' => false, 'message' => 'DB error: ' . $conn->error], 500);
	}
	$stmt->bind_param('ss', $memberCode, $memberCode);
	$stmt->execute();
	$result = $stmt->get_result();
	$member = $result->fetch_assoc();
	$stmt->close();
	
	if (!$member) {
		debugLog("Member not found", $memberCode);
		respond(['success' => false, 'message' => 'Member not found for code: ' . $memberCode], 404);
	}
	debugLog("Member found", $member);
	
	// Check if membership has expired
	$currentDate = date('Y-m-d');
	$expiredDate = null;
	
	// Calculate expired date if not stored
	if ($member['expired_date']) {
		$expiredDate = $member['expired_date'];
	} else {
		// Calculate expired date for legacy records
		if ($member['membership_type'] === 'session') {
			$expiredDate = date('Y-m-d', strtotime($member['join_date'] . ' +1 day'));
		} elseif (in_array($member['membership_type'], ['regular', 'student', 'vip']) && $member['membership_duration']) {
			$expiredDate = date('Y-m-d', strtotime($member['join_date'] . ' +' . $member['membership_duration'] . ' months'));
		} else {
			$expiredDate = date('Y-m-d', strtotime($member['join_date'] . ' +30 days'));
		}
	}
	
	// Check if membership is expired
	if ($expiredDate && $expiredDate < $currentDate) {
		debugLog("Member membership expired", ['member_code' => $memberCode, 'expired_date' => $expiredDate, 'current_date' => $currentDate]);
		respond(['success' => false, 'message' => 'Your membership has expired on ' . date('M d, Y', strtotime($expiredDate)) . '. Please renew your membership to continue using the gym.'], 403);
	}
	
	debugLog("Member membership is active", ['expired_date' => $expiredDate, 'current_date' => $currentDate]);
}

// Use database date to avoid timezone issues
$today = $conn->query("SELECT CURDATE() as today")->fetch_assoc()['today'];
debugLog("Processing for date", $today);

// Check if there is an active attendance record today (no time_out yet)
$tableName = $isStaff ? 'staff_attendance' : 'member_attendance';
$idField = $isStaff ? 'staff_id' : 'member_id';
$stmt = $conn->prepare("SELECT id, time_in, time_out FROM $tableName WHERE $idField = ? AND date = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param('is', $member['id'], $today);
$stmt->execute();
$last = $stmt->get_result()->fetch_assoc();
$stmt->close();

debugLog("Last attendance record", $last);

// Check time-based restrictions for staff - only for TIME IN
if ($isStaff && $member['schedule'] && (!$last || !empty($last['time_out']))) {
	// This is TIME IN - check schedule restrictions
	$currentHour = (int)date('H'); // 24-hour format
	$currentMinute = (int)date('i');
	$currentTime = $currentHour * 60 + $currentMinute; // Convert to minutes since midnight
	
	if ($member['schedule'] === 'morning') {
		// Morning schedule: 7AM - 12PM (7:00 - 12:00)
		$startTime = 7 * 60; // 7:00 AM
		$endTime = 12 * 60; // 12:00 PM
		
		if ($currentTime < $startTime || $currentTime > $endTime) {
			$scheduleMsg = "Morning shift: 7:00 AM - 12:00 PM";
			debugLog("Staff time-in outside morning hours", [
				'staff_id' => $member['staff_id'], 
				'schedule' => $member['schedule'], 
				'current_time' => date('H:i'), 
				'allowed_range' => '07:00-12:00'
			]);
			respond(['success' => false, 'message' => "⏰ Time Restriction!\n\nYou are scheduled for morning shift (7:00 AM - 12:00 PM).\n\nCurrent time: " . date('h:i A') . "\n\nPlease scan during your scheduled hours."], 403);
		}
	} elseif ($member['schedule'] === 'afternoon') {
		// Afternoon schedule: 1PM - 6PM (13:00 - 18:00)
		$startTime = 13 * 60; // 1:00 PM
		$endTime = 18 * 60; // 6:00 PM
		
		if ($currentTime < $startTime || $currentTime > $endTime) {
			$scheduleMsg = "Afternoon shift: 1:00 PM - 6:00 PM";
			debugLog("Staff time-in outside afternoon hours", [
				'staff_id' => $member['staff_id'], 
				'schedule' => $member['schedule'], 
				'current_time' => date('H:i'), 
				'allowed_range' => '13:00-18:00'
			]);
			respond(['success' => false, 'message' => "⏰ Time Restriction!\n\nYou are scheduled for afternoon shift (1:00 PM - 6:00 PM).\n\nCurrent time: " . date('h:i A') . "\n\nPlease scan during your scheduled hours."], 403);
		}
	}
	debugLog("Staff time-in within allowed hours", [
		'staff_id' => $member['staff_id'], 
		'schedule' => $member['schedule'], 
		'current_time' => date('H:i')
	]);
}

$action = '';
$timeIn = null;
$timeOut = null;

if ($last && empty($last['time_out'])) {
	// Time out
	debugLog("Processing time out");
	$update = $conn->prepare("UPDATE $tableName SET time_out = NOW() WHERE id = ?");
	$update->bind_param('i', $last['id']);
	if (!$update->execute()) {
		debugLog("Failed to log time out", $update->error);
		respond(['success' => false, 'message' => 'Failed to log time out'], 500);
	}
	$update->close();

	$action = 'time_out';
	$timeIn = $last['time_in'];
	// Fetch updated record
	$q = $conn->prepare("SELECT time_out FROM $tableName WHERE id = ?");
	$q->bind_param('i', $last['id']);
	$q->execute();
	$updated = $q->get_result()->fetch_assoc();
	$q->close();
	$timeOut = $updated['time_out'];
	debugLog("Time out recorded", ['time_out' => $timeOut]);
	
	// For members: Mark training sessions as completed when they time out
	if (!$isStaff && $action === 'time_out') {
		debugLog("Updating training sessions to completed for member time out");
		$update_sessions = $conn->prepare("UPDATE training_sessions SET status = 'completed' WHERE member_id = ? AND session_date = ? AND status IN ('booked', 'in_progress')");
		$update_sessions->bind_param('is', $member['id'], $today);
		$update_sessions->execute();
		$sessions_updated = $update_sessions->affected_rows;
		$update_sessions->close();
		debugLog("Training sessions marked as completed", ['sessions_updated' => $sessions_updated]);
	}
} else {
	// Time in (create new record)
	debugLog("Processing time in");
	$codeField = $isStaff ? 'staff_code' : 'member_code';
	$codeValue = $isStaff ? $member['staff_id'] : $member['member_id'];
	$insert = $conn->prepare("INSERT INTO $tableName ($idField, $codeField, date, time_in) VALUES (?, ?, ?, NOW())");
	$insert->bind_param('iss', $member['id'], $codeValue, $today);
	if (!$insert->execute()) {
		debugLog("Failed to log time in", $insert->error);
		respond(['success' => false, 'message' => 'Failed to log time in'], 500);
	}
	$insert->close();
	$action = 'time_in';
	// Fetch inserted values
	$q = $conn->prepare("SELECT time_in FROM $tableName WHERE id = LAST_INSERT_ID()");
	$q->execute();
	$inserted = $q->get_result()->fetch_assoc();
	$q->close();
	$timeIn = $inserted['time_in'];
	debugLog("Time in recorded", ['time_in' => $timeIn, 'insert_id' => $conn->insert_id]);
	
	// For members: Mark training sessions as in_progress when they time in
	if (!$isStaff && $action === 'time_in') {
		debugLog("Updating training sessions to in_progress for member time in");
		$update_sessions = $conn->prepare("UPDATE training_sessions SET status = 'in_progress' WHERE member_id = ? AND session_date = ? AND status = 'booked'");
		$update_sessions->bind_param('is', $member['id'], $today);
		$update_sessions->execute();
		$sessions_updated = $update_sessions->affected_rows;
		$update_sessions->close();
		debugLog("Training sessions marked as in_progress", ['sessions_updated' => $sessions_updated]);
	}
}

$name = $member['first_name'] . ' ' . $member['last_name'];
$type = ucfirst($member['membership_type'] ?? ($isStaff ? 'Staff' : 'Member'));
$status = ($action === 'time_out') ? 'Completed' : 'Active';

$response = [
	'success' => true,
	'action' => $action,
	'user_type' => $isStaff ? 'staff' : 'member',
	'member' => [
		'id' => (int)$member['id'],
		'code' => $isStaff ? $member['staff_id'] : $member['member_id'],
		'name' => $name,
		'type' => $type
	],
	'record' => [
		'id' => $action === 'time_in' ? $conn->insert_id : $last['id'],
		'time_in' => $timeIn,
		'time_out' => $timeOut,
		'status' => $status
	]
];

debugLog("Success response", $response);
respond($response);
