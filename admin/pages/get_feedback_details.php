<?php
include '../includes/functions.php';

$feedback_id = intval($_GET['id'] ?? 0);

if ($feedback_id > 0) {
    $sql = "SELECT * FROM feedback WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $feedback_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $feedback = $result->fetch_assoc();
        ?>
        <div class="row">
            <div class="col-md-6">
                <h6>Subject:</h6>
                <p><?php echo htmlspecialchars($feedback['subject']); ?></p>
                
                <h6>Member:</h6>
                <p>
                    <?php if ($feedback['is_anonymous']): ?>
                        <span class="badge bg-secondary">Anonymous Member</span>
                    <?php else: ?>
                        <?php echo htmlspecialchars($feedback['member_name']); ?>
                    <?php endif; ?>
                </p>
                
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
                
                <h6>Date:</h6>
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
                    <h6>Rating:</h6>
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
                <h6>Message:</h6>
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
        <?php endif; ?>
        
        <div class="row mt-3">
            <div class="col-12">
                <button class="btn btn-primary" onclick="respondToFeedback(<?php echo $feedback['id']; ?>, '<?php echo $feedback['status']; ?>')">
                    <i class="fas fa-reply"></i> Respond
                </button>
            </div>
        </div>
        <?php
    } else {
        echo "<p class='text-danger'>Feedback not found.</p>";
    }
} else {
    echo "<p class='text-danger'>Invalid feedback ID.</p>";
}
?>
