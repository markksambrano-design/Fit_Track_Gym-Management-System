<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication check
if (!isset($_SESSION['member_logged_in']) || !$_SESSION['member_logged_in']) {
    header('Location: login.php');
    exit;
}

require_once '../includes/db.php';
require_once '../includes/functions.php';

$member_id = $_SESSION['member_id'] ?? 0;
$member_name = $_SESSION['member_name'] ?? 'Member';

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $rating = intval($_POST['rating'] ?? 0);
    $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;
    $category = trim($_POST['category'] ?? 'general');
    $priority = trim($_POST['priority'] ?? 'medium');
    
    // Validation
    if (empty($subject) || empty($message)) {
        $_SESSION['feedback_error'] = "Please fill in all required fields.";
        header('Location: feedback.php');
        exit;
    } else {
        // Prepare data
        $display_name = $is_anonymous ? 'Anonymous Member' : $member_name;
        
        // Insert feedback
        $sql = "INSERT INTO feedback (member_id, member_name, subject, message, rating, category, priority, status, is_anonymous) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?)";
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            $stmt->bind_param("isssissi", $member_id, $display_name, $subject, $message, $rating, $category, $priority, $is_anonymous);
            
            if ($stmt->execute()) {
                if ($is_anonymous) {
                    $_SESSION['feedback_success'] = "Thank you for your anonymous feedback!";
                } else {
                    $_SESSION['feedback_success'] = "Thank you for your feedback!";
                }
                $stmt->close();
                header('Location: feedback.php');
                exit;
            } else {
                $_SESSION['feedback_error'] = "Error submitting feedback.";
                $stmt->close();
                header('Location: feedback.php');
                exit;
            }
        } else {
            $_SESSION['feedback_error'] = "Database error.";
            header('Location: feedback.php');
            exit;
        }
    }
}

$page_title = "Feedback";
include 'components/header.php';

// Handle messages
$success_message = '';
$error_message = '';

if (isset($_SESSION['feedback_success'])) {
    $success_message = $_SESSION['feedback_success'];
    unset($_SESSION['feedback_success']);
}

if (isset($_SESSION['feedback_error'])) {
    $error_message = $_SESSION['feedback_error'];
    unset($_SESSION['feedback_error']);
}

// Get member's feedback history
$feedback_history = [];
$history_sql = "SELECT * FROM feedback WHERE member_id = ? ORDER BY created_at DESC LIMIT 10";
$history_stmt = $conn->prepare($history_sql);
if ($history_stmt) {
    $history_stmt->bind_param("i", $member_id);
    $history_stmt->execute();
    $result = $history_stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $feedback_history[] = $row;
    }
    $history_stmt->close();
}
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

<!-- Feedback Form -->
<div class="card mb-4">
    <div class="card-header">
        <h5><i class="fas fa-paper-plane"></i> Submit Feedback</h5>
    </div>
    <div class="card-body">
        <form method="POST">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Subject *</label>
                        <input type="text" name="subject" class="form-control" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Category *</label>
                        <select name="category" class="form-select" required>
                            <option value="general">General</option>
                            <option value="facility">Facility</option>
                            <option value="staff">Staff</option>
                            <option value="equipment">Equipment</option>
                            <option value="suggestion">Suggestion</option>
                            <option value="complaint">Complaint</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Priority *</label>
                        <select name="priority" class="form-select" required>
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Rating (Optional)</label>
                        <div class="rating-container">
                            <div class="rating-stars">
                                <input type="radio" id="rating1" name="rating" value="1">
                                <label for="rating1" class="star">★</label>
                                <input type="radio" id="rating2" name="rating" value="2">
                                <label for="rating2" class="star">★</label>
                                <input type="radio" id="rating3" name="rating" value="3">
                                <label for="rating3" class="star">★</label>
                                <input type="radio" id="rating4" name="rating" value="4">
                                <label for="rating4" class="star">★</label>
                                <input type="radio" id="rating5" name="rating" value="5">
                                <label for="rating5" class="star">★</label>
                            </div>
                            <span class="rating-text">Click to rate</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Message *</label>
                <textarea name="message" class="form-control" rows="4" required></textarea>
            </div>
            
            <div class="mb-3">
                <div class="form-check">
                    <input type="checkbox" name="is_anonymous" value="1" class="form-check-input" id="is_anonymous">
                    <label class="form-check-label" for="is_anonymous">
                        Submit anonymously
                    </label>
                </div>
            </div>
            
            <button type="submit" name="submit_feedback" class="btn btn-primary">
                <i class="fas fa-paper-plane"></i> Submit Feedback
            </button>
        </form>
    </div>
</div>

<!-- Feedback History -->
<div class="feedback-history-section">
    <div class="feedback-history-card">
        <div class="history-header">
            <h3 class="history-title">
                <i class="fas fa-history"></i>
                Your Feedback History
            </h3>
            <p class="history-subtitle">Track the status of your submitted feedback</p>
        </div>
        
        <?php if (!empty($feedback_history)): ?>
            <div class="feedback-history-grid">
                <?php foreach ($feedback_history as $feedback): ?>
                    <div class="feedback-card" data-status="<?php echo $feedback['status']; ?>">
                        <!-- Status Badge -->
                        <div class="status-badge status-<?php echo $feedback['status']; ?>">
                            <span class="status-dot"></span>
                            <?php echo ucfirst($feedback['status']); ?>
                        </div>
                        
                        <!-- Card Header -->
                        <div class="card-header">
                            <div class="header-meta">
                                <div class="feedback-subject">
                                    <h4 class="feedback-title">
                                        <?php echo htmlspecialchars($feedback['subject']); ?>
                                        <?php if ($feedback['is_anonymous']): ?>
                                            <span class="anonymous-badge">
                                                <i class="fas fa-user-secret"></i>
                                                Anonymous
                                            </span>
                                        <?php endif; ?>
                                    </h4>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Card Content -->
                        <div class="card-content">
                            <div class="feedback-message">
                                <?php echo nl2br(htmlspecialchars($feedback['message'])); ?>
                            </div>
                            
                            <div class="feedback-meta">
                                <?php if ($feedback['rating'] > 0): ?>
                                    <div class="feedback-rating">
                                        <span>Rating: </span>
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <span class="star <?php echo $i <= $feedback['rating'] ? 'filled' : 'empty'; ?>">★</span>
                                        <?php endfor; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="feedback-category">
                                    <span><i class="fas fa-tag"></i> Category: </span>
                                    <span class="category-badge"><?php echo ucfirst($feedback['category'] ?? 'general'); ?></span>
                                </div>
                                
                                <div class="feedback-priority">
                                    <span><i class="fas fa-exclamation-circle"></i> Priority: </span>
                                    <span class="priority-badge priority-<?php echo $feedback['priority'] ?? 'medium'; ?>">
                                        <?php echo ucfirst($feedback['priority'] ?? 'medium'); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Card Footer -->
                        <div class="card-footer">
                            <div class="footer-info">
                                <div class="info-item">
                                    <i class="fas fa-clock"></i>
                                    <span>Submitted: <?php echo date('M j, Y g:i A', strtotime($feedback['created_at'])); ?></span>
                                </div>
                                <?php if ($feedback['updated_at'] != $feedback['created_at']): ?>
                                    <div class="info-item">
                                        <i class="fas fa-edit"></i>
                                        <span>Updated: <?php echo date('M j, Y g:i A', strtotime($feedback['updated_at'])); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($feedback['admin_response'])): ?>
                                <div class="admin-response-section">
                                    <h6><i class="fas fa-reply"></i>Admin Response</h6>
                                    <p><?php echo nl2br(htmlspecialchars($feedback['admin_response'])); ?></p>
                                    <?php if ($feedback['admin_name']): ?>
                                        <small><i class="fas fa-user"></i>By: <?php echo htmlspecialchars($feedback['admin_name']); ?></small>
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
                <h3 class="empty-title">No feedback submitted yet</h3>
                <p class="empty-description">Submit your first feedback to get started</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- View Feedback Modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Feedback Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="feedbackDetails">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<style>
/* Interactive Star Rating */
.rating-container {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.rating-stars {
    display: flex;
    gap: 5px;
}

.rating-stars input[type="radio"] {
    display: none;
}

.rating-stars .star {
    font-size: 24px;
    color: #ddd;
    cursor: pointer;
    transition: all 0.2s ease;
    margin: 0;
}

.rating-stars .star:hover {
    color: #ffd700;
    transform: scale(1.1);
}

.rating-stars input[type="radio"]:checked ~ .star,
.rating-stars input[type="radio"]:checked + .star {
    color: #ffd700;
    transform: scale(1.1);
}

.rating-stars:hover .star {
    color: #ffd700;
}

.rating-text {
    color: #666;
    font-size: 14px;
    font-style: italic;
}

/* Feedback History Card Layout */
.feedback-history-section {
    margin-bottom: 30px;
}

.feedback-history-card {
    background: rgba(30, 41, 59, 0.7);
    -webkit-backdrop-filter: blur(15px);
    backdrop-filter: blur(15px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    color: white;
    overflow: hidden;
    transition: all 0.3s ease;
    padding: 30px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

.feedback-history-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
    border-color: rgba(255, 255, 255, 0.2);
    background: rgba(30, 41, 59, 0.9);
}

.history-header {
    margin-bottom: 25px;
}

.history-title {
    font-size: 1.8rem;
    font-weight: 600;
    color: #f8f9fc;
    margin: 0 0 8px 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.history-subtitle {
    color: rgba(255, 255, 255, 0.7);
    font-size: 1rem;
    margin: 0;
    font-weight: 400;
}

/* Feedback History Grid */
.feedback-history-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    gap: 20px;
}

@media (max-width: 768px) {
    .feedback-history-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }
}

/* Feedback Cards */
.feedback-card {
    background: rgba(15, 23, 42, 0.8);
    -webkit-backdrop-filter: blur(10px);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 15px;
    overflow: hidden;
    transition: all 0.3s ease;
    position: relative;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
}

.feedback-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.4);
    background: rgba(15, 23, 42, 0.9);
    border-color: rgba(255, 255, 255, 0.2);
}

/* Status Badge */
.status-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.status-badge.status-pending {
    background: rgba(251, 191, 36, 0.2);
    color: #fbbf24;
    border: 1px solid rgba(251, 191, 36, 0.3);
}

.status-badge.status-in_progress {
    background: rgba(59, 130, 246, 0.2);
    color: #3b82f6;
    border: 1px solid rgba(59, 130, 246, 0.3);
}

.status-badge.status-resolved {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.status-badge.status-closed {
    background: rgba(107, 114, 128, 0.2);
    color: #6b7280;
    border: 1px solid rgba(107, 114, 128, 0.3);
}

.status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
}

/* Card Header */
.feedback-card .card-header {
    padding: 20px 20px 15px 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.feedback-card .header-meta {
    display: flex;
    align-items: flex-start;
    gap: 15px;
}

.feedback-card .feedback-title {
    font-size: 1.2rem;
    font-weight: 600;
    color: #f8f9fc;
    margin: 0;
    line-height: 1.4;
}

.anonymous-badge {
    background: linear-gradient(45deg, #667eea, #764ba2);
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-left: 10px;
    vertical-align: middle;
}

/* Card Content */
.feedback-card .card-content {
    padding: 15px 20px;
}

.feedback-card .feedback-message {
    color: rgba(255, 255, 255, 0.8);
    line-height: 1.6;
    margin-bottom: 15px;
    font-size: 0.9rem;
}

.feedback-card .feedback-rating {
    display: flex;
    align-items: center;
    gap: 5px;
    margin-bottom: 15px;
}

.feedback-card .star {
    color: #fbbf24;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.feedback-card .star.empty {
    color: rgba(255, 255, 255, 0.3);
}

.feedback-card .star.filled {
    color: #fbbf24;
    text-shadow: 0 0 5px rgba(251, 191, 36, 0.5);
}

/* Feedback Meta */
.feedback-meta {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 15px;
}

.feedback-category,
.feedback-priority {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.85rem;
}

.feedback-category span:first-child,
.feedback-priority span:first-child {
    color: rgba(255, 255, 255, 0.7);
    font-weight: 500;
}

.category-badge {
    background: rgba(59, 130, 246, 0.2);
    color: #3b82f6;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.priority-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.priority-badge.priority-low {
    background: rgba(107, 114, 128, 0.2);
    color: #6b7280;
}

.priority-badge.priority-medium {
    background: rgba(59, 130, 246, 0.2);
    color: #3b82f6;
}

.priority-badge.priority-high {
    background: rgba(251, 191, 36, 0.2);
    color: #fbbf24;
}

.priority-badge.priority-urgent {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
}

/* Card Footer */
.feedback-card .card-footer {
    padding: 15px 20px 20px 20px;
    background: rgba(255, 255, 255, 0.02);
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.feedback-card .footer-info {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 15px;
}

.feedback-card .info-item {
    display: flex;
    align-items: center;
    gap: 8px;
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.85rem;
}

.feedback-card .info-item i {
    color: #3b82f6;
    width: 14px;
    text-align: center;
}

/* Admin Response Section */
.feedback-card .admin-response-section {
    background: rgba(16, 185, 129, 0.1);
    border-left: 4px solid #10b981;
    padding: 15px;
    border-radius: 0 8px 8px 0;
    margin-top: 15px;
    border: 1px solid rgba(16, 185, 129, 0.2);
    -webkit-backdrop-filter: blur(5px);
    backdrop-filter: blur(5px);
}

.feedback-card .admin-response-section h6 {
    color: #10b981;
    font-weight: 600;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
}

.feedback-card .admin-response-section p {
    color: #f8f9fc;
    margin-bottom: 8px;
    line-height: 1.5;
    font-size: 0.85rem;
}

.feedback-card .admin-response-section small {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.75rem;
    display: flex;
    align-items: center;
    gap: 5px;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: rgba(255, 255, 255, 0.6);
}

.empty-state .empty-icon {
    font-size: 4rem;
    margin-bottom: 20px;
    color: rgba(255, 255, 255, 0.3);
}

.empty-state .empty-title {
    color: #f8f9fc;
    margin-bottom: 10px;
    font-weight: 600;
    font-size: 1.5rem;
}

.empty-state .empty-description {
    color: rgba(255, 255, 255, 0.6);
    font-size: 1rem;
    margin: 0;
}
</style>

<script>
function viewFeedback(feedbackId) {
    // Get feedback details via AJAX
    fetch(`get_feedback_details.php?id=${feedbackId}`)
        .then(response => response.text())
        .then(data => {
            document.getElementById('feedbackDetails').innerHTML = data;
            new bootstrap.Modal(document.getElementById('viewModal')).show();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading feedback details');
        });
}

// Interactive Star Rating
document.addEventListener('DOMContentLoaded', function() {
    const ratingStars = document.querySelectorAll('.rating-stars input[type="radio"]');
    const starLabels = document.querySelectorAll('.rating-stars .star');
    
    // Initialize all stars as empty
    starLabels.forEach(label => {
        label.style.color = '#ddd';
        label.style.transform = 'scale(1)';
    });
    
    ratingStars.forEach((star, index) => {
        star.addEventListener('change', function() {
            // Reset all stars first
            starLabels.forEach(label => {
                label.style.color = '#ddd';
                label.style.transform = 'scale(1)';
            });
            
            // Fill stars up to the selected rating
            for (let i = 0; i <= index; i++) {
                starLabels[i].style.color = '#ffd700';
                starLabels[i].style.transform = 'scale(1.1)';
            }
        });
        
        // Hover effect for preview
        star.addEventListener('mouseenter', function() {
            if (!star.checked) {
                // Reset all stars
                starLabels.forEach(label => {
                    label.style.color = '#ddd';
                    label.style.transform = 'scale(1)';
                });
                
                // Preview hover effect
                for (let i = 0; i <= index; i++) {
                    starLabels[i].style.color = '#ffd700';
                    starLabels[i].style.transform = 'scale(1.05)';
                }
            }
        });
        
        star.addEventListener('mouseleave', function() {
            if (!star.checked) {
                // Reset to empty state
                starLabels.forEach(label => {
                    label.style.color = '#ddd';
                    label.style.transform = 'scale(1)';
                });
            }
        });
    });
});
</script>

<?php include 'components/footer.php'; ?>