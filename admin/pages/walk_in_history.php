<?php
// Set timezone to Philippines for correct time display
date_default_timezone_set('Asia/Manila');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/db.php';
require_once '../includes/auth.php';

$page_title = "Walk-In History";

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    header("Location: index.php?page=login");
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'delete_walk_in':
                deleteWalkInHistory($conn);
                break;
            case 'export_data':
                exportWalkInHistory($conn);
                break;
        }
    }
}

// Get filter parameters
$select_date = $_GET['select_date'] ?? '';
$purpose_filter = $_GET['purpose'] ?? '';
$current_page = max(1, (int)($_GET['current_page'] ?? 1));
$per_page = 20;
$offset = ($current_page - 1) * $per_page;

// Get walk-in history data with filters
$walk_ins = getWalkInHistory($conn, $select_date, $purpose_filter, $per_page, $offset);
$total_records = getTotalWalkInRecords($conn, $select_date, $purpose_filter);
$total_pages = ceil($total_records / $per_page);

// Get statistics
$total_revenue = getTotalRevenue($conn, $select_date);
$total_walk_ins = getTotalWalkIns($conn, $select_date);
$students = getStudentCount($conn, $select_date);
$regular = getRegularCount($conn, $select_date);

function deleteWalkInHistory($conn) {
    $id = (int)$_POST['walk_in_id'];
    
    $sql = "DELETE FROM walk_in WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Walk-in record deleted successfully!";
    } else {
        $_SESSION['error'] = "Error deleting walk-in record: " . $conn->error;
    }
    
    $stmt->close();
}

function exportWalkInHistory($conn) {
    $select_date = $_POST['select_date'] ?? '';
    $purpose_filter = $_POST['purpose'] ?? '';
    
    $walk_ins = getWalkInHistory($conn, $select_date, $purpose_filter, 10000, 0);
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="walk_in_history_' . date('Y-m-d_H-i-s') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add CSV headers
    fputcsv($output, array('ID', 'First Name', 'Last Name', 'Address', 'Gender', 'Age', 'Visit Date', 'Time In', 'Purpose', 'Payment Amount', 'Status', 'Payment Method', 'Notes', 'Created At'));
    
    // Add data rows
    foreach ($walk_ins as $walk_in) {
        fputcsv($output, array(
            $walk_in['id'],
            $walk_in['first_name'],
            $walk_in['last_name'],
            $walk_in['address'],
            $walk_in['gender'],
            $walk_in['age'],
            $walk_in['visit_date'],
            $walk_in['time_in'],
            $walk_in['purpose'],
            $walk_in['payment_amount'],
            $walk_in['status'],
            $walk_in['payment_method'],
            $walk_in['notes'],
            $walk_in['created_at']
        ));
    }
    
    fclose($output);
    exit();
}

function getWalkInHistory($conn, $select_date, $purpose_filter, $per_page, $offset) {
    $where_conditions = array();
    $params = array();
    $types = '';
    
    if ($select_date) {
        $where_conditions[] = "visit_date = ?";
        $params[] = $select_date;
        $types .= 's';
    }
    
    if ($purpose_filter) {
        $where_conditions[] = "purpose = ?";
        $params[] = $purpose_filter;
        $types .= 's';
    }
    
    $where_clause = '';
    if (!empty($where_conditions)) {
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    }
    
    $sql = "SELECT * FROM walk_in $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = $per_page;
    $params[] = $offset;
    $types .= 'ii';
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getTotalWalkInRecords($conn, $select_date, $purpose_filter) {
    $where_conditions = array();
    $params = array();
    $types = '';
    
    if ($select_date) {
        $where_conditions[] = "visit_date = ?";
        $params[] = $select_date;
        $types .= 's';
    }
    
    if ($purpose_filter) {
        $where_conditions[] = "purpose = ?";
        $params[] = $purpose_filter;
        $types .= 's';
    }
    
    $where_clause = '';
    if (!empty($where_conditions)) {
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    }
    
    $sql = "SELECT COUNT(*) as total FROM walk_in $where_clause";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['total'];
}

function getTotalRevenue($conn, $select_date = '') {
    $where_conditions = array();
    $params = array();
    $types = '';
    
    if ($select_date) {
        $where_conditions[] = "visit_date = ?";
        $params[] = $select_date;
        $types .= 's';
    }
    
    $where_conditions[] = "payment_amount IS NOT NULL AND payment_amount > 0";
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    $sql = "SELECT SUM(payment_amount) as total FROM walk_in $where_clause";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['total'] ?? 0;
}

function getTotalWalkIns($conn, $select_date = '') {
    $where_conditions = array();
    $params = array();
    $types = '';
    
    if ($select_date) {
        $where_conditions[] = "visit_date = ?";
        $params[] = $select_date;
        $types .= 's';
    }
    
    $where_clause = '';
    if (!empty($where_conditions)) {
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    }
    
    $sql = "SELECT COUNT(*) as total FROM walk_in $where_clause";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['total'];
}

function getStudentCount($conn, $select_date = '') {
    $where_conditions = array("purpose IN ('trial', 'student')");
    $params = array();
    $types = '';
    
    if ($select_date) {
        $where_conditions[] = "visit_date = ?";
        $params[] = $select_date;
        $types .= 's';
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    $sql = "SELECT COUNT(*) as total FROM walk_in $where_clause";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['total'];
}

function getRegularCount($conn, $select_date = '') {
    $where_conditions = array("purpose IN ('gym_visit', 'regular')");
    $params = array();
    $types = '';
    
    if ($select_date) {
        $where_conditions[] = "visit_date = ?";
        $params[] = $select_date;
        $types .= 's';
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    $sql = "SELECT COUNT(*) as total FROM walk_in $where_clause";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['total'];
}

include 'components/header.php';
?>

<!-- Your dashboard content starts here -->

<!-- Alert Messages -->
<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i>
        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Page Header -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-0 text-primary">
                            <i class="fas fa-history"></i> Walk-In History
                        </h1>
                        <p class="text-muted mb-0">View and manage all walk-in customer records</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="index.php?page=walk_in" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left"></i> Back to Walk-In
                        </a>
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#exportModal">
                            <i class="fas fa-download"></i> Export Data
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Total Walk-ins
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_walk_ins; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Students
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $students; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-graduation-cap fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Regular Customers
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $regular; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Total Revenue
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">₱<?php echo number_format($total_revenue, 2); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-peso-sign fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-filter"></i> Load Records
                </h6>
            </div>
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <input type="hidden" name="page" value="walk_in_history">
                    <div class="col-md-4">
                        <label for="select_date" class="form-label">Select Date</label>
                        <input type="date" class="form-control" id="select_date" name="select_date" value="<?php echo htmlspecialchars($select_date); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="purpose" class="form-label">Purpose</label>
                        <select class="form-select" id="purpose" name="purpose">
                            <option value="">All Purposes</option>
                            <option value="gym_visit" <?php echo $purpose_filter === 'gym_visit' ? 'selected' : ''; ?>>Regular</option>
                            <option value="trial" <?php echo $purpose_filter === 'trial' ? 'selected' : ''; ?>>Student</option>
                            <option value="other" <?php echo $purpose_filter === 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-search"></i> Load
                        </button>
                        <a href="index.php?page=walk_in_history" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Walk-in History Table -->
<div class="row">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-list"></i> Walk-In Records
                    <span class="badge bg-secondary ms-2"><?php echo $total_records; ?> records</span>
                </h6>
                <div class="d-flex gap-2">
                    <span class="text-muted small">
                        Page <?php echo $current_page; ?> of <?php echo $total_pages; ?>
                    </span>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="walkInHistoryTable" width="100%" cellspacing="0">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Customer Info</th>
                                <th>Address</th>
                                <th>Visit Date</th>
                                <th>Time In</th>
                                <th>Purpose</th>
                                <th>Payment</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($walk_ins)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-5">
                                        <div class="text-muted">
                                            <i class="fas fa-inbox fa-3x mb-3"></i>
                                            <h5>No walk-in records found</h5>
                                            <p>Try adjusting your filters or add new walk-in customers</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($walk_ins as $walk_in): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-primary">WI-<?php echo str_pad($walk_in['id'], 4, '0', STR_PAD_LEFT); ?></span>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <strong><?php echo htmlspecialchars($walk_in['first_name'] . ' ' . $walk_in['last_name']); ?></strong>
                                            <div class="d-flex gap-1 mt-1">
                                                <?php if ($walk_in['gender']): ?>
                                                    <span class="badge bg-info"><?php echo ucfirst($walk_in['gender']); ?></span>
                                                <?php endif; ?>
                                                <?php if ($walk_in['age']): ?>
                                                    <span class="badge bg-secondary"><?php echo $walk_in['age']; ?> yrs</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($walk_in['address']): ?>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-map-marker-alt text-primary me-2"></i>
                                                <?php echo htmlspecialchars($walk_in['address']); ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">No address</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="text-dark fw-medium">
                                            <?php echo date('M d, Y', strtotime($walk_in['visit_date'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="text-dark fw-medium">
                                            <?php echo date('H:i', strtotime($walk_in['time_in'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                        $purpose_value = $walk_in['purpose'];
                                        if ($purpose_value == 'gym_visit') {
                                            echo '<span class="badge bg-primary">Regular</span>';
                                        } elseif ($purpose_value == 'trial') {
                                            echo '<span class="badge bg-success">Student</span>';
                                        } elseif ($purpose_value == 'consultation') {
                                            echo '<span class="badge bg-info">Consultation</span>';
                                        } elseif ($purpose_value == 'other') {
                                            echo '<span class="badge bg-secondary">Other</span>';
                                        } else {
                                            echo '<span class="badge bg-secondary">' . ucfirst($purpose_value) . '</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($walk_in['payment_amount'] && $walk_in['payment_amount'] > 0): ?>
                                            <div class="text-success fw-bold">₱<?php echo number_format($walk_in['payment_amount'], 2); ?></div>
                                            <?php if ($walk_in['payment_method']): ?>
                                                <small class="text-muted"><?php echo ucfirst($walk_in['payment_method']); ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-danger fst-italic">No payment</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $status = $walk_in['status'] ?? 'active';
                                        $statusClass = $status === 'active' ? 'bg-success' : 'bg-secondary';
                                        ?>
                                        <span class="badge <?php echo $statusClass; ?>">
                                            <?php echo ucfirst($status); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <button class="btn btn-sm btn-info" onclick="viewDetails(<?php echo $walk_in['id']; ?>)" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="deleteRecord(<?php echo $walk_in['id']; ?>)" title="Delete Record">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Walk-in history pagination" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($current_page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['current_page' => $current_page - 1])); ?>">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <li class="page-item <?php echo $i === $current_page ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['current_page' => $i])); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['current_page' => $current_page + 1])); ?>">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="detailsModalLabel">
                    <i class="fas fa-eye"></i> Walk-In Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="exportModalLabel">
                    <i class="fas fa-download"></i> Export Walk-In Data
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="exportForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="export_data">
                    
                    <div class="mb-3">
                        <label for="export_select_date" class="form-label">Select Date:</label>
                        <input type="date" class="form-control" id="export_select_date" name="select_date" value="<?php echo htmlspecialchars($select_date); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="export_purpose" class="form-label">Purpose:</label>
                        <select class="form-select" id="export_purpose" name="purpose">
                            <option value="">All Purposes</option>
                            <option value="gym_visit" <?php echo $purpose_filter === 'gym_visit' ? 'selected' : ''; ?>>Regular</option>
                            <option value="trial" <?php echo $purpose_filter === 'trial' ? 'selected' : ''; ?>>Student</option>
                            <option value="other" <?php echo $purpose_filter === 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-download"></i> Export to CSV
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// View walk-in details
function viewDetails(walkInId) {
    // You can implement AJAX call here to load details
    // For now, just show a simple message
    const modalBody = document.getElementById('modalBody');
    modalBody.innerHTML = `
        <div class="text-center py-4">
            <i class="fas fa-spinner fa-spin fa-2x text-primary mb-3"></i>
            <p>Loading walk-in details...</p>
        </div>
    `;
    
    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
    modal.show();
    
    // Simulate loading (replace with actual AJAX call)
    setTimeout(() => {
        modalBody.innerHTML = `
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                Walk-in ID: WI-${String(walkInId).padStart(4, '0')}
            </div>
            <p>Detailed information for this walk-in customer would be displayed here.</p>
            <p>You can implement AJAX functionality to load real data from the database.</p>
        `;
    }, 1000);
}

// Delete walk-in record
function deleteRecord(walkInId) {
    if (confirm('Are you sure you want to delete this walk-in record? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_walk_in">
            <input type="hidden" name="walk_in_id" value="${walkInId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Auto-submit export form when modal is shown
document.getElementById('exportModal').addEventListener('shown.bs.modal', function () {
    // Reset form values
    document.getElementById('export_select_date').value = '<?php echo htmlspecialchars($select_date); ?>';
    document.getElementById('export_purpose').value = '<?php echo htmlspecialchars($purpose_filter); ?>';
});
</script>

<style>
/* Enhanced Table Design with Transparency for Walk-In History */
.card.shadow {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.05) 0%, rgba(255, 255, 255, 0.02) 100%) !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
    backdrop-filter: blur(10px) !important;
    border-radius: 20px !important;
}

.card-header {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.2) 0%, rgba(118, 75, 162, 0.2) 100%) !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
    border-radius: 20px 20px 0 0 !important;
    backdrop-filter: blur(10px) !important;
}

.card-body {
    background-color: transparent !important;
    border: none !important;
    padding: 1.5rem !important;
}

.table {
    background-color: transparent !important;
    color: white !important;
    border-radius: 15px !important;
    overflow: hidden !important;
}

.table thead th {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.3) 0%, rgba(118, 75, 162, 0.3) 100%) !important;
    border: none !important;
    color: white !important;
    font-weight: 600 !important;
    text-transform: uppercase !important;
    letter-spacing: 1px !important;
    padding: 1rem !important;
    font-size: 0.85rem !important;
    backdrop-filter: blur(10px) !important;
    position: relative !important;
}

.table thead th::after {
    content: '' !important;
    position: absolute !important;
    bottom: 0 !important;
    left: 0 !important;
    right: 0 !important;
    height: 2px !important;
    background: linear-gradient(90deg, #667eea, #764ba2) !important;
}

.table tbody td {
    background-color: transparent !important;
    border: none !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
    color: white !important;
    padding: 1rem !important;
    vertical-align: middle !important;
    transition: all 0.3s ease !important;
}

.table tbody tr {
    transition: all 0.3s ease !important;
    position: relative !important;
}

.table tbody tr:hover {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%) !important;
    transform: translateY(-2px) !important;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2) !important;
    border-radius: 10px !important;
}

.table tbody tr:hover td {
    border-bottom-color: rgba(102, 126, 234, 0.3) !important;
}

/* Enhanced badge styles */
.badge {
    padding: 0.5rem 1rem !important;
    border-radius: 20px !important;
    font-weight: 600 !important;
    letter-spacing: 0.5px !important;
    text-transform: uppercase !important;
    font-size: 0.75rem !important;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2) !important;
}

.badge.bg-success {
    background: linear-gradient(135deg, #10b981, #059669) !important;
    border: 1px solid rgba(16, 185, 129, 0.3) !important;
}

.badge.bg-primary {
    background: linear-gradient(135deg, #3b82f6, #1d4ed8) !important;
    border: 1px solid rgba(59, 130, 246, 0.3) !important;
}

.badge.bg-info {
    background: linear-gradient(135deg, #06b6d4, #0891b2) !important;
    border: 1px solid rgba(6, 182, 212, 0.3) !important;
}

.badge.bg-secondary {
    background: linear-gradient(135deg, #6b7280, #4b5563) !important;
    border: 1px solid rgba(107, 114, 128, 0.3) !important;
}

.badge.bg-danger {
    background: linear-gradient(135deg, #ef4444, #dc2626) !important;
    border: 1px solid rgba(239, 68, 68, 0.3) !important;
}

/* Enhanced text styles */
.text-success {
    color: #10b981 !important;
    font-weight: 600 !important;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3) !important;
}

.text-danger {
    color: #ef4444 !important;
    font-weight: 600 !important;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3) !important;
}

.text-muted {
    color: rgba(255, 255, 255, 0.6) !important;
}

.text-dark {
    color: white !important;
}

/* Enhanced icons */
.fas {
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3)) !important;
}

/* Empty state styling */
.table tbody tr td[colspan] {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.05) 0%, rgba(255, 255, 255, 0.02) 100%) !important;
    border-radius: 15px !important;
    backdrop-filter: blur(10px) !important;
}

/* Responsive table */
.table-responsive {
    border-radius: 15px !important;
    overflow: hidden !important;
}

/* Enhanced button styles with Glass Morphism */
.btn {
    border-radius: 12px !important;
    font-weight: 600 !important;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1) !important;
    border: none !important;
    box-shadow: 
        0 4px 15px rgba(0, 0, 0, 0.2),
        0 0 0 1px rgba(255, 255, 255, 0.05) !important;
    backdrop-filter: blur(10px) !important;
    position: relative !important;
    overflow: hidden !important;
    padding: 0.75rem 1.5rem !important;
    font-size: 0.95rem !important;
}

.btn::before {
    content: '' !important;
    position: absolute !important;
    top: 0 !important;
    left: -100% !important;
    width: 100% !important;
    height: 100% !important;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent) !important;
    transition: left 0.5s ease !important;
}

.btn:hover::before {
    left: 100% !important;
}

.btn:hover {
    transform: translateY(-3px) scale(1.02) !important;
    box-shadow: 
        0 8px 25px rgba(0, 0, 0, 0.3),
        0 0 0 1px rgba(255, 255, 255, 0.1) !important;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    color: white !important;
    box-shadow: 
        0 4px 15px rgba(102, 126, 234, 0.3),
        0 0 0 1px rgba(102, 126, 234, 0.2) !important;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%) !important;
    box-shadow: 
        0 8px 25px rgba(102, 126, 234, 0.4),
        0 0 0 1px rgba(102, 126, 234, 0.3) !important;
}

.btn-outline-secondary {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0.05) 100%) !important;
    border: 2px solid rgba(255, 255, 255, 0.2) !important;
    color: white !important;
    backdrop-filter: blur(10px) !important;
}

.btn-outline-secondary:hover {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.2) 0%, rgba(255, 255, 255, 0.1) 100%) !important;
    border-color: rgba(255, 255, 255, 0.4) !important;
    color: white !important;
}

.btn-sm {
    padding: 0.375rem 0.75rem !important;
    font-size: 0.875rem !important;
}

/* Enhanced pagination */
.pagination {
    gap: 0.25rem !important;
}

.page-link {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%) !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    color: white !important;
    border-radius: 10px !important;
    backdrop-filter: blur(10px) !important;
    transition: all 0.3s ease !important;
}

.page-link:hover {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.2) 0%, rgba(118, 75, 162, 0.2) 100%) !important;
    color: white !important;
    transform: translateY(-1px) !important;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2) !important;
}

.page-item.active .page-link {
    background: linear-gradient(135deg, #667eea, #764ba2) !important;
    border-color: #667eea !important;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3) !important;
}

/* Enhanced form controls with Glass Morphism */
.form-control, .form-select {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.12) 0%, rgba(255, 255, 255, 0.05) 100%) !important;
    border: 2px solid rgba(255, 255, 255, 0.2) !important;
    color: white !important;
    border-radius: 12px !important;
    backdrop-filter: blur(12px) !important;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1) !important;
    box-shadow: 
        0 4px 15px rgba(0, 0, 0, 0.1),
        inset 0 1px 0 rgba(255, 255, 255, 0.1) !important;
    position: relative !important;
    overflow: hidden !important;
    padding: 0.75rem 1rem !important;
    font-size: 1rem !important;
}

.form-control:focus, .form-select:focus {
    background: rgba(255, 255, 255, 0.18) !important;
    border-color: #667eea !important;
    box-shadow: 
        0 0 0 3px rgba(102, 126, 234, 0.1),
        0 8px 25px rgba(102, 126, 234, 0.2),
        inset 0 1px 0 rgba(255, 255, 255, 0.2) !important;
    color: white !important;
    transform: translateY(-2px) !important;
}

.form-control::before, .form-select::before {
    content: '' !important;
    position: absolute !important;
    top: 0 !important;
    left: -100% !important;
    width: 100% !important;
    height: 100% !important;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent) !important;
    transition: left 0.5s ease !important;
}

.form-control:focus::before, .form-select:focus::before {
    left: 100% !important;
}

.form-control::placeholder {
    color: rgba(255, 255, 255, 0.6) !important;
}

.form-label {
    color: white !important;
    font-weight: 600 !important;
    margin-bottom: 0.5rem !important;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3) !important;
    font-size: 0.95rem !important;
}

/* Enhanced alert styles */
.alert {
    border-radius: 15px !important;
    border: none !important;
    backdrop-filter: blur(10px) !important;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2) !important;
}

.alert-success {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(5, 150, 105, 0.1) 100%) !important;
    color: #10b981 !important;
    border-left: 4px solid #10b981 !important;
}

.alert-danger {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(220, 38, 38, 0.1) 100%) !important;
    color: #ef4444 !important;
    border-left: 4px solid #ef4444 !important;
}
</style>

<?php include 'components/footer.php'; ?>
