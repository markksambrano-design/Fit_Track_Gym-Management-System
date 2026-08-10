<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../../includes/db.php';
include '../../includes/functions.php';

// Database connection - use MySQLi for consistency
try {
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
} catch (Exception $e) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'view':
        $staffId = $_GET['staff_id'] ?? '';
        if (empty($staffId)) {
            echo json_encode(['success' => false, 'message' => 'Staff ID is required']);
            exit;
        }
        
        try {
            $stmt = $conn->prepare("
                SELECT 
                    s.staff_id, s.first_name, s.last_name, s.email, s.phone, 'staff' as position, s.hire_date,
                    s.address, s.gender, s.age, s.photo, s.qr_code_data, s.schedule,
                    p.salary, p.employment_type, p.bank_name, p.account_number, p.tax_id
                FROM staff s
                LEFT JOIN payroll p ON s.id = p.staff_id
                WHERE s.staff_id = ?
            ");
            $stmt->bind_param('s', $staffId);
            $stmt->execute();
            $result = $stmt->get_result();
            $staff = $result->fetch_assoc();
            $stmt->close();
            
            if ($staff) {
                echo json_encode(['success' => true, 'staff' => $staff]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Staff not found']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;
        
    case 'view_payroll':
        $staffId = $_GET['staff_id'] ?? '';
        $month = $_GET['month'] ?? date('Y-m');
        
        if (empty($staffId)) {
            echo json_encode(['success' => false, 'message' => 'Staff ID is required']);
            exit;
        }
        
        try {
            // Get staff basic info first
            $stmt = $conn->prepare("
                SELECT 
                    s.staff_id, s.first_name, s.last_name, 'staff' as position, s.hire_date,
                    p.salary, p.employment_type, p.bank_name, p.account_number, p.tax_id
                FROM staff s
                LEFT JOIN payroll p ON s.id = p.staff_id
                WHERE s.staff_id = ?
            ");
            $stmt->bind_param('s', $staffId);
            $stmt->execute();
            $result = $stmt->get_result();
            $staff = $result->fetch_assoc();
            $stmt->close();
            
            if ($staff) {
                // Get attendance data with 8-hour daily cap
                $stmt = $conn->prepare("
                    SELECT 
                        sa.date,
                        TIMESTAMPDIFF(HOUR, sa.time_in, sa.time_out) as hours_worked
                    FROM staff_attendance sa 
                    WHERE sa.staff_id = (SELECT id FROM staff WHERE staff_id = ?)
                    AND DATE_FORMAT(sa.date, '%Y-%m') = ?
                    AND sa.time_out IS NOT NULL
                    ORDER BY sa.date
                ");
                $stmt->bind_param('ss', $staffId, $month);
                $stmt->execute();
                $result = $stmt->get_result();
                $attendanceData = [];
                while ($row = $result->fetch_assoc()) {
                    $attendanceData[] = $row;
                }
                $stmt->close();
                
                // Group by date and apply 8-hour cap
                $dailyHours = [];
                foreach ($attendanceData as $record) {
                    $date = $record['date'];
                    if (!isset($dailyHours[$date])) {
                        $dailyHours[$date] = 0;
                    }
                    $dailyHours[$date] += $record['hours_worked'];
                }
                
                // Calculate totals with 8-hour cap
                $totalHours = 0;
                $daysWorked = 0;
                $totalPay = 0.0;
                foreach ($dailyHours as $date => $dayHours) {
                    $cappedHours = min($dayHours, 8.0);
                    $totalHours += $cappedHours;
                    $totalPay += calculateStaffDailyPayroll($cappedHours);
                    if ($cappedHours > 0) {
                        $daysWorked++;
                    }
                }
                
                // Add calculated fields
                $staff['days_worked'] = $daysWorked;
                $staff['total_hours'] = $totalHours;
                $staff['calculated_pay'] = $totalPay;
            }
            
            if ($staff) {
                echo json_encode(['success' => true, 'staff' => $staff]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Staff not found']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;
        
    case 'edit_payroll':
        $staffId = $_POST['staff_id'] ?? '';
        $salary = $_POST['salary'] ?? '';
        $employmentType = $_POST['employment_type'] ?? 'full-time';
        $bankName = trim($_POST['bank_name'] ?? '');
        $accountNumber = trim($_POST['account_number'] ?? '');
        $taxId = trim($_POST['tax_id'] ?? '');
        
        if (empty($staffId) || empty($salary)) {
            echo json_encode(['success' => false, 'message' => 'Staff ID and salary are required']);
            exit;
        }
        
        try {
            // Get staff database ID
            $stmt = $conn->prepare("SELECT id FROM staff WHERE staff_id = ?");
            $stmt->bind_param('s', $staffId);
            $stmt->execute();
            $result = $stmt->get_result();
            $staffDbId = $result->fetch_assoc()['id'] ?? null;
            $stmt->close();
            
            if ($staffDbId) {
                // Check if payroll record exists
                $stmt = $conn->prepare("SELECT id FROM payroll WHERE staff_id = ?");
                $stmt->bind_param('i', $staffDbId);
                $stmt->execute();
                $result = $stmt->get_result();
                $payrollExists = $result->fetch_assoc()['id'] ?? null;
                $stmt->close();
                
                if ($payrollExists) {
                    // Update existing payroll record
                    $stmt = $conn->prepare("
                        UPDATE payroll 
                        SET salary = ?, employment_type = ?, bank_name = ?, account_number = ?, tax_id = ?
                        WHERE staff_id = ?
                    ");
                    $bankNameValue = $bankName ?: null;
                    $accountNumberValue = $accountNumber ?: null;
                    $taxIdValue = $taxId ?: null;
                    $stmt->bind_param('dssssi', $salary, $employmentType, 
                        $bankNameValue, $accountNumberValue, $taxIdValue, $staffDbId);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    // Create new payroll record
                    $stmt = $conn->prepare("
                        INSERT INTO payroll (staff_id, salary, employment_type, bank_name, account_number, tax_id)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $bankNameValue = $bankName ?: null;
                    $accountNumberValue = $accountNumber ?: null;
                    $taxIdValue = $taxId ?: null;
                    $stmt->bind_param('idssss', $staffDbId, $salary, $employmentType,
                        $bankNameValue, $accountNumberValue, $taxIdValue);
                    $stmt->execute();
                    $stmt->close();
                }
                
                echo json_encode(['success' => true, 'message' => 'Payroll information updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Staff not found']);
            }
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;
        
    case 'add_payroll':
        $staffDbId = $_POST['staff_id'] ?? '';
        $salary = $_POST['salary'] ?? '';
        $employmentType = $_POST['employment_type'] ?? 'full-time';
        $bankName = trim($_POST['bank_name'] ?? '');
        $accountNumber = trim($_POST['account_number'] ?? '');
        $taxId = trim($_POST['tax_id'] ?? '');
        
        if (empty($staffDbId) || empty($salary)) {
            echo json_encode(['success' => false, 'message' => 'Staff and salary are required']);
            exit;
        }
        
        try {
            // Check if payroll record already exists
            $stmt = $conn->prepare("SELECT id FROM payroll WHERE staff_id = ?");
            $stmt->bind_param('i', $staffDbId);
            $stmt->execute();
            $result = $stmt->get_result();
            $payrollExists = $result->fetch_assoc()['id'] ?? null;
            $stmt->close();
            
            if ($payrollExists) {
                echo json_encode(['success' => false, 'message' => 'Payroll record already exists for this staff member']);
            } else {
                // Create new payroll record
                $stmt = $conn->prepare("
                    INSERT INTO payroll (staff_id, salary, employment_type, bank_name, account_number, tax_id)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $bankNameValue = $bankName ?: null;
                $accountNumberValue = $accountNumber ?: null;
                $taxIdValue = $taxId ?: null;
                $stmt->bind_param('idssss', $staffDbId, $salary, $employmentType,
                    $bankNameValue, $accountNumberValue, $taxIdValue);
                $stmt->execute();
                $stmt->close();
                
                echo json_encode(['success' => true, 'message' => 'Payroll record added successfully']);
            }
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;
        
    case 'mark_paid':
        $staffId = $_POST['staff_id'] ?? '';
        $month = $_POST['month'] ?? date('Y-m');
        
        if (empty($staffId)) {
            echo json_encode(['success' => false, 'message' => 'Staff ID is required']);
            exit;
        }
        
        try {
            // Get staff database ID
            $stmt = $conn->prepare("SELECT id FROM staff WHERE staff_id = ?");
            $stmt->bind_param('s', $staffId);
            $stmt->execute();
            $result = $stmt->get_result();
            $staffDbId = $result->fetch_assoc()['id'] ?? null;
            $stmt->close();
            
            if ($staffDbId) {
                // Update payroll history to mark as paid
                $stmt = $conn->prepare("
                    UPDATE payroll_history 
                    SET status = 'paid', payment_date = CURDATE()
                    WHERE staff_id = ? AND period_start LIKE ?
                ");
                $periodStart = $month . '-01';
                $stmt->bind_param('is', $staffDbId, $periodStart);
                $stmt->execute();
                $stmt->close();
                
                echo json_encode(['success' => true, 'message' => 'Payroll marked as paid successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Staff not found']);
            }
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;
        
    case 'time_in':
        $staffId = $_POST['staff_id'] ?? '';
        
        if (empty($staffId)) {
            echo json_encode(['success' => false, 'message' => 'Staff ID is required']);
            exit;
        }
        
        try {
            // Get staff internal ID
            $stmt = $conn->prepare("SELECT id FROM staff WHERE staff_id = ?");
            $stmt->bind_param('s', $staffId);
            $stmt->execute();
            $result = $stmt->get_result();
            $staff = $result->fetch_assoc();
            $stmt->close();
            
            if (!$staff) {
                echo json_encode(['success' => false, 'message' => 'Staff not found']);
                exit;
            }
            
            $internalStaffId = $staff['id'];
            $today = date('Y-m-d');
            $currentTime = date('Y-m-d H:i:s');
            
            // Check if already timed in today
            $stmt = $conn->prepare("SELECT id FROM staff_attendance WHERE staff_id = ? AND date = ? AND time_out IS NULL");
            $stmt->bind_param('is', $internalStaffId, $today);
            $stmt->execute();
            $result = $stmt->get_result();
            $existing = $result->fetch_assoc();
            $stmt->close();
            
            if ($existing) {
                echo json_encode(['success' => false, 'message' => 'Staff is already timed in today']);
                exit;
            }
            
            // Record time in
            $stmt = $conn->prepare("INSERT INTO staff_attendance (staff_id, date, time_in) VALUES (?, ?, ?)");
            $stmt->bind_param('iss', $internalStaffId, $today, $currentTime);
            $stmt->execute();
            $stmt->close();
            
            echo json_encode(['success' => true, 'message' => 'Time in recorded successfully']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;
        
    case 'time_out':
        $staffId = $_POST['staff_id'] ?? '';
        
        if (empty($staffId)) {
            echo json_encode(['success' => false, 'message' => 'Staff ID is required']);
            exit;
        }
        
        try {
            // Get staff internal ID
            $stmt = $conn->prepare("SELECT id FROM staff WHERE staff_id = ?");
            $stmt->bind_param('s', $staffId);
            $stmt->execute();
            $result = $stmt->get_result();
            $staff = $result->fetch_assoc();
            $stmt->close();
            
            if (!$staff) {
                echo json_encode(['success' => false, 'message' => 'Staff not found']);
                exit;
            }
            
            $internalStaffId = $staff['id'];
            $today = date('Y-m-d');
            $currentTime = date('Y-m-d H:i:s');
            
            // Check if timed in today
            $stmt = $conn->prepare("SELECT id FROM staff_attendance WHERE staff_id = ? AND date = ? AND time_out IS NULL");
            $stmt->bind_param('is', $internalStaffId, $today);
            $stmt->execute();
            $result = $stmt->get_result();
            $attendance = $result->fetch_assoc();
            $stmt->close();
            
            if (!$attendance) {
                echo json_encode(['success' => false, 'message' => 'Staff is not timed in today']);
                exit;
            }
            
            // Record time out
            $stmt = $conn->prepare("UPDATE staff_attendance SET time_out = ? WHERE id = ?");
            $stmt->bind_param('si', $currentTime, $attendance['id']);
            $stmt->execute();
            $stmt->close();
            
            // Automatically create/update daily payroll history record
            require_once '../utilities/daily_payroll_helper.php';
            $payrollResult = createOrUpdateDailyPayroll($internalStaffId, $today, $conn);
            
            if ($payrollResult['success']) {
                echo json_encode(['success' => true, 'message' => 'Time out recorded successfully. Payroll record ' . ($payrollResult['action'] ?? 'processed') . '.']);
            } else {
                // Still return success for time out, but log payroll error
                error_log("Payroll update failed on time out: " . $payrollResult['message']);
                echo json_encode(['success' => true, 'message' => 'Time out recorded successfully']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;
        
    case 'generate_report':
        $month = $_GET['month'] ?? date('Y-m');
        
        try {
            // Generate payroll report data with 8-hour daily cap
            $stmt = $conn->prepare("
                SELECT 
                    s.staff_id, s.first_name, s.last_name, 'staff' as position,
                    p.salary, p.employment_type,
                    sa.date,
                    TIMESTAMPDIFF(HOUR, sa.time_in, sa.time_out) as hours_worked
                FROM staff s
                LEFT JOIN payroll p ON s.id = p.staff_id
                LEFT JOIN staff_attendance sa ON s.id = sa.staff_id 
                    AND DATE_FORMAT(sa.date, '%Y-%m') = ?
                    AND sa.time_out IS NOT NULL
                ORDER BY s.first_name, s.last_name, sa.date
            ");
            $stmt->bind_param('s', $month);
            $stmt->execute();
            $result = $stmt->get_result();
            $rawData = [];
            while ($row = $result->fetch_assoc()) {
                $rawData[] = $row;
            }
            $stmt->close();
            
            // Group by staff and apply 8-hour cap
            $staffGrouped = [];
            foreach ($rawData as $row) {
                $staffId = $row['staff_id'];
                if (!isset($staffGrouped[$staffId])) {
                    $staffGrouped[$staffId] = [
                        'staff_id' => $row['staff_id'],
                        'first_name' => $row['first_name'],
                        'last_name' => $row['last_name'],
                        'position' => $row['position'],
                        'salary' => $row['salary'],
                        'employment_type' => $row['employment_type'],
                        'daily_hours' => [],
                        'days_worked' => 0,
                        'total_hours' => 0
                    ];
                }
                
                if ($row['date']) {
                    $date = $row['date'];
                    if (!isset($staffGrouped[$staffId]['daily_hours'][$date])) {
                        $staffGrouped[$staffId]['daily_hours'][$date] = 0;
                    }
                    $staffGrouped[$staffId]['daily_hours'][$date] += $row['hours_worked'];
                }
            }
            
            // Apply 8-hour cap and calculate totals
            $payrollData = [];
            foreach ($staffGrouped as $staffId => $staff) {
                $totalHours = 0;
                $daysWorked = 0;
                
                foreach ($staff['daily_hours'] as $date => $dayHours) {
                    $cappedHours = min($dayHours, 8.0);
                    $totalHours += $cappedHours;
                    if ($cappedHours > 0) {
                        $daysWorked++;
                    }
                }
                
                $payrollData[] = [
                    'staff_id' => $staff['staff_id'],
                    'first_name' => $staff['first_name'],
                    'last_name' => $staff['last_name'],
                    'position' => $staff['position'],
                    'salary' => $staff['salary'],
                    'employment_type' => $staff['employment_type'],
                    'days_worked' => $daysWorked,
                    'total_hours' => $totalHours,
                    'calculated_pay' => $totalHours * 62.50
                ];
            }
            
            // Set headers for CSV download
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="payroll_report_' . $month . '.csv"');
            
            $output = fopen('php://output', 'w');
            
            // CSV headers
            fputcsv($output, [
                'Staff ID', 'Name', 'Position', 'Employment Type', 
                'Daily Rate', 'Days Worked', 'Total Hours', 'Calculated Pay'
            ]);
            
            // CSV data
            foreach ($payrollData as $row) {
                fputcsv($output, [
                    $row['staff_id'],
                    $row['first_name'] . ' ' . $row['last_name'],
                    $row['position'],
                    ucfirst($row['employment_type'] ?? 'Full-time'),
                    '₱' . number_format($row['salary'] ?? 0, 2),
                    $row['days_worked'] ?? 0,
                    $row['total_hours'] ?? 0,
                    '₱' . number_format($row['calculated_pay'] ?? 0, 2)
                ]);
            }
            
            fclose($output);
            exit;
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;
        
    case 'view_payroll_history':
        $recordId = $_GET['id'] ?? '';
        
        if (empty($recordId)) {
            echo json_encode(['success' => false, 'message' => 'Record ID is required']);
            exit;
        }
        
        try {
            $stmt = $conn->prepare("
                SELECT 
                    ph.id,
                    ph.staff_id,
                    s.staff_id as staff_code,
                    s.first_name,
                    s.last_name,
                    ph.period_start,
                    ph.period_end,
                    ph.hours_worked,
                    ph.hourly_rate,
                    ph.status,
                    ph.payment_date,
                    ph.notes,
                    ph.created_at,
                    ph.updated_at,
                    (ph.hours_worked * ph.hourly_rate) as total_pay
                FROM payroll_history ph
                LEFT JOIN staff s ON ph.staff_id = s.id
                WHERE ph.id = ?
            ");
            $stmt->bind_param('i', $recordId);
            $stmt->execute();
            $result = $stmt->get_result();
            $record = $result->fetch_assoc();
            $stmt->close();
            
            if ($record) {
                echo json_encode(['success' => true, 'record' => $record]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Record not found']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;
        
    case 'edit_payroll_history':
        $recordId = $_POST['record_id'] ?? '';
        $periodStart = $_POST['period_start'] ?? '';
        $periodEnd = $_POST['period_end'] ?? '';
        $hoursWorked = $_POST['hours_worked'] ?? '';
        $hourlyRate = $_POST['hourly_rate'] ?? '';
        $status = $_POST['status'] ?? 'pending';
        $paymentDate = $_POST['payment_date'] ?? null;
        $notes = trim($_POST['notes'] ?? '');
        
        if (empty($recordId) || empty($periodStart) || empty($periodEnd) || empty($hoursWorked) || empty($hourlyRate)) {
            echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
            exit;
        }
        
        try {
            $stmt = $conn->prepare("
                UPDATE payroll_history 
                SET period_start = ?, period_end = ?, hours_worked = ?, hourly_rate = ?, 
                    status = ?, payment_date = ?, notes = ?
                WHERE id = ?
            ");
            $paymentDateValue = $paymentDate ?: null;
            $notesValue = $notes ?: null;
            $stmt->bind_param('ssddsssi', $periodStart, $periodEnd, $hoursWorked, $hourlyRate, 
                $status, $paymentDateValue, $notesValue, $recordId);
            $stmt->execute();
            $stmt->close();
            
            echo json_encode(['success' => true, 'message' => 'Payroll record updated successfully']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;
        
    case 'add_payroll_history':
        $staffId = $_POST['staff_id'] ?? '';
        $periodStart = $_POST['period_start'] ?? '';
        $periodEnd = $_POST['period_end'] ?? '';
        $hoursWorked = $_POST['hours_worked'] ?? '';
        $hourlyRate = $_POST['hourly_rate'] ?? '';
        $status = $_POST['status'] ?? 'pending';
        $paymentDate = $_POST['payment_date'] ?? null;
        $notes = trim($_POST['notes'] ?? '');
        
        if (empty($staffId) || empty($periodStart) || empty($periodEnd) || empty($hoursWorked) || empty($hourlyRate)) {
            echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
            exit;
        }
        
        try {
            $stmt = $conn->prepare("
                INSERT INTO payroll_history (staff_id, period_start, period_end, hours_worked, hourly_rate, status, payment_date, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $paymentDateValue = $paymentDate ?: null;
            $notesValue = $notes ?: null;
            $stmt->bind_param('issddsss', $staffId, $periodStart, $periodEnd, $hoursWorked, $hourlyRate, 
                $status, $paymentDateValue, $notesValue);
            $stmt->execute();
            $stmt->close();
            
            echo json_encode(['success' => true, 'message' => 'Payroll record added successfully']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;
        
    case 'mark_payroll_paid':
        $recordId = $_POST['record_id'] ?? '';
        
        if (empty($recordId)) {
            echo json_encode(['success' => false, 'message' => 'Record ID is required']);
            exit;
        }
        
        try {
            $stmt = $conn->prepare("
                UPDATE payroll_history 
                SET status = 'paid', payment_date = CURDATE()
                WHERE id = ?
            ");
            $stmt->bind_param('i', $recordId);
            $stmt->execute();
            $stmt->close();
            
            echo json_encode(['success' => true, 'message' => 'Payroll record marked as paid successfully']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;
        
    case 'generate_daily_payroll':
        // Generate daily payroll history for all staff
        require_once '../utilities/daily_payroll_helper.php';
        
        $processDate = $_POST['date'] ?? $_GET['date'] ?? date('Y-m-d', strtotime('-1 day'));
        
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $processDate)) {
            echo json_encode(['success' => false, 'message' => 'Invalid date format. Use YYYY-MM-DD format.']);
            exit;
        }
        
        try {
            // Get all staff members
            $stmt = $conn->prepare("SELECT id, staff_id, first_name, last_name FROM staff ORDER BY first_name, last_name");
            $stmt->execute();
            $result = $stmt->get_result();
            $staff_members = [];
            while ($row = $result->fetch_assoc()) {
                $staff_members[] = $row;
            }
            $stmt->close();
            
            $totalProcessed = 0;
            $totalCreated = 0;
            $totalUpdated = 0;
            $totalSkipped = 0;
            $errors = [];
            
            foreach ($staff_members as $staff) {
                $result = createOrUpdateDailyPayroll($staff['id'], $processDate, $conn);
                $totalProcessed++;
                
                if ($result['success']) {
                    if (isset($result['action'])) {
                        if ($result['action'] === 'created') {
                            $totalCreated++;
                        } elseif ($result['action'] === 'updated') {
                            $totalUpdated++;
                        } else {
                            $totalSkipped++;
                        }
                    }
                } else {
                    $errors[] = $staff['first_name'] . ' ' . $staff['last_name'] . ': ' . $result['message'];
                }
            }
            
            $message = "Daily payroll generation completed for " . date('F d, Y', strtotime($processDate)) . ". ";
            $message .= "Processed: $totalProcessed, Created: $totalCreated, Updated: $totalUpdated, Skipped: $totalSkipped";
            
            if (!empty($errors)) {
                $message .= ". Errors: " . count($errors);
            }
            
            echo json_encode([
                'success' => true, 
                'message' => $message,
                'stats' => [
                    'processed' => $totalProcessed,
                    'created' => $totalCreated,
                    'updated' => $totalUpdated,
                    'skipped' => $totalSkipped,
                    'errors' => count($errors)
                ],
                'errors' => $errors
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;
        
    case 'export_payroll_history':
        $month = $_GET['month'] ?? date('Y-m');
        $status = $_GET['status'] ?? '';
        $staffId = $_GET['staff_id'] ?? '';
        
        try {
            // Build query with filters
            $whereConditions = [];
            $params = [];
            $paramTypes = '';
            
            if ($month) {
                $whereConditions[] = "DATE_FORMAT(ph.period_start, '%Y-%m') = ?";
                $params[] = $month;
                $paramTypes .= 's';
            }
            
            if ($status) {
                $whereConditions[] = "ph.status = ?";
                $params[] = $status;
                $paramTypes .= 's';
            }
            
            if ($staffId) {
                $whereConditions[] = "s.staff_id = ?";
                $params[] = $staffId;
                $paramTypes .= 's';
            }
            
            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            
            $query = "
                SELECT 
                    s.staff_id as staff_code,
                    s.first_name,
                    s.last_name,
                    ph.period_start,
                    ph.period_end,
                    ph.hours_worked,
                    ph.hourly_rate,
                    ph.status,
                    ph.payment_date,
                    ph.notes,
                    (ph.hours_worked * ph.hourly_rate) as total_pay
                FROM payroll_history ph
                LEFT JOIN staff s ON ph.staff_id = s.id
                {$whereClause}
                ORDER BY ph.period_start DESC, s.first_name, s.last_name
            ";
            
            $stmt = $conn->prepare($query);
            if (!empty($params)) {
                $stmt->bind_param($paramTypes, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $payrollHistory = [];
            while ($row = $result->fetch_assoc()) {
                $payrollHistory[] = $row;
            }
            $stmt->close();
            
            // Set headers for CSV download
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="payroll_history_' . $month . '.csv"');
            
            $output = fopen('php://output', 'w');
            
            // CSV headers
            fputcsv($output, [
                'Staff ID', 'Name', 'Period Start', 'Period End', 
                'Hours Worked', 'Hourly Rate', 'Total Pay', 'Status', 
                'Payment Date', 'Notes'
            ]);
            
            // CSV data
            foreach ($payrollHistory as $record) {
                fputcsv($output, [
                    $record['staff_code'],
                    $record['first_name'] . ' ' . $record['last_name'],
                    $record['period_start'],
                    $record['period_end'],
                    $record['hours_worked'],
                    '₱' . number_format($record['hourly_rate'], 2),
                    '₱' . number_format($record['total_pay'], 2),
                    ucfirst($record['status']),
                    $record['payment_date'] ?: '-',
                    $record['notes'] ?: '-'
                ]);
            }
            
            fclose($output);
            exit;
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;
        
    case 'get_staff_status':
        // Get staff time status for real-time updates
        $staffId = $_GET['staff_id'] ?? '';
        if (empty($staffId)) {
            echo json_encode(['success' => false, 'message' => 'Staff ID is required']);
            exit;
        }
        
        try {
            // Get today's date from database to ensure consistency
            $todayResult = $conn->query("SELECT CURDATE() as today");
            $today = $todayResult ? $todayResult->fetch_assoc()['today'] : date('Y-m-d');
            
            // Get staff internal ID first
            $stmt = $conn->prepare("SELECT id FROM staff WHERE staff_id = ?");
            $stmt->bind_param('s', $staffId);
            $stmt->execute();
            $result = $stmt->get_result();
            $staff = $result->fetch_assoc();
            $stmt->close();
            
            if (!$staff) {
                echo json_encode([
                    'success' => true,
                    'status' => [
                        'class' => 'status-out',
                        'icon' => 'fa-circle',
                        'text' => 'Not In',
                        'time' => '--:--'
                    ]
                ]);
                exit;
            }
            
            $internalStaffId = $staff['id'];
            
            // Get today's attendance record - check for records with time_out IS NULL first (currently in)
            $stmt = $conn->prepare("
                SELECT time_in, time_out 
                FROM staff_attendance 
                WHERE staff_id = ? 
                AND date = ? 
                ORDER BY 
                    CASE WHEN time_out IS NULL THEN 0 ELSE 1 END,
                    time_in DESC 
                LIMIT 1
            ");
            $stmt->bind_param('is', $internalStaffId, $today);
            $stmt->execute();
            $result = $stmt->get_result();
            $attendance = $result->fetch_assoc();
            $stmt->close();
            
            if (!$attendance) {
                echo json_encode([
                    'success' => true,
                    'status' => [
                        'class' => 'status-out',
                        'icon' => 'fa-circle',
                        'text' => 'Not In',
                        'time' => '--:--'
                    ]
                ]);
                exit;
            }
            
            if ($attendance['time_out']) {
                echo json_encode([
                    'success' => true,
                    'status' => [
                        'class' => 'status-out',
                        'icon' => 'fa-check-circle',
                        'text' => 'Out',
                        'time' => date('H:i', strtotime($attendance['time_out']))
                    ]
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'status' => [
                        'class' => 'status-in',
                        'icon' => 'fa-play-circle',
                        'text' => 'In',
                        'time' => date('H:i', strtotime($attendance['time_in']))
                    ]
                ]);
            }
        } catch (Exception $e) {
            error_log("Error in get_staff_status: " . $e->getMessage());
            echo json_encode([
                'success' => true,
                'status' => [
                    'class' => 'status-out',
                    'icon' => 'fa-circle',
                    'text' => 'Not In',
                    'time' => '--:--'
                ]
            ]);
        }
        break;
        
    case 'get_all_staff_status':
        // Get all staff time statuses for batch updates
        try {
            // Get today's date from database
            $todayResult = $conn->query("SELECT CURDATE() as today");
            $today = $todayResult ? $todayResult->fetch_assoc()['today'] : date('Y-m-d');
            
            // Get all staff IDs first
            $allStaffStmt = $conn->query("SELECT id, staff_id FROM staff");
            $allStaff = [];
            while ($row = $allStaffStmt->fetch_assoc()) {
                $allStaff[$row['staff_id']] = $row['id'];
            }
            $allStaffStmt->close();
            
            $statuses = [];
            
            // Get attendance for each staff
            foreach ($allStaff as $staffId => $internalId) {
                $stmt = $conn->prepare("
                    SELECT time_in, time_out 
                    FROM staff_attendance 
                    WHERE staff_id = ? 
                    AND date = ? 
                    ORDER BY 
                        CASE WHEN time_out IS NULL THEN 0 ELSE 1 END,
                        time_in DESC 
                    LIMIT 1
                ");
                $stmt->bind_param('is', $internalId, $today);
                $stmt->execute();
                $result = $stmt->get_result();
                $attendance = $result->fetch_assoc();
                $stmt->close();
                
                if ($attendance && $attendance['time_out']) {
                    $statuses[$staffId] = [
                        'class' => 'status-out',
                        'icon' => 'fa-check-circle',
                        'text' => 'Out',
                        'time' => date('H:i', strtotime($attendance['time_out']))
                    ];
                } elseif ($attendance && $attendance['time_in']) {
                    $statuses[$staffId] = [
                        'class' => 'status-in',
                        'icon' => 'fa-play-circle',
                        'text' => 'In',
                        'time' => date('H:i', strtotime($attendance['time_in']))
                    ];
                } else {
                    $statuses[$staffId] = [
                        'class' => 'status-out',
                        'icon' => 'fa-circle',
                        'text' => 'Not In',
                        'time' => '--:--'
                    ];
                }
            }
            
            echo json_encode(['success' => true, 'statuses' => $statuses]);
        } catch (Exception $e) {
            error_log("Error in get_all_staff_status: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error fetching statuses']);
        }
        break;
        
    case 'delete':
        $staffId = $_POST['staff_id'] ?? '';
        if (empty($staffId)) {
            echo json_encode(['success' => false, 'message' => 'Staff ID is required']);
            exit;
        }
        
        try {
            // Start transaction
            $conn->begin_transaction();
            
            // Get staff internal ID
            $stmt = $conn->prepare("SELECT id FROM staff WHERE staff_id = ?");
            $stmt->bind_param('s', $staffId);
            $stmt->execute();
            $result = $stmt->get_result();
            $staff = $result->fetch_assoc();
            $stmt->close();
            
            if (!$staff) {
                throw new Exception('Staff not found');
            }
            
            $internalId = $staff['id'];
            
            // Delete from payroll table first (due to foreign key constraint)
            $stmt = $conn->prepare("DELETE FROM payroll WHERE staff_id = ?");
            $stmt->bind_param('i', $internalId);
            $stmt->execute();
            $stmt->close();
            
            // Delete from staff_attendance_archive
            $stmt = $conn->prepare("DELETE FROM staff_attendance_archive WHERE staff_id = ?");
            $stmt->bind_param('i', $internalId);
            $stmt->execute();
            $stmt->close();
            
            // Delete from staff_attendance
            $stmt = $conn->prepare("DELETE FROM staff_attendance WHERE staff_id = ?");
            $stmt->bind_param('i', $internalId);
            $stmt->execute();
            $stmt->close();
            
            // Finally delete from staff table
            $stmt = $conn->prepare("DELETE FROM staff WHERE id = ?");
            $stmt->bind_param('i', $internalId);
            $stmt->execute();
            $stmt->close();
            
            // Commit transaction
            $conn->commit();
            
            echo json_encode(['success' => true, 'message' => 'Staff deleted successfully']);
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            error_log("Error deleting staff: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to delete staff: ' . $e->getMessage()]);
        }
        break;

    case 'assign_trainer':
        $memberId = $_POST['member_id'] ?? '';
        $trainerId = $_POST['trainer_id'] ?? '';

        if (empty($memberId) || empty($trainerId)) {
            echo json_encode(['success' => false, 'message' => 'Member ID and Trainer ID are required']);
            exit;
        }

        try {
            // Start transaction
            $conn->begin_transaction();

            // Verify trainer exists
            $trainerCheck = $conn->prepare("SELECT id, first_name, last_name FROM staff WHERE id = ?");
            $trainerCheck->bind_param('i', $trainerId);
            $trainerCheck->execute();
            $trainerResult = $trainerCheck->get_result();
            $trainer = $trainerResult->fetch_assoc();
            $trainerCheck->close();

            if (!$trainer) {
                throw new Exception('Selected staff member not found');
            }

            // Verify member exists and is not expired
            $memberCheck = $conn->prepare("SELECT id, member_id, first_name, last_name FROM members WHERE id = ? AND (expired_date IS NULL OR expired_date >= CURDATE())");
            $memberCheck->bind_param('i', $memberId);
            $memberCheck->execute();
            $memberResult = $memberCheck->get_result();
            $member = $memberResult->fetch_assoc();
            $memberCheck->close();

            if (!$member) {
                throw new Exception('Member not found or membership has expired');
            }

            // Get the next upcoming session date (tomorrow as default)
            $nextSessionDate = date('Y-m-d', strtotime('+1 day'));
            
            // Update all unassigned sessions for this member
            $updateStmt = $conn->prepare("UPDATE training_sessions SET trainer_id = ? WHERE member_id = ? AND (trainer_id IS NULL OR trainer_id = 0 OR trainer_id = '')");
            $updateStmt->bind_param('ii', $trainerId, $memberId);
            $updateStmt->execute();
            $affectedRows = $updateStmt->affected_rows;
            $updateStmt->close();

            // Set the assigned trainer in members table
            $memberUpdateStmt = $conn->prepare("UPDATE members SET trainer_id = ?, with_trainees = 'with' WHERE id = ?");
            $memberUpdateStmt->bind_param('ii', $trainerId, $memberId);
            $memberUpdateStmt->execute();
            $memberUpdateStmt->close();

            // If no existing sessions were updated, create a new one
            if ($affectedRows == 0) {
                // First, get a default time (e.g., 10:00 AM)
                $sessionTime = '10:00:00';
                
                $insertStmt = $conn->prepare("INSERT INTO training_sessions (member_id, trainer_id, session_date, session_time, status, created_at) VALUES (?, ?, ?, ?, 'booked', NOW())");
                $insertStmt->bind_param('iiss', $memberId, $trainerId, $nextSessionDate, $sessionTime);
                $insertStmt->execute();
                $affectedRows = 1; // Count the new session
                $insertStmt->close();
            }
            // Commit transaction
            $conn->commit();

            // Return JSON response
            echo json_encode([
                'success' => true,
                'message' => "Trainer {$trainer['first_name']} {$trainer['last_name']} has been successfully assigned to member {$member['first_name']} {$member['last_name']}",
                'trainer_name' => $trainer['first_name'] . ' ' . $trainer['last_name'],
                'member_name' => $member['first_name'] . ' ' . $member['last_name'],
                'member_id' => $member['member_id']
            ]);

        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            error_log("Error assigning trainer: " . $e->getMessage());
            
            echo json_encode(['success' => false, 'message' => 'An error occurred while assigning the trainer: ' . $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>