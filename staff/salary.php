<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set timezone to match the QR scanning system
date_default_timezone_set('Asia/Manila');

// Check if logged in as staff
if (!isset($_SESSION['staff_logged_in']) || !$_SESSION['staff_logged_in']) {
    header('Location: login.php');
    exit;
}

$page_title = "Salary Information";
include 'components/header.php';
include '../includes/db.php';
include '../includes/functions.php';

// Helper functions for auto-timeout calculations
function getAutoTimeoutTime($staff, $payroll, $date, $time_in) {
    $schedule = strtolower($staff['schedule'] ?? '');
    $employment_type = strtolower($payroll['employment_type'] ?? '');
    
    // Determine timeout time based on employment type and schedule
    if ($employment_type === 'half day' && $schedule) {
        if ($schedule === 'morning') {
            $timeout_time = $date . ' 12:30:00'; // 12:00 PM shift end + 30 min grace
        } elseif ($schedule === 'afternoon') {
            $timeout_time = $date . ' 18:30:00'; // 6:00 PM shift end + 30 min grace
        } else {
            return null; // Unknown schedule
        }
    } elseif ($employment_type === 'wholeday' && $schedule) {
        if ($schedule === 'morning') {
            $timeout_time = $date . ' 12:30:00'; // 12:00 PM shift end + 30 min grace
        } elseif ($schedule === 'afternoon') {
            $timeout_time = $date . ' 18:30:00'; // 6:00 PM shift end + 30 min grace
        } elseif ($schedule === 'night') {
            $timeout_time = $date . ' 08:30:00'; // 8:00 AM shift end + 30 min grace
        } else {
            return null; // Unknown schedule
        }
    } elseif ($employment_type === 'wholeday') {
        // Full day staff without specific schedule - assume until 6:30 PM
        $timeout_time = $date . ' 18:30:00';
    } else {
        return null; // Other employment types - no auto timeout
    }
    
    // Only apply auto-timeout if current time is past the timeout time
    $current_datetime = date('Y-m-d H:i:s');
    if ($current_datetime >= $timeout_time) {
        return $timeout_time;
    }
    
    return null; // Still within grace period
}

function calculateHoursWorked($time_in, $time_out) {
    $start = strtotime($time_in);
    $end = strtotime($time_out);
    
    if ($end <= $start) {
        return 0;
    }
    
    $hours = ($end - $start) / 3600; // Convert seconds to hours
    return round($hours, 2);
}

// Get current staff ID from session
$staff_db_id = $_SESSION['staff_id'] ?? null;
$currentStaffId = $staff_db_id;

if (!$staff_db_id) {
    die("Staff member not found - please log in again");
}

// Get staff info for display
$staff_sql = "SELECT first_name, last_name, staff_id, photo FROM staff WHERE id = ?";
$staff_stmt = $conn->prepare($staff_sql);
if ($staff_stmt) {
    $staff_stmt->bind_param('i', $staff_db_id);
    $staff_stmt->execute();
    $staff_info = $staff_stmt->get_result()->fetch_assoc();
    $staff_stmt->close();
} else {
    error_log("Database error in staff info: " . $conn->error);
    $staff_info = null;
}

try {
    // Get staff information
    $stmt = $conn->prepare("SELECT * FROM staff WHERE id = ?");
    $stmt->bind_param('i', $staff_db_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $staff = $result->fetch_assoc();
    $stmt->close();
    
    // Get the staff_id string for display
    $staff_id = $staff['staff_id'] ?? null;
    
    if (!$staff) {
        throw new Exception("Staff member not found");
    }
    
    // Get payroll information
    $stmt = $conn->prepare("SELECT * FROM payroll WHERE staff_id = ?");
    $stmt->bind_param('i', $staff_db_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $payroll = $result->fetch_assoc();
    $stmt->close();
    
    // Determine daily hours cap based on employment type
    $employment_type = strtolower($payroll['employment_type'] ?? '');
    $schedule = strtolower($staff['schedule'] ?? '');
    $daily_hours_cap = 8.0; // Default
    
    if ($employment_type === 'half day' || $employment_type === 'half-day' || 
        $schedule === 'morning' || $schedule === 'afternoon') {
        $daily_hours_cap = 4.0;
    }
    
    // Get real-time attendance data for current month
    $current_month = date('Y-m');
    $stmt = $conn->prepare("
        SELECT
            sa.date,
            sa.time_in,
            sa.time_out
        FROM staff_attendance sa
        WHERE sa.staff_id = ?
        AND DATE_FORMAT(sa.date, '%Y-%m') = ?
        ORDER BY sa.date DESC
    ");
    $stmt->bind_param('is', $staff_db_id, $current_month);
    $stmt->execute();
    $result = $stmt->get_result();
    $attendance_history = [];
    while ($row = $result->fetch_assoc()) {
        // Calculate hours worked properly with timezone handling
        $timezone = new DateTimeZone('Asia/Manila');
        $time_in = new DateTime($row['time_in'], $timezone);
        $time_out = $row['time_out'] ? new DateTime($row['time_out'], $timezone) : new DateTime('now', $timezone);
        $hours_worked = 0;

        if ($time_out > $time_in) {
            $interval = $time_in->diff($time_out);
            $hours_worked = $interval->h + ($interval->i / 60) + ($interval->s / 3600);
        }

        $row['hours_worked'] = round($hours_worked, 2);
        $attendance_history[] = $row;
    }
    $stmt->close();
    
    // Get attendance data for last 30 days
    $stmt = $conn->prepare("
        SELECT
            sa.date,
            sa.time_in,
            sa.time_out
        FROM staff_attendance sa
        WHERE sa.staff_id = ?
        AND sa.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ORDER BY sa.date DESC
    ");
    $stmt->bind_param('i', $staff_db_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $attendance_30_days = [];
    while ($row = $result->fetch_assoc()) {
        // Calculate hours worked properly with timezone handling
        $timezone = new DateTimeZone('Asia/Manila');
        $time_in = new DateTime($row['time_in'], $timezone);
        $time_out = $row['time_out'] ? new DateTime($row['time_out'], $timezone) : new DateTime('now', $timezone);
        $hours_worked = 0;

        if ($time_out > $time_in) {
            $interval = $time_in->diff($time_out);
            $hours_worked = $interval->h + ($interval->i / 60) + ($interval->s / 3600);
        }

        $row['hours_worked'] = round($hours_worked, 2);
        $attendance_30_days[] = $row;
    }
    $stmt->close();
    
    // Calculate total hours worked in the last 30 days with daily hours cap
    $total_hours = 0;
    $daily_earnings = [];
    $hourly_rate = 62.50; // Fixed hourly rate as per payroll information
    
    if ($payroll && $payroll['salary']) {
        // Use the fixed hourly rate for calculations
        $hourly_rate = 62.50;
    }
    
    // Group attendance by date to handle multiple entries per day
    $daily_attendance = [];
    foreach ($attendance_30_days as $record) {
        $date = $record['date'];
        $time_out = $record['time_out'];
        
        // If no time_out, check if staff should be auto-timed out
        if (!$time_out) {
            $time_out = getAutoTimeoutTime($staff, $payroll, $date, $record['time_in']);
        }
        
        if ($time_out) {
            if (!isset($daily_attendance[$date])) {
                $daily_attendance[$date] = [
                    'date' => $date,
                    'total_hours' => 0,
                    'time_in' => $record['time_in'],
                    'time_out' => $time_out
                ];
            }
            // Recalculate hours with auto-timeout time
            $hours = calculateHoursWorked($record['time_in'], $time_out);
            $daily_attendance[$date]['total_hours'] += $hours;
            // Keep the latest time_out
            if (strtotime($time_out) > strtotime($daily_attendance[$date]['time_out'])) {
                $daily_attendance[$date]['time_out'] = $time_out;
            }
        }
    }
    
    // Apply daily hours cap and calculate earnings
    foreach ($daily_attendance as $date => $day_data) {
        $hours = min($day_data['total_hours'], $daily_hours_cap); // Cap at daily hours limit per day
        $total_hours += $hours;
        
        // Calculate daily earnings using helper function
        $daily_pay = calculateStaffDailyPayroll($hours);
        
        // Calculate daily earnings
        $daily_earnings[] = [
            'date' => $day_data['date'],
            'hours' => $hours,
            'earnings' => $daily_pay,
            'time_in' => $day_data['time_in'],
            'time_out' => $day_data['time_out']
        ];
    }
    
    // Calculate total earnings for the month based on actual attendance
    $total_earnings = 0.0;
    foreach ($daily_earnings as $earning) {
        $total_earnings += $earning['earnings'];
    }
    $total_earnings = round($total_earnings, 2);
    
    // Calculate current month's hours from attendance with daily hours cap
    $current_month_hours = 0;
    $monthly_daily_grouped = [];
    foreach ($attendance_history as $record) {
        $date = $record['date'];
        $time_out = $record['time_out'];
        
        // If no time_out, check if staff should be auto-timed out based on schedule
        if (!$time_out) {
            $time_out = getAutoTimeoutTime($staff, $payroll, $date, $record['time_in']);
        }
        
        if ($time_out) {
            if (!isset($monthly_daily_grouped[$date])) {
                $monthly_daily_grouped[$date] = 0;
            }
            // Recalculate hours with auto-timeout time
            $hours = calculateHoursWorked($record['time_in'], $time_out);
            $monthly_daily_grouped[$date] += $hours;
        }
    }
    
    // Apply daily hours cap per day and calculate current month earnings
    $current_month_earnings = 0.0;
    $current_month_trainer_earnings = 0.0;
    foreach ($monthly_daily_grouped as $date => $day_hours) {
        $capped_hours = min($day_hours, $daily_hours_cap);
        $current_month_hours += $capped_hours;
        $current_month_earnings += calculateStaffDailyPayroll($capped_hours);
        
        // Add trainer earnings for this date
        $trainer_stmt = $conn->prepare("
            SELECT COUNT(CASE WHEN ts.status = 'completed' THEN 1 END) as completed_sessions
            FROM training_sessions ts
            INNER JOIN members m ON ts.member_id = m.id
            WHERE ts.trainer_id = ? AND DATE(ts.session_date) = ? AND m.with_trainees = 'with'
        ");
        $trainer_stmt->bind_param('is', $staff_db_id, $date);
        $trainer_stmt->execute();
        $trainer_result = $trainer_stmt->get_result();
        $trainer_data = $trainer_result->fetch_assoc();
        $trainer_earnings_for_date = ($trainer_data['completed_sessions'] ?? 0) * 100.00;
        $current_month_trainer_earnings += $trainer_earnings_for_date;
        $trainer_stmt->close();
    }
    
    // Add trainer earnings to current month earnings
    $current_month_earnings += $current_month_trainer_earnings;
    
    // Calculate current month's expected salary
    $current_month_expected = $payroll ? $payroll['salary'] : 0;
    
    // Get individual attendance records for salary calculation with daily grouping
    $stmt = $conn->prepare("
        SELECT
            sa.date,
            sa.time_in,
            sa.time_out,
            CASE
                WHEN sa.time_out IS NOT NULL THEN 'Completed'
                ELSE 'In Progress'
            END as status
        FROM staff_attendance sa
        WHERE sa.staff_id = ?
        AND sa.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ORDER BY sa.date DESC, sa.time_in DESC
    ");
    $stmt->bind_param('i', $staff_db_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $raw_attendance = [];
    while ($row = $result->fetch_assoc()) {
        // Calculate hours worked properly with timezone handling
        $timezone = new DateTimeZone('Asia/Manila');
        $time_in = new DateTime($row['time_in'], $timezone);
        $time_out = $row['time_out'] ? new DateTime($row['time_out'], $timezone) : new DateTime('now', $timezone);
        $hours_worked = 0;

        if ($time_out > $time_in) {
            $interval = $time_in->diff($time_out);
            $hours_worked = $interval->h + ($interval->i / 60) + ($interval->s / 3600);
        }

        $row['hours_worked'] = round($hours_worked, 2);
        $raw_attendance[] = $row;
    }
    $stmt->close();
    
    // Group by date and apply daily hours cap
    $daily_grouped = [];
    foreach ($raw_attendance as $record) {
        $date = $record['date'];
        $time_out = $record['time_out'];
        $status = $record['status'];
        
        // If no time_out, check if staff should be auto-timed out
        if (!$time_out) {
            $time_out = getAutoTimeoutTime($staff, $payroll, $date, $record['time_in']);
            if ($time_out) {
                $status = 'Auto-timed out';
            }
        }
        
        // Always include the record, even if no time_out (for current attendance)
        if (!isset($daily_grouped[$date])) {
            $daily_grouped[$date] = [
                'date' => $date,
                'total_hours' => 0,
                'time_in' => $record['time_in'],
                'time_out' => $time_out,
                'status' => $status ?: 'In Progress'
            ];
        }
        
        // Calculate hours for this record (use current time if no time_out)
        $hours = calculateHoursWorked($record['time_in'], $time_out ?: date('Y-m-d H:i:s'));
        $daily_grouped[$date]['total_hours'] += $hours;
        
        // Keep the latest time_out if it exists
        if ($time_out && strtotime($time_out) > strtotime($daily_grouped[$date]['time_out'] ?: '1970-01-01 00:00:00')) {
            $daily_grouped[$date]['time_out'] = $time_out;
            $daily_grouped[$date]['status'] = $status;
        }
    }
    
    // Apply daily hours cap and calculate earnings
    $attendance_records = [];
    foreach ($daily_grouped as $date => $day_data) {
        $hours = min($day_data['total_hours'], $daily_hours_cap); // Cap at daily hours limit per day
        $daily_pay = calculateStaffDailyPayroll($hours);
        
        // Calculate trainer earnings for this date (only for today)
        $trainer_earnings = 0;
        if ($date === date('Y-m-d')) {
            // Only add trainer earnings for today's attendance
            $trainer_stmt = $conn->prepare("
                SELECT COUNT(CASE WHEN status IN ('completed') THEN 1 END) as conducted_sessions
                FROM training_sessions
                WHERE trainer_id = ? AND DATE(session_date) = CURDATE()
            ");
            $trainer_stmt->bind_param('i', $staff_db_id);
            $trainer_stmt->execute();
            $trainer_result = $trainer_stmt->get_result();
            $trainer_data = $trainer_result->fetch_assoc();
            $trainer_earnings = ($trainer_data['conducted_sessions'] ?? 0) * 100.00;
            $trainer_stmt->close();
        }
        
        $attendance_records[] = [
            'date' => $day_data['date'],
            'time_in' => $day_data['time_in'],
            'time_out' => $day_data['time_out'],
            'hours_worked' => $hours,
            'daily_earnings' => $daily_pay,
            'trainer_earnings' => $trainer_earnings,
            'status' => $day_data['status']
        ];
    }
    
    // Get payroll history from payroll_history table for summary
    $stmt = $conn->prepare("
        SELECT 
            period_start,
            period_end,
            hours_worked,
            hourly_rate,
            status,
            payment_date
        FROM payroll_history 
        WHERE staff_id = ? 
        ORDER BY period_start DESC 
        LIMIT 12
    ");
    $stmt->bind_param('i', $staff_db_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $payroll_history = [];
    while ($row = $result->fetch_assoc()) {
        $payroll_history[] = $row;
    }
    $stmt->close();
    
    // Calculate summary statistics from payroll history
    $total_hours_history = 0;
    $total_net_history = 0;
    $paid_records = 0;
    
    foreach ($payroll_history as $record) {
        $total_hours_history += $record['hours_worked'];
        $calculated_net_pay = $record['hours_worked'] * $record['hourly_rate'];
        $total_net_history += $calculated_net_pay;
        if ($record['status'] === 'paid') {
            $paid_records++;
        }
    }
    
    $average_monthly = count($payroll_history) > 0 ? $total_net_history / count($payroll_history) : 0;
    $payment_rate = count($payroll_history) > 0 ? ($paid_records / count($payroll_history)) * 100 : 0;
    
    // Get trainer earnings from training sessions (only if trainer attended work today)
    $trainer_stats = ['total_sessions' => 0, 'completed_sessions' => 0, 'booked_sessions' => 0]; // Initialize with defaults

    // First check if staff attended today
    $staff_attended_today = false;
    $attendance_check = $conn->prepare("SELECT id FROM staff_attendance WHERE staff_id = ? AND date = CURDATE() AND time_in IS NOT NULL");
    $attendance_check->bind_param('i', $staff_db_id);
    $attendance_check->execute();
    $attendance_result = $attendance_check->get_result();
    if ($attendance_result->num_rows > 0) {
        $staff_attended_today = true;
    }
    $attendance_check->close();

    if ($staff_attended_today) {
        // Only calculate earnings if staff attended today
        $stmt = $conn->prepare("
            SELECT
                COUNT(*) as total_sessions,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_sessions,
                COUNT(CASE WHEN status = 'booked' THEN 1 END) as booked_sessions
            FROM training_sessions
            WHERE trainer_id = ?
            AND session_date IS NOT NULL
            AND DATE(session_date) = CURDATE()
        ");
        $stmt->bind_param('i', $staff_db_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats_result = $result->fetch_assoc();
        if ($stats_result) {
            $trainer_stats = $stats_result;
        }
        $stmt->close();
    } else {
        // Staff didn't attend today - no earnings
        $trainer_stats = ['total_sessions' => 0, 'completed_sessions' => 0, 'booked_sessions' => 0];
    }
    
    // Calculate trainer earnings (₱100 per completed training session)
    $trainer_earnings_per_session = 100.00;
    $trainer_daily_earnings = $trainer_stats['completed_sessions'] * $trainer_earnings_per_session;
    
    // Get trainer sessions for today (only if staff attended)
    $trainer_sessions_30_days = []; // Initialize as empty array
    if ($staff_attended_today) {
        $stmt = $conn->prepare("
            SELECT
                session_date,
                status,
                notes
            FROM training_sessions
            WHERE trainer_id = ?
            AND session_date IS NOT NULL
            AND DATE(session_date) = CURDATE()
            ORDER BY session_date DESC
        ");
        $stmt->bind_param('i', $staff_db_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            // Additional check: ensure session_date is not null and is a valid date
            if (!empty($row['session_date']) && strtotime($row['session_date']) > 0) {
                $trainer_sessions_30_days[] = $row;
            }
        }
        $stmt->close();
    }
    
} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}
?>

<!-- Page-specific CSS -->
<link rel="stylesheet" href="../assets/css/staff/salary.css">

<?php if (isset($error)): ?>
    <div class="alert alert-danger" role="alert">
        <?= htmlspecialchars($error) ?>
    </div>
<?php else: ?>

<!-- Staff Profile Card - Admin Style -->
<div class="staff-profile-card">
    <div class="profile-content">
        <div class="profile-left">
            <div class="profile-avatar">
                <?php 
                $profileImage = '';
                if (!empty($staff_info['photo'])) {
                    $photoPath = '../uploads/staff_photos/' . $staff_info['photo'];
                    if (file_exists($photoPath)) {
                        $profileImage = $photoPath;
                    }
                }
                
                if (empty($profileImage)) {
                    $firstName = $staff_info['first_name'] ?? '';
                    $lastName = $staff_info['last_name'] ?? '';
                    $profileImage = 'https://ui-avatars.com/api/?name=' . urlencode($firstName . '+' . $lastName) . '&background=3b82f6&color=fff&size=200&font-size=0.4';
                }
                ?>
                <img src="<?= htmlspecialchars($profileImage) ?>" 
                     alt="Profile Picture" 
                     class="profile-avatar-img"
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <div class="profile-avatar-fallback" style="display: none;">
                    <i class="fas fa-user-tie"></i>
                </div>
            </div>
            <div class="profile-info">
                <h4 class="staff-name">
                    <?= htmlspecialchars($staff_info['first_name'] . ' ' . $staff_info['last_name']) ?>
                </h4>
                <div class="staff-details">
                    <span class="staff-code">
                        <i class="fas fa-id-card"></i>
                        Staff ID: <?= htmlspecialchars($staff_info['staff_id']) ?>
                    </span>
                    <span class="staff-position">
                        <i class="fas fa-briefcase"></i>
                        Staff Member
                    </span>
                </div>
            </div>
        </div>
        <div class="profile-right">
            <div class="salary-summary">
                <div class="summary-item">
                    <span class="summary-label">Current Month Earnings</span>
                    <span class="summary-value">₱<?= number_format($current_month_earnings, 2) ?></span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Hours Worked</span>
                    <span class="summary-value"><?= number_format($current_month_hours, 1) ?>h</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Salary Overview Cards -->
<div class="salary-overview-section">
    <div class="overview-cards">
        <div class="salary-card primary-card">
            <div class="card-content">
                <div class="card-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="card-info">
                    <h3 class="card-title">Daily Rate</h3>
                    <h2 class="card-value">₱<?php
                        $schedule = $staff['schedule'] ?? '';
                        $employmentType = strtolower($payroll['employment_type'] ?? '');

                        // Fixed daily rates: half-day ~ ₱250, whole-day ~ ₱500
                        if ($employmentType === 'wholeday' || $employmentType === 'whole day' || $employmentType === 'full-time') {
                            echo number_format(500.00, 2);
                        } elseif ($schedule === 'morning' || $schedule === 'afternoon' || $employmentType === 'half day' || $employmentType === 'half-day') {
                            echo number_format(250.00, 2);
                        } else {
                            // Fallback: if payroll salary is stored as hourly, compute 8-hour equivalent
                            $salaryVal = isset($payroll['salary']) ? floatval($payroll['salary']) : 62.5;
                            if ($salaryVal <= 100) {
                                // treat as hourly rate
                                echo number_format($salaryVal * 8, 2);
                            } else {
                                // treat as daily amount already
                                echo number_format($salaryVal, 2);
                            }
                        }
                    ?></h2>
                </div>
            </div>
        </div>
        
        <div class="salary-card success-card">
            <div class="card-content">
                <div class="card-icon">
                    <i class="fas fa-briefcase"></i>
                </div>
                <div class="card-info">
                    <h3 class="card-title">Employment Type</h3>
                    <h2 class="card-value">
                        <?php
                        $employmentType = $payroll['employment_type'] ?? 'Not specified';
                        $schedule = $staff['schedule'] ?? '';
                        
                        if (strtolower($employmentType) === 'half day' || strtolower($employmentType) === 'half-day') {
                            $scheduleText = ucfirst($schedule);
                            if (!empty($scheduleText)) {
                                echo "Half Day ($scheduleText)";
                            } else {
                                echo "Half Day";
                            }
                        } else {
                            echo ucfirst($employmentType);
                        }
                        ?>
                    </h2>
                </div>
            </div>
        </div>
        
        <div class="salary-card info-card">
            <div class="card-content">
                <div class="card-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="card-info">
                    <h3 class="card-title">Hours This Month</h3>
                    <h2 class="card-value"><?= number_format($current_month_hours, 1) ?>h</h2>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Payment Breakdown Section -->
<div class="payment-breakdown-section">
    <div class="breakdown-cards">
        <div class="breakdown-card success-card">
            <div class="card-content">
                <div class="card-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="card-info">
                    <h3 class="card-title">Earned This Month</h3>
                    <h2 class="card-value">₱<?= number_format(array_sum(array_column($attendance_records, 'daily_earnings')) + array_sum(array_column($attendance_records, 'trainer_earnings')), 2) ?></h2>
                    <div class="earnings-breakdown" style="margin-top: 15px;">
                        <button class="btn btn-sm btn-outline-light" type="button" data-bs-toggle="collapse" data-bs-target="#earningsDetails" aria-expanded="false" aria-controls="earningsDetails">
                            <i class="fas fa-eye"></i> View Details
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Earnings Details Collapse -->
    <div class="collapse mt-3" id="earningsDetails">
        <div class="card" style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px;">
            <div class="card-body">
                <h5 class="card-title text-white mb-3">
                    <i class="fas fa-list-ul me-2"></i>Earnings Breakdown
                </h5>

                <?php
                // Use the attendance_records data directly (already processed for last 30 days)
                $daily_earnings_breakdown = $attendance_records;
                
                // Sort by date descending (already sorted, but ensure it)
                usort($daily_earnings_breakdown, function($a, $b) {
                    return strtotime($b['date']) - strtotime($a['date']);
                });
                ?>

                <!-- Summary -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="p-3 rounded" style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3);">
                            <h6 class="text-info mb-1">Attendance Earnings</h6>
                            <h4 class="text-white mb-0">₱<?= number_format(array_sum(array_column($attendance_records, 'daily_earnings')), 2) ?></h4>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 rounded" style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3);">
                            <h6 class="text-success mb-1">Trainer Earnings</h6>
                            <h4 class="text-white mb-0">₱<?= number_format(array_sum(array_column($attendance_records, 'trainer_earnings')), 2) ?></h4>
                        </div>
                    </div>
                </div>

                <!-- Daily Breakdown Table -->
                <div class="table-responsive">
                    <table class="table table-dark table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Hours Worked</th>
                                <th>Attendance Pay</th>
                                <th>Trainer Pay</th>
                                <th>Total Daily</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($daily_earnings_breakdown as $earning): ?>
                            <tr>
                                <td><?= date('M d, Y', strtotime($earning['date'])) ?></td>
                                <td><?= number_format($earning['hours_worked'], 1) ?> hrs</td>
                                <td>₱<?= number_format($earning['daily_earnings'], 2) ?></td>
                                <td>₱<?= number_format($earning['trainer_earnings'], 2) ?></td>
                                <td><strong>₱<?= number_format($earning['daily_earnings'] + $earning['trainer_earnings'], 2) ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (empty($daily_earnings_breakdown)): ?>
                <div class="text-center text-muted">
                    <i class="fas fa-info-circle fa-2x mb-2"></i>
                    <p>No earnings recorded in the last 30 days.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
 
            

<!-- Trainer Earnings Section -->
<div class="trainer-earnings-section">
    <div class="trainer-earnings-card">
        <div class="earnings-header">
            <h3 class="earnings-title">
                <i class="fas fa-dumbbell"></i>
                Trainer Earnings
            </h3>
            <p class="earnings-subtitle">Additional income from training sessions</p>
        </div>
        <div class="earnings-content">
            <?php if (!$staff_attended_today): ?>
            <div class="alert alert-warning" style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 12px; padding: 16px; margin-bottom: 20px;">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle text-warning me-3" style="font-size: 24px;"></i>
                    <div>
                        <h5 class="mb-1 text-warning">Attendance Required</h5>
                        <p class="mb-0 text-white">You must scan your QR code for attendance before trainer earnings can be calculated.</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <div class="trainer-stats-grid">
                <div class="stat-card primary-card">
                    <div class="stat-content">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-info">
                            <h4 class="stat-title">Sessions Today</h4>
                            <h3 class="stat-value"><?= $trainer_stats['total_sessions'] ?? 0 ?></h3>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card success-card">
                    <div class="stat-content">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h4 class="stat-title">Completed Sessions</h4>
                            <h3 class="stat-value"><?= $trainer_stats['completed_sessions'] ?? 0 ?></h3>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card info-card">
                    <div class="stat-content">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-info">
                            <h4 class="stat-title">Rate per Session</h4>
                            <h3 class="stat-value">₱<?= number_format($trainer_earnings_per_session, 2) ?></h3>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card warning-card">
                    <div class="stat-content">
                        <div class="stat-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="stat-info">
                            <h4 class="stat-title">Today's Earnings</h4>
                            <h3 class="stat-value">₱<?= number_format($trainer_daily_earnings, 2) ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($trainer_sessions_30_days)): ?>
            <div class="trainer-sessions-table-container">
                <h4 class="table-title">Today's Training Sessions</h4>
                <table class="trainer-sessions-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Earnings</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trainer_sessions_30_days as $session): ?>
                        <tr class="session-row">
                            <td class="date-cell">
                                <strong><?php 
                                    $sessionDate = $session['session_date'] ?? null;
                                    if ($sessionDate) {
                                        echo date('M d, Y', strtotime($sessionDate));
                                    } else {
                                        echo 'N/A';
                                    }
                                ?></strong>
                            </td>
                            <td class="status-cell">
                                <?php 
                                $statusClass = '';
                                $statusText = '';
                                $sessionStatus = $session['status'] ?? 'unknown';
                                switch($sessionStatus) {
                                    case 'completed':
                                        $statusClass = 'status-success';
                                        $statusText = 'Completed';
                                        $sessionEarnings = $trainer_earnings_per_session;
                                        break;
                                    case 'in_progress':
                                        $statusClass = 'status-warning';
                                        $statusText = 'In Progress';
                                        $sessionEarnings = $trainer_earnings_per_session;
                                        break;
                                    case 'booked':
                                        $statusClass = 'status-warning';
                                        $statusText = 'Booked';
                                        $sessionEarnings = 0;
                                        break;
                                    case 'cancelled':
                                        $statusClass = 'status-danger';
                                        $statusText = 'Cancelled';
                                        $sessionEarnings = 0;
                                        break;
                                    default:
                                        $statusClass = 'status-secondary';
                                        $statusText = ucfirst($sessionStatus);
                                        $sessionEarnings = 0;
                                }
                                ?>
                                <span class="status-badge <?= $statusClass ?>"><?= $statusText ?></span>
                            </td>
                            <td class="earnings-cell">
                                <span class="earnings-value">₱<?= number_format($sessionEarnings, 2) ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <th colspan="2">Total Earnings (Today)</th>
                            <th class="total-earnings">₱<?= number_format(array_sum(array_map(function($s) { 
                                $status = $s['status'] ?? 'unknown';
                                return in_array($status, ['completed', 'in_progress']) ? 100 : 0; 
                            }, $trainer_sessions_30_days)), 2) ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-trainer-sessions">
                <div class="empty-icon">
                    <i class="fas fa-dumbbell"></i>
                </div>
                <h4 class="empty-title">
                    <?php if (!$staff_attended_today): ?>
                        No Earnings Today
                    <?php else: ?>
                        No Training Sessions Today
                    <?php endif; ?>
                </h4>
                <p class="empty-description">
                    <?php if (!$staff_attended_today): ?>
                        You haven't scanned your QR code for attendance today. Trainer earnings require staff attendance.
                    <?php else: ?>
                        You haven't conducted any training sessions today.
                    <?php endif; ?>
                </p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Salary Records Section - Admin Style -->
<div class="salary-records-section">
    <div class="salary-records-card">
        <div class="records-header">
            <div class="header-content">
                <h3 class="records-title">
                    <i class="fas fa-table"></i>
                    Salary Records
                </h3>
                <p class="records-subtitle">Your complete salary history and payment details</p>
            </div>
        </div>
        <div class="records-content">
            <?php if (!empty($attendance_records)): ?>
                <div class="records-table-container">
                    <table class="records-table" id="recordsTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time In</th>
                                <th>Time Out</th>
                                <th>Hours Worked</th>
                                <th>Daily Earnings</th>
                                <th>Trainer Earnings</th>
                                <th>Total Earnings</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendance_records as $record): ?>
                            <tr class="record-row">
                                <td class="date-cell">
                                    <strong><?= date('M d, Y', strtotime($record['date'])) ?></strong>
                                </td>
                                <td class="time-cell time-in">
                                    <span class="time-value"><?= date('h:i A', strtotime($record['time_in'])) ?></span>
                                </td>
                                <td class="time-cell time-out">
                                    <span class="time-value"><?= $record['time_out'] ? date('h:i A', strtotime($record['time_out'])) : '-' ?></span>
                                </td>
                                <td class="hours-cell">
                                    <span class="hours-badge"><?= number_format($record['hours_worked'], 2) ?>h</span>
                                </td>
                                <td class="earnings-cell">
                                    <span class="earnings-value">₱<?= number_format($record['daily_earnings'], 2) ?></span>
                                </td>
                                <td class="trainer-earnings-cell">
                                    <span class="trainer-earnings-value">₱<?= number_format($record['trainer_earnings'], 2) ?></span>
                                </td>
                                <td class="total-earnings-cell">
                                    <span class="total-earnings-value">₱<?= number_format($record['daily_earnings'] + $record['trainer_earnings'], 2) ?></span>
                                </td>
                                <td class="status-cell">
                                    <?php 
                                    $statusClass = '';
                                    $statusText = '';
                                    switch($record['status']) {
                                        case 'Completed':
                                            $statusClass = 'status-success';
                                            $statusText = 'Completed';
                                            break;
                                        case 'In Progress':
                                            $statusClass = 'status-warning';
                                            $statusText = 'In Progress';
                                            break;
                                        case 'Auto-timed out':
                                            $statusClass = 'status-secondary';
                                            $statusText = 'Auto-timed out';
                                            break;
                                        default:
                                            $statusClass = 'status-secondary';
                                            $statusText = ucfirst($record['status']);
                                    }
                                    ?>
                                    <span class="status-badge <?= $statusClass ?>"><?= $statusText ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="total-row">
                                <th colspan="6">Total (Last 30 Days)</th>
                                <th class="total-earnings">₱<?= number_format(array_sum(array_column($attendance_records, 'daily_earnings')) + array_sum(array_column($attendance_records, 'trainer_earnings')), 2) ?></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
            <?php else: ?>
                <div class="empty-records">
                    <div class="empty-icon">
                        <i class="fas fa-table"></i>
                    </div>
                    <h3 class="empty-title">No salary records found</h3>
                    <p class="empty-description">No attendance records found.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php endif; ?>

<?php include 'components/footer.php'; ?>

<style>
/* Modern Staff Salary Styles - Admin Design Pattern */

/* Staff Profile Card - Admin Style */
.staff-profile-card {
    background: rgba(30, 41, 59, 0.7);
    -webkit-backdrop-filter: blur(15px);
    backdrop-filter: blur(15px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    color: white;
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}

.staff-profile-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #3b82f6, #1d4ed8);
    pointer-events: none;
}

.staff-profile-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
    background: rgba(30, 41, 59, 0.9);
}

.profile-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.profile-left {
    display: flex;
    align-items: center;
    gap: 20px;
    flex: 1;
    min-width: 300px;
}

.profile-avatar {
    width: 80px;
    height: 80px;
    background: rgba(59, 130, 246, 0.1);
    border: 2px solid #3b82f6;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #3b82f6;
    font-size: 2rem;
    flex-shrink: 0;
    position: relative;
    overflow: hidden;
}

.profile-avatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
}

.profile-avatar-fallback {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
    font-size: 2rem;
}

.profile-info {
    flex: 1;
}

.staff-name {
    font-size: 1.8rem;
    font-weight: 700;
    color: #f8f9fc;
    margin: 0 0 10px 0;
}

.staff-details {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.staff-code, .staff-position {
    display: flex;
    align-items: center;
    gap: 8px;
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.9rem;
}

.staff-code i, .staff-position i {
    color: #3b82f6;
    width: 16px;
    text-align: center;
}

.profile-right {
    display: flex;
    align-items: center;
    gap: 20px;
}

.salary-summary {
    text-align: right;
}

.summary-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-bottom: 15px;
}

.summary-label {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.9rem;
    font-weight: 500;
}

.summary-value {
    color: #3b82f6;
    font-size: 1.4rem;
    font-weight: 700;
}

/* Salary Overview Section */
.salary-overview-section {
    margin-bottom: 30px;
}

.overview-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.salary-card {
    background: rgba(30, 41, 59, 0.7);
    -webkit-backdrop-filter: blur(15px);
    backdrop-filter: blur(15px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    padding: 25px;
    color: white;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.salary-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    pointer-events: none;
}

.salary-card.primary-card::before {
    background: linear-gradient(90deg, #3b82f6, #1d4ed8);
}

.salary-card.success-card::before {
    background: linear-gradient(90deg, #10b981, #059669);
}

.salary-card.info-card::before {
    background: linear-gradient(90deg, #06b6d4, #0891b2);
}

.salary-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
    background: rgba(30, 41, 59, 0.9);
}

.card-content {
    display: flex;
    align-items: center;
    gap: 20px;
}

.card-icon {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.primary-card .card-icon {
    background: rgba(59, 130, 246, 0.2);
    color: #3b82f6;
}

.success-card .card-icon {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
}

.info-card .card-icon {
    background: rgba(6, 182, 212, 0.2);
    color: #06b6d4;
}

.card-info {
    flex: 1;
}

.card-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.8);
    margin: 0 0 8px 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.card-value {
    font-size: 1.8rem;
    font-weight: 700;
    color: #f8f9fc;
    margin: 0;
}

/* Payment Breakdown Section */
.payment-breakdown-section {
    margin-bottom: 30px;
}

.breakdown-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.breakdown-card {
    background: rgba(30, 41, 59, 0.7);
    -webkit-backdrop-filter: blur(15px);
    backdrop-filter: blur(15px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    padding: 25px;
    color: white;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.breakdown-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    pointer-events: none;
}

.breakdown-card.warning-card::before {
    background: linear-gradient(90deg, #f59e0b, #d97706);
}

.breakdown-card.success-card::before {
    background: linear-gradient(90deg, #10b981, #059669);
}

.breakdown-card.primary-card::before {
    background: linear-gradient(90deg, #3b82f6, #1d4ed8);
}

.breakdown-card.secondary-card::before {
    background: linear-gradient(90deg, #6b7280, #4b5563);
}

.breakdown-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
    background: rgba(30, 41, 59, 0.9);
}

.warning-card .card-icon {
    background: rgba(245, 158, 11, 0.2);
    color: #f59e0b;
}

.secondary-card .card-icon {
    background: rgba(107, 114, 128, 0.2);
    color: #6b7280;
}

/* Payroll Info Section */
.payroll-info-section {
    margin-bottom: 30px;
}

.payroll-info-card {
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

.payroll-info-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
    border-color: rgba(255, 255, 255, 0.2);
    background: rgba(30, 41, 59, 0.9);
}

.info-header {
    padding: 25px 30px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.info-title {
    font-size: 1.8rem;
    font-weight: 600;
    color: #f8f9fc;
    margin: 0 0 8px 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.info-title i {
    color: #3b82f6;
    font-size: 1.5rem;
}

.info-subtitle {
    color: rgba(255, 255, 255, 0.7);
    font-size: 1rem;
    margin: 0;
    font-weight: 400;
}

.info-content {
    padding: 30px;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 30px;
    margin-bottom: 25px;
}

.info-section {
    background: rgba(15, 23, 42, 0.5);
    border-radius: 15px;
    padding: 20px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.section-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #f8f9fc;
    margin: 0 0 15px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-title i {
    color: #3b82f6;
    font-size: 1rem;
}

.rates-list, .work-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.rate-item, .work-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.rate-item:last-child, .work-item:last-child {
    border-bottom: none;
}

.rate-label, .work-label {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.9rem;
}

.rate-value, .work-value {
    color: #10b981;
    font-weight: 600;
    font-size: 0.9rem;
}

.info-note {
    background: rgba(59, 130, 246, 0.1);
    border: 1px solid rgba(59, 130, 246, 0.3);
    border-radius: 12px;
    padding: 15px;
    display: flex;
    align-items: flex-start;
    gap: 10px;
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.9rem;
}

.info-note i {
    color: #3b82f6;
    font-size: 1.1rem;
    margin-top: 2px;
    flex-shrink: 0;
}

/* Earnings Section */
.earnings-section {
    margin-bottom: 30px;
}

.earnings-card {
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

.earnings-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
    border-color: rgba(255, 255, 255, 0.2);
    background: rgba(30, 41, 59, 0.9);
}

.earnings-header {
    padding: 25px 30px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.earnings-title {
    font-size: 1.8rem;
    font-weight: 600;
    color: #f8f9fc;
    margin: 0 0 8px 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.earnings-title i {
    color: #3b82f6;
    font-size: 1.5rem;
}

.earnings-subtitle {
    color: rgba(255, 255, 255, 0.7);
    font-size: 1rem;
    margin: 0;
    font-weight: 400;
}

.earnings-content {
    padding: 30px;
}

.earnings-table-container {
    overflow-x: auto;
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    margin-bottom: 25px;
}

.earnings-table {
    width: 100%;
    border-collapse: collapse;
    background: rgba(15, 23, 42, 0.8);
    -webkit-backdrop-filter: blur(10px);
    backdrop-filter: blur(10px);
}

.earnings-table thead {
    background: rgba(59, 130, 246, 0.1);
    border-bottom: 2px solid rgba(59, 130, 246, 0.3);
}

.earnings-table th {
    padding: 20px 15px;
    text-align: left;
    font-weight: 600;
    color: #f8f9fc;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: none;
}

.earnings-table td {
    padding: 20px 15px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.9rem;
}

.earnings-row:hover {
    background: rgba(59, 130, 246, 0.05);
    transform: translateX(5px);
    transition: all 0.3s ease;
}

.date-cell {
    font-weight: 600;
}

.time-cell {
    font-family: 'Courier New', monospace;
    font-weight: 500;
}

.time-in .time-value {
    color: #3b82f6;
}

.time-out .time-value {
    color: #10b981;
}

.hours-badge {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.rate-value {
    color: #f59e0b;
    font-weight: 600;
}

.earnings-value {
    color: #10b981;
    font-weight: 700;
    font-size: 1.1rem;
}

.trainer-earnings-value {
    color: #3b82f6;
    font-weight: 700;
    font-size: 1.1rem;
}

.total-earnings-value {
    color: #f59e0b;
    font-weight: 700;
    font-size: 1.1rem;
}

.total-row {
    background: rgba(16, 185, 129, 0.1);
    border-top: 2px solid rgba(16, 185, 129, 0.3);
}

.total-row th {
    color: #10b981;
    font-weight: 700;
    padding: 20px 15px;
}

.total-trainer-earnings {
    color: #3b82f6;
    font-weight: 700;
}

.total-earnings {
    color: #f59e0b;
    font-weight: 700;
}

.total-earnings {
    color: #10b981;
    font-size: 1.2rem;
}

.empty-earnings {
    text-align: center;
    padding: 60px 20px;
    color: rgba(255, 255, 255, 0.6);
}

.empty-earnings .empty-icon {
    font-size: 4rem;
    margin-bottom: 20px;
    color: rgba(255, 255, 255, 0.3);
}

.empty-earnings .empty-title {
    color: #f8f9fc;
    margin-bottom: 10px;
    font-weight: 600;
    font-size: 1.5rem;
}

.empty-earnings .empty-description {
    color: rgba(255, 255, 255, 0.6);
    font-size: 1rem;
    margin: 0;
}

/* Salary Records Section */
.salary-records-section {
    margin-bottom: 30px;
}

.salary-records-card {
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

.salary-records-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
    border-color: rgba(255, 255, 255, 0.2);
    background: rgba(30, 41, 59, 0.9);
}

.records-header {
    padding: 25px 30px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.records-title {
    font-size: 1.8rem;
    font-weight: 600;
    color: #f8f9fc;
    margin: 0 0 8px 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.records-title i {
    color: #3b82f6;
    font-size: 1.5rem;
}

.records-subtitle {
    color: rgba(255, 255, 255, 0.7);
    font-size: 1rem;
    margin: 0;
    font-weight: 400;
}

.records-content {
    padding: 30px;
}

.records-table-container {
    overflow-x: auto;
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    margin-bottom: 25px;
}

.records-table {
    width: 100%;
    border-collapse: collapse;
    background: rgba(15, 23, 42, 0.8);
    -webkit-backdrop-filter: blur(10px);
    backdrop-filter: blur(10px);
}

.records-table thead {
    background: rgba(59, 130, 246, 0.1);
    border-bottom: 2px solid rgba(59, 130, 246, 0.3);
}

.records-table th {
    padding: 20px 15px;
    text-align: left;
    font-weight: 600;
    color: #f8f9fc;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: none;
}

.records-table td {
    padding: 20px 15px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.9rem;
}

.record-row:hover {
    background: rgba(59, 130, 246, 0.05);
    transform: translateX(5px);
    transition: all 0.3s ease;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-badge.status-success {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.status-badge.status-warning {
    background: rgba(245, 158, 11, 0.2);
    color: #f59e0b;
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.status-badge.status-secondary {
    background: rgba(107, 114, 128, 0.2);
    color: #6b7280;
    border: 1px solid rgba(107, 114, 128, 0.3);
}

.total-hours {
    color: #3b82f6;
    font-weight: 700;
}

.summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.summary-card {
    background: rgba(30, 41, 59, 0.7);
    -webkit-backdrop-filter: blur(15px);
    backdrop-filter: blur(15px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    padding: 25px;
    color: white;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.summary-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    pointer-events: none;
}

.summary-card.primary-card::before {
    background: linear-gradient(90deg, #3b82f6, #1d4ed8);
}

.summary-card.success-card::before {
    background: linear-gradient(90deg, #10b981, #059669);
}

.summary-card.info-card::before {
    background: linear-gradient(90deg, #06b6d4, #0891b2);
}

.summary-card.warning-card::before {
    background: linear-gradient(90deg, #f59e0b, #d97706);
}

.summary-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
    background: rgba(30, 41, 59, 0.9);
}

.empty-records {
    text-align: center;
    padding: 60px 20px;
    color: rgba(255, 255, 255, 0.6);
}

.empty-records .empty-icon {
    font-size: 4rem;
    margin-bottom: 20px;
    color: rgba(255, 255, 255, 0.3);
}

.empty-records .empty-title {
    color: #f8f9fc;
    margin-bottom: 10px;
    font-weight: 600;
    font-size: 1.5rem;
}

.empty-records .empty-description {
    color: rgba(255, 255, 255, 0.6);
    font-size: 1rem;
    margin: 0;
}

/* Responsive Design */
@media (max-width: 768px) {
    .profile-content {
        flex-direction: column;
        text-align: center;
    }
    
    .profile-left {
        flex-direction: column;
        text-align: center;
    }
    
    .overview-cards {
        grid-template-columns: 1fr;
    }
    
    .breakdown-cards {
        grid-template-columns: 1fr;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .summary-cards {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    }
    
    .earnings-table-container,
    .records-table-container {
        overflow-x: auto;
    }
    
    .earnings-table th,
    .earnings-table td,
    .records-table th,
    .records-table td {
        padding: 15px 10px;
        font-size: 0.85rem;
    }
    
    .trainer-stats-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .trainer-sessions-table-container {
        overflow-x: auto;
    }
    
    .trainer-sessions-table th,
    .trainer-sessions-table td {
        padding: 12px 8px;
        font-size: 0.8rem;
    }
    
    .stat-content {
        flex-direction: column;
        text-align: center;
        gap: 10px;
    }
    
    .stat-icon {
        width: 45px;
        height: 45px;
        font-size: 1.1rem;
    }
}

/* Trainer Earnings Section */
.trainer-earnings-section {
    margin-bottom: 30px;
}

.trainer-earnings-card {
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

.trainer-earnings-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
    border-color: rgba(255, 255, 255, 0.2);
    background: rgba(30, 41, 59, 0.9);
}

.earnings-header {
    padding: 25px 30px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.earnings-title {
    font-size: 1.8rem;
    font-weight: 600;
    color: #f8f9fc;
    margin: 0 0 8px 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.earnings-title i {
    color: #3b82f6;
    font-size: 1.5rem;
}

.earnings-subtitle {
    color: rgba(255, 255, 255, 0.7);
    font-size: 1rem;
    margin: 0;
    font-weight: 400;
}

.earnings-content {
    padding: 30px;
}

.trainer-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: rgba(15, 23, 42, 0.5);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 15px;
    padding: 20px;
    color: white;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    pointer-events: none;
}

.stat-card.primary-card::before {
    background: linear-gradient(90deg, #3b82f6, #1d4ed8);
}

.stat-card.success-card::before {
    background: linear-gradient(90deg, #10b981, #059669);
}

.stat-card.info-card::before {
    background: linear-gradient(90deg, #06b6d4, #0891b2);
}

.stat-card.warning-card::before {
    background: linear-gradient(90deg, #f59e0b, #d97706);
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
    background: rgba(15, 23, 42, 0.7);
}

.stat-content {
    display: flex;
    align-items: center;
    gap: 15px;
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.primary-card .stat-icon {
    background: rgba(59, 130, 246, 0.2);
    color: #3b82f6;
}

.success-card .stat-icon {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
}

.info-card .stat-icon {
    background: rgba(6, 182, 212, 0.2);
    color: #06b6d4;
}

.warning-card .stat-icon {
    background: rgba(245, 158, 11, 0.2);
    color: #f59e0b;
}

.stat-info {
    flex: 1;
}

.stat-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.8);
    margin: 0 0 8px 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-value {
    font-size: 1.6rem;
    font-weight: 700;
    color: #f8f9fc;
    margin: 0;
}

.trainer-sessions-table-container {
    margin-top: 30px;
}

.table-title {
    font-size: 1.2rem;
    font-weight: 600;
    color: #f8f9fc;
    margin: 0 0 20px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.table-title::before {
    content: '';
    width: 4px;
    height: 20px;
    background: linear-gradient(180deg, #3b82f6, #1d4ed8);
    border-radius: 2px;
}

.trainer-sessions-table {
    width: 100%;
    border-collapse: collapse;
    background: rgba(15, 23, 42, 0.8);
    -webkit-backdrop-filter: blur(10px);
    backdrop-filter: blur(10px);
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.trainer-sessions-table thead {
    background: rgba(59, 130, 246, 0.1);
    border-bottom: 2px solid rgba(59, 130, 246, 0.3);
}

.trainer-sessions-table th {
    padding: 18px 15px;
    text-align: left;
    font-weight: 600;
    color: #f8f9fc;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: none;
}

.trainer-sessions-table td {
    padding: 18px 15px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.9rem;
}

.session-row:hover {
    background: rgba(59, 130, 246, 0.05);
    transform: translateX(3px);
    transition: all 0.3s ease;
}

.status-badge.status-danger {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.earnings-value {
    color: #10b981;
    font-weight: 600;
    font-size: 0.95rem;
}

.empty-trainer-sessions {
    text-align: center;
    padding: 50px 20px;
    color: rgba(255, 255, 255, 0.6);
}

.empty-trainer-sessions .empty-icon {
    font-size: 3rem;
    margin-bottom: 15px;
    color: rgba(255, 255, 255, 0.3);
}

.empty-trainer-sessions .empty-title {
    color: #f8f9fc;
    margin-bottom: 8px;
    font-weight: 600;
    font-size: 1.3rem;
}

.empty-trainer-sessions .empty-description {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.95rem;
    margin: 0;
}

/* Print Styles */
@media print {
    .salary-records-card,
    .earnings-card,
    .payroll-info-card,
    .staff-profile-card,
    .trainer-earnings-card {
        box-shadow: none !important;
        border: 1px solid #dee2e6 !important;
        background: white !important;
        color: black !important;
    }
    
    .earnings-table,
    .records-table,
    .trainer-sessions-table {
        background: white !important;
    }
    
    .earnings-table th,
    .earnings-table td,
    .records-table th,
    .records-table td,
    .trainer-sessions-table th,
    .trainer-sessions-table td {
        color: black !important;
        border: 1px solid #dee2e6 !important;
    }
}
</style>
