<?php
$page_title = "Payments Management";
include 'components/header.php';
include '../includes/db.php';

// Database connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$success = '';
$error = '';

// Handle payment processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_payment':
                $memberId = $_POST['member_id'] ?? '';
                $amount = $_POST['amount'] ?? '';
                $paymentMethod = $_POST['payment_method'] ?? '';
                $paymentDate = $_POST['payment_date'] ?? date('Y-m-d');
                $notes = $_POST['notes'] ?? '';
                
                if (empty($memberId) || empty($amount) || empty($paymentMethod)) {
                    $error = 'Please fill in all required fields.';
                } else {
                    try {
                        // Get member details
                        $stmt = $pdo->prepare("SELECT id, membership_type FROM members WHERE member_id = ?");
                        $stmt->execute([$memberId]);
                        $member = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$member) {
                            $error = 'Member not found.';
                        } else {
                            // If there is an existing pending/overdue record for this member, turn it into paid instead of creating another row
                            $stmt = $pdo->prepare("SELECT id FROM member_payroll 
                                WHERE member_id = ? AND status IN ('pending','overdue')
                                ORDER BY payment_date DESC, created_at DESC
                                LIMIT 1");
                            $stmt->execute([$member['id']]);
                            $pendingPayment = $stmt->fetch(PDO::FETCH_ASSOC);

                            // Check for exact duplicate paid payment on the same date/amount/type
                            $stmt = $pdo->prepare("SELECT id FROM member_payroll 
                                WHERE member_id = ? AND payment_date = ? AND status = 'paid' 
                                AND amount = ? AND membership_type = ?");
                            $stmt->execute([$member['id'], $paymentDate, $amount, $member['membership_type']]);
                            $existingPaid = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($existingPaid) {
                                $error = 'A paid record already exists for this member with the same amount (₱' . number_format($amount, 2) . ') on ' . date('M d, Y', strtotime($paymentDate)) . '.';
                            } else {
                                if ($pendingPayment) {
                                    // Update existing pending/overdue record to paid
                                    $stmt = $pdo->prepare("UPDATE member_payroll 
                                        SET amount = ?, payment_date = ?, status = 'paid', payment_method = ?, notes = ?, membership_type = ?
                                        WHERE id = ?");
                                    $stmt->execute([
                                        $amount,
                                        $paymentDate,
                                        $paymentMethod,
                                        $notes,
                                        $member['membership_type'],
                                        $pendingPayment['id']
                                    ]);
                                    $success = 'Existing pending payment updated to Paid.';
                                } else {
                                    // Insert payment record
                                    $stmt = $pdo->prepare("INSERT INTO member_payroll 
                                        (member_id, membership_type, amount, payment_date, status, payment_method, notes) 
                                        VALUES (?, ?, ?, ?, 'paid', ?, ?)");
                                    
                                    $stmt->execute([
                                        $member['id'],
                                        $member['membership_type'],
                                        $amount,
                                        $paymentDate,
                                        $paymentMethod,
                                        $notes
                                    ]);
                                    
                                    $success = 'Payment recorded successfully!';
                                }
                            }
                        }
                    } catch (PDOException $e) {
                        $error = 'Database error: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'update_payment_status':
                $paymentId = $_POST['payment_id'] ?? '';
                $status = $_POST['status'] ?? '';
                
                if (empty($paymentId) || empty($status)) {
                    $error = 'Missing required fields.';
                } else {
                    try {
                        $stmt = $pdo->prepare("UPDATE member_payroll SET status = ? WHERE id = ?");
                        $stmt->execute([$status, $paymentId]);
                        $success = 'Payment status updated successfully!';
                    } catch (PDOException $e) {
                        $error = 'Database error: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'delete_payment':
                $paymentId = $_POST['payment_id'] ?? '';
                
                if (empty($paymentId)) {
                    $error = 'Payment ID is required.';
                } else {
                    try {
                        $stmt = $pdo->prepare("DELETE FROM member_payroll WHERE id = ?");
                        $stmt->execute([$paymentId]);
                        $success = 'Payment deleted successfully!';
                    } catch (PDOException $e) {
                        $error = 'Database error: ' . $e->getMessage();
                    }
                }
                break;
        }
    }
}

// Get all payments with member details
$paymentsQuery = "
    SELECT 
        mp.id,
        mp.member_id,
        mp.membership_type,
        mp.amount,
        mp.payment_date,
        mp.status,
        mp.payment_method,
        mp.transaction_id,
        mp.notes,
        mp.created_at,
        m.member_id as member_code,
        m.first_name,
        m.last_name,
        m.email,
        m.phone,
        m.address,
        m.gender,
        m.join_date,
        m.expired_date,
        m.photo,
        m.membership_type as member_membership_type,
        m.membership_duration
    FROM member_payroll mp
    JOIN members m ON mp.member_id = m.id
    ORDER BY mp.payment_date DESC, mp.created_at DESC
";

$payments = $pdo->query($paymentsQuery)->fetchAll(PDO::FETCH_ASSOC);

// Get all members for dropdown
$membersQuery = "SELECT member_id, first_name, last_name, membership_type FROM members ORDER BY first_name, last_name";
$members = $pdo->query($membersQuery)->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$totalPayments = array_sum(array_column($payments, 'amount'));
$paidPayments = array_sum(array_column(array_filter($payments, function($p) { return $p['status'] === 'paid'; }), 'amount'));
$pendingPayments = array_sum(array_column(array_filter($payments, function($p) { return $p['status'] === 'pending'; }), 'amount'));
$overduePayments = array_sum(array_column(array_filter($payments, function($p) { return $p['status'] === 'overdue'; }), 'amount'));
?>


<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Payment Statistics -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stats-card border-left-primary">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Total Payments
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            ₱<?= number_format($totalPayments, 2) ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
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
                            Paid Amount
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            ₱<?= number_format($paidPayments, 2) ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
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
                            Pending Amount
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            ₱<?= number_format($pendingPayments, 2) ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-clock fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stats-card border-left-danger">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                            Overdue Amount
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            ₱<?= number_format($overduePayments, 2) ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Payments Table -->
<div class="card main-content-card">
    <div class="card-header">
        <h6>
            <i class="fas fa-credit-card me-2"></i>
        </h6>
        <div class="d-flex align-items-center">
            <div class="input-group me-3" style="width: 300px;">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" class="form-control" id="searchInput" placeholder="Search payments...">
            </div>
            <select class="form-select form-select-sm me-2" id="statusFilter" style="width: auto;">
                <option value="">All Status</option>
                <option value="paid">Paid</option>
                <option value="pending">Pending</option>
                <option value="overdue">Overdue</option>
            </select>
            <select class="form-select form-select-sm me-2" id="memberTypeFilter" style="width: auto;">
                <option value="">All Member Types</option>
                <option value="regular">Regular</option>
                <option value="student">Student</option>
            </select>
            <button class="btn btn-secondary btn-sm me-2" onclick="resetAllFilters()" title="Reset all filters to show all payments">
                <i class="fas fa-undo me-1"></i>Reset Filters
            </button>
            <button class="btn btn-secondary btn-sm me-2" onclick="printSortedPayments()" title="Print payments sorted by member type">
                <i class="fas fa-print me-1"></i>Print Sorted
            </button>
            <button class="btn btn-secondary btn-sm me-2" data-bs-toggle="modal" data-bs-target="#paymentHistoryModal" title="View Payment History">
                <i class="fas fa-history me-1"></i>Payment History
            </button>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
                <i class="fas fa-plus me-1"></i>Add Payment
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive table-container">
            <table class="table table-hover mb-0 table-custom" id="paymentsTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Member Name</th>
                        <th>Member ID</th>
                        <th>Membership Type</th>
                        <th>Amount</th>
                        <th>Payment Date</th>
                        <th>Payment Method</th>
                        <th>Status</th>
                        <th>Notes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <p>No payments found</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($payments as $index => $payment): ?>
                            <tr data-status="<?= htmlspecialchars($payment['status']) ?>" data-member-type="<?= htmlspecialchars($payment['membership_type']) ?>">
                                <td><?= $index + 1 ?></td>
                                <td>
                                    <div class="member-info">
                                        <div class="member-avatar">
                                            <?php if ($payment['photo']): ?>
                                                <img src="../uploads/member_photos/<?= htmlspecialchars($payment['photo']) ?>" 
                                                     alt="Profile" class="member-photo">
                                            <?php else: ?>
                                                <i class="fas fa-user"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="member-details">
                                            <div class="member-name" 
                                                 style="cursor: pointer;" 
                                                 onclick="showMemberProfile(<?= htmlspecialchars(json_encode($payment)) ?>)">
                                                <?= htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']) ?>
                                            </div>
                                            <div class="member-email"><?= htmlspecialchars($payment['email']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge bg-primary"><?= htmlspecialchars($payment['member_code']) ?></span></td>
                                <td>
                                    <span class="badge bg-<?= $payment['membership_type'] === 'regular' ? 'primary' : 'info' ?>">
                                        <?= ucfirst(htmlspecialchars($payment['membership_type'])) ?>
                                    </span>
                                </td>
                                <td class="fw-bold text-success">₱<?= number_format($payment['amount'], 2) ?></td>
                                <td><?= date('M d, Y', strtotime($payment['payment_date'])) ?></td>
                                <td>
                                    <?php if ($payment['payment_method']): ?>
                                        <span class="badge bg-light text-dark">
                                            <?= ucfirst(htmlspecialchars($payment['payment_method'])) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = '';
                                    $statusText = '';
                                    switch ($payment['status']) {
                                        case 'paid':
                                            $statusClass = 'bg-success';
                                            $statusText = 'Paid';
                                            break;
                                        case 'pending':
                                            $statusClass = 'bg-warning';
                                            $statusText = 'Pending';
                                            break;
                                        case 'overdue':
                                            $statusClass = 'bg-danger';
                                            $statusText = 'Overdue';
                                            break;
                                        default:
                                            $statusClass = 'bg-secondary';
                                            $statusText = ucfirst($payment['status']);
                                    }
                                    ?>
                                    <span class="badge <?= $statusClass ?>"><?= $statusText ?></span>
                                </td>
                                <td>
                                    <?php if ($payment['notes']): ?>
                                        <span class="text-muted" title="<?= htmlspecialchars($payment['notes']) ?>">
                                            <?= strlen($payment['notes']) > 30 ? substr(htmlspecialchars($payment['notes']), 0, 30) . '...' : htmlspecialchars($payment['notes']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-outline-success btn-sm" 
                                                onclick="printReceipt(<?= $payment['id'] ?>)"
                                                title="Print Receipt">
                                            <i class="fas fa-print"></i>
                                        </button>
                                        <button class="btn btn-outline-primary btn-sm" 
                                                onclick="editPayment(<?= $payment['id'] ?>, '<?= htmlspecialchars($payment['status']) ?>')"
                                                title="Edit Status">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-outline-danger btn-sm" 
                                                onclick="deletePayment(<?= $payment['id'] ?>)"
                                                title="Delete Payment">
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
    </div>
</div>

<!-- Add Payment Modal -->
<div class="modal fade" id="addPaymentModal" tabindex="-1" aria-labelledby="addPaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="POST">
            <input type="hidden" name="action" value="add_payment">
            <div class="modal-header">
                <h5 class="modal-title" id="addPaymentModalLabel">
                    <i class="fas fa-credit-card me-1"></i> Add Payment
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="member_id" class="form-label">Member *</label>
                    <select class="form-select" name="member_id" id="member_id" required>
                        <option value="">Select Member</option>
                        <?php foreach ($members as $member): ?>
                            <option value="<?= htmlspecialchars($member['member_id']) ?>">
                                <?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?> 
                                (<?= htmlspecialchars($member['member_id']) ?>) - 
                                <?= ucfirst(htmlspecialchars($member['membership_type'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="existingPayments" class="mt-2" style="display: none;">
                        <small class="text-muted">Recent payments for this member:</small>
                        <div id="paymentsList" class="mt-1"></div>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="amount" class="form-label">Amount (₱) *</label>
                    <input type="number" class="form-control" name="amount" id="amount" 
                           min="0" step="0.01" required>
                </div>
                <div class="mb-3">
                    <label for="payment_date" class="form-label">Payment Date</label>
                    <input type="date" class="form-control" name="payment_date" id="payment_date" 
                           value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="mb-3">
                    <label for="payment_method" class="form-label">Payment Method *</label>
                    <select class="form-select" name="payment_method" id="payment_method" required>
                        <option value="">Select Method</option>
                        <option value="cash">Cash</option>
                        <option value="gcash">GCash</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="notes" class="form-label">Notes</label>
                    <textarea class="form-control" name="notes" id="notes" rows="3" 
                              placeholder="Optional notes about this payment"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Payment</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Payment Status Modal -->
<div class="modal fade" id="editPaymentModal" tabindex="-1" aria-labelledby="editPaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="POST">
            <input type="hidden" name="action" value="update_payment_status">
            <input type="hidden" name="payment_id" id="edit_payment_id">
            <div class="modal-header">
                <h5 class="modal-title" id="editPaymentModalLabel">
                    <i class="fas fa-edit me-1"></i> Update Payment Status
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="edit_status" class="form-label">Payment Status *</label>
                    <select class="form-select" name="status" id="edit_status" required>
                        <option value="pending">Pending</option>
                        <option value="paid">Paid</option>

                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Status</button>
            </div>
        </form>
    </div>
</div>

<!-- Receipt Modal -->
<div class="modal fade" id="receiptModal" tabindex="-1" aria-labelledby="receiptModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="receiptModalLabel">
                    <i class="fas fa-receipt me-1"></i> Payment Receipt
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="receiptContent">
                <!-- Receipt content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" onclick="printReceiptContent()">
                    <i class="fas fa-print me-1"></i> Print Receipt
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Member Profile Modal -->
<div class="modal fade" id="memberProfileModal" tabindex="-1" aria-labelledby="memberProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="memberProfileModalLabel">
                    <i class="fas fa-user me-1"></i> Member Profile
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="memberProfileContent">
                <!-- Profile content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="viewMemberDetails()">
                    <i class="fas fa-eye me-1"></i> View Full Details
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Payment Confirmation Modal -->
<div class="modal fade" id="deletePaymentModal" tabindex="-1" aria-labelledby="deletePaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="POST">
            <input type="hidden" name="action" value="delete_payment">
            <input type="hidden" name="payment_id" id="delete_payment_id">
            <div class="modal-header">
                <h5 class="modal-title" id="deletePaymentModalLabel">
                    <i class="fas fa-exclamation-triangle me-1 text-danger"></i> Confirm Delete
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this payment record? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete Payment</button>
            </div>
        </form>
    </div>
</div>

<!-- Payment History Modal -->
<div class="modal fade" id="paymentHistoryModal" tabindex="-1" aria-labelledby="paymentHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="paymentHistoryModalLabel">
                    <i class="fas fa-history me-1"></i> Payment History
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Filters -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <label for="historyMemberFilter" class="form-label">Member</label>
                        <select class="form-select form-select-sm" id="historyMemberFilter">
                            <option value="">All Members</option>
                            <?php foreach ($members as $member): ?>
                                <option value="<?= htmlspecialchars($member['member_id']) ?>">
                                    <?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?> 
                                    (<?= htmlspecialchars($member['member_id']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="historyStatusFilter" class="form-label">Status</label>
                        <select class="form-select form-select-sm" id="historyStatusFilter">
                            <option value="">All Status</option>
                            <option value="paid">Paid</option>
                            <option value="pending">Pending</option>
                            <option value="overdue">Overdue</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="historyDateFrom" class="form-label">Date From</label>
                        <input type="date" class="form-control form-control-sm" id="historyDateFrom">
                    </div>
                    <div class="col-md-2">
                        <label for="historyDateTo" class="form-label">Date To</label>
                        <input type="date" class="form-control form-control-sm" id="historyDateTo" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="historyPaymentMethod" class="form-label">Payment Method</label>
                        <select class="form-select form-select-sm" id="historyPaymentMethod">
                            <option value="">All Methods</option>
                            <option value="cash">Cash</option>
                            <option value="gcash">GCash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="bank_deposit">Bank Deposit</option>
                            <option value="credit_card">Credit Card</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button class="btn btn-primary btn-sm w-100" onclick="loadPaymentHistory()">
                            <i class="fas fa-search"></i> Filter
                        </button>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="row mb-4" id="historyStats">
                    <div class="col-md-3">
                        <div class="card stats-card border-left-primary">
                            <div class="card-body">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Total Records
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800" id="historyTotalRecords">0</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card border-left-success">
                            <div class="card-body">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Total Amount
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800" id="historyTotalAmount">₱0.00</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card border-left-info">
                            <div class="card-body">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    Paid Count
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800" id="historyPaidCount">0</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card border-left-warning">
                            <div class="card-body">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    Average Amount
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800" id="historyAvgAmount">₱0.00</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- History Table -->
                <div class="table-responsive">
                    <table class="table table-hover" id="paymentHistoryTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Member Name</th>
                                <th>Member ID</th>
                                <th>Membership Type</th>
                                <th>Amount</th>
                                <th>Payment Date</th>
                                <th>Payment Method</th>
                                <th>Status</th>
                                <th>Notes</th>
                                <th>Created At</th>
                            </tr>
                        </thead>
                        <tbody id="paymentHistoryBody">
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">
                                    <i class="fas fa-spinner fa-spin fa-2x mb-3"></i>
                                    <p>Loading payment history...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Export Button -->
                <div class="mt-3 text-end">
                    <button class="btn btn-success btn-sm" onclick="exportPaymentHistory()">
                        <i class="fas fa-download me-1"></i> Export to CSV
                    </button>
                    <button class="btn btn-primary btn-sm" onclick="printPaymentHistory()">
                        <i class="fas fa-print me-1"></i> Print History
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
/* Glassmorphism Design Overrides for Payments */
body {
    background: linear-gradient(rgba(15, 23, 42, 0.95), rgba(15, 23, 42, 0.95)), 
                url('https://images.unsplash.com/photo-1571902943202-507ec2618e8f?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1075&q=80') !important;
    background-size: cover !important;
    background-position: center !important;
    background-attachment: fixed !important;
    color: #f8f9fc !important;
}

.main {
    background: transparent !important;
}

/* Statistics Cards - Glassmorphism */
.stats-card {
    background: rgba(255, 255, 255, 0.1);
    -webkit-backdrop-filter: blur(15px);
    backdrop-filter: blur(15px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 16px;
    padding: 24px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stats-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
    border-radius: 16px;
    z-index: -1;
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
    border-color: rgba(255, 255, 255, 0.3);
}

.stats-card .card-body {
    background: transparent !important;
    padding: 0 !important;
}

.stats-card .text-xs {
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}

.stats-card .h5 {
    font-size: 2rem;
    font-weight: 700;
    margin: 0;
}

.stats-card .col-auto i {
    font-size: 2.5rem;
    opacity: 0.8;
}

/* Border Colors for Stats Cards */
.border-left-primary {
    border-left: 4px solid #3B82F6 !important;
}

.border-left-success {
    border-left: 4px solid #10B981 !important;
}

.border-left-warning {
    border-left: 4px solid #F59E0B !important;
}

.border-left-danger {
    border-left: 4px solid #EF4444 !important;
}

/* Main Content Card - Glassmorphism */
.main-content-card {
    background: rgba(255, 255, 255, 0.08);
    -webkit-backdrop-filter: blur(20px);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 20px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    overflow: hidden;
    margin-bottom: 30px;
}

.main-content-card .card-header {
    background: rgba(255, 255, 255, 0.1);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    padding: 24px 30px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 20px;
}

.main-content-card .card-header h6 {
    font-size: 1.25rem;
    font-weight: 600;
    color: #fff;
    margin: 0;
}

.main-content-card .card-header .input-group {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    overflow: hidden;
}

.main-content-card .card-header .input-group-text {
    background: transparent;
    border: none;
    color: rgba(255, 255, 255, 0.7);
    padding: 12px 16px;
}

.main-content-card .card-header .form-control {
    background: transparent;
    border: none;
    color: #fff;
    padding: 12px 16px;
}

.main-content-card .card-header .form-control::placeholder {
    color: rgba(255, 255, 255, 0.5);
}

.main-content-card .card-header .btn-primary {
    background: linear-gradient(135deg, #3B82F6, #1D4ED8);
    border: none;
    border-radius: 12px;
    padding: 12px 24px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.main-content-card .card-header .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(59, 130, 246, 0.4);
}

/* Table Styling - Glassmorphism */
.table-container {
    background: transparent !important;
    border-radius: 0 0 20px 20px;
    overflow: hidden;
}

.table {
    background: transparent !important;
    margin: 0;
}

.table thead th {
    background: rgba(255, 255, 255, 0.1) !important;
    border: none !important;
    color: #fff !important;
    font-weight: 600;
    padding: 20px 16px;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.table tbody tr {
    background: transparent !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
    transition: all 0.3s ease;
}

.table tbody tr:hover {
    background: rgba(255, 255, 255, 0.08) !important;
    transform: scale(1.01);
}

.table tbody td {
    background: transparent !important;
    color: #fff !important;
    border: none !important;
    padding: 20px 16px;
    vertical-align: middle;
}

/* Override Bootstrap table styles */
.table-hover tbody tr:hover {
    background: rgba(255, 255, 255, 0.08) !important;
}

.table-hover tbody tr {
    background: transparent !important;
}

/* Force transparent background for all table elements */
.table, .table tbody, .table tbody tr, .table tbody td {
    background: transparent !important;
    background-color: transparent !important;
}

/* Override any Bootstrap white backgrounds */
.table tbody tr:nth-child(even),
.table tbody tr:nth-child(odd) {
    background: transparent !important;
    background-color: transparent !important;
}

/* Member Info */
.member-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.member-avatar {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #3B82F6, #1D4ED8);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: #fff;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    overflow: hidden;
    flex-shrink: 0;
}

.member-avatar .member-photo {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
}

.member-avatar i {
    font-size: 1.2rem;
}

.member-details {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 1;
    min-width: 0;
}

.member-name {
    font-weight: 600;
    color: #fff !important;
    font-size: 1rem;
}

.member-email {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.6) !important;
}

/* Badge Styling */
.badge {
    font-size: 0.75rem;
    padding: 6px 12px;
    border-radius: 8px;
    font-weight: 500;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.badge.bg-primary {
    background: linear-gradient(135deg, #3B82F6, #1D4ED8) !important;
}

.badge.bg-success {
    background: linear-gradient(135deg, #10B981, #059669) !important;
}

.badge.bg-warning {
    background: linear-gradient(135deg, #F59E0B, #D97706) !important;
}

.badge.bg-danger {
    background: linear-gradient(135deg, #EF4444, #DC2626) !important;
}

.badge.bg-secondary {
    background: linear-gradient(135deg, #6B7280, #4B5563) !important;
}

.badge.bg-info {
    background: linear-gradient(135deg, #06B6D4, #0891B2) !important;
}

.badge.bg-light {
    background: rgba(255, 255, 255, 0.1) !important;
    color: #fff !important;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 8px;
    justify-content: center;
}

.action-buttons .btn {
    padding: 8px 12px;
    font-size: 0.8rem;
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
    transition: all 0.3s ease;
    -webkit-backdrop-filter: blur(10px);
    backdrop-filter: blur(10px);
}

.action-buttons .btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
}

.action-buttons .btn-outline-success:hover {
    background: linear-gradient(135deg, #10B981, #059669);
    border-color: #10B981;
}

.action-buttons .btn-outline-primary:hover {
    background: linear-gradient(135deg, #3B82F6, #1D4ED8);
    border-color: #3B82F6;
}

.action-buttons .btn-outline-danger:hover {
    background: linear-gradient(135deg, #EF4444, #DC2626);
    border-color: #EF4444;
}

/* Modal Styling */
.modal-content {
    background: rgba(30, 41, 59, 0.95);
    -webkit-backdrop-filter: blur(20px);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 20px;
    color: #fff;
}

.modal-header {
    background: rgba(255, 255, 255, 0.1);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px 20px 0 0;
    padding: 24px 30px;
}

.modal-footer {
    background: rgba(255, 255, 255, 0.05);
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 0 0 20px 20px;
    padding: 20px 30px;
}

/* Form Styling */
.form-control, .form-select {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: #fff;
    border-radius: 12px;
    padding: 12px 16px;
}

.form-control:focus, .form-select:focus {
    background: rgba(255, 255, 255, 0.15);
    border-color: #3B82F6;
    box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
    color: #fff;
}

.form-control::placeholder {
    color: rgba(255, 255, 255, 0.5);
}

.form-label {
    color: #fff;
    font-weight: 500;
    margin-bottom: 8px;
}

/* Alert Styling */
.alert {
    background: rgba(255, 255, 255, 0.1);
    -webkit-backdrop-filter: blur(10px);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    color: #fff;
}

.alert-success {
    border-left: 4px solid #10B981;
}

.alert-danger {
    border-left: 4px solid #EF4444;
}

.alert-warning {
    border-left: 4px solid #F59E0B;
}

/* Text Color Overrides */
.text-muted {
    color: rgba(255, 255, 255, 0.6) !important;
}

.text-center {
    text-align: center !important;
}

/* Avatar Styling */
.avatar-sm {
    width: 32px;
    height: 32px;
}

.avatar-title {
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: bold;
    color: white;
}

.member-name-link:hover {
    text-decoration: underline;
    color: #0056b3 !important;
}

.profile-header {
    text-align: center;
    padding: 20px 0;
    border-bottom: 2px solid #e9ecef;
    margin-bottom: 20px;
}

.profile-avatar {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid #007bff;
    margin: 0 auto 15px auto;
    display: block;
}

.profile-avatar-placeholder {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: linear-gradient(135deg, #007bff, #0056b3);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 36px;
    font-weight: bold;
    margin: 0 auto 15px auto;
    border: 4px solid #007bff;
}

.profile-info {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.profile-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #dee2e6;
}

.profile-row:last-child {
    border-bottom: none;
}

.profile-label {
    font-weight: 600;
    color: #495057;
    min-width: 120px;
}

.profile-value {
    color: #212529;
    text-align: right;
    flex: 1;
}

.membership-status {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.membership-status.active {
    background: #d4edda;
    color: #155724;
}

.membership-status.expired {
    background: #f8d7da;
    color: #721c24;
}

.membership-status.expiring {
    background: #fff3cd;
    color: #856404;
}

.table th {
    font-size: 0.875rem;
    font-weight: 600;
}

.table td {
    font-size: 0.875rem;
    vertical-align: middle;
}

.badge {
    font-size: 0.75rem;
}

.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}

/* Receipt Styles */
.receipt-container {
    max-width: 240px;
    margin: 0 auto;
    padding: 15px;
    border: 2px solid #000;
    background: white;
    font-family: 'Courier New', monospace;
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    position: relative;
}

.receipt-header {
    text-align: center;
    border-bottom: 2px solid #000;
    padding-bottom: 20px;
    margin-bottom: 25px;
    position: relative;
}

.receipt-title {
    font-size: 18px;
    font-weight: bold;
    margin: 0 0 5px 0;
    color: #000;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.receipt-subtitle {
    font-size: 12px;
    margin: 6px 0;
    color: #000;
    font-weight: normal;
}

.receipt-subtitle.receipt-number {
    font-size: 14px;
    font-weight: bold;
    color: #000;
    background: #f0f0f0;
    padding: 4px 10px;
    border: 1px solid #000;
    display: inline-block;
    margin: 8px 0;
}

.receipt-details {
    margin-bottom: 20px;
    background: #f9f9f9;
    padding: 15px;
    border: 1px solid #000;
}

.receipt-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
    font-size: 12px;
    padding: 6px 0;
    border-bottom: 1px solid #ccc;
}

.receipt-row:last-child {
    border-bottom: none;
}

.receipt-row .label {
    font-weight: bold;
    color: #000;
    min-width: 100px;
}

.receipt-row .value {
    font-weight: normal;
    color: #000;
    text-align: right;
    flex: 1;
}

.receipt-row.total {
    border-top: 2px solid #000;
    border-bottom: none;
    padding-top: 12px;
    margin-top: 12px;
    font-weight: bold;
    font-size: 16px;
    background: #000;
    color: white;
    padding: 12px;
    margin: 15px -8px 0 -8px;
}

.receipt-row.total .label,
.receipt-row.total .value {
    color: white;
}

.receipt-footer {
    text-align: center;
    margin-top: 20px;
    font-size: 11px;
    border-top: 2px solid #000;
    padding-top: 15px;
    color: #000;
    line-height: 1.4;
}

.receipt-footer .thank-you {
    font-size: 14px;
    font-weight: bold;
    color: #000;
    margin-bottom: 8px;
}

.receipt-footer .contact {
    background: #f0f0f0;
    padding: 8px;
    border: 1px solid #000;
    margin: 8px 0;
    font-weight: normal;
}

.receipt-footer .generated {
    font-size: 10px;
    color: #666;
    margin-top: 12px;
}

.receipt-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    background: #000;
    color: white;
    padding: 6px 12px;
    border: 1px solid #000;
    font-size: 10px;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.receipt-logo {
    width: 50px;
    height: 50px;
    background: #000;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 12px auto;
    color: white;
    font-size: 20px;
    font-weight: bold;
    border: 2px solid #000;
}

@media print {
    body * {
        visibility: hidden;
    }
    .receipt-container, .receipt-container * {
        visibility: visible;
    }
    .receipt-container {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        border: none;
    }
    .modal-footer {
        display: none !important;
    }
}

/* Payment History Modal Styling */
#paymentHistoryModal .modal-content {
    background: rgba(30, 41, 59, 0.98);
    -webkit-backdrop-filter: blur(20px);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 20px;
    color: #fff;
}

#paymentHistoryModal .modal-header {
    background: linear-gradient(135deg, #06B6D4, #0891B2);
    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 20px 20px 0 0;
}

#paymentHistoryModal .form-label {
    color: #fff;
    font-weight: 500;
    font-size: 0.85rem;
}

#paymentHistoryModal .form-control,
#paymentHistoryModal .form-select {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: #fff;
    border-radius: 8px;
}

#paymentHistoryModal .form-control:focus,
#paymentHistoryModal .form-select:focus {
    background: rgba(255, 255, 255, 0.15);
    border-color: #06B6D4;
    box-shadow: 0 0 0 0.2rem rgba(6, 182, 212, 0.25);
    color: #fff;
}

#paymentHistoryModal .table {
    background: transparent !important;
}

#paymentHistoryModal .table thead th {
    background: rgba(255, 255, 255, 0.1) !important;
    border: none !important;
    color: #fff !important;
    font-weight: 600;
    padding: 15px 12px;
    font-size: 0.85rem;
}

#paymentHistoryModal .table tbody tr {
    background: transparent !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
}

#paymentHistoryModal .table tbody tr:hover {
    background: rgba(255, 255, 255, 0.08) !important;
}

#paymentHistoryModal .table tbody td {
    background: transparent !important;
    color: #fff !important;
    border: none !important;
    padding: 15px 12px;
    vertical-align: middle;
}

#paymentHistoryModal .btn-primary {
    background: linear-gradient(135deg, #06B6D4, #0891B2);
    border: none;
    border-radius: 8px;
}

#paymentHistoryModal .btn-primary:hover {
    background: linear-gradient(135deg, #0891B2, #0E7490);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(6, 182, 212, 0.4);
}

#paymentHistoryModal .btn-success {
    background: linear-gradient(135deg, #10B981, #059669);
    border: none;
    border-radius: 8px;
}

#paymentHistoryModal .btn-success:hover {
    background: linear-gradient(135deg, #059669, #047857);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
}

#paymentHistoryModal .stats-card {
    background: rgba(255, 255, 255, 0.1);
    -webkit-backdrop-filter: blur(15px);
    backdrop-filter: blur(15px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    padding: 20px;
    transition: all 0.3s ease;
}

#paymentHistoryModal .stats-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

#paymentHistoryModal .stats-card .card-body {
    background: transparent !important;
    padding: 0 !important;
}

#paymentHistoryModal .border-left-info {
    border-left: 4px solid #06B6D4 !important;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Search functionality
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    const memberTypeFilter = document.getElementById('memberTypeFilter');
    const table = document.getElementById('paymentsTable');
    const rows = table.querySelectorAll('tbody tr');

    function filterTable() {
        const searchTerm = searchInput.value.toLowerCase();
        const statusFilterValue = statusFilter.value.toLowerCase();
        const memberTypeFilterValue = memberTypeFilter.value.toLowerCase();

        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            const status = row.getAttribute('data-status');
            const memberType = row.getAttribute('data-member-type');
            
            const matchesSearch = text.includes(searchTerm);
            const matchesStatus = !statusFilterValue || statusFilterValue === '' || status === statusFilterValue;
            const matchesMemberType = !memberTypeFilterValue || memberTypeFilterValue === '' || memberType === memberTypeFilterValue;
            
            row.style.display = matchesSearch && matchesStatus && matchesMemberType ? '' : 'none';
        });
    }

    searchInput.addEventListener('input', filterTable);
    statusFilter.addEventListener('change', filterTable);
    memberTypeFilter.addEventListener('change', filterTable);
    
    // Add function to reset all filters
    window.resetAllFilters = function() {
        searchInput.value = '';
        statusFilter.value = '';
        memberTypeFilter.value = '';
        filterTable();
    };

    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
});

function editPayment(paymentId, currentStatus) {
    document.getElementById('edit_payment_id').value = paymentId;
    document.getElementById('edit_status').value = currentStatus;
    new bootstrap.Modal(document.getElementById('editPaymentModal')).show();
}

function deletePayment(paymentId) {
    document.getElementById('delete_payment_id').value = paymentId;
    new bootstrap.Modal(document.getElementById('deletePaymentModal')).show();
}

// Auto-calculate amount based on membership type and show existing payments
document.getElementById('member_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const amountField = document.getElementById('amount');
    const existingPaymentsDiv = document.getElementById('existingPayments');
    const paymentsList = document.getElementById('paymentsList');
    
    if (selectedOption.value) {
        const membershipType = selectedOption.text.includes('Regular') ? 'regular' : 'student';
        const amount = membershipType === 'regular' ? 1000 : 700;
        amountField.value = amount;
        
        // Show existing payments for this member
        showExistingPayments(selectedOption.value);
    } else {
        amountField.value = '';
        existingPaymentsDiv.style.display = 'none';
    }
});

function showExistingPayments(memberId) {
    const existingPaymentsDiv = document.getElementById('existingPayments');
    const paymentsList = document.getElementById('paymentsList');
    
    // Get payments for this member from the current page data
    const payments = <?= json_encode($payments) ?>;
    const memberPayments = payments.filter(payment => payment.member_code === memberId);
    
    if (memberPayments.length > 0) {
        let html = '<div class="list-group list-group-flush">';
        memberPayments.slice(0, 5).forEach(payment => {
            const statusClass = payment.status === 'paid' ? 'success' : 
                               payment.status === 'pending' ? 'warning' : 'danger';
            html += `
                <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2);">
                    <div>
                        <div class="fw-bold">₱${parseFloat(payment.amount).toFixed(2)}</div>
                        <small class="text-muted">${payment.payment_date} - ${payment.payment_method || 'Cash'}</small>
                    </div>
                    <span class="badge bg-${statusClass}">${payment.status}</span>
                </div>
            `;
        });
        html += '</div>';
        paymentsList.innerHTML = html;
        existingPaymentsDiv.style.display = 'block';
    } else {
        existingPaymentsDiv.style.display = 'none';
    }
}


// Print receipt functionality
function printReceipt(paymentId) {
    try {
        // Get payment data from the table row
        const row = event.target.closest('tr');
        if (!row) {
            console.error('Could not find table row');
            alert('Error: Could not find payment data. Please try again.');
            return;
        }
        
        const cells = row.cells;
        if (!cells || cells.length < 9) {
            console.error('Invalid table structure');
            alert('Error: Invalid table structure. Please refresh the page and try again.');
            return;
        }
        
        // Extract member name from the member details div
        const memberNameElement = cells[1].querySelector('.member-name');
        const memberName = memberNameElement ? memberNameElement.textContent.trim() : 'Unknown Member';
        
        // Extract member ID from badge
        const memberIdElement = cells[2].querySelector('.badge');
        const memberId = memberIdElement ? memberIdElement.textContent.trim() : 'N/A';
        
        // Extract membership type from badge
        const membershipTypeElement = cells[3].querySelector('.badge');
        const membershipType = membershipTypeElement ? membershipTypeElement.textContent.trim() : 'N/A';
        
        // Extract amount (remove currency symbol and trim)
        const amount = cells[4].textContent.trim();
        
        // Extract payment date
        const paymentDate = cells[5].textContent.trim();
        
        // Extract payment method from badge or text
        const paymentMethodElement = cells[6].querySelector('.badge');
        const paymentMethod = paymentMethodElement ? paymentMethodElement.textContent.trim() : 
                             (cells[6].textContent.trim() !== '-' ? cells[6].textContent.trim() : 'Cash');
        
        // Extract notes
        const notes = cells[8].textContent.trim() !== '-' ? cells[8].textContent.trim() : '';
        
        // Debug logging
        console.log('Extracted payment data:', {
            memberName,
            memberId,
            membershipType,
            amount,
            paymentDate,
            paymentMethod,
            notes
        });
        
        // Generate receipt HTML
        const receiptHTML = generateReceiptHTML({
            memberName: memberName,
            memberId: memberId,
            membershipType: membershipType,
            amount: amount,
            paymentDate: paymentDate,
            paymentMethod: paymentMethod,
            notes: notes,
            receiptNumber: 'RCP-' + String(paymentId).padStart(6, '0'),
            transactionId: 'TXN-' + Date.now()
        });
        
        // Display receipt in modal
        const receiptContent = document.getElementById('receiptContent');
        if (!receiptContent) {
            console.error('Receipt content element not found');
            alert('Error: Receipt modal not found. Please refresh the page and try again.');
            return;
        }
        
        receiptContent.innerHTML = receiptHTML;
        new bootstrap.Modal(document.getElementById('receiptModal')).show();
        
    } catch (error) {
        console.error('Error in printReceipt function:', error);
        alert('Error generating receipt: ' + error.message + '. Please try again.');
    }
}

function generateReceiptHTML(data) {
    return `
        <div class="receipt-container">
            <div class="receipt-badge">PAID</div>
            <div class="receipt-header">
                <div class="receipt-logo">FT</div>
                <h1 class="receipt-title">FIT TRACK GYM</h1>
                <div class="receipt-subtitle">Professional Fitness & Wellness Center</div>
                <div class="receipt-subtitle receipt-number">Receipt #: ${data.receiptNumber}</div>
                <div class="receipt-subtitle">Date: ${data.paymentDate}</div>
                <div class="receipt-subtitle">Time: ${new Date().toLocaleTimeString()}</div>
            </div>
            
            <div class="receipt-details">
                <div class="receipt-row">
                    <span class="label">Member Name:</span>
                    <span class="value">${data.memberName}</span>
                </div>
                <div class="receipt-row">
                    <span class="label">Member ID:</span>
                    <span class="value">${data.memberId}</span>
                </div>
                <div class="receipt-row">
                    <span class="label">Membership Type:</span>
                    <span class="value">${data.membershipType}</span>
                </div>
                <div class="receipt-row">
                    <span class="label">Payment Method:</span>
                    <span class="value">${data.paymentMethod}</span>
                </div>
                <div class="receipt-row">
                    <span class="label">Transaction ID:</span>
                    <span class="value">${data.transactionId}</span>
                </div>
                ${data.notes ? `
                <div class="receipt-row">
                    <span class="label">Notes:</span>
                    <span class="value">${data.notes}</span>
                </div>
                ` : ''}
                <div class="receipt-row total">
                    <span class="label">TOTAL AMOUNT:</span>
                    <span class="value">${data.amount}</span>
                </div>
            </div>
            
            <div class="receipt-footer">
                <div class="thank-you">Thank you for your payment!</div>
                <div>Please keep this receipt for your records.</div>
                <div class="contact">
                    For inquiries: info@fittrackgym.com<br>
                    Location: Your Gym Address Here<br>
                    Website: www.fittrackgym.com
                </div>
                <div>We appreciate your trust in our fitness services!</div>
                <div class="generated">Generated on: ${new Date().toLocaleString()}</div>
            </div>
        </div>
    `;
}

function printReceiptContent() {
    try {
        // Ensure the receipt content is visible for printing
        const receiptContainer = document.querySelector('.receipt-container');
        if (!receiptContainer) {
            console.error('Receipt container not found');
            alert('Error: Receipt content not found. Please try again.');
            return;
        }
        
        // Open print dialog
        window.print();
    } catch (error) {
        console.error('Error in printReceiptContent function:', error);
        alert('Error printing receipt: ' + error.message + '. Please try again.');
    }
}

// Global variable to store current member data
let currentMemberData = null;

function showMemberProfile(memberData) {
    currentMemberData = memberData;
    
    // Calculate membership status
    const today = new Date();
    const expiredDate = memberData.expired_date ? new Date(memberData.expired_date) : null;
    const joinDate = memberData.join_date ? new Date(memberData.join_date) : null;
    
    let membershipStatus = 'active';
    let statusClass = 'active';
    let statusText = 'Active';
    
    if (expiredDate) {
        if (expiredDate < today) {
            membershipStatus = 'expired';
            statusClass = 'expired';
            statusText = 'Expired';
        } else if (expiredDate - today < 7 * 24 * 60 * 60 * 1000) { // 7 days
            membershipStatus = 'expiring';
            statusClass = 'expiring';
            statusText = 'Expiring Soon';
        }
    }
    
    // Generate profile HTML
    const profileHTML = `
        <div class="profile-header">
            ${memberData.photo ? 
                `<img src="../uploads/member_photos/${memberData.photo}" alt="Profile" class="profile-avatar">` :
                `<div class="profile-avatar-placeholder">${memberData.first_name.charAt(0)}${memberData.last_name.charAt(0)}</div>`
            }
            <h4 class="mb-2">${memberData.first_name} ${memberData.last_name}</h4>
            <p class="text-muted mb-2">${memberData.email}</p>
            <span class="membership-status ${statusClass}">${statusText}</span>
        </div>
        
        <div class="profile-info">
            <div class="profile-row">
                <span class="profile-label">Member ID:</span>
                <span class="profile-value">${memberData.member_code}</span>
            </div>
            <div class="profile-row">
                <span class="profile-label">Phone:</span>
                <span class="profile-value">${memberData.phone || 'Not provided'}</span>
            </div>
            <div class="profile-row">
                <span class="profile-label">Gender:</span>
                <span class="profile-value">${memberData.gender ? memberData.gender.charAt(0).toUpperCase() + memberData.gender.slice(1) : 'Not specified'}</span>
            </div>
            <div class="profile-row">
                <span class="profile-label">Age:</span>
                <span class="profile-value">${memberData.age ? memberData.age + ' years old' : 'Not specified'}</span>
            </div>
            <div class="profile-row">
                <span class="profile-label">Address:</span>
                <span class="profile-value">${memberData.address || 'Not provided'}</span>
            </div>
            <div class="profile-row">
                <span class="profile-label">Membership Type:</span>
                <span class="profile-value">
                    <span class="badge bg-${memberData.member_membership_type === 'regular' ? 'primary' : 'info'}">
                        ${memberData.member_membership_type.charAt(0).toUpperCase() + memberData.member_membership_type.slice(1)}
                    </span>
                </span>
            </div>
            <div class="profile-row">
                <span class="profile-label">Join Date:</span>
                <span class="profile-value">${joinDate ? joinDate.toLocaleDateString() : 'Not specified'}</span>
            </div>
            <div class="profile-row">
                <span class="profile-label">Expiry Date:</span>
                <span class="profile-value">${expiredDate ? expiredDate.toLocaleDateString() : 'Not specified'}</span>
            </div>
            <div class="profile-row">
                <span class="profile-label">Membership Duration:</span>
                <span class="profile-value">${memberData.membership_duration ? memberData.membership_duration + ' months' : 'Not specified'}</span>
            </div>
        </div>
        
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Payment Information:</strong> This member has made payments for ${memberData.membership_type} membership.
        </div>
    `;
    
    document.getElementById('memberProfileContent').innerHTML = profileHTML;
    new bootstrap.Modal(document.getElementById('memberProfileModal')).show();
}

function viewMemberDetails() {
    if (currentMemberData) {
        // Redirect to member details page (you can create this page or modify as needed)
        window.open(`members.php?member_id=${currentMemberData.member_code}`, '_blank');
    }
}

// Print sorted payments by member type
function printSortedPayments() {
    try {
        const table = document.getElementById('paymentsTable');
        if (!table) {
            alert('Error: Payments table not found. Please refresh the page and try again.');
            return;
        }
        
        // Get all visible rows (not hidden by filters)
        const allRows = Array.from(table.querySelectorAll('tbody tr'));
        const visibleRows = allRows.filter(row => {
            // Check if row is visible (not hidden by display:none)
            const style = window.getComputedStyle(row);
            return style.display !== 'none' && !row.classList.contains('d-none');
        });
        
        // If no visible rows, try to get all rows (in case filters aren't applied)
        const rowsToProcess = visibleRows.length > 0 ? visibleRows : allRows;
        
        if (rowsToProcess.length === 0) {
            alert('No payments to print. Please check if there are any payments in the system.');
            return;
        }
        
        // Get current filter values
        const statusFilter = document.getElementById('statusFilter') ? document.getElementById('statusFilter').value : '';
        const memberTypeFilter = document.getElementById('memberTypeFilter') ? document.getElementById('memberTypeFilter').value : '';
        const searchTerm = document.getElementById('searchInput') ? document.getElementById('searchInput').value : '';
        
        // Debug logging
        console.log('Filter values:', { statusFilter, memberTypeFilter, searchTerm });
        
        // Create print content
        let printContent = `
            <html>
            <head>
                <title>Payments Report - Sorted by Member Type</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    .header { text-align: center; margin-bottom: 30px; }
                    .header h1 { color: #333; margin: 0; }
                    .header h2 { color: #666; margin: 5px 0; }
                    .filters { background: #f5f5f5; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
                    .filters h3 { margin: 0 0 10px 0; color: #333; }
                    .filter-item { margin: 5px 0; }
                    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                    th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
                    th { background-color: #f2f2f2; font-weight: bold; }
                    .member-type-regular { background-color: #e3f2fd; }
                    .member-type-student { background-color: #f3e5f5; }
                    .amount { text-align: right; font-weight: bold; }
                    .status-paid { color: #28a745; font-weight: bold; }
                    .status-pending { color: #ffc107; font-weight: bold; }
                    .status-overdue { color: #dc3545; font-weight: bold; }
                    .summary { margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 5px; }
                    .summary h3 { margin: 0 0 15px 0; color: #333; }
                    .summary-row { display: flex; justify-content: space-between; margin: 8px 0; }
                    .summary-total { font-weight: bold; font-size: 1.1em; border-top: 2px solid #333; padding-top: 8px; }
                </style>
            </head>
            <body>
                <div class="header">
                    <h1>FIT TRACK GYM</h1>
                    <h2>Payments Report - Sorted by Member Type</h2>
                    <p>Generated on: ${new Date().toLocaleString()}</p>
                </div>
                
                <div class="filters">
                    <h3>Applied Filters:</h3>
                    <div class="filter-item"><strong>Search:</strong> ${searchTerm || 'None'}</div>
                    <div class="filter-item"><strong>Status:</strong> ${statusFilter && statusFilter !== '' ? statusFilter.charAt(0).toUpperCase() + statusFilter.slice(1) : 'All'}</div>
                    <div class="filter-item"><strong>Member Type:</strong> ${memberTypeFilter && memberTypeFilter !== '' ? memberTypeFilter.charAt(0).toUpperCase() + memberTypeFilter.slice(1) : 'All'}</div>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Member Name</th>
                            <th>Member ID</th>
                            <th>Membership Type</th>
                            <th>Amount</th>
                            <th>Payment Date</th>
                            <th>Payment Method</th>
                            <th>Status</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        // Calculate totals
        let totalAmount = 0;
        let regularTotal = 0;
        let studentTotal = 0;
        let paidCount = 0;
        let pendingCount = 0;
        let overdueCount = 0;
        
        // Sort rows by member type (Regular first, then Student)
        rowsToProcess.sort((a, b) => {
            const aType = a.getAttribute('data-member-type');
            const bType = b.getAttribute('data-member-type');
            if (aType === 'regular' && bType === 'student') return -1;
            if (aType === 'student' && bType === 'regular') return 1;
            return 0;
        });
        
        rowsToProcess.forEach((row, index) => {
            const cells = row.cells;
            if (cells && cells.length >= 9) {
                // Extract member name from the member details div
                const memberNameElement = cells[1].querySelector('.member-name');
                const memberName = memberNameElement ? memberNameElement.textContent.trim() : cells[1].textContent.trim();
                
                // Extract member ID from badge
                const memberIdElement = cells[2].querySelector('.badge');
                const memberId = memberIdElement ? memberIdElement.textContent.trim() : cells[2].textContent.trim();
                
                // Extract membership type from badge
                const membershipTypeElement = cells[3].querySelector('.badge');
                const membershipType = membershipTypeElement ? membershipTypeElement.textContent.trim() : cells[3].textContent.trim();
                
                const amount = cells[4].textContent.trim();
                const paymentDate = cells[5].textContent.trim();
                
                // Extract payment method from badge or text
                const paymentMethodElement = cells[6].querySelector('.badge');
                const paymentMethod = paymentMethodElement ? paymentMethodElement.textContent.trim() : 
                                   (cells[6].textContent.trim() !== '-' ? cells[6].textContent.trim() : 'Cash');
                
                // Extract status from badge
                const statusElement = cells[7].querySelector('.badge');
                const status = statusElement ? statusElement.textContent.trim() : cells[7].textContent.trim();
                
                const notes = cells[8].textContent.trim() !== '-' ? cells[8].textContent.trim() : '';
                
                // Extract numeric amount
                const amountValue = parseFloat(amount.replace('₱', '').replace(/,/g, ''));
                totalAmount += amountValue;
                
                if (membershipType.toLowerCase().includes('regular')) {
                    regularTotal += amountValue;
                } else if (membershipType.toLowerCase().includes('student')) {
                    studentTotal += amountValue;
                }
                
                // Count status
                if (status.toLowerCase().includes('paid')) paidCount++;
                else if (status.toLowerCase().includes('pending')) pendingCount++;
                else if (status.toLowerCase().includes('overdue')) overdueCount++;
                
                const rowClass = membershipType.toLowerCase().includes('regular') ? 'member-type-regular' : 'member-type-student';
                const statusClass = status.toLowerCase().includes('paid') ? 'status-paid' : 
                                  status.toLowerCase().includes('pending') ? 'status-pending' : 'status-overdue';
                
                printContent += `
                    <tr class="${rowClass}">
                        <td>${index + 1}</td>
                        <td>${memberName}</td>
                        <td>${memberId}</td>
                        <td>${membershipType}</td>
                        <td class="amount">${amount}</td>
                        <td>${paymentDate}</td>
                        <td>${paymentMethod}</td>
                        <td class="${statusClass}">${status}</td>
                        <td>${notes}</td>
                    </tr>
                `;
            }
        });
        
        printContent += `
                    </tbody>
                </table>
                
                <div class="summary">
                    <h3>Summary Report</h3>
                    <div class="summary-row">
                        <span>Total Records:</span>
                        <span>${rowsToProcess.length}</span>
                    </div>
                    <div class="summary-row">
                        <span>Regular Members Total:</span>
                        <span>₱${regularTotal.toFixed(2)}</span>
                    </div>
                    <div class="summary-row">
                        <span>Student Members Total:</span>
                        <span>₱${studentTotal.toFixed(2)}</span>
                    </div>
                    <div class="summary-row">
                        <span>Paid Count:</span>
                        <span>${paidCount}</span>
                    </div>
                    <div class="summary-row">
                        <span>Pending Count:</span>
                        <span>${pendingCount}</span>
                    </div>
                    <div class="summary-row">
                        <span>Overdue Count:</span>
                        <span>${overdueCount}</span>
                    </div>
                    <div class="summary-row summary-total">
                        <span>Grand Total Amount:</span>
                        <span>₱${totalAmount.toFixed(2)}</span>
                    </div>
                </div>
            </body>
            </html>
        `;
        
        // Open print window
        const printWindow = window.open('', '_blank', 'width=800,height=600');
        if (!printWindow) {
            alert('Error: Unable to open print window. Please check your browser popup settings.');
            return;
        }
        
        printWindow.document.write(printContent);
        printWindow.document.close();
        
        // Wait for content to load then print
        setTimeout(() => {
            try {
                printWindow.focus();
                printWindow.print();
            } catch (printError) {
                console.error('Print error:', printError);
                alert('Error printing: ' + printError.message + '. The report has been opened in a new window.');
            }
        }, 500);
        
    } catch (error) {
        console.error('Error in printSortedPayments function:', error);
        alert('Error generating print report: ' + error.message + '. Please try again.');
    }
}

// Debug function to test the print functionality
function debugPrintFunction() {
    console.log('Testing print function...');
    const table = document.getElementById('paymentsTable');
    console.log('Table found:', !!table);
    
    if (table) {
        const rows = Array.from(table.querySelectorAll('tbody tr'));
        console.log('Total rows found:', rows.length);
        
        const visibleRows = rows.filter(row => {
            const style = window.getComputedStyle(row);
            return style.display !== 'none' && !row.classList.contains('d-none');
        });
        console.log('Visible rows:', visibleRows.length);
        
        // Check filter values
        const statusFilter = document.getElementById('statusFilter');
        const memberTypeFilter = document.getElementById('memberTypeFilter');
        const searchInput = document.getElementById('searchInput');
        
        console.log('Current filter values:', {
            status: statusFilter ? statusFilter.value : 'not found',
            memberType: memberTypeFilter ? memberTypeFilter.value : 'not found',
            search: searchInput ? searchInput.value : 'not found'
        });
        
        if (visibleRows.length > 0) {
            const firstRow = visibleRows[0];
            const cells = firstRow.cells;
            console.log('First row cells:', cells.length);
            console.log('First row data attributes:', {
                status: firstRow.getAttribute('data-status'),
                memberType: firstRow.getAttribute('data-member-type')
            });
            if (cells.length >= 9) {
                console.log('Sample data:', {
                    memberName: cells[1].textContent.trim(),
                    memberId: cells[2].textContent.trim(),
                    membershipType: cells[3].textContent.trim(),
                    amount: cells[4].textContent.trim()
                });
            }
        }
    }
}

// Payment History Functions
let allPaymentHistory = <?= json_encode($payments) ?>;

// Load payment history when modal opens
document.getElementById('paymentHistoryModal')?.addEventListener('show.bs.modal', function() {
    loadPaymentHistory();
});

function loadPaymentHistory() {
    const memberFilter = document.getElementById('historyMemberFilter')?.value || '';
    const statusFilter = document.getElementById('historyStatusFilter')?.value || '';
    const dateFrom = document.getElementById('historyDateFrom')?.value || '';
    const dateTo = document.getElementById('historyDateTo')?.value || '';
    const paymentMethod = document.getElementById('historyPaymentMethod')?.value || '';
    
    // Filter payments
    let filteredPayments = allPaymentHistory.filter(payment => {
        if (memberFilter && payment.member_code !== memberFilter) return false;
        if (statusFilter && payment.status !== statusFilter) return false;
        if (dateFrom && payment.payment_date < dateFrom) return false;
        if (dateTo && payment.payment_date > dateTo) return false;
        if (paymentMethod && payment.payment_method !== paymentMethod) return false;
        return true;
    });
    
    // Sort by date (newest first)
    filteredPayments.sort((a, b) => {
        return new Date(b.payment_date) - new Date(a.payment_date);
    });
    
    // Update statistics
    updateHistoryStats(filteredPayments);
    
    // Update table
    const tbody = document.getElementById('paymentHistoryBody');
    if (!tbody) return;
    
    if (filteredPayments.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="10" class="text-center text-muted py-4">
                    <i class="fas fa-inbox fa-3x mb-3"></i>
                    <p>No payment history found matching the filters</p>
                </td>
            </tr>
        `;
        return;
    }
    
    let html = '';
    filteredPayments.forEach((payment, index) => {
        const statusClass = payment.status === 'paid' ? 'bg-success' : 
                           payment.status === 'pending' ? 'bg-warning' : 'bg-danger';
        const membershipClass = payment.membership_type === 'regular' ? 'bg-primary' : 'bg-info';
        
        html += `
            <tr>
                <td>${index + 1}</td>
                <td>
                    <div class="member-info">
                        <div class="member-avatar">
                            ${payment.photo ? 
                                `<img src="../uploads/member_photos/${payment.photo}" alt="Profile" class="member-photo">` :
                                `<i class="fas fa-user"></i>`
                            }
                        </div>
                        <div class="member-details">
                            <div class="member-name">${payment.first_name} ${payment.last_name}</div>
                            <div class="member-email">${payment.email || ''}</div>
                        </div>
                    </div>
                </td>
                <td><span class="badge bg-primary">${payment.member_code}</span></td>
                <td><span class="badge ${membershipClass}">${payment.membership_type.charAt(0).toUpperCase() + payment.membership_type.slice(1)}</span></td>
                <td class="fw-bold text-success">₱${parseFloat(payment.amount).toFixed(2)}</td>
                <td>${new Date(payment.payment_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })}</td>
                <td>
                    ${payment.payment_method ? 
                        `<span class="badge bg-light text-dark">${payment.payment_method.charAt(0).toUpperCase() + payment.payment_method.slice(1).replace('_', ' ')}</span>` :
                        '<span class="text-muted">-</span>'
                    }
                </td>
                <td><span class="badge ${statusClass}">${payment.status.charAt(0).toUpperCase() + payment.status.slice(1)}</span></td>
                <td>
                    ${payment.notes ? 
                        `<span class="text-muted" title="${payment.notes.replace(/"/g, '&quot;')}">${payment.notes.length > 30 ? payment.notes.substring(0, 30) + '...' : payment.notes}</span>` :
                        '<span class="text-muted">-</span>'
                    }
                </td>
                <td>${payment.created_at ? new Date(payment.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-'}</td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
}

function updateHistoryStats(payments) {
    const totalRecords = payments.length;
    const totalAmount = payments.reduce((sum, p) => sum + parseFloat(p.amount), 0);
    const paidCount = payments.filter(p => p.status === 'paid').length;
    const avgAmount = totalRecords > 0 ? totalAmount / totalRecords : 0;
    
    document.getElementById('historyTotalRecords').textContent = totalRecords;
    document.getElementById('historyTotalAmount').textContent = '₱' + totalAmount.toFixed(2);
    document.getElementById('historyPaidCount').textContent = paidCount;
    document.getElementById('historyAvgAmount').textContent = '₱' + avgAmount.toFixed(2);
}

function exportPaymentHistory() {
    const tbody = document.getElementById('paymentHistoryBody');
    if (!tbody) return;
    
    const rows = tbody.querySelectorAll('tr');
    if (rows.length === 0 || rows[0].querySelector('td[colspan]')) {
        alert('No data to export');
        return;
    }
    
    let csv = 'Payment History Export\n';
    csv += 'Generated: ' + new Date().toLocaleString() + '\n\n';
    csv += '#,Member Name,Member ID,Membership Type,Amount,Payment Date,Payment Method,Status,Notes,Created At\n';
    
    rows.forEach((row, index) => {
        const cells = row.querySelectorAll('td');
        if (cells.length >= 10) {
            const rowData = [];
            rowData.push(index + 1);
            rowData.push(cells[1].textContent.trim().replace(/\n/g, ' '));
            rowData.push(cells[2].textContent.trim());
            rowData.push(cells[3].textContent.trim());
            rowData.push(cells[4].textContent.trim());
            rowData.push(cells[5].textContent.trim());
            rowData.push(cells[6].textContent.trim());
            rowData.push(cells[7].textContent.trim());
            rowData.push(cells[8].textContent.trim().replace(/"/g, '""'));
            rowData.push(cells[9].textContent.trim());
            csv += rowData.map(cell => `"${cell}"`).join(',') + '\n';
        }
    });
    
    // Download CSV
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', 'payment_history_' + new Date().toISOString().split('T')[0] + '.csv');
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function printPaymentHistory() {
    const tbody = document.getElementById('paymentHistoryBody');
    if (!tbody) return;
    
    const rows = tbody.querySelectorAll('tr');
    if (rows.length === 0 || rows[0].querySelector('td[colspan]')) {
        alert('No data to print');
        return;
    }
    
    const memberFilter = document.getElementById('historyMemberFilter');
    const statusFilter = document.getElementById('historyStatusFilter');
    const dateFrom = document.getElementById('historyDateFrom');
    const dateTo = document.getElementById('historyDateTo');
    
    let printContent = `
        <html>
        <head>
            <title>Payment History Report</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                .header h1 { color: #333; margin: 0; }
                .header h2 { color: #666; margin: 5px 0; }
                .filters { background: #f5f5f5; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
                .filters h3 { margin: 0 0 10px 0; color: #333; }
                .filter-item { margin: 5px 0; }
                .stats { display: flex; justify-content: space-around; margin-bottom: 20px; }
                .stat-item { text-align: center; padding: 10px; background: #f8f9fa; border-radius: 5px; }
                .stat-label { font-size: 12px; color: #666; }
                .stat-value { font-size: 18px; font-weight: bold; color: #333; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 12px; }
                th { background-color: #f2f2f2; font-weight: bold; }
                .amount { text-align: right; font-weight: bold; }
                .status-paid { color: #28a745; font-weight: bold; }
                .status-pending { color: #ffc107; font-weight: bold; }
                .status-overdue { color: #dc3545; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>FIT TRACK GYM</h1>
                <h2>Payment History Report</h2>
                <p>Generated on: ${new Date().toLocaleString()}</p>
            </div>
            
            <div class="filters">
                <h3>Applied Filters:</h3>
                <div class="filter-item"><strong>Member:</strong> ${memberFilter?.options[memberFilter.selectedIndex]?.text || 'All'}</div>
                <div class="filter-item"><strong>Status:</strong> ${statusFilter?.options[statusFilter.selectedIndex]?.text || 'All'}</div>
                <div class="filter-item"><strong>Date From:</strong> ${dateFrom?.value || 'N/A'}</div>
                <div class="filter-item"><strong>Date To:</strong> ${dateTo?.value || 'N/A'}</div>
            </div>
            
            <div class="stats">
                <div class="stat-item">
                    <div class="stat-label">Total Records</div>
                    <div class="stat-value">${document.getElementById('historyTotalRecords')?.textContent || '0'}</div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Total Amount</div>
                    <div class="stat-value">${document.getElementById('historyTotalAmount')?.textContent || '₱0.00'}</div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Paid Count</div>
                    <div class="stat-value">${document.getElementById('historyPaidCount')?.textContent || '0'}</div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Average Amount</div>
                    <div class="stat-value">${document.getElementById('historyAvgAmount')?.textContent || '₱0.00'}</div>
                </div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Member Name</th>
                        <th>Member ID</th>
                        <th>Membership Type</th>
                        <th>Amount</th>
                        <th>Payment Date</th>
                        <th>Payment Method</th>
                        <th>Status</th>
                        <th>Notes</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    rows.forEach((row, index) => {
        const cells = row.querySelectorAll('td');
        if (cells.length >= 10) {
            const memberName = cells[1].textContent.trim().replace(/\n/g, ' ');
            const memberId = cells[2].textContent.trim();
            const membershipType = cells[3].textContent.trim();
            const amount = cells[4].textContent.trim();
            const paymentDate = cells[5].textContent.trim();
            const paymentMethod = cells[6].textContent.trim();
            const status = cells[7].textContent.trim();
            const notes = cells[8].textContent.trim();
            const createdAt = cells[9].textContent.trim();
            
            const statusClass = status.toLowerCase().includes('paid') ? 'status-paid' : 
                               status.toLowerCase().includes('pending') ? 'status-pending' : 'status-overdue';
            
            printContent += `
                <tr>
                    <td>${index + 1}</td>
                    <td>${memberName}</td>
                    <td>${memberId}</td>
                    <td>${membershipType}</td>
                    <td class="amount">${amount}</td>
                    <td>${paymentDate}</td>
                    <td>${paymentMethod}</td>
                    <td class="${statusClass}">${status}</td>
                    <td>${notes}</td>
                    <td>${createdAt}</td>
                </tr>
            `;
        }
    });
    
    printContent += `
                </tbody>
            </table>
        </body>
        </html>
    `;
    
    const printWindow = window.open('', '_blank', 'width=1200,height=800');
    if (!printWindow) {
        alert('Error: Unable to open print window. Please check your browser popup settings.');
        return;
    }
    
    printWindow.document.write(printContent);
    printWindow.document.close();
    
    setTimeout(() => {
        printWindow.focus();
        printWindow.print();
    }, 500);
}
</script>

<?php include 'components/footer.php'; ?>
