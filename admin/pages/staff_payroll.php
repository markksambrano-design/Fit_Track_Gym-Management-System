<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$page_title = "Staff Payroll Management";
include 'components/header.php';
include '../includes/db.php';
include '../includes/functions.php';

// Database connection
try {
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get current month and year for filtering
$currentMonth = date('Y-m');
$selectedMonth = isset($_GET['month']) ? $_GET['month'] : $currentMonth;
$viewMode = isset($_GET['view']) ? $_GET['view'] : 'daily';
$selectedDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Get all staff
$allStaffStmt = $conn->prepare("
    SELECT 
        s.id, s.staff_id, s.first_name, s.last_name, s.hire_date, s.photo,
        p.salary, p.employment_type, p.bank_name, p.account_number
    FROM staff s
    LEFT JOIN payroll p ON s.id = p.staff_id
    ORDER BY s.first_name, s.last_name
");
$allStaffStmt->execute();
$allStaffResult = $allStaffStmt->get_result();
$allStaffMap = [];
while ($staffRow = $allStaffResult->fetch_assoc()) {
    $allStaffMap[$staffRow['id']] = [
        'staff_id' => $staffRow['staff_id'],
        'first_name' => $staffRow['first_name'],
        'last_name' => $staffRow['last_name'],
        'hire_date' => $staffRow['hire_date'],
        'photo' => $staffRow['photo'],
        'salary' => $staffRow['salary'],
        'employment_type' => $staffRow['employment_type'],
        'bank_name' => $staffRow['bank_name'],
        'account_number' => $staffRow['account_number'],
        'daily_hours' => []
    ];
}
$allStaffStmt->close();

// Get attendance data based on view mode
if ($viewMode === 'daily') {
    $stmt = $conn->prepare("
        SELECT 
            sa.staff_id,
            sa.date,
            ROUND(TIMESTAMPDIFF(MINUTE, sa.time_in, COALESCE(sa.time_out, NOW())) / 60.0, 2) as hours_worked
        FROM staff_attendance sa
        WHERE sa.date = ?
        AND sa.time_in IS NOT NULL
        AND (sa.time_out IS NULL OR sa.time_out > sa.time_in)
        
        UNION ALL
        
        SELECT 
            saa.staff_id,
            saa.date,
            ROUND(TIMESTAMPDIFF(MINUTE, saa.time_in, saa.time_out) / 60.0, 2) as hours_worked
        FROM staff_attendance_archive saa
        WHERE saa.date = ?
        AND saa.time_out IS NOT NULL
        AND saa.time_in IS NOT NULL
        AND saa.time_out > saa.time_in
    ");
    $stmt->bind_param('ss', $selectedDate, $selectedDate);
} else {
    $stmt = $conn->prepare("
        SELECT 
            sa.staff_id,
            sa.date,
            ROUND(TIMESTAMPDIFF(MINUTE, sa.time_in, COALESCE(sa.time_out, NOW())) / 60.0, 2) as hours_worked
        FROM staff_attendance sa
        WHERE DATE_FORMAT(sa.date, '%Y-%m') = ?
        AND sa.time_in IS NOT NULL
        AND (sa.time_out IS NULL OR sa.time_out > sa.time_in)
        
        UNION ALL
        
        SELECT 
            saa.staff_id,
            saa.date,
            ROUND(TIMESTAMPDIFF(MINUTE, saa.time_in, saa.time_out) / 60.0, 2) as hours_worked
        FROM staff_attendance_archive saa
        WHERE DATE_FORMAT(saa.date, '%Y-%m') = ?
        AND saa.time_out IS NOT NULL
        AND saa.time_in IS NOT NULL
        AND saa.time_out > saa.time_in
    ");
    $stmt->bind_param('ss', $selectedMonth, $selectedMonth);
}
$stmt->execute();
$result = $stmt->get_result();

// Group attendance data by staff
while ($row = $result->fetch_assoc()) {
    $staffInternalId = $row['staff_id'];
    $date = $row['date'];
    $hours = $row['hours_worked'];
    
    if (isset($allStaffMap[$staffInternalId]) && $date) {
        $hours = is_numeric($hours) ? (float)$hours : 0;
        
        if ($hours > 0) {
            if (!isset($allStaffMap[$staffInternalId]['daily_hours'][$date])) {
                $allStaffMap[$staffInternalId]['daily_hours'][$date] = 0;
            }
            $allStaffMap[$staffInternalId]['daily_hours'][$date] += $hours;
        }
    }
}
$stmt->close();

// Apply 8-hour cap and calculate totals
$staffData = [];
foreach ($allStaffMap as $staff) {
    $totalHours = 0;
    $daysWorked = 0;
    $totalPay = 0.0;
    
    foreach ($staff['daily_hours'] as $date => $dayHours) {
        $cappedHours = min($dayHours, 8.0);
        $totalHours += $cappedHours;
        $totalPay += calculateStaffDailyPayroll($cappedHours);
        if ($cappedHours > 0) {
            $daysWorked++;
        }
    }
    
    $staffData[] = [
        'staff_id' => $staff['staff_id'],
        'first_name' => $staff['first_name'],
        'last_name' => $staff['last_name'],
        'hire_date' => $staff['hire_date'],
        'photo' => $staff['photo'],
        'salary' => $staff['salary'],
        'employment_type' => $staff['employment_type'],
        'bank_name' => $staff['bank_name'],
        'account_number' => $staff['account_number'],
        'days_worked' => $daysWorked,
        'total_hours' => $totalHours,
        'calculated_pay' => $totalPay
    ];
}

// Function to get staff time status
function getStaffTimeStatus($staffId) {
    global $conn;
    
    try {
        $todayResult = $conn->query("SELECT CURDATE() as today");
        $today = $todayResult ? $todayResult->fetch_assoc()['today'] : date('Y-m-d');
        
        $stmt = $conn->prepare("SELECT id FROM staff WHERE staff_id = ?");
        $stmt->bind_param('s', $staffId);
        $stmt->execute();
        $result = $stmt->get_result();
        $staff = $result->fetch_assoc();
        $stmt->close();
        
        if (!$staff) {
            return [
                'class' => 'status-out',
                'icon' => 'fa-circle',
                'text' => 'Not In',
                'time' => '--:--'
            ];
        }
        
        $internalStaffId = $staff['id'];
        
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
            return [
                'class' => 'status-out',
                'icon' => 'fa-circle',
                'text' => 'Not In',
                'time' => '--:--'
            ];
        }
        
        if ($attendance['time_out']) {
            return [
                'class' => 'status-out',
                'icon' => 'fa-check-circle',
                'text' => 'Out',
                'time' => date('H:i', strtotime($attendance['time_out']))
            ];
        } else {
            return [
                'class' => 'status-in',
                'icon' => 'fa-play-circle',
                'text' => 'In',
                'time' => date('H:i', strtotime($attendance['time_in']))
            ];
        }
    } catch (Exception $e) {
        error_log("Error in getStaffTimeStatus: " . $e->getMessage());
        return [
            'class' => 'status-out',
            'icon' => 'fa-circle',
            'text' => 'Not In',
            'time' => '--:--'
        ];
    }
}
?>

<!-- Custom Styles -->
<link rel="stylesheet" href="../assets/css/admin/payroll.css">
<style>
/* Time Status Styles */
.time-status {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}

.status-indicator {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
}

.status-indicator.status-in {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.status-indicator.status-out {
    background: rgba(107, 114, 128, 0.1);
    color: #6b7280;
}

.time-display {
    font-size: 0.9rem;
    font-weight: 600;
    color: #1e3a8a;
}
</style>

<?php if (isset($_SESSION['message'])): ?>
<div class="alert alert-<?= $_SESSION['type'] ?? 'success' ?> alert-dismissible fade show" role="alert">
    <?= $_SESSION['message'] ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php unset($_SESSION['message'], $_SESSION['type']); endif; ?>

<!-- Main Content Card -->
<div class="card main-content-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div class="header-left">
            <h6 class="gym-brand">
                <i class="fas fa-money-bill-wave gym-icon me-2"></i>Staff Payroll Management
            </h6>
            <p class="card-subtitle">
                <?php if ($viewMode === 'daily'): ?>
                    Manage staff payroll for <?= date('F d, Y', strtotime($selectedDate)) ?>
                <?php else: ?>
                    Manage staff payroll for <?= date('F Y', strtotime($selectedMonth . '-01')) ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="header-actions">
            <div class="view-mode-toggle me-3">
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-sm <?= $viewMode === 'daily' ? 'btn-primary' : 'btn-outline-primary' ?>" onclick="setViewMode('daily')">
                        <i class="fas fa-calendar-day me-1"></i>Daily
                    </button>
                    <button type="button" class="btn btn-sm <?= $viewMode === 'monthly' ? 'btn-primary' : 'btn-outline-primary' ?>" onclick="setViewMode('monthly')">
                        <i class="fas fa-calendar-alt me-1"></i>Monthly
                    </button>
                </div>
            </div>
            <?php if ($viewMode === 'daily'): ?>
                <div class="date-filter me-3">
                    <input type="date" class="form-control form-control-sm" id="dateFilter" value="<?= $selectedDate ?>" onchange="filterByDate(this.value)" style="width: 150px;">
                </div>
            <?php else: ?>
                <div class="month-filter me-3">
                    <select class="form-select form-select-sm" id="monthFilter" onchange="filterByMonth(this.value)">
                        <?php
                        for ($i = 0; $i < 12; $i++) {
                            $month = date('Y-m', strtotime("-$i months"));
                            $monthName = date('F Y', strtotime($month . '-01'));
                            $selected = ($month === $selectedMonth) ? 'selected' : '';
                            echo "<option value='{$month}' {$selected}>{$monthName}</option>";
                        }
                        ?>
                    </select>
                </div>
            <?php endif; ?>
            <button class="btn btn-secondary btn-sm me-2" id="refreshPayroll" onclick="refreshPayrollData()" title="Refresh payroll data">
                <i class="fas fa-sync-alt me-1"></i>Refresh
            </button>
            <button class="btn btn-info btn-sm" id="viewPayrollHistory" onclick="window.location.href='index.php?page=payroll_history'">
                <i class="fas fa-history me-1"></i>View Payroll History
            </button>
        </div>
    </div>

    <div class="card-body p-0">
        <!-- Payroll Summary -->
        <div class="payroll-summary">
            <div class="container-fluid py-3">
                <div class="row g-3">
                    <div class="col-md-3 col-sm-6">
                        <div class="summary-card text-center">
                            <div class="summary-icon mb-2">
                                <i class="fas fa-users fa-2x text-primary"></i>
                            </div>
                            <div class="summary-value h4 mb-1 text-white"><?= count($staffData) ?></div>
                            <div class="summary-label text-white-50">Total Staff</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="summary-card text-center">
                            <div class="summary-icon mb-2">
                                <i class="fas fa-clock fa-2x text-info"></i>
                            </div>
                            <div class="summary-value h4 mb-1 text-white"><?= number_format(array_sum(array_column($staffData, 'total_hours')), 1) ?></div>
                            <div class="summary-label text-white-50">Total Hours</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="summary-card text-center">
                            <div class="summary-icon mb-2">
                                <i class="fas fa-calendar-check fa-2x text-warning"></i>
                            </div>
                            <div class="summary-value h4 mb-1 text-white"><?= array_sum(array_column($staffData, 'days_worked')) ?></div>
                            <div class="summary-label text-white-50">Days Worked</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="summary-card text-center">
                            <div class="summary-icon mb-2">
                                <i class="fas fa-money-bill-wave fa-2x text-success"></i>
                            </div>
                            <div class="summary-value h4 mb-1 text-success">₱<?= number_format(array_sum(array_column($staffData, 'calculated_pay')), 2) ?></div>
                            <div class="summary-label text-white-50">Total Payroll</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th class="text-start"><i class="fas fa-user me-2"></i>Staff Member</th>
                        <th class="text-center"><i class="fas fa-calendar-day me-2"></i>Days Worked</th>
                        <th class="text-center"><i class="fas fa-clock me-2"></i>Total Hours</th>
                        <th class="text-center"><i class="fas fa-user-clock me-2"></i>Time Status</th>
                        <th class="text-end"><i class="fas fa-money-bill-wave me-2"></i>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($staffData)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <div class="empty-state">
                                    <i class="fas fa-users fa-3x text-white-50 mb-3"></i>
                                    <h5 class="text-white-50">No staff found</h5>
                                    <p class="text-white-50">Try selecting a different date or add staff members.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($staffData as $s): ?>
                            <?php
                            $fullName = htmlspecialchars($s['first_name'] . ' ' . $s['last_name']);
                            $staffId = htmlspecialchars($s['staff_id']);
                            $daysWorked = (int)$s['days_worked'];
                            $totalHours = (float)$s['total_hours'];
                            $calculatedPayValue = (float)$s['calculated_pay'];
                            $calculatedPay = '₱' . number_format($calculatedPayValue, 2);
                            
                            $timeStatus = getStaffTimeStatus($staffId);
                            
                            $hoursDisplay = number_format($totalHours, 2);
                            if ($totalHours == (int)$totalHours) {
                                $hoursDisplay = (int)$totalHours;
                            }
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="staff-avatar me-3">
                                            <?php if (!empty($s['photo'])): ?>
                                                <?php $photoPath = '../uploads/staff_photos/' . $s['photo']; ?>
                                                <?php if (file_exists($photoPath)): ?>
                                                    <img src="<?= $photoPath ?>" alt="Profile" class="profile-pic">
                                                <?php else: ?>
                                                    <i class="fas fa-user"></i>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <i class="fas fa-user"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold"><?= $fullName ?></div>
                                            <small class="text-white-50"><?= $staffId ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary fs-6 px-3 py-2"><?= $daysWorked ?> day<?= $daysWorked !== 1 ? 's' : '' ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-info fs-6 px-3 py-2"><?= $hoursDisplay ?> hrs</span>
                                </td>
                                <td class="text-center">
                                    <div class="time-status">
                                        <div class="status-indicator <?= $timeStatus['class'] ?>">
                                            <i class="fas <?= $timeStatus['icon'] ?> me-1"></i>
                                            <span><?= $timeStatus['text'] ?></span>
                                        </div>
                                        <small class="text-white-50 d-block mt-1"><?= $timeStatus['time'] ?></small>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <div class="amount-display">
                                        <div class="total-amount fw-bold text-success fs-5"><?= $calculatedPay ?></div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// View Mode Function
function setViewMode(mode) {
    if (mode === 'daily') {
        const today = new Date().toISOString().split('T')[0];
        window.location.href = `index.php?page=staff_payroll&view=daily&date=${today}`;
    } else {
        const currentMonth = new Date().toISOString().slice(0, 7);
        window.location.href = `index.php?page=staff_payroll&view=monthly&month=${currentMonth}`;
    }
}

// Date Filter Function
function filterByDate(date) {
    if (date) {
        window.location.href = `index.php?page=staff_payroll&view=daily&date=${date}`;
    }
}

// Month Filter Function
function filterByMonth(month) {
    if (month) {
        window.location.href = `index.php?page=staff_payroll&view=monthly&month=${month}`;
    }
}

// Refresh Payroll Data Function
function refreshPayrollData() {
    const refreshBtn = document.getElementById('refreshPayroll');
    if (refreshBtn) {
        const icon = refreshBtn.querySelector('i');
        icon.classList.add('fa-spin');
        refreshBtn.disabled = true;
        setTimeout(() => {
            window.location.reload();
        }, 300);
    }
}

// Auto-refresh staff time status
document.addEventListener('DOMContentLoaded', function() {
    let statusRefreshInterval = null;
    
    function updateStaffStatuses() {
        const urlParams = new URLSearchParams(window.location.search);
        const viewMode = urlParams.get('view') || 'daily';
        const selectedDate = urlParams.get('date') || new Date().toISOString().split('T')[0];
        const today = new Date().toISOString().split('T')[0];
        
        if (viewMode === 'daily' && selectedDate === today) {
            fetch(`actions/staff_actions.php?action=get_all_staff_status`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success && data.statuses) {
                        Object.keys(data.statuses).forEach(staffId => {
                            const statusElement = document.querySelector(`[data-staff-status-id="${staffId}"]`);
                            if (statusElement) {
                                const status = data.statuses[staffId];
                                const indicator = statusElement.querySelector('.status-indicator');
                                const icon = statusElement.querySelector('[data-status-icon]');
                                const text = statusElement.querySelector('[data-status-text]');
                                const time = statusElement.querySelector('[data-status-time]');
                                
                                if (indicator && icon && text && time) {
                                    indicator.className = `status-indicator ${status.class}`;
                                    indicator.setAttribute('data-status-class', status.class);
                                    icon.className = `fas ${status.icon}`;
                                    icon.setAttribute('data-status-icon', status.icon);
                                    text.textContent = status.text;
                                    time.textContent = status.time;
                                }
                            }
                        });
                    }
                })
                .catch(error => {
                    console.error('Error updating staff statuses:', error);
                });
        }
    }
    
    // Start auto-refresh (every 5 seconds)
    statusRefreshInterval = setInterval(updateStaffStatuses, 5000);
    
    // Update immediately on page load
    setTimeout(() => {
        updateStaffStatuses();
    }, 1000);
    
    // Listen for QR scan events
    window.addEventListener('message', function(event) {
        if (event.data && event.data.type === 'scanResult' && event.data.success) {
            if (event.data.user_type === 'staff') {
                setTimeout(updateStaffStatuses, 1000);
            }
        }
    });
    
    // Clean up interval when page is hidden
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            if (statusRefreshInterval) {
                clearInterval(statusRefreshInterval);
                statusRefreshInterval = null;
            }
        } else {
            if (!statusRefreshInterval) {
                statusRefreshInterval = setInterval(updateStaffStatuses, 5000);
                updateStaffStatuses();
            }
        }
    });
});
</script>

<?php include 'components/footer.php'; ?>
