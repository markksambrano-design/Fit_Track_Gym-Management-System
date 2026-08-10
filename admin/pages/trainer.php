<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$page_title = "Trainer Management";
include 'components/header.php';
include '../includes/db.php';

// Query: Available Staff (can be assigned as trainers)
$trainersResult = $conn->query("
    SELECT s.id, s.staff_id, s.first_name, s.last_name, s.photo, s.position,
           GROUP_CONCAT(DISTINCT sp.specialty SEPARATOR ', ') as specialties
    FROM staff s
    LEFT JOIN staff_specialties sp ON s.id = sp.staff_id
    GROUP BY s.id
    ORDER BY s.first_name
");
$trainers = $trainersResult ? $trainersResult->fetch_all(MYSQLI_ASSOC) : [];

$membersWithTrainingResult = $conn->query(" 
    SELECT m.id, m.member_id, m.first_name, m.last_name, m.email, m.phone, m.photo,
           (SELECT COUNT(*) FROM training_sessions ts2 WHERE ts2.member_id = m.id AND ts2.trainer_id IS NOT NULL AND ts2.trainer_id != 0) as total_sessions,
           (SELECT COUNT(*) FROM training_sessions ts3 WHERE ts3.member_id = m.id AND ts3.trainer_id IS NOT NULL AND ts3.trainer_id != 0 AND ts3.status IN ('booked', 'in_progress')) as active_sessions,
           (SELECT COUNT(*) FROM training_sessions ts4 WHERE ts4.member_id = m.id AND ts4.status = 'completed' AND ts4.trainer_id IS NOT NULL AND ts4.trainer_id != 0) as completed_sessions,
           (SELECT GROUP_CONCAT(DISTINCT CONCAT(s.first_name, ' ', s.last_name) SEPARATOR ', ')
            FROM training_sessions ts
            LEFT JOIN staff s ON ts.trainer_id = s.id
            WHERE ts.member_id = m.id AND ts.status IN ('booked', 'in_progress') AND ts.trainer_id IS NOT NULL AND ts.trainer_id != 0
           ) as assigned_trainers,
           (SELECT GROUP_CONCAT(DISTINCT DATE_FORMAT(ts.session_date, '%M %e, %Y') SEPARATOR '; ')
            FROM training_sessions ts
            WHERE ts.member_id = m.id AND ts.status = 'booked' AND ts.trainer_id IS NOT NULL AND ts.trainer_id != 0
            ORDER BY ts.session_date ASC
           ) as upcoming_sessions,
           (SELECT COUNT(*) FROM member_workout_sessions mws WHERE mws.member_id = m.id AND mws.status IN ('pending', 'in_progress')) as pending_workouts
    FROM members m
    WHERE (m.expired_date IS NULL OR m.expired_date >= CURDATE())
    AND (m.membership_type IS NOT NULL OR m.training_package > 0)
    AND EXISTS (SELECT 1 FROM training_sessions ts WHERE ts.member_id = m.id AND ts.trainer_id IS NOT NULL AND ts.trainer_id != 0)
    ORDER BY m.first_name
");
$membersWithTraining = $membersWithTrainingResult ? $membersWithTrainingResult->fetch_all(MYSQLI_ASSOC) : [];

// Compute total active sessions: count of training sessions with assigned trainers
$totalActiveQuery = "
SELECT COUNT(*) as active_sessions
FROM training_sessions ts
INNER JOIN members m ON ts.member_id = m.id
WHERE ts.trainer_id IS NOT NULL AND ts.trainer_id != 0
    AND (m.expired_date IS NULL OR m.expired_date >= CURDATE())
";
$totalActiveSessionsResult = $conn->query($totalActiveQuery);
$totalActiveSessions = $totalActiveSessionsResult ? $totalActiveSessionsResult->fetch_assoc()['active_sessions'] : 0;

// Query: Members without assigned trainers (using subqueries for accurate counting)
$membersNeedingTrainersResult = $conn->query(" 
    SELECT m.id, m.member_id, m.first_name, m.last_name, m.email, m.phone, m.photo,
           (SELECT COUNT(*) FROM training_sessions ts2 WHERE ts2.member_id = m.id AND (ts2.trainer_id IS NULL OR ts2.trainer_id = 0)) as total_sessions,
           (SELECT COUNT(*) FROM training_sessions ts3 WHERE ts3.member_id = m.id AND (ts3.trainer_id IS NULL OR ts3.trainer_id = 0) AND ts3.status IN ('booked', 'in_progress')) as active_sessions,
           (SELECT COUNT(*) FROM training_sessions ts4 WHERE ts4.member_id = m.id AND ts4.status = 'completed' AND (ts4.trainer_id IS NULL OR ts4.trainer_id = 0)) as completed_sessions,
           (SELECT GROUP_CONCAT(DISTINCT DATE_FORMAT(ts.session_date, '%M %e, %Y') SEPARATOR '; ')
            FROM training_sessions ts
            WHERE ts.member_id = m.id AND ts.status = 'booked' AND (ts.trainer_id IS NULL OR ts.trainer_id = 0)
            ORDER BY ts.session_date ASC
           ) as upcoming_sessions,
           (SELECT COUNT(*) FROM member_workout_sessions mws WHERE mws.member_id = m.id AND mws.status IN ('pending', 'in_progress')) as pending_workouts
    FROM members m
    WHERE (m.expired_date IS NULL OR m.expired_date >= CURDATE())
    AND m.membership_type IS NOT NULL
    AND EXISTS (SELECT 1 FROM training_sessions ts WHERE ts.member_id = m.id AND (ts.trainer_id IS NULL OR ts.trainer_id = 0) AND ts.status IN ('booked', 'in_progress'))
    ORDER BY m.first_name
");
$membersNeedingTrainers = $membersNeedingTrainersResult ? $membersNeedingTrainersResult->fetch_all(MYSQLI_ASSOC) : [];
?>

<?php
// Display success/error messages
if (isset($_SESSION['success_message'])) {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
            ' . htmlspecialchars($_SESSION['success_message']) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            ' . htmlspecialchars($_SESSION['error_message']) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
    unset($_SESSION['error_message']);
}
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
           <h1 class="h3 mb-0 text-white font-weight-bold">
                <i class="fas fa-dumbbell text-primary me-2"></i>Trainer Assignment
            </h1>
           <p class="text-white">Assign staff members as trainers to members</p>
        </div>
        <a href="index.php?page=staff" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Staff
        </a>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Available Staff
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= count($trainers) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dumbbell fa-2x text-gray-300"></i>
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
                                Members Needing Trainers
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= count($membersNeedingTrainers) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-plus fa-2x text-gray-300"></i>
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
                                Members with Trainers
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= count($membersWithTraining) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users-cog fa-2x text-gray-300"></i>
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
                                Active Training Sessions
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo $totalActiveSessions; ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar-check fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="row">
        <!-- Members Needing Trainers -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow trainer-assignment-card">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between trainer-assignment-header">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-user-plus me-2"></i>Members Needing Trainer Assignment
                    </h6>
                    <span class="badge badge-warning"><?= count($membersNeedingTrainers) ?></span>
                </div>
                <div class="card-body trainer-assignment-body">
                    <?php if (empty($membersNeedingTrainers)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle fa-3x mb-3" style="color: rgba(255, 255, 255, 0.5);"></i>
                            <h5 style="color: rgba(255, 255, 255, 0.8);">All members have assigned trainers!</h5>
                            <p style="color: rgba(255, 255, 255, 0.6);">No action needed at this time.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive" style="height: 400px; overflow-y: auto;">
                            <table class="table table-sm table-bordered trainer-assignment-table">
                                <thead>
                                    <tr>
                                        <th>Member</th>
                                        <th>Trainer</th>
                                        <th>Sessions</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($membersNeedingTrainers as $member): ?>
                                        <tr id="member-row-<?php echo $member['id']; ?>">
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?php echo !empty($member['photo']) && file_exists('../uploads/member_photos/' . $member['photo'])
                                                             ? '../uploads/member_photos/' . $member['photo']
                                                             : 'https://ui-avatars.com/api/?name=' . urlencode($member['first_name'] . ' ' . $member['last_name']) . '&background=6C5CE7&color=fff&size=32'; ?>"
                                                         alt="<?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>"
                                                         class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;">
                                                    <div>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></div>
                                                        <small class="text-muted"><?php echo htmlspecialchars($member['member_id']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="text-muted">Not Assigned</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo $member['active_sessions']; ?> active</span>
                                                <span class="badge bg-success"><?php echo $member['completed_sessions']; ?> completed</span>
                                            </td>
                                            <td>
                                                <form method="POST" action="actions/staff_actions.php" style="display: inline;" id="assign-form-<?php echo $member['id']; ?>">
                                                    <input type="hidden" name="action" value="assign_trainer">
                                                    <input type="hidden" name="member_id" value="<?php echo $member['id']; ?>">
                                                    <input type="hidden" name="trainer_id" id="selected-trainer-<?php echo $member['id']; ?>">
                                                    <select class="form-select form-select-sm assign-trainer-select" onchange="showTrainerConfirm(<?php echo $member['id']; ?>, this.value, '<?php echo htmlspecialchars(addslashes($member['first_name'] . ' ' . $member['last_name'])); ?>')">
                                                        <option value="">Select Trainer</option>
                                                        <?php foreach ($trainers as $trainer): ?>
                                                            <option value="<?php echo $trainer['id']; ?>" data-trainer-name="<?php echo htmlspecialchars(addslashes($trainer['first_name'] . ' ' . $trainer['last_name'])); ?>">
                                                                <?php echo htmlspecialchars($trainer['first_name'] . ' ' . $trainer['last_name']); ?>
                                                                <?php if (!empty($trainer['specialties'])): ?>
                                                                    (<?php echo htmlspecialchars($trainer['specialties']); ?>)
                                                                <?php endif; ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Members with Assigned Trainers -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow trainer-assignment-card">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between trainer-assignment-header">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-users-cog me-2"></i>Members with Assigned Trainers
                    </h6>
                    <span class="badge badge-success"><?= count($membersWithTraining) ?></span>
                </div>
                <div class="card-body trainer-assignment-body">
                    <?php if (empty($membersWithTraining)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-users fa-3x mb-3" style="color: rgba(255, 255, 255, 0.5);"></i>
                            <h5 style="color: rgba(255, 255, 255, 0.8);">No members with training sessions yet</h5>
                            <p style="color: rgba(255, 255, 255, 0.6);">Members will appear here once they book training sessions.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive" style="height: 400px; overflow-y: auto;">
                            <table class="table table-sm table-bordered trainer-assignment-table" id="assigned-trainers-table">
                                <thead>
                                    <tr>
                                        <th>Member</th>
                                        <th>Trainer</th>
                                        <th>Sessions</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($membersWithTraining as $member): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?php echo !empty($member['photo']) && file_exists('../uploads/member_photos/' . $member['photo'])
                                                             ? '../uploads/member_photos/' . $member['photo']
                                                             : 'https://ui-avatars.com/api/?name=' . urlencode($member['first_name'] . ' ' . $member['last_name']) . '&background=6C5CE7&color=fff&size=32'; ?>"
                                                         alt="<?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>"
                                                         class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;">
                                                    <div>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></div>
                                                        <small class="text-muted"><?php echo htmlspecialchars($member['member_id']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($member['assigned_trainers'] ?: 'Multiple'); ?></td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo $member['active_sessions']; ?> active</span>
                                                <span class="badge bg-success"><?php echo $member['completed_sessions']; ?> completed</span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary manage-trainer-btn"
                                                        data-member-id="<?php echo $member['id']; ?>"
                                                        data-member-name="<?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>">
                                                    <i class="fas fa-cog"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Available Trainers -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-dumbbell me-2"></i>Available Trainers
                    </h6>
                    <span class="badge badge-primary"><?= count($trainers) ?></span>
                </div>
                <div class="card-body">
                    <?php if (empty($trainers)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No trainers available</h5>
                            <p class="text-muted">Add staff members with trainer positions to see them here.</p>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($trainers as $trainer): ?>
                                <div class="col-md-4 col-lg-3 mb-4">
                                    <div class="card h-100 border-0 shadow-sm">
                                        <div class="card-body text-center p-3">
                                            <img src="<?php echo !empty($trainer['photo']) && file_exists('../uploads/staff_photos/' . $trainer['photo'])
                                                     ? '../uploads/staff_photos/' . $trainer['photo']
                                                     : 'https://ui-avatars.com/api/?name=' . urlencode($trainer['first_name'] . ' ' . $trainer['last_name']) . '&background=28a745&color=fff&size=80'; ?>"
                                                 alt="<?php echo htmlspecialchars($trainer['first_name'] . ' ' . $trainer['last_name']); ?>"
                                                 class="rounded-circle mb-3" style="width: 80px; height: 80px; object-fit: cover;">
                                            <h6 class="card-title mb-1"><?php echo htmlspecialchars($trainer['first_name'] . ' ' . $trainer['last_name']); ?></h6>
                                            <p class="card-text text-muted small mb-2"><?php echo htmlspecialchars($trainer['position']); ?></p>
                                            <?php if (!empty($trainer['specialties'])): ?>
                                                <p class="card-text small text-primary mb-3"><?php echo htmlspecialchars($trainer['specialties']); ?></p>
                                            <?php endif; ?>
                                            <div class="trainer-stats small">
                                                <?php
                                                // Get trainer's session count
                                                $trainer_sessions = $conn->query("SELECT COUNT(*) as count FROM training_sessions WHERE trainer_id = {$trainer['id']} AND status = 'booked'");
                                                $session_count = $trainer_sessions ? $trainer_sessions->fetch_assoc()['count'] : 0;
                                                ?>
                                                <span class="badge bg-primary"><?php echo $session_count; ?> active sessions</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.trainer-assignment-card {
    background: rgba(30, 41, 59, 0.7) !important;
    -webkit-backdrop-filter: blur(15px);
    backdrop-filter: blur(15px);
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    border-radius: 16px;
}

.trainer-assignment-header {
    background: rgba(15, 23, 42, 0.8) !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
}

.trainer-assignment-header h6 {
    color: white !important;
}

.trainer-assignment-header i {
    color: #3b82f6;
}

.trainer-assignment-body {
    background: transparent !important;
    padding: 1.5rem;
}

.trainer-assignment-table {
    background: transparent !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
    color: white;
}

.trainer-assignment-table thead {
    background: #2f5496 !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.2) !important;
    position: sticky;
    top: 0;
    z-index: 10;
}

.trainer-assignment-table thead th {
    background: #2f5496 !important;
    color: #ffffff !important;
    font-weight: 600;
    font-size: 1.05rem;
    border-color: rgba(255, 255, 255, 0.2) !important;
    padding: 0.8rem 2rem;
}

/* Override Bootstrap table-light class */
.trainer-assignment-table thead.table-light {
    background: #2f5496 !important;
}

.trainer-assignment-table thead.table-light th {
    background: #2f5496 !important;
    color: #ffffff !important;
}

.trainer-assignment-table tbody {
    background: transparent !important;
}

.trainer-assignment-table tbody tr {
    background: transparent !important;
}

.trainer-assignment-table tbody td {
    background: transparent !important;
    color: rgba(255, 255, 255, 0.8) !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
    padding: 1rem;
    vertical-align: middle;
}

.trainer-assignment-table tbody tr:hover {
    background: rgba(59, 130, 246, 0.05) !important;
}

.trainer-assignment-table tbody tr:hover td {
    background: rgba(59, 130, 246, 0.05) !important;
}

.trainer-assignment-table .fw-bold {
    color: rgba(255, 255, 255, 0.9) !important;
}

.trainer-assignment-table .text-muted {
    color: rgba(255, 255, 255, 0.6) !important;
}

/* Custom scrollbar styling for better visibility */
.trainer-assignment-body .table-responsive::-webkit-scrollbar {
    width: 8px;
}

.trainer-assignment-body .table-responsive::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 10px;
}

.trainer-assignment-body .table-responsive::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 10px;
}

.trainer-assignment-body .table-responsive::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.3);
}

.workout-info {
    max-width: 200px;
}

.workout-info .badge {
    font-size: 0.7rem;
    margin-bottom: 0.25rem;
}

.workout-info strong {
    font-size: 0.8rem;
}

.workout-info .small {
    font-size: 0.75rem;
    line-height: 1.2;
}
</style>

<!-- Trainer Assignment Confirmation Modal -->
<div class="modal fade" id="trainerConfirmModal" tabindex="-1" aria-labelledby="trainerConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: rgba(30, 41, 59, 0.95); border: 1px solid rgba(255, 255, 255, 0.1);">
            <div class="modal-header" style="border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                <h5 class="modal-title text-white" id="trainerConfirmModalLabel">
                    <i class="fas fa-user-plus me-2 text-primary"></i>Confirm Trainer Assignment
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-white">
                <div class="text-center mb-3">
                    <i class="fas fa-dumbbell fa-3x text-primary mb-3"></i>
                </div>
                <p class="mb-2">Are you sure you want to assign:</p>
                <div class="alert alert-info" style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3);">
                    <strong id="confirm-trainer-name"></strong>
                </div>
                <p class="mb-0">to member:</p>
                <div class="alert alert-success" style="background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.3);">
                    <strong id="confirm-member-name"></strong>
                </div>
                <p class="text-muted small mt-3">
                    <i class="fas fa-info-circle me-1"></i>
                    This will create a training session for the member and move them to the "Assigned Trainers" section.
                </p>
            </div>
            <div class="modal-footer" style="border-top: 1px solid rgba(255, 255, 255, 0.1);">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>No, Cancel
                </button>
                <button type="button" class="btn btn-primary" id="confirm-assign-btn">
                    <i class="fas fa-check me-1"></i>Yes, Assign Trainer
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Global variables for modal
let currentMemberId = null;
let currentTrainerId = null;

function showTrainerConfirm(memberId, trainerId, memberName) {
    if (!trainerId) return;

    // Find trainer name
    const select = document.querySelector(`#assign-form-${memberId} select`);
    const option = select.querySelector(`option[value="${trainerId}"]`);
    const trainerName = option ? option.getAttribute('data-trainer-name') : 'Unknown Trainer';

    // Set modal content
    document.getElementById('confirm-member-name').textContent = memberName;
    document.getElementById('confirm-trainer-name').textContent = trainerName;

    // Store values
    currentMemberId = memberId;
    currentTrainerId = trainerId;

    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('trainerConfirmModal'));
    modal.show();
}

// Handle confirm button click
document.getElementById('confirm-assign-btn').addEventListener('click', function() {
    if (currentMemberId && currentTrainerId) {
        // Set the hidden input value
        document.getElementById(`selected-trainer-${currentMemberId}`).value = currentTrainerId;

        // Get form data
        const form = document.getElementById(`assign-form-${currentMemberId}`);
        const formData = new FormData(form);

        // Get trainer name for display
        const trainerName = document.getElementById('confirm-trainer-name').textContent;
        const memberName = document.getElementById('confirm-member-name').textContent;

        // Disable button and show loading
        const confirmBtn = document.getElementById('confirm-assign-btn');
        const originalText = confirmBtn.innerHTML;
        confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Assigning...';
        confirmBtn.disabled = true;

        // Send AJAX request
        fetch('actions/staff_actions.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            // Hide modal first
            const modal = bootstrap.Modal.getInstance(document.getElementById('trainerConfirmModal'));
            modal.hide();
            
            // Reset button
            confirmBtn.innerHTML = originalText;
            confirmBtn.disabled = false;
            
            // Reset dropdown
            const select = document.querySelector(`#assign-form-${currentMemberId} select`);
            if (select) {
                select.value = '';
            }
            
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text();
        })
        .then(text => {
            try {
                // Try to parse as JSON
                const data = JSON.parse(text);
                
                if (data.success) {
                    // Show success message
                    showAlert('Trainer assigned successfully! Page will refresh...', 'success');
                    
                    // Reload the page after a short delay to show updated data
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    // Show error message
                    showAlert(data.message || 'An error occurred while assigning the trainer.', 'error');
                }
            } catch (e) {
                // If not JSON, check for success text
                if (text.includes('success') || text.includes('Success')) {
                    showAlert('Trainer assigned successfully! Page will refresh...', 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showAlert('An error occurred while assigning the trainer.', 'error');
                    console.error('Response:', text);
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('An error occurred while assigning the trainer.', 'error');
        });
    }
});

// Helper function to show alerts
function showAlert(message, type = 'info') {
    // Remove any existing custom alerts
    const existingAlerts = document.querySelectorAll('.custom-alert');
    existingAlerts.forEach(alert => alert.remove());
    
    // Create alert element
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type === 'error' ? 'danger' : (type === 'success' ? 'success' : 'info')} alert-dismissible fade show custom-alert`;
    alertDiv.style.position = 'fixed';
    alertDiv.style.top = '20px';
    alertDiv.style.right = '20px';
    alertDiv.style.zIndex = '9999';
    alertDiv.style.minWidth = '300px';
    
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alertDiv);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

// Also add event listener to reset dropdown when modal is closed without confirming
document.getElementById('trainerConfirmModal').addEventListener('hidden.bs.modal', function () {
    if (currentMemberId) {
        const select = document.querySelector(`#assign-form-${currentMemberId} select`);
        if (select) {
            select.value = '';
        }
    }
    // Reset global variables
    currentMemberId = null;
    currentTrainerId = null;
});
</script>

<?php include 'components/footer.php'; ?>