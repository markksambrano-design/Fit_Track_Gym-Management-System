<?php
// Set timezone to Philippines for correct time display
date_default_timezone_set('Asia/Manila');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title = "Announcements Management";
include 'components/header.php';
include '../includes/functions.php';
include '../includes/auth.php';

// Check if user is logged in as admin
if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Include announcement-specific CSS and JS
echo '<link rel="stylesheet" href="../assets/css/admin/announcement.css">';
echo '<script src="../assets/js/admin/announcement.js" defer></script>';

// Get announcements with basic data
$sql = "SELECT a.*, 
               COALESCE(adm.name, 'System') as created_by_name
        FROM announcements a
        LEFT JOIN admins adm ON a.created_by = adm.id
        ORDER BY a.is_pinned DESC, a.priority DESC, a.created_at DESC";
$result = $conn->query($sql);
$announcements = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $announcements[] = $row;
    }
}

// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total_announcements,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
    SUM(CASE WHEN is_pinned = 1 THEN 1 ELSE 0 END) as pinned_count,
    SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent_count
FROM announcements";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result ? $stats_result->fetch_assoc() : [];
?>


<!-- Statistics Overview -->
<div class="stats-overview">
    <div class="stats-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">
                <i class="fas fa-bullhorn"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo $stats['total_announcements'] ?? 0; ?></div>
                <div class="stat-label">Total Announcements</div>
            </div>
        </div>
        
        <div class="stat-card stat-success">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo $stats['active_count'] ?? 0; ?></div>
                <div class="stat-label">Active</div>
            </div>
        </div>

        <div class="stat-card stat-warning">
            <div class="stat-icon">
                <i class="fas fa-thumbtack"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo $stats['pinned_count'] ?? 0; ?></div>
                <div class="stat-label">Pinned</div>
            </div>
        </div>

        <div class="stat-card stat-danger">
            <div class="stat-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo $stats['urgent_count'] ?? 0; ?></div>
                <div class="stat-label">Urgent</div>
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
                <input type="text" class="search-input" id="searchFilter" placeholder="Search announcements...">
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters-container">
            <div class="d-flex align-items-center gap-2">
                <select name="status" class="filter-select" id="filterStatus">
                    <option value="all">All Status</option>
                    <option value="active" selected>Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="expired">Expired</option>
                </select>
                
                <select name="priority" class="filter-select" id="filterPriority">
                    <option value="all">All Priorities</option>
                    <option value="urgent">Urgent</option>
                    <option value="high">High</option>
                    <option value="medium">Medium</option>
                    <option value="low">Low</option>
                </select>
                
                <select name="audience" class="filter-select" id="filterAudience">
                    <option value="all">All Audiences</option>
                    <option value="all">Everyone</option>
                    <option value="members">Members Only</option>
                    <option value="staff">Staff Only</option>
                    <option value="walk_in">Walk-in Only</option>
                </select>
            </div>
        </div>
        
        <!-- View Controls -->
        <div class="view-controls-group">
            <div class="view-toggle">
                <span class="toggle-label">View:</span>
                <div class="toggle-buttons">
                    <button type="button" class="toggle-btn" id="viewModeGrid" title="Grid View">
                        <i class="fas fa-th-large"></i>
                    </button>
                    <button type="button" class="toggle-btn active" id="viewModeList" title="List View">
                        <i class="fas fa-list"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Create Button -->
        <div class="create-button-group">
            <button class="btn-create" data-bs-toggle="modal" data-bs-target="#addAnnouncementModal">
                <i class="fas fa-plus"></i>
                <span>Create Announcement</span>
            </button>
        </div>
    </div>
</div>

<!-- Announcements Container -->
<div class="announcements-container">
    <div id="announcementsList">
        <?php if (empty($announcements)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-bullhorn"></i>
                </div>
                <h3 class="empty-title">No announcements yet</h3>
                <p class="empty-description">Start building your communication hub by creating your first announcement</p>
                <button class="btn-create" data-bs-toggle="modal" data-bs-target="#addAnnouncementModal">
                    <i class="fas fa-plus"></i>
                    <span>Create First Announcement</span>
                </button>
            </div>
        <?php else: ?>
            <div class="announcements-grid" id="announcementsContainer">
                <?php foreach ($announcements as $announcement): ?>
                    <div class="announcement-card" data-id="<?php echo $announcement['id']; ?>" 
                         data-status="<?php echo $announcement['status']; ?>" 
                         data-priority="<?php echo $announcement['priority']; ?>" 
                         data-audience="<?php echo $announcement['target_audience']; ?>"
                         data-title="<?php echo htmlspecialchars(strtolower($announcement['title'])); ?>">
                        
                        <!-- Priority Badge -->
                        <div class="priority-badge priority-<?php echo $announcement['priority']; ?>">
                            <?php echo ucfirst($announcement['priority']); ?>
                        </div>
                        
                        <!-- Pin Badge -->
                        <?php if ($announcement['is_pinned']): ?>
                            <div class="pin-badge" title="Pinned Announcement">
                                <i class="fas fa-thumbtack"></i>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Card Header -->
                        <div class="card-header">
                            <div class="header-meta">
                                <div class="audience-badge audience-<?php echo $announcement['target_audience']; ?>">
                                    <?php echo ucfirst($announcement['target_audience']); ?>
                                </div>
                                <div class="status-badge status-<?php echo $announcement['status']; ?>">
                                    <span class="status-dot"></span>
                                    <?php echo ucfirst($announcement['status']); ?>
                                </div>
                            </div>
                            
                            <h3 class="announcement-title">
                                <?php echo htmlspecialchars($announcement['title']); ?>
                            </h3>
                        </div>
                        
                        <!-- Card Content -->
                        <div class="card-content">
                            <div class="announcement-message">
                                <?php echo nl2br(htmlspecialchars($announcement['message'])); ?>
                            </div>
                        </div>
                        
                        <!-- Card Footer -->
                        <div class="card-footer">
                            <div class="footer-info">
                                <div class="info-item">
                                    <i class="fas fa-user"></i>
                                    <span><?php echo htmlspecialchars($announcement['created_by_name'] ?: 'Admin'); ?></span>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-clock"></i>
                                    <span><?php echo date('M j, Y g:i A', strtotime($announcement['created_at'])); ?></span>
                                </div>
                                <?php if ($announcement['expires_at']): ?>
                                    <div class="info-item">
                                        <i class="fas fa-calendar-times"></i>
                                        <span>Expires: <?php echo date('M j, Y', strtotime($announcement['expires_at'])); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Action Menu -->
                            <div class="action-menu">
                                <div class="dropdown">
                                    <button class="action-btn" type="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-h"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item edit-announcement" href="#" data-id="<?php echo $announcement['id']; ?>">
                                                <i class="fas fa-edit"></i>Edit
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item toggle-pin" href="#" data-id="<?php echo $announcement['id']; ?>">
                                                <i class="fas fa-thumbtack"></i>
                                                <?php echo $announcement['is_pinned'] ? 'Unpin' : 'Pin'; ?>
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item toggle-status" href="#" data-id="<?php echo $announcement['id']; ?>">
                                                <i class="fas fa-toggle-on"></i>
                                                <?php echo $announcement['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                                            </a>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <a class="dropdown-item text-danger delete-announcement" href="#" data-id="<?php echo $announcement['id']; ?>">
                                                <i class="fas fa-trash"></i>Delete
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modern Add/Edit Announcement Modal -->
<div class="modal fade" id="addAnnouncementModal" tabindex="-1" aria-labelledby="announcementModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content modern-modal">
            <div class="modal-header modern-header">
                <div class="header-content">
                    <h5 class="modal-title" id="announcementModalLabel">
                        <i class="fas fa-plus me-2"></i>
                        New Announcement
                    </h5>
                    <p class="modal-subtitle">Share important information with your community</p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <form id="announcementForm" action="actions/announcement_actions.php" method="POST">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="id" id="editId">
                
                <div class="modal-body modern-body">
                    <div class="form-sections">
                        <!-- Title Section -->
                        <div class="form-section">
                            <div class="section-header">
                                <i class="fas fa-heading section-icon"></i>
                                <h6 class="section-title">Announcement Details</h6>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <div class="form-group-modern">
                                        <label for="announcementTitle" class="form-label-modern">Title *</label>
                                        <input type="text" class="form-control form-control-modern" id="announcementTitle" name="title" 
                                               placeholder="Enter a compelling title..." required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group-modern">
                                        <label for="announcementPriority" class="form-label-modern">Priority Level</label>
                                        <select class="form-select form-select-modern" id="announcementPriority" name="priority">
                                            <option value="low">Low Priority</option>
                                            <option value="medium" selected>Medium Priority</option>
                                            <option value="high">High Priority</option>
                                            <option value="urgent">Urgent</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Audience Section -->
                        <div class="form-section">
                            <div class="section-header">
                                <i class="fas fa-users section-icon"></i>
                                <h6 class="section-title">Target Audience & Settings</h6>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-group-modern">
                                        <label for="announcementAudience" class="form-label-modern">Target Audience</label>
                                        <select class="form-select form-select-modern" id="announcementAudience" name="target_audience">
                                            <option value="all" selected>Everyone</option>
                                            <option value="members">Members Only</option>
                                            <option value="staff">Staff Only</option>
                                            <option value="walk_in">Walk-in Only</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group-modern">
                                        <label for="announcementExpires" class="form-label-modern">Expiry Date (Optional)</label>
                                        <input type="datetime-local" class="form-control form-control-modern" id="announcementExpires" name="expires_at">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Message Section -->
                        <div class="form-section">
                            <div class="section-header">
                                <i class="fas fa-comment section-icon"></i>
                                <h6 class="section-title">Message Content</h6>
                            </div>
                            <div class="form-group-modern">
                                <label for="announcementMessage" class="form-label-modern">Message *</label>
                                <textarea class="form-control form-control-modern" id="announcementMessage" name="message" rows="8" 
                                          placeholder="Write your announcement message here..." required></textarea>
                                <div class="form-help">
                                    <i class="fas fa-info-circle me-1"></i>
                                    You can use basic HTML formatting. Keep your message clear and engaging.
                                </div>
                            </div>
                        </div>
                        
                        <!-- Options Section -->
                        <div class="form-section">
                            <div class="section-header">
                                <i class="fas fa-cog section-icon"></i>
                                <h6 class="section-title">Additional Options</h6>
                            </div>
                            <div class="form-check-modern">
                                <input class="form-check-input-modern" type="checkbox" id="announcementPinned" name="is_pinned">
                                <label class="form-check-label-modern" for="announcementPinned">
                                    <i class="fas fa-thumbtack me-2"></i>
                                    Pin this announcement (will appear at the top)
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer modern-footer">
                    <button type="button" class="btn btn-secondary btn-modern" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary btn-modern" id="submitBtn">
                        <i class="fas fa-paper-plane me-1"></i>
                        Post Announcement
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modern Confirmation Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modern-modal">
            <div class="modal-header modern-header">
                <h5 class="modal-title" id="confirmModalTitle">Confirm Action</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body modern-body" id="confirmModalBody">
                Are you sure you want to perform this action?
            </div>
            <div class="modal-footer modern-footer">
                <button type="button" class="btn btn-secondary btn-modern" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger btn-modern" id="confirmActionBtn">Confirm</button>
            </div>
        </div>
    </div>
</div>

<!-- Modern Toast Container -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1055;">
    <div id="announcementToast" class="toast modern-toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header modern-toast-header">
            <i class="fas fa-bullhorn me-2"></i>
            <strong class="me-auto" id="toastTitle">Announcement</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body modern-toast-body" id="toastBody">
            This is a toast message.
        </div>
    </div>
</div>

<?php include 'components/footer.php'; ?>
