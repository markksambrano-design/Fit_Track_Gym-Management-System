<?php
// Set timezone to Philippines for correct time display
date_default_timezone_set('Asia/Manila');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    header("Location: index.php?page=login");
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $redirect = false;
        switch ($_POST['action']) {
            case 'add_walk_in':
                addWalkIn($conn);
                $redirect = true;
                break;
            case 'update_walk_in':
                updateWalkIn($conn);
                $redirect = true;
                break;
            case 'delete_walk_in':
                deleteWalkIn($conn);
                $redirect = true;
                break;
            case 'checkout_walk_in':
                checkoutWalkIn($conn);
                $redirect = true;
                break;
        }
        
        // Redirect to prevent duplicate submissions on refresh
        if ($redirect) {
            header("Location: index.php?page=walk_in");
            exit();
        }
    }
}

// Get walk-in data
$walk_ins = getWalkIns($conn);
$today_walk_ins = getTodayWalkIns($conn);
$total_revenue = getTotalRevenue($conn);

function addWalkIn($conn) {
    $first_name = $conn->real_escape_string($_POST['first_name']);
    $last_name = $conn->real_escape_string($_POST['last_name']);
    $address = $conn->real_escape_string($_POST['address'] ?? '');
    $gender = !empty($_POST['gender']) ? trim($_POST['gender']) : null;
    $age = (int)($_POST['age'] ?? 0);
    $customer_type = $conn->real_escape_string($_POST['customer_type']);
    $payment_amount = (float)($_POST['payment_amount'] ?? 0);
    $payment_method = !empty($_POST['payment_method']) ? $conn->real_escape_string($_POST['payment_method']) : null;
    
    $visit_date = date('Y-m-d');
    $time_in = date('Y-m-d H:i:s');
    
    $sql = "INSERT INTO walk_in (first_name, last_name, address, gender, age, purpose, payment_amount, payment_method, visit_date, time_in) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssisdsss", $first_name, $last_name, $address, $gender, $age, $customer_type, $payment_amount, $payment_method, $visit_date, $time_in);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Walk-in customer added successfully!";
    } else {
        $_SESSION['error'] = "Error adding walk-in customer: " . $conn->error;
    }
    
    $stmt->close();
}

function updateWalkIn($conn) {
    $id = (int)$_POST['walk_in_id'];
    $first_name = $conn->real_escape_string($_POST['first_name']);
    $last_name = $conn->real_escape_string($_POST['last_name']);
    $address = $conn->real_escape_string($_POST['address'] ?? '');
    $gender = !empty($_POST['gender']) ? trim($_POST['gender']) : null;
    $age = (int)($_POST['age'] ?? 0);
    $customer_type = $conn->real_escape_string($_POST['customer_type']);
    $payment_amount = (float)($_POST['payment_amount'] ?? 0);
    
    $sql = "UPDATE walk_in SET first_name=?, last_name=?, address=?, gender=?, age=?, purpose=?, payment_amount=? WHERE id=?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssi", $first_name, $last_name, $address, $gender, $age, $customer_type, $payment_amount, $id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Walk-in customer updated successfully!";
    } else {
        $_SESSION['error'] = "Error updating walk-in customer: " . $conn->error;
    }
    
    $stmt->close();
}

function deleteWalkIn($conn) {
    $id = (int)$_POST['walk_in_id'];
    
    $sql = "DELETE FROM walk_in WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Walk-in customer deleted successfully!";
    } else {
        $_SESSION['error'] = "Error deleting walk-in customer: " . $conn->error;
    }
    
    $stmt->close();
}

function checkoutWalkIn($conn) {
    $id = (int)$_POST['walk_in_id'];
    $time_out = date('Y-m-d H:i:s');
    
    $sql = "UPDATE walk_in SET time_out = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $time_out, $id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Walk-in customer checked out successfully!";
    } else {
        $_SESSION['error'] = "Error checking out walk-in customer: " . $conn->error;
    }
    
    $stmt->close();
}

function getWalkIns($conn) {
    $sql = "SELECT * FROM walk_in ORDER BY visit_date DESC, time_in DESC";
    $result = $conn->query($sql);
    
    $walk_ins = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $walk_ins[] = $row;
        }
    }
    
    return $walk_ins;
}

function getTodayWalkIns($conn) {
    $today = date('Y-m-d');
    $sql = "SELECT * FROM walk_in WHERE DATE(visit_date) = ? ORDER BY time_in DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $walk_ins = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $walk_ins[] = $row;
        }
    }
    
    $stmt->close();
    return $walk_ins;
}

function getTotalRevenue($conn) {
    $today = date('Y-m-d');
    $sql = "SELECT SUM(payment_amount) as total FROM walk_in WHERE DATE(visit_date) = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['total'] ?? 0;
    }
    
    $stmt->close();
    return 0;
}

// Calculate statistics
$total_records = count($walk_ins);
$students = 0;
$regular = 0;

foreach ($walk_ins as $walk_in) {
    if ($walk_in['purpose'] === 'gym_visit') {
        $regular++;
    } elseif ($walk_in['purpose'] === 'trial') {
        $students++;
    }
}

$page_title = "Walk-in";
include 'components/header.php';
?>

<!-- Your dashboard content starts here -->

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Total Records
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_records; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-database fa-2x text-gray-300"></i>
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
                            Today's Revenue
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

<!-- Action Bar -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#walkInModal">
                        <i class="fas fa-plus"></i> Add New Walk-In
                    </button>
                    
                    <div class="d-flex gap-3 align-items-center">
                        <a href="index.php?page=walk_in_history" class="btn btn-outline-secondary">
                            <i class="fas fa-history"></i> View History
                        </a>
                        <span class="badge bg-primary fs-6">
                            <i class="fas fa-calendar-day"></i> <?php echo count($today_walk_ins); ?> walk-ins today
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Today's Walk-ins Table -->
<div class="row">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-list"></i> Today's Walk-Ins
                </h6>
                <button class="btn btn-sm btn-outline-primary" onclick="location.reload()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" id="walkInTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Customer Name</th>
                                <th>Address</th>
                                <th>Details</th>
                                <th>Type</th>
                                <th>Payment</th>
                                <th>Time In</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($today_walk_ins)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <div class="text-muted">
                                            <i class="fas fa-users fa-3x mb-3"></i>
                                            <h5>No walk-ins recorded today</h5>
                                            <p>Start by adding a new walk-in customer using the button above</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($today_walk_ins as $walk_in): ?>
                                <tr>
                                    <td>
                                        <strong>WI-<?php echo str_pad($walk_in['id'], 4, '0', STR_PAD_LEFT); ?></strong>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($walk_in['first_name'] . ' ' . $walk_in['last_name']); ?></strong>
                                    </td>
                                    <td>
                                        <?php if ($walk_in['address']): ?>
                                            <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($walk_in['address']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">No address</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($walk_in['gender'] && $walk_in['age']): ?>
                                            <span class="text-dark">
                                                <?php echo ucfirst($walk_in['gender']); ?>, <?php echo $walk_in['age']; ?> years
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">No details</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $purpose_value = $walk_in['purpose'];
                                        if ($purpose_value == 'trial') {
                                            echo '<span class="badge bg-success">Student</span>';
                                        } elseif ($purpose_value == 'gym_visit') {
                                            echo '<span class="badge bg-primary">Regular</span>';
                                        } else {
                                            echo '<span class="badge bg-primary">Regular</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($walk_in['payment_amount'] && $walk_in['payment_amount'] > 0): ?>
                                            <div class="text-success fw-bold">₱<?php echo number_format($walk_in['payment_amount'], 2); ?></div>
                                        <?php else: ?>
                                            <span class="text-danger fst-italic">No Payment</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="text-dark fw-medium">
                                            <?php echo date('H:i', strtotime($walk_in['time_in'])); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Walk-in Modal -->
<div class="modal fade" id="walkInModal" tabindex="-1" aria-labelledby="walkInModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <!-- Modern Modal Header -->
            <div class="modal-header">
                <div class="d-flex align-items-center">
                    <div class="header-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="header-content">
                        <h3 class="modal-title" id="walkInModalLabel">Add New Walk-In Customer</h3>
                        <p class="header-subtitle">Complete the form below to register a new walk-in customer</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form method="POST" class="needs-validation" novalidate>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_walk_in">
                    
                    <!-- Personal Information Section -->
                    <div class="form-section">
                        <div class="section-header">
                            <div class="section-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <h4>Personal Information</h4>
                            <p>Basic customer details</p>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="first_name">First Name <span class="required">*</span></label>
                                <input type="text" class="form-control" id="first_name" name="first_name" placeholder="Enter first name" required>
                                <div class="invalid-feedback">Please enter the first name.</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="last_name">Last Name <span class="required">*</span></label>
                                <input type="text" class="form-control" id="last_name" name="last_name" placeholder="Enter last name" required>
                                <div class="invalid-feedback">Please enter the last name.</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contact & Demographics Section -->
                    <div class="form-section">
                        <div class="section-header">
                            <div class="section-icon">
                                <i class="fas fa-address-card"></i>
                            </div>
                            <h4>Contact & Demographics</h4>
                            <p>Additional customer information</p>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label for="address">Address/Location</label>
                                <input type="text" class="form-control" id="address" name="address" placeholder="Enter customer's address or location">
                                <small class="form-hint">Enter customer's address or location</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="gender">Gender</label>
                                <select class="form-control" id="gender" name="gender">
                                    <option value="">Select Gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="age">Age</label>
                                <input type="number" class="form-control" id="age" name="age" placeholder="Enter age" min="1" max="120">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Customer Type & Payment Section -->
                    <div class="form-section">
                        <div class="section-header">
                            <div class="section-icon">
                                <i class="fas fa-credit-card"></i>
                            </div>
                            <h4>Customer Type & Payment</h4>
                            <p>Select customer category and view payment</p>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="customer_type">Customer Type <span class="required">*</span></label>
                                <select class="form-control" id="customer_type" name="customer_type" required onchange="updatePaymentAmount()">
                                    <option value="">Select Customer Type</option>
                                    <option value="gym_visit">Regular Customer</option>
                                    <option value="trial">Student/Trial</option>
                                </select>
                                <div class="invalid-feedback">Please select a customer type.</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="payment_amount">Payment Amount</label>
                                <div class="payment-display">
                                    <span class="currency">₱</span>
                                    <input type="number" class="form-control" id="payment_amount" name="payment_amount" placeholder="0.00" step="0.01" readonly>
                                </div>
                                <small class="form-hint">Auto-calculated based on type</small>
                            </div>
                        </div>
                        
                        <!-- Payment Info Cards -->
                        <div class="payment-info-cards" id="paymentInfoCards" style="display: none;">
                            <div class="payment-cards-grid">
                                <div class="payment-card regular-card" style="display: none;">
                                    <div class="card-content">
                                        <div class="card-icon">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <h5>Regular Customer</h5>
                                        <div class="price">₱100.00</div>
                                        <p>Full gym access</p>
                                    </div>
                                </div>
                                <div class="payment-card student-card" style="display: none;">
                                    <div class="card-content">
                                        <div class="card-icon">
                                            <i class="fas fa-graduation-cap"></i>
                                        </div>
                                        <h5>Student/Trial</h5>
                                        <div class="price">₱60.00</div>
                                        <p>Limited access</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Modal Footer -->
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-user-plus"></i> Add Customer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Enhanced Table Design with Transparency */
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

/* Enhanced Glass Morphism Modal Design */
.modal-content {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.15) 0%, rgba(255, 255, 255, 0.08) 100%) !important;
    border: 1px solid rgba(255, 255, 255, 0.3) !important;
    border-radius: 25px !important;
    overflow: hidden !important;
    box-shadow: 
        0 25px 50px rgba(0, 0, 0, 0.3),
        0 0 0 1px rgba(255, 255, 255, 0.1),
        inset 0 1px 0 rgba(255, 255, 255, 0.2) !important;
    backdrop-filter: blur(20px) !important;
    animation: modalSlideIn 0.6s ease-out !important;
}

@keyframes modalSlideIn {
    0% {
        opacity: 0;
        transform: translateY(-50px) scale(0.9);
    }
    100% {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.modal-header {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.4) 0%, rgba(118, 75, 162, 0.4) 100%) !important;
    color: white !important;
    border: none !important;
    padding: 2rem !important;
    position: relative !important;
    overflow: hidden !important;
}

.modal-header::before {
    content: '' !important;
    position: absolute !important;
    top: 0 !important;
    left: -100% !important;
    width: 100% !important;
    height: 100% !important;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent) !important;
    animation: shimmer 3s infinite !important;
}

@keyframes shimmer {
    0% { left: -100%; }
    100% { left: 100%; }
}

.header-icon {
    width: 60px !important;
    height: 60px !important;
    background: rgba(255, 255, 255, 0.2) !important;
    border-radius: 50% !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    margin-right: 1.5rem !important;
    backdrop-filter: blur(10px) !important;
}

.header-icon i {
    font-size: 1.5rem !important;
    color: white !important;
}

.header-content h3 {
    margin: 0 !important;
    font-weight: 700 !important;
    font-size: 1.8rem !important;
}

.header-subtitle {
    margin: 0.5rem 0 0 0 !important;
    opacity: 0.9 !important;
    font-size: 1rem !important;
}

.modal-body {
    padding: 2rem !important;
    background: transparent !important;
}

.form-section {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.12) 0%, rgba(255, 255, 255, 0.05) 100%) !important;
    border-radius: 20px !important;
    padding: 2rem !important;
    margin-bottom: 2rem !important;
    box-shadow: 
        0 8px 32px rgba(0, 0, 0, 0.2),
        0 0 0 1px rgba(255, 255, 255, 0.05),
        inset 0 1px 0 rgba(255, 255, 255, 0.1) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1) !important;
    backdrop-filter: blur(15px) !important;
    position: relative !important;
    overflow: hidden !important;
}

.form-section:last-child {
    margin-bottom: 0 !important;
}

.form-section::before {
    content: '' !important;
    position: absolute !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    height: 2px !important;
    background: linear-gradient(90deg, #667eea, #764ba2, #667eea) !important;
    opacity: 0 !important;
    transition: opacity 0.3s ease !important;
}

.form-section:hover {
    box-shadow: 
        0 15px 50px rgba(0, 0, 0, 0.3),
        0 0 0 1px rgba(102, 126, 234, 0.2),
        inset 0 1px 0 rgba(255, 255, 255, 0.2) !important;
    transform: translateY(-5px) scale(1.02) !important;
    border-color: rgba(102, 126, 234, 0.4) !important;
}

.form-section:hover::before {
    opacity: 1 !important;
}

.section-header {
    display: flex !important;
    align-items: center !important;
    margin-bottom: 2rem !important;
    padding-bottom: 1rem !important;
    border-bottom: 2px solid #f1f3f4 !important;
}

.section-icon {
    width: 50px !important;
    height: 50px !important;
    background: linear-gradient(135deg, #667eea, #764ba2) !important;
    border-radius: 12px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    margin-right: 1rem !important;
    color: white !important;
    font-size: 1.2rem !important;
}

.section-header h4 {
    margin: 0 !important;
    font-weight: 600 !important;
    color: white !important;
    font-size: 1.3rem !important;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3) !important;
}

.section-header p {
    margin: 0.25rem 0 0 0 !important;
    color: rgba(255, 255, 255, 0.8) !important;
    font-size: 0.9rem !important;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3) !important;
}

.form-grid {
    display: grid !important;
    grid-template-columns: 1fr 1fr !important;
    gap: 1.5rem !important;
}

.form-group.full-width {
    grid-column: 1 / -1 !important;
}

.form-group label {
    display: block !important;
    margin-bottom: 0.5rem !important;
    font-weight: 600 !important;
    color: white !important;
    font-size: 0.95rem !important;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3) !important;
}

.required {
    color: #ff6b6b !important;
    font-weight: 700 !important;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3) !important;
}

.form-control {
    width: 100% !important;
    padding: 0.75rem 1rem !important;
    border: 2px solid rgba(255, 255, 255, 0.2) !important;
    border-radius: 12px !important;
    font-size: 1rem !important;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1) !important;
    background: rgba(255, 255, 255, 0.12) !important;
    color: white !important;
    backdrop-filter: blur(12px) !important;
    box-shadow: 
        0 4px 15px rgba(0, 0, 0, 0.1),
        inset 0 1px 0 rgba(255, 255, 255, 0.1) !important;
    position: relative !important;
    overflow: hidden !important;
}

.form-control option {
    background: #2c3e50 !important;
    color: white !important;
    padding: 0.5rem !important;
    font-weight: 500 !important;
}

.form-control option:hover {
    background: #667eea !important;
    color: white !important;
}

.form-control option:checked {
    background: #667eea !important;
    color: white !important;
}

.form-control:focus {
    outline: none !important;
    border-color: #667eea !important;
    box-shadow: 
        0 0 0 3px rgba(102, 126, 234, 0.1),
        0 8px 25px rgba(102, 126, 234, 0.2),
        inset 0 1px 0 rgba(255, 255, 255, 0.2) !important;
    background: rgba(255, 255, 255, 0.18) !important;
    color: white !important;
    transform: translateY(-2px) !important;
}

.form-control::before {
    content: '' !important;
    position: absolute !important;
    top: 0 !important;
    left: -100% !important;
    width: 100% !important;
    height: 100% !important;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent) !important;
    transition: left 0.5s ease !important;
}

.form-control:focus::before {
    left: 100% !important;
}

.form-control::placeholder {
    color: #adb5bd !important;
}

.form-hint {
    display: block !important;
    margin-top: 0.5rem !important;
    color: rgba(255, 255, 255, 0.7) !important;
    font-size: 0.85rem !important;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3) !important;
}

.payment-display {
    position: relative !important;
    display: flex !important;
    align-items: center !important;
}

.currency {
    position: absolute !important;
    left: 1rem !important;
    color: #6c757d !important;
    font-weight: 600 !important;
    z-index: 1 !important;
}

.payment-display .form-control {
    padding-left: 2.5rem !important;
}

.payment-info-cards {
    margin-top: 2rem !important;
    padding-top: 2rem !important;
    border-top: 2px solid #f1f3f4 !important;
}

.payment-cards-grid {
    display: grid !important;
    grid-template-columns: 1fr 1fr !important;
    gap: 1.5rem !important;
}

.payment-card {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.15) 0%, rgba(255, 255, 255, 0.08) 100%) !important;
    border-radius: 18px !important;
    padding: 1.5rem !important;
    text-align: center !important;
    border: 2px solid rgba(255, 255, 255, 0.15) !important;
    transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1) !important;
    box-shadow: 
        0 8px 32px rgba(0, 0, 0, 0.2),
        0 0 0 1px rgba(255, 255, 255, 0.05) !important;
    position: relative !important;
    backdrop-filter: blur(15px) !important;
}

.payment-card::before {
    content: '' !important;
    position: absolute !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    height: 3px !important;
    background: linear-gradient(90deg, #667eea, #764ba2, #667eea) !important;
    opacity: 0 !important;
    transition: opacity 0.3s ease !important;
}

.payment-card:hover {
    transform: scale(1.05) translateY(-5px) !important;
    box-shadow: 
        0 15px 50px rgba(0, 0, 0, 0.3),
        0 0 0 1px rgba(102, 126, 234, 0.2) !important;
    border-color: rgba(102, 126, 234, 0.4) !important;
}

.payment-card:hover::before {
    opacity: 1 !important;
}

.card-icon {
    width: 60px !important;
    height: 60px !important;
    background: linear-gradient(135deg, #667eea, #764ba2) !important;
    border-radius: 50% !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    margin: 0 auto 1rem !important;
    color: white !important;
    font-size: 1.5rem !important;
}

.payment-card h5 {
    margin: 0 0 0.5rem 0 !important;
    font-weight: 600 !important;
    color: #2c3e50 !important;
}

.price {
    font-size: 1.5rem !important;
    font-weight: 700 !important;
    color: #667eea !important;
    margin: 0.5rem 0 !important;
}

.payment-card p {
    margin: 0 !important;
    color: #6c757d !important;
    font-size: 0.9rem !important;
}

.modal-footer {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.05) 0%, rgba(255, 255, 255, 0.02) 100%) !important;
    border-top: 1px solid rgba(255, 255, 255, 0.1) !important;
    padding: 1.5rem 2rem !important;
    display: flex !important;
    justify-content: flex-end !important;
    gap: 1rem !important;
    backdrop-filter: blur(10px) !important;
}

.btn {
    padding: 0.75rem 1.5rem !important;
    border-radius: 10px !important;
    font-weight: 600 !important;
    border: none !important;
    transition: all 0.3s ease !important;
    font-size: 0.95rem !important;
}

.btn-secondary {
    background: #6c757d !important;
    color: white !important;
}

.btn-secondary:hover {
    background: #5a6268 !important;
    transform: translateY(-2px) !important;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea, #764ba2) !important;
    color: white !important;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3) !important;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #5a6fd8, #6a4190) !important;
    transform: translateY(-2px) !important;
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4) !important;
}

/* Responsive Design */
@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr !important;
    }
    
    .payment-cards-grid {
        grid-template-columns: 1fr !important;
    }
    
    .modal-header {
        padding: 1.5rem !important;
    }
    
    .modal-body {
        padding: 1.5rem !important;
    }
    
    .form-section {
        padding: 1.5rem !important;
    }
}

/* Animation */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.form-section {
    animation: fadeInUp 0.6s ease-out !important;
}

.form-section:nth-child(2) {
    animation-delay: 0.1s !important;
}

.form-section:nth-child(3) {
    animation-delay: 0.2s !important;
}

.form-floating {
    position: relative;
}

.form-floating > .form-control,
.form-floating > .form-select {
    height: 60px !important;
    border-radius: 12px !important;
    border: 2px solid rgba(255, 255, 255, 0.2) !important;
    transition: all 0.3s ease !important;
    background: rgba(255, 255, 255, 0.1) !important;
    font-size: 0.95rem !important;
    color: white !important;
    backdrop-filter: blur(10px) !important;
}

.form-floating > .form-control:focus,
.form-floating > .form-select:focus {
    border-color: #667eea !important;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25) !important;
    background: rgba(255, 255, 255, 0.15) !important;
    color: white !important;
}

.form-floating > .form-control:hover,
.form-floating > .form-select:hover {
    border-color: rgba(102, 126, 234, 0.5) !important;
    background: rgba(255, 255, 255, 0.12) !important;
}

.form-floating > label {
    padding: 1rem 0.75rem !important;
    color: rgba(255, 255, 255, 0.8) !important;
    font-weight: 500 !important;
}

.form-floating > .form-control:focus ~ label,
.form-floating > .form-control:not(:placeholder-shown) ~ label,
.form-floating > .form-select ~ label {
    color: #667eea !important;
    transform: scale(0.85) translateY(-0.5rem) translateX(0.15rem) !important;
    font-weight: 600 !important;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3) !important;
}

.form-text {
    font-size: 0.8rem !important;
    color: rgba(255, 255, 255, 0.6) !important;
    margin-top: 0.25rem !important;
}

.payment-info-cards {
    animation: fadeInUp 0.5s ease-out;
}

.payment-card {
    transition: all 0.3s ease;
}

.payment-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.payment-card .card {
    border-radius: 12px !important;
    overflow: hidden !important;
    transition: all 0.3s ease !important;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0.05) 100%) !important;
    backdrop-filter: blur(10px) !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
}

.payment-card .card:hover {
    transform: scale(1.02) !important;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3) !important;
    border-color: rgba(102, 126, 234, 0.3) !important;
}

.payment-amount {
    font-size: 1.5rem !important;
    font-weight: 700 !important;
    color: white !important;
    margin: 0.5rem 0 !important;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3) !important;
}

.payment-card .card-body {
    color: white !important;
}

.payment-card .card-body small {
    color: rgba(255, 255, 255, 0.7) !important;
}

.modal-footer {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.05) 0%, rgba(255, 255, 255, 0.02) 100%) !important;
    border-top: 1px solid rgba(255, 255, 255, 0.1) !important;
    backdrop-filter: blur(10px) !important;
}

.btn {
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.3s ease;
    border: none;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.btn-primary:hover {
    background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}

.btn-outline-secondary {
    border: 2px solid #6c757d;
    color: #6c757d;
    background: transparent;
}

.btn-outline-secondary:hover {
    background: #6c757d;
    color: white;
    transform: translateY(-1px);
}

.btn-lg {
    padding: 0.75rem 2rem;
    font-size: 1rem;
}

/* Animation */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Form validation styles */
.was-validated .form-control:invalid,
.was-validated .form-select:invalid {
    border-color: #dc3545;
    box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
}

.was-validated .form-control:valid,
.was-validated .form-select:valid {
    border-color: #198754;
    box-shadow: 0 0 0 0.2rem rgba(25, 135, 84, 0.25);
}

.invalid-feedback {
    font-size: 0.8rem;
    color: #dc3545;
    margin-top: 0.25rem;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .modal-dialog {
        margin: 1rem;
    }
    
    .form-section {
        padding: 1rem;
    }
    
    .modal-header {
        padding: 1rem 1.5rem;
    }
    
    .modal-footer {
        padding: 1rem 1.5rem;
    }
    
    .btn-lg {
        padding: 0.5rem 1.5rem;
        font-size: 0.9rem;
    }
}

/* Loading state for submit button */
.btn:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none !important;
}

/* Enhanced focus states */
.form-control:focus,
.form-select:focus {
    outline: none;
}

/* Smooth transitions for all interactive elements */
* {
    transition: all 0.2s ease;
}

/* Enhanced dropdown visibility */
select.form-control {
    background: rgba(255, 255, 255, 0.15) !important;
    color: white !important;
    border: 2px solid rgba(255, 255, 255, 0.3) !important;
}

select.form-control:focus {
    background: rgba(255, 255, 255, 0.2) !important;
    color: white !important;
    border-color: #667eea !important;
}

select.form-control option {
    background: #2c3e50 !important;
    color: white !important;
    padding: 0.75rem 1rem !important;
    font-size: 1rem !important;
    font-weight: 500 !important;
    border: none !important;
}

select.form-control option:hover,
select.form-control option:focus {
    background: #667eea !important;
    color: white !important;
}

select.form-control option:checked,
select.form-control option:selected {
    background: #667eea !important;
    color: white !important;
    font-weight: 600 !important;
}

/* Placeholder text styling */
select.form-control option[value=""] {
    color: #adb5bd !important;
    font-style: italic !important;
    font-weight: 500 !important;
    background: rgba(255, 255, 255, 0.1) !important;
}

/* Make dropdown text always visible */
select.form-control {
    color: white !important;
}

select.form-control:focus {
    color: white !important;
}

select.form-control option:not([value=""]) {
    color: white !important;
    background: #2c3e50 !important;
}

/* When dropdown has a selected value, keep text white */
select.form-control option:checked {
    color: white !important;
    background: #667eea !important;
}

/* Make specific form fields white background */
#first_name,
#last_name,
#address,
#age,
#gender,
#customer_type,
#payment_amount {
    background: white !important;
    color: #495057 !important;
}

#first_name:focus,
#last_name:focus,
#address:focus,
#age:focus,
#gender:focus,
#customer_type:focus,
#payment_amount:focus {
    background: white !important;
    color: #495057 !important;
}
</style>

<script>
// Update payment amount based on customer type
function updatePaymentAmount() {
    const customerType = document.getElementById('customer_type').value;
    const paymentAmountInput = document.getElementById('payment_amount');
    const paymentInfoCards = document.getElementById('paymentInfoCards');

    if (customerType === 'gym_visit') {
        paymentAmountInput.value = '100.00';
        paymentInfoCards.style.display = 'block';
        document.querySelector('.regular-card').style.display = 'block';
        document.querySelector('.student-card').style.display = 'none';
    } else if (customerType === 'trial') {
        paymentAmountInput.value = '60.00';
        paymentInfoCards.style.display = 'block';
        document.querySelector('.regular-card').style.display = 'none';
        document.querySelector('.student-card').style.display = 'block';
    } else {
        paymentAmountInput.value = '';
        paymentInfoCards.style.display = 'none';
        document.querySelector('.regular-card').style.display = 'none';
        document.querySelector('.student-card').style.display = 'none';
    }
}

// Keep dropdown text always white
document.addEventListener('DOMContentLoaded', function() {
    const dropdowns = document.querySelectorAll('select.form-control');
    
    dropdowns.forEach(function(dropdown) {
        // Always keep text white
        dropdown.style.color = 'white';
        
        // Add event listener for changes - keep white
        dropdown.addEventListener('change', function() {
            this.style.color = 'white';
        });
    });
});

// Address field validation (optional)
document.getElementById('address').addEventListener('input', function(e) {
    // You can add address validation logic here if needed
});

// Form validation
document.querySelector('form').addEventListener('submit', function(e) {
    const firstName = document.getElementById('first_name').value.trim();
    const lastName = document.getElementById('last_name').value.trim();
    const customerType = document.getElementById('customer_type').value;
    
    if (!firstName) {
        alert('First name is required');
        document.getElementById('first_name').focus();
        e.preventDefault();
        return;
    }
    
    if (!lastName) {
        alert('Last name is required');
        document.getElementById('last_name').focus();
        e.preventDefault();
        return;
    }
    
    if (!customerType) {
        alert('Customer type is required');
        document.getElementById('customer_type').focus();
        e.preventDefault();
        return;
    }
    
    // Show loading state
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding Customer...';
    submitBtn.disabled = true;
});
</script>

<?php include 'components/footer.php'; ?>
