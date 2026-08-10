<?php

$page_title = "Feedback Management";
include '../includes/functions.php';
include 'components/header.php';

// Handle feedback actions
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $feedback_id = intval($_POST['feedback_id']);
        $new_status = $_POST['status'];
        $admin_response = trim($_POST['admin_response'] ?? '');
        
        $sql = "UPDATE feedback SET status = ?, admin_response = ?, admin_id = ?, admin_name = ?, updated_at = NOW()";
        if ($new_status === 'resolved') {
            $sql .= ", resolved_at = NOW()";
        }
        $sql .= " WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("ssiis", $new_status, $admin_response, $admin_id, $admin_name, $feedback_id);
            if ($stmt->execute()) {
                $success_message = "Feedback status updated successfully!";
            } else {
                $error_message = "Error updating feedback status.";
            }
            $stmt->close();
        } else {
            $error_message = "Database error.";
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = [];
$params = [];
$param_types = '';

if ($status_filter !== 'all') {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}



if (!empty($search)) {
    $where_conditions[] = "(subject LIKE ? OR message LIKE ? OR member_name LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $param_types .= 'sss';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get feedback data
$sql = "SELECT * FROM feedback $where_clause ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
if ($stmt && !empty($params)) {
    $stmt->bind_param($param_types, ...$params);
} elseif ($stmt) {
    // No parameters
}

$feedback_list = [];
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $feedback_list[] = $row;
    }
    $stmt->close();
}

// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
    SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed,
    AVG(rating) as avg_rating
    FROM feedback";

$stats_result = $conn->query($stats_sql);
$stats = $stats_result ? $stats_result->fetch_assoc() : [];
?>


<!-- Alert Messages -->
<?php if ($success_message): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?php echo $success_message; ?>
    </div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo $error_message; ?>
    </div>
<?php endif; ?>

<!-- Statistics Overview -->
<div class="stats-overview">
    <div class="stats-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">
                <i class="fas fa-comment-dots"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
                <div class="stat-label">Total Feedback</div>
            </div>
        </div>
        
        <div class="stat-card stat-warning">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo $stats['pending'] ?? 0; ?></div>
                <div class="stat-label">Pending</div>
            </div>
        </div>

        <div class="stat-card stat-info">
            <div class="stat-icon">
                <i class="fas fa-spinner"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo $stats['in_progress'] ?? 0; ?></div>
                <div class="stat-label">In Progress</div>
            </div>
        </div>

        <div class="stat-card stat-success">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo $stats['resolved'] ?? 0; ?></div>
                <div class="stat-label">Resolved</div>
            </div>
        </div>

        <div class="stat-card stat-secondary">
            <div class="stat-icon">
                <i class="fas fa-star"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo number_format($stats['avg_rating'] ?? 0, 1); ?></div>
                <div class="stat-label">Avg Rating</div>
            </div>
        </div>
    </div>
</div>

<!-- Compact Filters & View Controls -->
<div class="compact-controls">
    <div class="controls-row">
        <!-- Search Input -->
        <div class="search-container">
            <div class="search-input-group">
                <i class="fas fa-search search-icon"></i>
                <input type="text" class="search-input" id="searchFilter" placeholder="Search feedback..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters-container">
            <form method="GET" action="" class="d-flex align-items-center gap-2">
                <select name="status" class="filter-select">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                    <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                    <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                </select>
                
                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                
                <button type="submit" class="btn btn-primary btn-filter">
                    <i class="fas fa-filter me-1"></i>Filter
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Feedback Container -->
<div class="feedback-container">
    <div id="feedbackList">

        <?php if (!empty($feedback_list)): ?>
            <div class="feedback-grid" id="feedbackContainer">
                <?php foreach ($feedback_list as $feedback): ?>
                    <div class="feedback-card" data-id="<?php echo $feedback['id']; ?>" 
                         data-status="<?php echo $feedback['status']; ?>" 
                         data-subject="<?php echo htmlspecialchars(strtolower($feedback['subject'])); ?>">
                        
                        <!-- Status Badge -->
                        <div class="status-badge status-<?php echo $feedback['status']; ?>">
                            <span class="status-dot"></span>
                            <?php echo ucfirst($feedback['status']); ?>
                        </div>
                        
                        <!-- Card Header -->
                        <div class="card-header">
                            <div class="header-meta">
                                <div class="feedback-subject">
                                    <h3 class="feedback-title">
                                        <?php echo htmlspecialchars($feedback['subject']); ?>
                                    </h3>
                                </div>
                                
                                <!-- Action Menu -->
                                <div class="action-menu">
                                    <button class="action-btn btn-respond" 
                                            onclick="openResponseModal(<?php echo $feedback['id']; ?>, '<?php echo htmlspecialchars($feedback['subject']); ?>', '<?php echo $feedback['status']; ?>', '<?php echo htmlspecialchars($feedback['admin_response']); ?>')">
                                        <i class="fas fa-reply"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Card Content -->
                        <div class="card-content">
                            <div class="feedback-message">
                                <?php echo nl2br(htmlspecialchars($feedback['message'])); ?>
                            </div>
                            
                            <?php if ($feedback['rating'] > 0): ?>
                                <div class="feedback-rating">
                                    <span>Rating: </span>
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <span class="star <?php echo $i <= $feedback['rating'] ? 'filled' : 'empty'; ?>">★</span>
                                    <?php endfor; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Card Footer -->
                        <div class="card-footer">
                            <div class="footer-info">
                                <div class="info-item">
                                    <i class="fas fa-<?php echo $feedback['is_anonymous'] ? 'user-secret' : 'user'; ?>"></i>
                                    <span>
                                        <?php if ($feedback['is_anonymous']): ?>
                                            <span class="anonymous-member">Anonymous Member</span>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($feedback['member_name']); ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-clock"></i>
                                    <span><?php echo date('M j, Y g:i A', strtotime($feedback['created_at'])); ?></span>
                                </div>
                            </div>
                            
                            <?php if (!empty($feedback['admin_response'])): ?>
                                <div class="admin-response-section">
                                    <h6><i class="fas fa-reply me-2"></i>Admin Response</h6>
                                    <p><?php echo nl2br(htmlspecialchars($feedback['admin_response'])); ?></p>
                                    <?php if ($feedback['admin_name']): ?>
                                        <small><i class="fas fa-user me-1"></i>By: <?php echo htmlspecialchars($feedback['admin_name']); ?></small>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-comment-slash"></i>
                </div>
                <h3 class="empty-title">No feedback found</h3>
                <p class="empty-description">Try adjusting your filters or search terms.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modern Response Modal -->
<div class="modal fade" id="responseModal" tabindex="-1" aria-labelledby="responseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content modern-modal">
            <div class="modal-header modern-header">
                <div class="header-content">
                    <h5 class="modal-title" id="responseModalLabel">
                        <i class="fas fa-reply me-2"></i>
                        Respond to Feedback
                    </h5>
                    <p class="modal-subtitle">Update feedback status and provide admin response</p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" id="modal_feedback_id" name="feedback_id" value="">
                
                <div class="modal-body modern-body">
                    <div class="form-sections">
                        <!-- Status Section -->
                        <div class="form-section">
                            <div class="section-header">
                                <i class="fas fa-flag section-icon"></i>
                                <h6 class="section-title">Feedback Status</h6>
                            </div>
                            <div class="form-group-modern">
                                <label for="modal_status" class="form-label-modern">Status *</label>
                                <select id="modal_status" name="status" class="form-select form-select-modern" required>
                                    <option value="pending">Pending</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="resolved">Resolved</option>
                                    <option value="closed">Closed</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Response Section -->
                        <div class="form-section">
                            <div class="section-header">
                                <i class="fas fa-comment section-icon"></i>
                                <h6 class="section-title">Admin Response</h6>
                            </div>
                            <div class="form-group-modern">
                                <label for="modal_response" class="form-label-modern">Response Message</label>
                                <textarea id="modal_response" name="admin_response" class="form-control form-control-modern" 
                                          placeholder="Enter your response to the member..." rows="6"></textarea>
                                <div class="form-help">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Provide a helpful response to address the member's feedback.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer modern-footer">
                    <button type="button" class="btn btn-secondary btn-modern" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancel
                    </button>
                    <button type="submit" name="update_status" class="btn btn-primary btn-modern">
                        <i class="fas fa-save me-1"></i>Update Feedback
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Include CSS -->
<link href="../assets/css/admin/feedback.css" rel="stylesheet">

<!-- Include JavaScript -->
<script>
// Search functionality
document.getElementById('searchFilter').addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const cards = document.querySelectorAll('.feedback-card');
    
    cards.forEach(card => {
        const subject = card.dataset.subject || '';
        const message = card.querySelector('.feedback-message')?.textContent.toLowerCase() || '';
        const memberName = card.querySelector('.info-item span')?.textContent.toLowerCase() || '';
        
        const matches = subject.includes(searchTerm) || 
                       message.includes(searchTerm) || 
                       memberName.includes(searchTerm);
        
        card.style.display = matches ? 'block' : 'none';
    });
});

// Status filtering
document.querySelectorAll('.filter-select').forEach(select => {
    select.addEventListener('change', function() {
        const selectedStatus = this.value;
        const cards = document.querySelectorAll('.feedback-card');
        
        cards.forEach(card => {
            const cardStatus = card.dataset.status;
            const shouldShow = selectedStatus === 'all' || cardStatus === selectedStatus;
            card.style.display = shouldShow ? 'block' : 'none';
        });
    });
});

// Response modal functionality
function openResponseModal(feedbackId, subject, currentStatus, currentResponse) {
    document.getElementById('modal_feedback_id').value = feedbackId;
    document.getElementById('modal_status').value = currentStatus;
    document.getElementById('modal_response').value = currentResponse;
    
    const modal = new bootstrap.Modal(document.getElementById('responseModal'));
    modal.show();
}

// Auto-resize textarea
document.getElementById('modal_response').addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = this.scrollHeight + 'px';
});

// Real-time clock
function updateClock() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', { 
        hour12: true, 
        hour: '2-digit', 
        minute: '2-digit', 
        second: '2-digit' 
    });
    
    const clockElement = document.getElementById('currentTime');
    if (clockElement) {
        clockElement.textContent = timeString;
    }
}

// Update clock every second
setInterval(updateClock, 1000);
updateClock(); // Initial call

// Card hover effects
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.feedback-card');
    
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
});

// Smooth animations
document.addEventListener('DOMContentLoaded', function() {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    });
    
    document.querySelectorAll('.feedback-card').forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'all 0.3s ease';
        observer.observe(card);
    });
});
</script>

<?php include 'components/footer.php'; ?>