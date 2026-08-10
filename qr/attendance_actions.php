<?php
header('Content-Type: application/json');
require_once '../includes/db.php';

function respond($data, $status = 200) {
	http_response_code($status);
	echo json_encode($data);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	respond(['success' => false, 'message' => 'Invalid request method'], 405);
}

action:
$action = $_POST['action'] ?? '';
if ($action === '') {
	respond(['success' => false, 'message' => 'Missing action'], 400);
}

// Ensure separate archive tables exist
$createMemberArchive = "CREATE TABLE IF NOT EXISTS member_attendance_archive (
	id INT AUTO_INCREMENT PRIMARY KEY,
	member_id INT NOT NULL,
	member_code VARCHAR(50) NOT NULL,
	date DATE NOT NULL,
	time_in DATETIME DEFAULT NULL,
	time_out DATETIME DEFAULT NULL,
	archive_date DATE NOT NULL,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	KEY member_id (member_id),
	KEY date (date),
	KEY archive_date (archive_date),
	KEY idx_member_date (member_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$createStaffArchive = "CREATE TABLE IF NOT EXISTS staff_attendance_archive (
	id INT AUTO_INCREMENT PRIMARY KEY,
	staff_id INT NOT NULL,
	staff_code VARCHAR(50) NOT NULL,
	date DATE NOT NULL,
	time_in DATETIME DEFAULT NULL,
	time_out DATETIME DEFAULT NULL,
	archive_date DATE NOT NULL,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	KEY staff_id (staff_id),
	KEY date (date),
	KEY archive_date (archive_date),
	KEY idx_staff_date (staff_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if (!$conn->query($createMemberArchive)) {
	respond(['success' => false, 'message' => 'Failed to create member archive table: ' . $conn->error], 500);
}

if (!$conn->query($createStaffArchive)) {
	respond(['success' => false, 'message' => 'Failed to create staff archive table: ' . $conn->error], 500);
}

$today = date('Y-m-d');

if ($action === 'save_today') {
	$conn->begin_transaction();
	try {
		// Archive member attendance
		$memberSql = "INSERT INTO member_attendance_archive (member_id, member_code, date, time_in, time_out, archive_date)
			SELECT member_id, member_code, date, time_in, time_out, ? as archive_date
			FROM member_attendance WHERE date = ?";
		$memberStmt = $conn->prepare($memberSql);
		$memberStmt->bind_param('ss', $today, $today);
		$memberStmt->execute();
		$memberAffected = $memberStmt->affected_rows;
		$memberStmt->close();

		// Archive staff attendance
		$staffSql = "INSERT INTO staff_attendance_archive (staff_id, staff_code, date, time_in, time_out, archive_date)
			SELECT staff_id, staff_code, date, time_in, time_out, ? as archive_date
			FROM staff_attendance WHERE date = ?";
		$staffStmt = $conn->prepare($staffSql);
		$staffStmt->bind_param('ss', $today, $today);
		$staffStmt->execute();
		$staffAffected = $staffStmt->affected_rows;
		$staffStmt->close();

		$conn->commit();
		$totalAffected = $memberAffected + $staffAffected;
		respond(['success' => true, 'message' => "Archived $memberAffected member and $staffAffected staff records ($totalAffected total)."], 200);
	} catch (Throwable $e) {
		$conn->rollback();
		respond(['success' => false, 'message' => 'Failed to save today\'s attendance: ' . $e->getMessage()], 500);
	}
}

if ($action === 'reset_day') {
	$conn->begin_transaction();
	try {
		// Archive member attendance first
		$memberSql = "INSERT INTO member_attendance_archive (member_id, member_code, date, time_in, time_out, archive_date)
			SELECT member_id, member_code, date, time_in, time_out, ? as archive_date
			FROM member_attendance WHERE date = ?";
		$memberStmt = $conn->prepare($memberSql);
		$memberStmt->bind_param('ss', $today, $today);
		$memberStmt->execute();
		$memberSaved = $memberStmt->affected_rows;
		$memberStmt->close();

		// Archive staff attendance first
		$staffSql = "INSERT INTO staff_attendance_archive (staff_id, staff_code, date, time_in, time_out, archive_date)
			SELECT staff_id, staff_code, date, time_in, time_out, ? as archive_date
			FROM staff_attendance WHERE date = ?";
		$staffStmt = $conn->prepare($staffSql);
		$staffStmt->bind_param('ss', $today, $today);
		$staffStmt->execute();
		$staffSaved = $staffStmt->affected_rows;
		$staffStmt->close();

		// Clear today's member attendance
		$memberDel = $conn->prepare("DELETE FROM member_attendance WHERE date = ?");
		$memberDel->bind_param('s', $today);
		$memberDel->execute();
		$memberDeleted = $memberDel->affected_rows;
		$memberDel->close();

		// Clear today's staff attendance
		$staffDel = $conn->prepare("DELETE FROM staff_attendance WHERE date = ?");
		$staffDel->bind_param('s', $today);
		$staffDel->execute();
		$staffDeleted = $staffDel->affected_rows;
		$staffDel->close();

		$conn->commit();
		$totalSaved = $memberSaved + $staffSaved;
		$totalDeleted = $memberDeleted + $staffDeleted;
		respond(['success' => true, 'message' => "Archived $memberSaved member + $staffSaved staff records ($totalSaved total) and reset $memberDeleted member + $staffDeleted staff records ($totalDeleted total)."], 200);
	} catch (Throwable $e) {
		$conn->rollback();
		respond(['success' => false, 'message' => 'Reset failed: ' . $e->getMessage()], 500);
	}
}

respond(['success' => false, 'message' => 'Unknown action'], 400); 