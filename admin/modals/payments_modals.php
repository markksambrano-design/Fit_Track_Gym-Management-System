<!-- Add Payment Modal -->
<div class="modal fade" id="addPaymentModal" tabindex="-1" aria-labelledby="addPaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" method="POST">
            <input type="hidden" name="action" value="add_payment">
            <div class="modal-header">
                <h5 class="modal-title" id="addPaymentModalLabel">
                    <i class="fas fa-credit-card me-1"></i> Add Payment
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
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
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="payment_method" class="form-label">Payment Method *</label>
                            <select class="form-select" name="payment_method" id="payment_method" required>
                                <option value="">Select Method</option>
                                <option value="cash">Cash</option>
                                <option value="gcash">GCash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="credit_card">Credit Card</option>
                                <option value="debit_card">Debit Card</option>
                                <option value="paypal">PayPal</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="transaction_id" class="form-label">Transaction ID</label>
                            <input type="text" class="form-control" name="transaction_id" id="transaction_id" 
                                   placeholder="Optional transaction reference">
                        </div>
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" id="notes" rows="3" 
                                      placeholder="Optional notes about this payment"></textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i>Save Payment
                </button>
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
                        <option value="overdue">Overdue</option>
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

<!-- Bulk Update Modal -->
<div class="modal fade" id="bulkUpdateModal" tabindex="-1" aria-labelledby="bulkUpdateModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="POST">
            <input type="hidden" name="action" value="bulk_update_status">
            <div class="modal-header">
                <h5 class="modal-title" id="bulkUpdateModalLabel">
                    <i class="fas fa-edit me-1"></i> Bulk Update Payment Status
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>You are about to update <span id="selectedCount">0</span> payment(s).</p>
                <div class="mb-3">
                    <label for="bulk_status" class="form-label">New Status *</label>
                    <select class="form-select" name="bulk_status" id="bulk_status" required>
                        <option value="">Select Status</option>
                        <option value="pending">Pending</option>
                        <option value="paid">Paid</option>
                        <option value="overdue">Overdue</option>
                    </select>
                </div>
                <div id="selectedPayments"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update All</button>
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
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-trash-alt me-1"></i> Delete Payment
                </button>
            </div>
        </form>
    </div>
</div>

<style>
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

/* Modern Modal Styles */
.modal-content {
    background: rgba(255, 255, 255, 0.1) !important;
    -webkit-backdrop-filter: blur(20px) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
    border-radius: var(--border-radius) !important;
    color: white !important;
}

.modal-header {
    background: rgba(255, 255, 255, 0.05) !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
    color: white !important;
}

.modal-title {
    color: white !important;
}

.modal-body {
    color: white !important;
}

.modal-footer {
    background: rgba(255, 255, 255, 0.05) !important;
    border-top: 1px solid rgba(255, 255, 255, 0.1) !important;
}

/* Form Controls */
.form-control,
.form-select {
    background: rgba(255, 255, 255, 0.1) !important;
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
    color: white !important;
    -webkit-backdrop-filter: blur(10px) !important;
    backdrop-filter: blur(10px) !important;
}

.form-control:focus,
.form-select:focus {
    background: rgba(255, 255, 255, 0.15) !important;
    border-color: var(--primary-color) !important;
    box-shadow: 0 0 0 3px rgba(78, 115, 223, 0.2) !important;
    color: white !important;
}

.form-control::placeholder {
    color: rgba(255, 255, 255, 0.6) !important;
}

.form-label {
    color: rgba(255, 255, 255, 0.9) !important;
    font-weight: 600 !important;
}

/* Modal Buttons */
.modal .btn-light {
    background: rgba(255, 255, 255, 0.1) !important;
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
    color: white !important;
}

.modal .btn-light:hover {
    background: rgba(255, 255, 255, 0.2) !important;
}

.modal .btn-primary {
    background: var(--gradient-primary) !important;
    border: 1px solid rgba(78, 115, 223, 0.3) !important;
    color: white !important;
}

.modal .btn-success {
    background: var(--gradient-success) !important;
    border: 1px solid rgba(28, 200, 138, 0.3) !important;
    color: white !important;
}

.modal .btn-danger {
    background: linear-gradient(135deg, #dc3545, #e74c3c) !important;
    border: 1px solid rgba(231, 76, 60, 0.3) !important;
    color: white !important;
}

/* Close Button */
.btn-close {
    filter: invert(1) !important;
}

.btn-close-white {
    filter: invert(1) !important;
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
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
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

function selectAllPayments() {
    const checkboxes = document.querySelectorAll('.payment-checkbox');
    const selectAllCheckbox = document.getElementById('selectAll');
    const isChecked = selectAllCheckbox.checked;
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = isChecked;
    });
    
    updateBulkButton();
}

function toggleSelectAll() {
    selectAllPayments();
}

function updateBulkButton() {
    const checkboxes = document.querySelectorAll('.payment-checkbox:checked');
    const bulkBtn = document.getElementById('bulkUpdateBtn');
    
    if (checkboxes.length > 0) {
        bulkBtn.style.display = 'inline-block';
        bulkBtn.innerHTML = `<i class="fas fa-edit me-1"></i>Bulk Update (${checkboxes.length})`;
    } else {
        bulkBtn.style.display = 'none';
    }
}

function bulkUpdateStatus() {
    const checkboxes = document.querySelectorAll('.payment-checkbox:checked');
    const selectedIds = Array.from(checkboxes).map(cb => cb.value);
    
    if (selectedIds.length === 0) {
        alert('Please select at least one payment.');
        return;
    }
    
    document.getElementById('selectedCount').textContent = selectedIds.length;
    document.getElementById('selectedPayments').innerHTML = '';
    
    // Add hidden inputs for selected payment IDs
    selectedIds.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'payment_ids[]';
        input.value = id;
        document.getElementById('selectedPayments').appendChild(input);
    });
    
    new bootstrap.Modal(document.getElementById('bulkUpdateModal')).show();
}

// Auto-calculate amount based on membership type
document.getElementById('member_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const amountField = document.getElementById('amount');
    
    if (selectedOption.value) {
        const membershipType = selectedOption.text.includes('Regular') ? 'regular' : 'student';
        const amount = membershipType === 'regular' ? 1000 : 700;
        amountField.value = amount;
    } else {
        amountField.value = '';
    }
});

// Update payment statuses
function updatePaymentStatuses() {
    if (confirm('This will update payment statuses for expired memberships. Continue?')) {
        const button = event.target;
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Updating...';
        button.disabled = true;
        
        fetch('payment_status_updater.php')
            .then(response => response.text())
            .then(data => {
                alert('Payment statuses updated successfully!');
                location.reload();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating payment statuses. Please try again.');
            })
            .finally(() => {
                button.innerHTML = originalText;
                button.disabled = false;
            });
    }
}

// Export payments
function exportPayments() {
    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set('export', '1');
    window.open(currentUrl.toString(), '_blank');
}

// Print receipt functionality
function printReceipt(paymentId) {
    // Get payment data from the table row
    const row = event.target.closest('tr');
    const cells = row.cells;
    
    const memberName = cells[2].querySelector('.fw-bold').textContent;
    const memberId = cells[2].querySelector('small').textContent;
    const amount = cells[3].textContent;
    const paymentDate = cells[4].textContent;
    const paymentMethod = cells[5].querySelector('.badge') ? cells[5].querySelector('.badge').textContent : '-';
    
    // Generate receipt HTML
    const receiptHTML = generateReceiptHTML({
        memberName: memberName,
        memberId: memberId,
        amount: amount,
        paymentDate: paymentDate,
        paymentMethod: paymentMethod,
        receiptNumber: 'RCP-' + String(paymentId).padStart(6, '0'),
        transactionId: 'TXN-' + Date.now()
    });
    
    // Display receipt in modal
    document.getElementById('receiptContent').innerHTML = receiptHTML;
    new bootstrap.Modal(document.getElementById('receiptModal')).show();
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
                    <span class="label">Payment Method:</span>
                    <span class="value">${data.paymentMethod}</span>
                </div>
                <div class="receipt-row">
                    <span class="label">Transaction ID:</span>
                    <span class="value">${data.transactionId}</span>
                </div>
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
    window.print();
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
        window.open(`members.php?member_id=${currentMemberData.member_code}`, '_blank');
    }
}

function viewPaymentDetails(paymentId) {
    // This function can be implemented to show detailed payment information
    alert('Payment details feature coming soon!');
}
</script>
