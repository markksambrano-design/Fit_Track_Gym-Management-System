<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$page_title = "Staff Management";
include 'components/header.php';
include '../includes/db.php';

// Query: All Staff
$allStaffResult = $conn->query("
    SELECT 
        s.staff_id, s.first_name, s.last_name, s.email, s.phone, s.hire_date,
        s.address, s.gender, s.photo, s.qr_code_data, s.schedule,
        p.salary, p.employment_type, p.bank_name, p.account_number
    FROM staff s
    LEFT JOIN payroll p ON s.id = p.staff_id
    ORDER BY s.hire_date DESC
");
$allStaff = $allStaffResult ? $allStaffResult->fetch_all(MYSQLI_ASSOC) : [];

// Query: Active Staff (employed within last 2 years)
$activeStaffResult = $conn->query("
    SELECT 
        s.staff_id, s.first_name, s.last_name, s.email, s.phone, s.hire_date,
        s.address, s.gender, s.photo, s.qr_code_data, s.schedule,
        p.salary, p.employment_type, p.bank_name, p.account_number
    FROM staff s
    LEFT JOIN payroll p ON s.id = p.staff_id
    WHERE s.hire_date >= DATE_SUB(CURDATE(), INTERVAL 2 YEAR)
    ORDER BY s.hire_date DESC
");
$activeStaff = $activeStaffResult ? $activeStaffResult->fetch_all(MYSQLI_ASSOC) : [];

// Query: Long-term Staff (employed for more than 2 years)
$longTermStaffResult = $conn->query("
    SELECT 
        s.staff_id, s.first_name, s.last_name, s.email, s.phone, s.hire_date,
        s.address, s.gender, s.photo, s.qr_code_data, s.schedule,
        p.salary, p.employment_type, p.bank_name, p.account_number
    FROM staff s
    LEFT JOIN payroll p ON s.id = p.staff_id
    WHERE s.hire_date < DATE_SUB(CURDATE(), INTERVAL 2 YEAR)
    ORDER BY s.hire_date DESC
");
$longTermStaff = $longTermStaffResult ? $longTermStaffResult->fetch_all(MYSQLI_ASSOC) : [];

// Function to render table rows
function renderStaffRows($staff) {
    if (!$staff) {
        return "<tr><td colspan='7' class='text-center text-muted py-4'><i class='fas fa-users fa-2x mb-2'></i><br>No staff found.</td></tr>";
    }
    
    $rows = "";
    foreach ($staff as $s) {
        $badge = match(strtolower($s['employment_type'] ?? 'full-time')) {
            'full-time' => 'primary',
            'part-time' => 'success',
            'contract'  => 'warning',
            default     => 'secondary'
        };
        // Properly format names - capitalize first letter of each name
        $firstName = ucfirst(strtolower(trim($s['first_name'])));
        $lastName = ucfirst(strtolower(trim($s['last_name'])));
        $fullName = htmlspecialchars($firstName . ' ' . $lastName);
        $staffId = htmlspecialchars($s['staff_id']);
        $email = htmlspecialchars($s['email']);
        $phone = htmlspecialchars($s['phone']);
        $hireDate = htmlspecialchars($s['hire_date']);
        $employmentType = htmlspecialchars(ucfirst($s['employment_type'] ?? 'Full-time'));
        $schedule = htmlspecialchars(ucfirst($s['schedule'] ?? 'Not Set'));
        $salary = $s['salary'] ? '₱' . number_format($s['salary'], 2) : '-';
        $photoHtml = $s['photo'] ? "<img src='../../uploads/staff_photos/{$s['photo']}' alt='Profile Picture' class='staff-photo' onerror='this.style.display=\"none\"; this.nextElementSibling.style.display=\"block\";'>" : "<i class='fas fa-user'></i>";
        if ($s['photo']) {
            $photoHtml .= "<i class='fas fa-user' style='display:none;'></i>";
        }
        
        // Build action buttons
        $actionButtons = "
            <button class='btn btn-outline-info btn-view' data-staff-id='{$staffId}' title='View Staff'>
                <i class='fas fa-eye'></i>
            </button>
            <button class='btn btn-outline-danger btn-delete' data-staff-id='{$staffId}' title='Delete Staff'>
                <i class='fas fa-trash'></i>
            </button>";
        
        $rows .= "
        <tr data-staff-id='{$staffId}' class='staff-row'>
            <td><span class='staff-id-badge'>{$staffId}</span></td>
            <td>
                <div class='staff-info'>
                    <div class='staff-avatar'>{$photoHtml}</div>
                    <div class='staff-details'>
                        <div class='staff-name'>{$fullName}</div>
                        <div class='staff-email text-muted small'>{$email}</div>
                    </div>
                </div>
            </td>
            <td>{$phone}</td>
            <td><span class='badge bg-{$badge} employment-badge'><i class='fas fa-briefcase me-1'></i> {$employmentType}</span></td>
            <td><span class='badge bg-info schedule-badge'><i class='fas fa-clock me-1'></i> {$schedule}</span></td>
            <td>{$hireDate}</td>
            <td>
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
<link rel="stylesheet" href="../assets/css/admin/staff.css">
<style>
.form-control:disabled,
.form-select:disabled,
textarea:disabled {
    background-color: #f8f9fa;
    opacity: 0.6;
    cursor: not-allowed;
}

.form-control:disabled:focus,
.form-select:disabled:focus,
textarea:disabled:focus {
    box-shadow: none;
    border-color: #dee2e6;
}

.form-section.disabled-section {
    opacity: 0.7;
}

.form-section.disabled-section .section-header {
    color: #6c757d;
}




/* Loading optimizations */
.spinner-border {
    animation: spinner-border 0.75s linear infinite;
}

@keyframes spinner-border {
    to { transform: rotate(360deg); }
}


/* Optimize modal loading */
#viewStaffModal .modal-content {
    transition: all 0.3s ease;
}

#viewStaffModal .modal-body {
    min-height: 200px;
}

/* Profile loading states */
.profile-loading {
    opacity: 0.7;
    pointer-events: none;
}

/* Schedule badge styling */
.schedule-badge {
    font-size: 0.75rem;
    padding: 4px 8px;
}

.schedule-badge i {
    font-size: 0.7rem;
}
.trainer-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border: 1px solid #dee2e6;
    border-radius: 0.5rem;
}

.trainer-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.trainer-photo {
    border: 2px solid #e9ecef;
    transition: border-color 0.2s ease;
}

.trainer-card:hover .trainer-photo {
    border-color: #6C5CE7;
}

.trainer-stats {
    font-size: 0.75rem;
    color: #6c757d;
}

.assign-trainer-select {
    min-width: 150px;
}

.manage-trainer-btn {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}
.staff-profile-modal {
    background: #2d3748;
    color: #fff;
    border-radius: 15px;
    border: none;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
}

.staff-profile-header {
    background: linear-gradient(135deg, #3182ce, #2c5282);
    border-bottom: none;
    border-radius: 15px 15px 0 0;
    padding: 20px 25px;
}

.staff-profile-header .header-content {
    display: flex;
    align-items: center;
    gap: 12px;
}

.staff-profile-header .header-icon {
    width: 40px;
    height: 40px;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.staff-profile-header .header-icon i {
    font-size: 1.2rem;
    color: white;
}

.staff-profile-header .header-text h5 {
    font-weight: 600;
    font-size: 1.3rem;
    color: white;
    margin: 0;
}

.staff-profile-header .header-text p {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.85rem;
    margin: 2px 0 0 0;
}

.staff-profile-body {
    background: #2d3748;
    padding: 25px;
}

.staff-profile-footer {
    background: #2d3748;
    border-top: none;
    border-radius: 0 0 15px 15px;
    padding: 15px 25px;
}

/* Staff Summary Card - Matching Member Profile */
.staff-summary-card {
    background: #4a5568;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    border: none;
    display: flex;
    align-items: center;
    gap: 15px;
}

.staff-avatar-large {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    overflow: hidden;
    border: 2px solid #3182ce;
    flex-shrink: 0;
}

.staff-avatar-large img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.staff-avatar-large i {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #3182ce;
    color: white;
    font-size: 2rem;
}

.staff-name {
    font-size: 1.0rem;
    font-weight: 600;
    color: white;
    margin: 0 0 5px 0;
}

.staff-id {
    color: #a0aec0;
    font-size: 0.9rem;
    margin: 0 0 8px 0;
}

.staff-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #38a169;
    color: white;
    padding: 4px 12px;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 500;
}

.staff-status-dot {
    width: 6px;
    height: 6px;
    background: white;
    border-radius: 50%;
}

/* Information Cards - Matching Member Profile */
.info-cards-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 15px;
}

.info-card {
    background: #4a5568;
    border-radius: 12px;
    padding: 18px;
    border: none;
}

.info-card-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 15px;
}

.info-card-icon {
    width: 28px;
    height: 28px;
    background: #3182ce;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.9rem;
}

.info-card-title {
    font-size: 1rem;
    font-weight: 600;
    color: white;
    margin: 0;
}

.info-field {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.info-field:last-child {
    border-bottom: none;
}

.info-label {
    color: #a0aec0;
    font-size: 0.85rem;
    font-weight: 400;
}

.info-value {
    color: white;
    font-weight: 500;
    font-size: 0.85rem;
    text-align: right;
}

.info-badge {
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 0.75rem;
    font-weight: 500;
}

.info-badge.primary {
    background: #3182ce;
    color: white;
}

.info-badge.success {
    background: #38a169;
    color: white;
}

.info-badge.warning {
    background: #d69e2e;
    color: white;
}

/* Address Card - Matching Member Profile */
.address-card {
    background: #4a5568;
    border-radius: 12px;
    padding: 18px;
    border: none;
}

.address-field {
    background: #2d3748;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 6px;
    padding: 10px 12px;
    color: white;
    font-size: 0.85rem;
    width: 100%;
    margin-top: 8px;
}

/* Responsive Design */
@media (max-width: 768px) {
    .info-cards-container {
        grid-template-columns: 1fr;
    }
    
    .staff-profile-body {
        padding: 20px;
    }
    
    .staff-profile-header {
        padding: 15px 20px;
    }
    
    .staff-profile-footer {
        padding: 12px 20px;
    }
    
    .staff-summary-card {
        flex-direction: column;
        text-align: center;
        gap: 10px;
    }
}

</style>

<?php if (isset($_SESSION['message'])): ?>
<div class="alert alert-<?= $_SESSION['type'] ?? 'success' ?> alert-dismissible fade show" role="alert">
    <i class="fas fa-<?= $_SESSION['type'] === 'danger' ? 'exclamation-triangle' : 'check-circle' ?> me-2"></i>
    <?= $_SESSION['message'] ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php unset($_SESSION['message'], $_SESSION['type']); endif; ?>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stats-card border-left-primary">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Total Staff
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= count($allStaff) ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stats-card border-left-success">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Active Staff
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= count($activeStaff) ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user-check fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stats-card border-left-warning">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Long-term Staff
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= count($longTermStaff) ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user-clock fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stats-card border-left-info">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            This Month
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?= count(array_filter($allStaff, function($s) {
                                return date('Y-m', strtotime($s['hire_date'])) === date('Y-m');
                            })) ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-calendar fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Content Card -->
<div class="card main-content-card">
    <div class="card-header">
        <h6 class="gym-brand">
            <i class="fas fa-dumbbell gym-icon"></i>Gym Staff Management
        </h6>
        <div class="d-flex align-items-center"> 
            <div class="input-group me-3" style="width: 300px;">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" class="form-control" id="searchInput" placeholder="Search gym staff...">
            </div>
            <button class="btn btn-primary btn-sm" onclick="window.location.href='index.php?page=trainer'">
                <i class="fas fa-dumbbell me-1"></i>Trainer Management
            </button>
        </div>
    </div>

    <div class="card-body p-0">
        <ul class="nav nav-tabs" id="staffTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all-staff" type="button" role="tab">
                    <i class="fas fa-users me-1"></i>All Staff
                    <span class="badge bg-secondary ms-1"><?= count($allStaff) ?></span>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="active-tab" data-bs-toggle="tab" data-bs-target="#active-staff" type="button" role="tab">
                    <i class="fas fa-user-check me-1"></i>Active Staff
                    <span class="badge bg-success ms-1"><?= count($activeStaff) ?></span>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="longterm-tab" data-bs-toggle="tab" data-bs-target="#longterm-staff" type="button" role="tab">
                    <i class="fas fa-user-clock me-1"></i>Long-term Staff
                    <span class="badge bg-warning ms-1"><?= count($longTermStaff) ?></span>
                </button>
            </li>
            
        </ul>

        <div class="tab-content" id="staffTabsContent">

            <!-- All Staff -->
            <div class="tab-pane fade show active" id="all-staff" role="tabpanel">
                <div class="table-responsive table-container">
                    <table class="table table-hover mb-0" id="staffTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Staff</th>
                                <th>Phone</th>
                                <th>Employment</th>
                                <th>Schedule</th>
                                <th></th>Hire Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?= renderStaffRows($allStaff) ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Active Staff -->
            <div class="tab-pane fade" id="active-staff" role="tabpanel">
                <div class="table-responsive table-container">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Staff</th>
                                <th>Phone</th>
                                <th>Employment</th>
                                <th>Schedule</th>
                                <th></th>Hire Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?= renderStaffRows($activeStaff) ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Long-term Staff -->
            <div class="tab-pane fade" id="longterm-staff" role="tabpanel">
                <div class="table-responsive table-container">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Staff</th>
                                <th>Phone</th>
                                <th>Employment</th>
                                <th>Schedule</th>
                                <th></th>Hire Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?= renderStaffRows($longTermStaff) ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Staff Modal -->
<div class="modal fade" id="viewStaffModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div class="header-content">
                    <div class="header-icon">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <div class="header-text">
                        <h5 class="modal-title">Staff Profile</h5>
                        <p class="modal-subtitle">View complete staff information</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="staffDetails">
                    <!-- populated by JS -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>


<!-- Delete Confirm Modal -->
<div class="modal fade" id="deleteStaffModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle me-2"></i>Delete Staff
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <i class="fas fa-user-times fa-3x text-danger mb-3"></i>
                    <h5>Are you sure?</h5>
                </div>
                <p>You are about to delete <strong id="delete_staff_name"></strong> (<code id="delete_staff_id"></code>).</p>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Warning:</strong> This action cannot be undone and will permanently remove the staff from the system.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cancel
                </button>
                <button type="button" class="btn btn-danger" id="confirmDeleteStaff">
                    <i class="fas fa-trash me-1"></i>Delete Staff
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Search functionality - works across all tabs
document.getElementById('searchInput').addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('.staff-row');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});

// Initialize modals
const viewModal = new bootstrap.Modal(document.getElementById('viewStaffModal'));
const deleteModal = new bootstrap.Modal(document.getElementById('deleteStaffModal'));

// View Staff
document.addEventListener('click', function(e) {
    if (e.target.closest('.btn-view')) {
        const btn = e.target.closest('.btn-view');
        const staffId = btn.dataset.staffId;
        
        // Show loading state
        document.getElementById('staffDetails').innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        `;
        
        viewModal.show();
        
        // Fetch staff data
        fetch(`actions/staff_actions.php?action=view&staff_id=${encodeURIComponent(staffId)}`)
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Staff data received:', data);
                if (data.success) {
                    const s = data.staff;
                    // Properly format names
                    const firstName = s.first_name ? s.first_name.charAt(0).toUpperCase() + s.first_name.slice(1).toLowerCase() : '';
                    const lastName = s.last_name ? s.last_name.charAt(0).toUpperCase() + s.last_name.slice(1).toLowerCase() : '';
                    const fullName = `${firstName} ${lastName}`.trim();
                    
                    const employmentBadge = s.employment_type === 'full-time' ? 'primary' : 
                                          s.employment_type === 'part-time' ? 'success' : 'warning';
                    const employmentText = s.employment_type ? s.employment_type.charAt(0).toUpperCase() + s.employment_type.slice(1) : 'Full-time';
                    
                    document.getElementById('staffDetails').innerHTML = `
                        <!-- Staff Summary Card -->
                        <div class="staff-summary-card">
                            <div class="staff-avatar-large">
                                ${s.photo ? `<img src="../uploads/staff_photos/${s.photo}" alt="Profile Picture">` : `<i class="fas fa-user"></i>`}
                            </div>
                            <div class="staff-name">${fullName}</div>
                            <div class="staff-id">Staff #${s.staff_id}</div>
                            <div class="staff-status">
                                <div class="staff-status-dot"></div>
                                ACTIVE
                            </div>
                        </div>

                        <!-- Information Cards -->
                        <div class="info-cards-container">
                            <!-- Personal Information Card -->
                            <div class="info-card">
                                <div class="info-card-header">
                                    <div class="info-card-icon">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <h6 class="info-card-title">Personal Information</h6>
                                </div>
                                <div class="info-field">
                                    <span class="info-label">Email Address</span>
                                    <span class="info-value">${s.email}</span>
                                </div>
                                <div class="info-field">
                                    <span class="info-label">Phone Number</span>
                                    <span class="info-value">${s.phone || '-'}</span>
                                </div>
                                <div class="info-field">
                                    <span class="info-label">Gender</span>
                                    <span class="info-value">${s.gender ? s.gender.charAt(0).toUpperCase() + s.gender.slice(1) : '-'}</span>
                                </div>
                                <div class="info-field">
                                    <span class="info-label">Age</span>
                                    <span class="info-value">${s.age ? s.age + ' years old' : '-'}</span>
                                </div>
                            </div>

                            <!-- Employment Details Card -->
                            <div class="info-card">
                                <div class="info-card-header">
                                    <div class="info-card-icon">
                                        <i class="fas fa-briefcase"></i>
                                    </div>
                                    <h6 class="info-card-title">Employment Details</h6>
                                </div>
                                <div class="info-field">
                                    <span class="info-label">Employment Type</span>
                                    <span class="info-value">
                                        <span class="info-badge ${employmentBadge}">${employmentText}</span>
                                    </span>
                                </div>
                                <div class="info-field">
                                    <span class="info-label">Daily Salary</span>
                                    <span class="info-value">${
                                        s.schedule === 'morning' || s.schedule === 'afternoon' ? '₱250' : 
                                        (s.salary ? '₱' + parseFloat(s.salary).toLocaleString() : '-')
                                    }</span>
                                </div>
                                <div class="info-field">
                                    <span class="info-label">Hire Date</span>
                                    <span class="info-value">${s.hire_date}</span>
                                </div>
                                <div class="info-field">
                                    <span class="info-label">Schedule</span>
                                    <span class="info-value">${s.schedule ? s.schedule.charAt(0).toUpperCase() + s.schedule.slice(1) + ' Shift' : '-'}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Address Card -->
                        <div class="address-card">
                            <div class="info-card-header">
                                <div class="info-card-icon">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <h6 class="info-card-title">Address</h6>
                            </div>
                            <div class="address-field">${s.address || 'No address provided'}</div>
                        </div>
                    `;
                } else {
                    document.getElementById('staffDetails').innerHTML = `
                        <div class="alert alert-danger">${data.message || 'Failed to load staff data'}</div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('staffDetails').innerHTML = `
                    <div class="alert alert-danger">An error occurred while loading staff data: ${error.message}</div>
                `;
            });
    }
});


// Delete Staff - Open Confirmation
let toDeleteId = null;
let toDeleteName = '';

document.addEventListener('click', function(e) {
    if (e.target.closest('.btn-delete')) {
        const btn = e.target.closest('.btn-delete');
        const staffId = btn.dataset.staffId;
        
        try {
            // Fetch staff data to get name
            fetch(`actions/staff_actions.php?action=view&staff_id=${encodeURIComponent(staffId)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const staff = data.staff;
                        toDeleteId = staff.staff_id;
                        // Properly format names for delete confirmation
                        const firstName = staff.first_name ? staff.first_name.charAt(0).toUpperCase() + staff.first_name.slice(1).toLowerCase() : '';
                        const lastName = staff.last_name ? staff.last_name.charAt(0).toUpperCase() + staff.last_name.slice(1).toLowerCase() : '';
                        toDeleteName = `${firstName} ${lastName}`.trim();
                        
                        // Populate delete modal
                        document.getElementById('delete_staff_name').textContent = toDeleteName;
                        document.getElementById('delete_staff_id').textContent = toDeleteId;
                        
                        // Show delete modal
                        deleteModal.show();
                    } else {
                        alert('Failed to load staff data');
                    }
                })
                .catch(error => {
                    console.error('Error loading staff data:', error);
                    alert('Failed to load staff data');
                });
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to load staff data');
        }
    }
});

// Delete Staff - Confirm
document.getElementById('confirmDeleteStaff').addEventListener('click', function() {
    if (!toDeleteId) return;
    
    const btn = this;
    const originalBtnText = btn.innerHTML;
    
    // Show loading state
    btn.innerHTML = `
        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Deleting...
    `;
    btn.disabled = true;
    
    // Prepare data
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('staff_id', toDeleteId);
    
    // Send request
    fetch('actions/staff_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Delete - Response status:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Delete - Response data:', data);
        if (data.success) {
            // Remove row from table
            const row = document.querySelector(`tr[data-staff-id="${toDeleteId}"]`);
            if (row) row.remove();
            
            // Close modal
            deleteModal.hide();
            
            // Show success message
            alert('Staff deleted successfully!');
            
            // Reload page to update statistics
            window.location.reload();
        } else {
            throw new Error(data.message || 'Delete failed');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert(error.message || 'An error occurred while deleting staff');
    })
    .finally(() => {
        btn.innerHTML = originalBtnText;
        btn.disabled = false;
        toDeleteId = null;
    });
});
</script>

<!-- Link JS file -->
<script src="../assets/js/admin/staff.js"></script>

<?php include 'components/footer.php'; ?>
