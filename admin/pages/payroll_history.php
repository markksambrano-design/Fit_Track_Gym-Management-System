<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$page_title = "Payroll History";
include 'components/header.php';
include '../includes/db.php';

// Database connection - use MySQLi for consistency
try {
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get filter parameters
$selectedMonth = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$selectedStatus = isset($_GET['status']) ? $_GET['status'] : '';
$selectedStaff = isset($_GET['staff_id']) ? $_GET['staff_id'] : '';

// Build query with filters
$whereConditions = [];
$params = [];
$paramTypes = '';

if ($selectedMonth) {
    $whereConditions[] = "DATE_FORMAT(ph.period_start, '%Y-%m') = ?";
    $params[] = $selectedMonth;
    $paramTypes .= 's';
}

if ($selectedStatus) {
    $whereConditions[] = "ph.status = ?";
    $params[] = $selectedStatus;
    $paramTypes .= 's';
}

if ($selectedStaff) {
    $whereConditions[] = "s.staff_id = ?";
    $params[] = $selectedStaff;
    $paramTypes .= 's';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Query: Payroll History with filters
$query = "
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

// Get all staff for filter dropdown
$allStaff = $conn->query("
    SELECT s.staff_id, s.first_name, s.last_name 
    FROM staff s 
    ORDER BY s.first_name, s.last_name
")->fetch_all(MYSQLI_ASSOC);

// Calculate summary statistics
$totalRecords = count($payrollHistory);
$totalPay = array_sum(array_column($payrollHistory, 'total_pay'));
$paidRecords = count(array_filter($payrollHistory, function($record) { return $record['status'] === 'paid'; }));
$pendingRecords = count(array_filter($payrollHistory, function($record) { return $record['status'] === 'pending'; }));

// Function to render payroll history rows
function renderPayrollHistoryRows($records) {
    if (!$records) {
        return "<tr><td colspan='9' class='text-center text-muted py-4'><i class='fas fa-history fa-2x mb-2'></i><br>No payroll records found for the selected filters.</td></tr>";
    }
    
    $rows = "";
    foreach ($records as $record) {
        $recordId = htmlspecialchars($record['id']);
        $staffCode = htmlspecialchars($record['staff_code']);
        $fullName = htmlspecialchars($record['first_name'] . ' ' . $record['last_name']);
        $periodStart = date('M d, Y', strtotime($record['period_start']));
        $periodEnd = date('M d, Y', strtotime($record['period_end']));
        
        // Format period display - show as range if different dates, single date if same
        $periodDisplay = '';
        if ($record['period_start'] === $record['period_end']) {
            $periodDisplay = $periodStart;
        } else {
            $periodDisplay = $periodStart . ' - ' . $periodEnd;
        }
        $hoursWorked = $record['hours_worked'];
        $hourlyRate = '₱' . number_format($record['hourly_rate'], 2);
        $totalPay = '₱' . number_format($record['total_pay'], 2);
        $status = $record['status'];
        $paymentDate = $record['payment_date'] ? date('M d, Y', strtotime($record['payment_date'])) : '-';
        
        // Format payment date display
        $paymentDateDisplay = '';
        if ($record['payment_date']) {
            $paymentDateDisplay = '<span class="payment-date-paid">' . date('M d, Y', strtotime($record['payment_date'])) . '</span>';
        } else {
            $paymentDateDisplay = '<span class="payment-date-unpaid">Not paid</span>';
        }
        
        $statusBadge = match($status) {
            'paid' => 'success',
            'pending' => 'warning',
            'cancelled' => 'danger',
            default => 'secondary'
        };
        
        $statusText = match($status) {
            'paid' => 'Paid',
            'pending' => 'Pending',
            'cancelled' => 'Cancelled',
            default => ucfirst($status)
        };
        
        // Build action buttons
        $actionButtons = "
            <button class='btn btn-sm btn-outline-info btn-view-record' data-record-id='{$recordId}' title='View Details'>
                <i class='fas fa-eye'></i>
            </button>
            <button class='btn btn-sm btn-outline-warning btn-edit-record' data-record-id='{$recordId}' title='Edit Record'>
                <i class='fas fa-edit'></i>
            </button>";
            
        if ($status === 'pending') {
            $actionButtons .= "
            <button class='btn btn-sm btn-outline-success btn-mark-paid' data-record-id='{$recordId}' title='Mark as Paid'>
                <i class='fas fa-check'></i>
            </button>";
        }
        
        $rows .= "
        <tr data-record-id='{$recordId}' class='payroll-history-row'>
            <td class='text-center'>
                <span class='record-id-badge'>{$recordId}</span>
            </td>
            <td>
                <div class='staff-info'>
                    <div class='staff-avatar'>
                        <i class='fas fa-user-tie'></i>
                    </div>
                    <div class='staff-details'>
                        <div class='staff-name'>{$fullName}</div>
                        <div class='staff-code'>{$staffCode}</div>
                    </div>
                </div>
            </td>
            <td>
                <div class='period-info'>
                    <div class='period-dates'>{$periodDisplay}</div>
                </div>
            </td>
            <td class='text-center'>
                <span class='hours-badge'>{$hoursWorked}h</span>
            </td>
            <td class='text-end'>
                <span class='rate-amount'>{$hourlyRate}</span>
            </td>
            <td class='text-end'>
                <span class='total-amount'>{$totalPay}</span>
            </td>
            <td class='text-center'>
                <span class='badge bg-{$statusBadge} status-badge'>{$statusText}</span>
            </td>
            <td class='text-center'>
                {$paymentDateDisplay}
            </td>
            <td class='text-center'>
                <div class='action-buttons'>
                    {$actionButtons}
                </div>
            </td>
        </tr>";
    }
    return $rows;
}
?>

<!-- Custom Styles -->
<link rel="stylesheet" href="../assets/css/admin/payroll.css">

<?php if (isset($_SESSION['message'])): ?>
<div class="alert alert-<?= $_SESSION['type'] ?? 'success' ?> alert-dismissible fade show" role="alert">
    <i class="fas fa-<?= $_SESSION['type'] === 'danger' ? 'exclamation-triangle' : 'check-circle' ?> me-2"></i>
    <?= $_SESSION['message'] ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php unset($_SESSION['message'], $_SESSION['type']); endif; ?>

<!-- Page Header -->
<div class="page-header">
    <div class="header-content">
        <div class="header-left">
            <h1 class="page-title gym-brand">
                <i class="fas fa-money-bill-wave gym-icon me-2"></i>
                Payroll History
            </h1>
            <p class="page-subtitle">Manage and track staff payroll records</p>
        </div>
        <div class="header-right">
            <div class="header-actions">
                <button class="btn btn-outline-light btn-back" id="backToStaffPayroll" title="Back to Staff Payroll">
                    <i class="fas fa-arrow-left me-2"></i>Back to Staff Payroll
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stats-card border-left-primary">
        <div class="stats-content">
            <div class="stats-icon">
                <i class="fas fa-list"></i>
            </div>
            <div class="stats-info">
                <div class="stats-label">Total Records</div>
                <div class="stats-value"><?= $totalRecords ?></div>
            </div>
        </div>
    </div>

    <div class="stats-card border-left-success">
        <div class="stats-content">
            <div class="stats-icon">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stats-info">
                <div class="stats-label">Total Pay</div>
                <div class="stats-value">₱<?= number_format($totalPay, 2) ?></div>
            </div>
        </div>
    </div>

    <div class="stats-card border-left-warning">
        <div class="stats-content">
            <div class="stats-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stats-info">
                <div class="stats-label">Pending</div>
                <div class="stats-value"><?= $pendingRecords ?></div>
            </div>
        </div>
    </div>

    <div class="stats-card border-left-info">
        <div class="stats-content">
            <div class="stats-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stats-info">
                <div class="stats-label">Paid</div>
                <div class="stats-value"><?= $paidRecords ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Main Content Card -->
<div class="main-content-card">
    <div class="card-header">
        <div class="header-content">
            <div class="header-left">
                <h6 class="card-title gym-brand">
                    <i class="fas fa-history gym-icon me-2"></i>Payroll Records
                </h6>
                <p class="card-subtitle">View and manage payroll history</p>
            </div>
            <div class="header-actions">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" class="search-input" id="searchInput" placeholder="Search payroll records...">
                </div>
            </div>
        </div>
    </div>

    <div class="card-body">
        <!-- Advanced Filters -->
        <div class="filters-section">
            <div class="filters-header">
                <h6 class="filters-title">
                    <i class="fas fa-filter me-2"></i>Filter Records
                </h6>
                <button class="btn btn-sm btn-outline-secondary" id="toggleFilters">
                    <i class="fas fa-chevron-down me-1"></i>Toggle Filters
                </button>
            </div>
            <div class="filters-content" id="filtersContent">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label class="filter-label">
                            <i class="fas fa-calendar-alt me-1"></i>Month
                        </label>
                        <input type="month" class="filter-input" id="monthFilter" value="<?= $selectedMonth ?>">
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">
                            <i class="fas fa-tag me-1"></i>Status
                        </label>
                        <select class="filter-select" id="statusFilter">
                            <option value="">All Status</option>
                            <option value="pending" <?= $selectedStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="paid" <?= $selectedStatus === 'paid' ? 'selected' : '' ?>>Paid</option>
                            <option value="cancelled" <?= $selectedStatus === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">
                            <i class="fas fa-user-tie me-1"></i>Staff
                        </label>
                        <select class="filter-select" id="staffFilter">
                            <option value="">All Staff</option>
                            <?php foreach ($allStaff as $staff): ?>
                            <option value="<?= $staff['staff_id'] ?>" <?= $selectedStaff === $staff['staff_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($staff['staff_id'] . ' - ' . $staff['first_name'] . ' ' . $staff['last_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button class="btn btn-primary btn-apply" id="applyFilters">
                            <i class="fas fa-filter me-1"></i>Apply
                        </button>
                        <button class="btn btn-outline-secondary btn-clear" id="clearFilters">
                            <i class="fas fa-times me-1"></i>Clear
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payroll History Table -->
        <div class="table-container">
            <table class="table table-hover payroll-history-table" id="payrollHistoryTable">
                <thead>
                    <tr>
                        <th width="5%">
                            <i class="fas fa-hashtag me-1"></i>ID
                        </th>
                        <th width="20%">
                            <i class="fas fa-user-tie me-1"></i>Staff
                        </th>
                        <th width="15%">
                            <i class="fas fa-calendar me-1"></i>Period
                        </th>
                        <th width="8%" class="text-center">
                            <i class="fas fa-clock me-1"></i>Hours
                        </th>
                        <th width="10%" class="text-end">
                            <i class="fas fa-dollar-sign me-1"></i>Rate
                        </th>
                        <th width="12%" class="text-end">
                            <i class="fas fa-money-bill-wave me-1"></i>Total Pay
                        </th>
                        <th width="10%" class="text-center">
                            <i class="fas fa-tag me-1"></i>Status
                        </th>
                        <th width="12%" class="text-center">
                            <i class="fas fa-calendar-check me-1"></i>Payment Date
                        </th>
                        <th width="15%" class="text-center">
                            <i class="fas fa-cogs me-1"></i>Actions
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?= renderPayrollHistoryRows($payrollHistory) ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- View Payroll Record Modal -->
<div class="modal fade payroll-modal" id="viewRecordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div class="header-content">
                    <div class="header-icon">
                        <i class="fas fa-eye"></i>
                    </div>
                    <div class="header-text">
                        <h5 class="modal-title">Payroll Record Details</h5>
                        <p class="modal-subtitle">View complete payroll record information</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="recordDetails">
                    <!-- populated by JS -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Payroll Record Modal -->
<div class="modal fade payroll-modal" id="editRecordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <div class="header-content">
                    <div class="header-icon">
                        <i class="fas fa-edit"></i>
                    </div>
                    <div class="header-text">
                        <h5 class="modal-title">Edit Payroll Record</h5>
                        <p class="modal-subtitle">Update payroll record details</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editRecordForm">
                <div class="modal-body">
                    <input type="hidden" name="record_id" id="edit_record_id">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Period Start</label>
                            <input type="date" class="form-control" name="period_start" id="edit_period_start" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Period End</label>
                            <input type="date" class="form-control" name="period_end" id="edit_period_end" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Hours Worked</label>
                            <input type="number" class="form-control" name="hours_worked" id="edit_hours_worked" step="0.1" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Hourly Rate (₱)</label>
                            <input type="number" class="form-control" name="hourly_rate" id="edit_hourly_rate" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="edit_status" required>
                                <option value="pending">Pending</option>
                                <option value="paid">Paid</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Payment Date</label>
                            <input type="date" class="form-control" name="payment_date" id="edit_payment_date">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" id="edit_notes" rows="3" placeholder="Additional notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>


<script>
// Initialize modals
const viewModal = new bootstrap.Modal(document.getElementById('viewRecordModal'));
const editModal = new bootstrap.Modal(document.getElementById('editRecordModal'));

// Toggle filters
document.getElementById('toggleFilters').addEventListener('click', function() {
    const filtersContent = document.getElementById('filtersContent');
    const icon = this.querySelector('i');
    
    if (filtersContent.style.display === 'none') {
        filtersContent.style.display = 'block';
        icon.className = 'fas fa-chevron-up me-1';
    } else {
        filtersContent.style.display = 'none';
        icon.className = 'fas fa-chevron-down me-1';
    }
});

// Search functionality
document.getElementById('searchInput').addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('.payroll-history-row');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});

// Apply Filters
document.getElementById('applyFilters').addEventListener('click', function() {
    const month = document.getElementById('monthFilter').value;
    const status = document.getElementById('statusFilter').value;
    const staff = document.getElementById('staffFilter').value;
    
    const params = new URLSearchParams();
    if (month) params.append('month', month);
    if (status) params.append('status', status);
    if (staff) params.append('staff_id', staff);
    
    window.location.href = 'index.php?page=payroll_history&' + params.toString();
});

// Clear Filters
document.getElementById('clearFilters').addEventListener('click', function() {
    window.location.href = 'index.php?page=payroll_history';
});

// View Record Details
document.addEventListener('click', function(e) {
    if (e.target.closest('.btn-view-record')) {
        const btn = e.target.closest('.btn-view-record');
        const recordId = btn.dataset.recordId;
        
        // Show loading state
        document.getElementById('recordDetails').innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        `;
        
        viewModal.show();
        
        // Fetch record data
        fetch(`actions/staff_actions.php?action=view_payroll_history&id=${encodeURIComponent(recordId)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const record = data.record;
                    document.getElementById('recordDetails').innerHTML = `
                        <div class="record-summary">
                            <div class="summary-card">
                                <div class="summary-header">
                                    <i class="fas fa-user-tie"></i>
                                    <h6>Staff Information</h6>
                                </div>
                                <div class="summary-content">
                                    <div class="summary-item">
                                        <span class="label">Staff ID:</span>
                                        <span class="value">${record.staff_code}</span>
                                    </div>
                                    <div class="summary-item">
                                        <span class="label">Name:</span>
                                        <span class="value">${record.first_name} ${record.last_name}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="summary-card">
                                <div class="summary-header">
                                    <i class="fas fa-money-bill-wave"></i>
                                    <h6>Payroll Information</h6>
                                </div>
                                <div class="summary-content">
                                    <div class="summary-item">
                                        <span class="label">Period:</span>
                                        <span class="value">${new Date(record.period_start).toLocaleDateString()} - ${new Date(record.period_end).toLocaleDateString()}</span>
                                    </div>
                                    <div class="summary-item">
                                        <span class="label">Hours Worked:</span>
                                        <span class="value">${record.hours_worked} hours</span>
                                    </div>
                                    <div class="summary-item">
                                        <span class="label">Hourly Rate:</span>
                                        <span class="value">₱${parseFloat(record.hourly_rate).toLocaleString()}</span>
                                    </div>
                                    <div class="summary-item">
                                        <span class="label">Total Pay:</span>
                                        <span class="value total-pay">₱${parseFloat(record.total_pay).toLocaleString()}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="summary-card">
                                <div class="summary-header">
                                    <i class="fas fa-info-circle"></i>
                                    <h6>Status Information</h6>
                                </div>
                                <div class="summary-content">
                                    <div class="summary-item">
                                        <span class="label">Status:</span>
                                        <span class="value">
                                            <span class="badge bg-${record.status === 'paid' ? 'success' : record.status === 'pending' ? 'warning' : 'danger'}">
                                                ${record.status.charAt(0).toUpperCase() + record.status.slice(1)}
                                            </span>
                                        </span>
                                    </div>
                                    <div class="summary-item">
                                        <span class="label">Payment Date:</span>
                                        <span class="value">${record.payment_date ? new Date(record.payment_date).toLocaleDateString() : 'Not paid'}</span>
                                    </div>
                                    <div class="summary-item">
                                        <span class="label">Notes:</span>
                                        <span class="value">${record.notes || 'No notes'}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                } else {
                    document.getElementById('recordDetails').innerHTML = `
                        <div class="alert alert-danger">${data.message || 'Failed to load record data'}</div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('recordDetails').innerHTML = `
                    <div class="alert alert-danger">An error occurred while loading record data</div>
                `;
            });
    }
});

// Edit Record - Open Modal
document.addEventListener('click', function(e) {
    if (e.target.closest('.btn-edit-record')) {
        const btn = e.target.closest('.btn-edit-record');
        const recordId = btn.dataset.recordId;
        const submitBtn = document.querySelector('#editRecordForm button[type="submit"]');
        
        // Show loading state
        submitBtn.innerHTML = `
            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading...
        `;
        submitBtn.disabled = true;
        
        editModal.show();
        
        // Fetch record data
        fetch(`actions/staff_actions.php?action=view_payroll_history&id=${encodeURIComponent(recordId)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const record = data.record;
                    document.getElementById('edit_record_id').value = record.id;
                    document.getElementById('edit_period_start').value = record.period_start;
                    document.getElementById('edit_period_end').value = record.period_end;
                    document.getElementById('edit_hours_worked').value = record.hours_worked;
                    document.getElementById('edit_hourly_rate').value = record.hourly_rate;
                    document.getElementById('edit_status').value = record.status;
                    document.getElementById('edit_payment_date').value = record.payment_date || '';
                    document.getElementById('edit_notes').value = record.notes || '';
                } else {
                    alert(data.message || 'Failed to load record data');
                    editModal.hide();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while loading record data');
                editModal.hide();
            })
            .finally(() => {
                submitBtn.innerHTML = 'Save Changes';
                submitBtn.disabled = false;
            });
    }
});

// Edit Record - Form Submission
document.getElementById('editRecordForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const form = e.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.innerHTML;
    
    // Show loading state
    submitBtn.innerHTML = `
        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...
    `;
    submitBtn.disabled = true;
    
    // Prepare form data
    const formData = new FormData(form);
    formData.append('action', 'edit_payroll_history');
    
    // Send request
    fetch('actions/staff_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Close modal
            editModal.hide();
            
            // Show success message
            alert('Payroll record updated successfully!');
            
            // Reload page to show updated data
            location.reload();
        } else {
            throw new Error(data.message || 'Update failed');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert(error.message || 'An error occurred while updating record');
    })
    .finally(() => {
        submitBtn.innerHTML = originalBtnText;
        submitBtn.disabled = false;
    });
});


// Mark as Paid
document.addEventListener('click', function(e) {
    if (e.target.closest('.btn-mark-paid')) {
        const btn = e.target.closest('.btn-mark-paid');
        const recordId = btn.dataset.recordId;
        
        if (confirm('Mark this payroll record as paid?')) {
            const originalBtnText = btn.innerHTML;
            
            // Show loading state
            btn.innerHTML = `
                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
            `;
            btn.disabled = true;
            
            // Send request
            fetch('actions/staff_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=mark_payroll_paid&record_id=${encodeURIComponent(recordId)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Payroll record marked as paid successfully!');
                    // Update button to show paid status
                    btn.innerHTML = '<i class="fas fa-check-circle"></i>';
                    btn.classList.remove('btn-outline-success');
                    btn.classList.add('btn-outline-success');
                } else {
                    throw new Error(data.message || 'Mark as paid failed');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert(error.message || 'An error occurred while marking as paid');
                btn.innerHTML = originalBtnText;
                btn.disabled = false;
            });
        }
    }
});

// Back to Staff Payroll
document.getElementById('backToStaffPayroll').addEventListener('click', function() {
    window.location.href = 'index.php?page=staff_payroll';
});

</script>

<?php include 'components/footer.php'; ?>