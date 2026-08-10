<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication check
if (!isset($_SESSION['member_logged_in']) || !$_SESSION['member_logged_in']) {
    echo "<p class='text-danger'>Access denied.</p>";
    exit;
}

require_once '../includes/db.php';

$feedback_id = intval($_GET['id'] ?? 0);
$member_id = $_SESSION['member_id'] ?? 0;

if ($feedback_id > 0) {
    $sql = "SELECT * FROM feedback WHERE id = ? AND member_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $feedback_id, $member_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $feedback = $result->fetch_assoc();
        ?>
        <div class="row">
            <div class="col-md-6">
                <h6>Subject:</h6>
                <p><?php echo htmlspecialchars($feedback['subject']); ?></p>
                
                <h6>Status:</h6>
                <p>
                    <span class="badge bg-<?php 
                        echo $feedback['status'] === 'pending' ? 'warning' : 
                            ($feedback['status'] === 'in_progress' ? 'info' : 
                            ($feedback['status'] === 'resolved' ? 'success' : 'secondary')); 
                    ?>">
                        <?php echo ucfirst($feedback['status']); ?>
                    </span>
                </p>
                
                <h6>Date Submitted:</h6>
                <p><?php echo date('M j, Y g:i A', strtotime($feedback['created_at'])); ?></p>
            </div>
            <div class="col-md-6">
                <h6>Category:</h6>
                <p><?php echo ucfirst($feedback['category'] ?? 'general'); ?></p>
                
                <h6>Priority:</h6>
                <p>
                    <span class="badge bg-<?php 
                        echo $feedback['priority'] === 'urgent' ? 'danger' : 
                            ($feedback['priority'] === 'high' ? 'warning' : 
                            ($feedback['priority'] === 'medium' ? 'info' : 'secondary')); 
                    ?>">
                        <?php echo ucfirst($feedback['priority'] ?? 'medium'); ?>
                    </span>
                </p>
                
                <?php if ($feedback['rating'] > 0): ?>
                    <h6>Your Rating:</h6>
                    <p>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span class="text-warning"><?php echo $i <= $feedback['rating'] ? '★' : '☆'; ?></span>
                        <?php endfor; ?>
                        (<?php echo $feedback['rating']; ?>/5)
                    </p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-12">
                <h6>Your Message:</h6>
                <div class="border p-3 rounded">
                    <?php echo nl2br(htmlspecialchars($feedback['message'])); ?>
                </div>
            </div>
        </div>
        
        <?php if (!empty($feedback['admin_response'])): ?>
            <div class="row mt-3">
                <div class="col-12">
                    <h6>Admin Response:</h6>
                    <div class="border p-3 rounded bg-light">
                        <?php echo nl2br(htmlspecialchars($feedback['admin_response'])); ?>
                        <?php if ($feedback['admin_name']): ?>
                            <br><small class="text-muted">By: <?php echo htmlspecialchars($feedback['admin_name']); ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="row mt-3">
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        No admin response yet. We'll get back to you soon!
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <?php
    } else {
        echo "<p class='text-danger'>Feedback not found or you don't have permission to view it.</p>";
    }
} else {
    echo "<p class='text-danger'>Invalid feedback ID.</p>";
}
?>
