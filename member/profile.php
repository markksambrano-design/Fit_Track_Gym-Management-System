<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$page_title = "My Profile";

// Check if member is logged in
if (!isset($_SESSION['member_logged_in'])) {
    header('Location: login.php');
    exit;
}

include '../includes/db.php';
include 'components/header.php';

// Get member data from database
$member_id = $_SESSION['member_id'];

try {
    $stmt = $conn->prepare("SELECT * FROM members WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Database prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $member_id);
    if (!$stmt->execute()) {
        throw new Exception("Database execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $member = $result->fetch_assoc();

    if (!$member) {
        header('Location: logout.php');
        exit;
    }
} catch (Exception $e) {
    error_log("Profile database error: " . $e->getMessage());
    $member = null;
    $update_error = "Unable to load profile data. Please try again later.";
}

// Handle profile updates
$update_success = '';
$update_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName = trim($_POST['lastName'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $gender = !empty($_POST['gender']) ? $_POST['gender'] : null;
    $age = intval($_POST['age'] ?? 0);
    
    if (empty($firstName) || empty($lastName) || empty($email)) {
        $update_error = 'First name, last name, and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $update_error = 'Please enter a valid email address.';
    } elseif ($age < 0 || $age > 150) {
        $update_error = 'Please enter a valid age (0-150).';
    } else {
        try {
            // Check if email already exists for another member
            $check_stmt = $conn->prepare("SELECT id FROM members WHERE email = ? AND id != ?");
            $check_stmt->bind_param("si", $email, $member_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $update_error = 'This email address is already in use by another member.';
            } else {
                $stmt = $conn->prepare("UPDATE members SET 
                    first_name = ?, last_name = ?, email = ?, phone = ?, address = ?, gender = ?, age = ? WHERE id = ?");
                
                $stmt->bind_param("ssssssii", $firstName, $lastName, $email, $phone, $address, $gender, $age, $member_id);
                
                if ($stmt->execute()) {
                    $update_success = 'Profile updated successfully!';
                    // Refresh member data
                    $stmt = $conn->prepare("SELECT * FROM members WHERE id = ?");
                    $stmt->bind_param("i", $member_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $member = $result->fetch_assoc();
                } else {
                    $update_error = 'Failed to update profile.';
                }
            }
        } catch (Exception $e) {
            $update_error = 'Database error occurred.';
        }
    }
}

// Handle password change
$password_success = '';
$password_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $password_error = 'All password fields are required.';
    } elseif (strlen($new_password) < 6) {
        $password_error = 'New password must be at least 6 characters long.';
    } elseif ($new_password !== $confirm_password) {
        $password_error = 'New password and confirm password do not match.';
    } else {
        try {
            // Verify current password
            $stmt = $conn->prepare("SELECT password FROM members WHERE id = ?");
            $stmt->bind_param("i", $member_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $member_data = $result->fetch_assoc();
            
            if (!$member_data || !password_verify($current_password, $member_data['password'])) {
                $password_error = 'Current password is incorrect.';
            } else {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE members SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hashed_password, $member_id);
                
                if ($stmt->execute()) {
                    $password_success = 'Password changed successfully!';
                } else {
                    $password_error = 'Failed to update password.';
                }
            }
        } catch (Exception $e) {
            $password_error = 'Database error occurred.';
        }
    }
}

// Handle avatar upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_avatar'])) {
    $avatar_upload_success = '';
    $avatar_upload_error = '';
    
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if ($_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            if (in_array($_FILES['avatar']['type'], $allowedTypes)) {
                $uploadDir = '../uploads/member_photos/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $fileName = uniqid() . '_' . basename($_FILES['avatar']['name']);
                $targetFile = $uploadDir . $fileName;

                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $targetFile)) {
                    $stmt = $conn->prepare("UPDATE members SET photo = ? WHERE id = ?");
                    $stmt->bind_param("si", $fileName, $member_id);
                    
                    if ($stmt->execute()) {
                        $avatar_upload_success = 'Profile picture updated successfully!';
                        // Refresh member data
                        $stmt = $conn->prepare("SELECT * FROM members WHERE id = ?");
                        $stmt->bind_param("i", $member_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $member = $result->fetch_assoc();
                    } else {
                        $avatar_upload_error = 'Failed to update profile picture in database.';
                    }
                } else {
                    $avatar_upload_error = 'Failed to upload photo.';
                }
            } else {
                $avatar_upload_error = 'Invalid photo file type. Only JPG, PNG, GIF allowed.';
            }
        } else {
            $avatar_upload_error = 'Error uploading photo.';
        }
    } else {
        $avatar_upload_error = 'Please select a photo to upload.';
    }
}

// Get membership info
$membership_status = ucfirst($member['membership_type'] ?? 'Regular');
$join_date = $member['join_date'] ? date('M d, Y', strtotime($member['join_date'])) : 'N/A';

// Get attendance stats
$total_workouts = 0;
$attendance_percentage = 0;

$table_check = $conn->query("SHOW TABLES LIKE 'attendance'");
if ($table_check && $table_check->num_rows > 0) {
    $attendance_query = "SELECT COUNT(*) as total_workouts FROM attendance WHERE member_id = ?";
    $stmt = $conn->prepare($attendance_query);
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $attendance_result = $stmt->get_result();
    $attendance_data = $attendance_result->fetch_assoc();
    $total_workouts = $attendance_data['total_workouts'] ?? 0;
    $attendance_percentage = $total_workouts > 0 ? round(($total_workouts / 30) * 100) : 0;
}

// Get weekly activity data for chart
$weekly_activity = [];
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
foreach ($days as $day) {
    $weekly_activity[$day] = rand(0, 3); // Random data for demo, replace with actual data
}

// Get member's BMI data
$bmi_data = null;
if (isset($member['height']) && isset($member['weight']) && $member['height'] > 0 && $member['weight'] > 0) {
    $height_meters = $member['height'] / 100;
    $bmi_data = $member['weight'] / ($height_meters * $height_meters);
}

// Get achievement badges based on activity
$achievements = [];
if ($total_workouts >= 10) $achievements[] = ['name' => 'First Steps', 'icon' => 'fas fa-shoe-prints', 'color' => '#10b981'];
if ($total_workouts >= 25) $achievements[] = ['name' => 'Regular', 'icon' => 'fas fa-fire', 'color' => '#f59e0b'];
if ($total_workouts >= 50) $achievements[] = ['name' => 'Dedicated', 'icon' => 'fas fa-trophy', 'color' => '#fbbf24'];
if ($total_workouts >= 100) $achievements[] = ['name' => 'Elite', 'icon' => 'fas fa-crown', 'color' => '#8b5cf6'];

// Get membership history
$membership_history = [];
try {
    $history_query = "SELECT * FROM membership_history WHERE member_id = ? ORDER BY date_changed DESC LIMIT 5";
    $stmt = $conn->prepare($history_query);
    if ($stmt) {
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $membership_history[] = $row;
        }
    }
} catch (Exception $e) {
    // Table might not exist yet
}
?>

<?php if ($member): ?>
<!-- Main Content -->
<div class="profile-container">
    <!-- Success/Error Messages -->
    <?php if ($update_success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($update_success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
    <?php endif; ?>
    
    <?php if ($update_error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($update_error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($avatar_upload_success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($avatar_upload_success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($avatar_upload_error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($avatar_upload_error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($password_success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($password_success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($password_error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($password_error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Profile Header -->
                    <div class="profile-header">
        <div class="profile-avatar-section">
                            <?php 
                            $profileImage = '';
                            if (!empty($member['photo'])) {
                                $photoPath = '../uploads/member_photos/' . $member['photo'];
                                if (file_exists($photoPath)) {
                                    $profileImage = $photoPath;
                                }
            }
            
            if (empty($profileImage)) {
                                $firstName = $member['first_name'] ?? '';
                                $lastName = $member['last_name'] ?? '';
                $profileImage = 'https://ui-avatars.com/api/?name=' . urlencode($firstName . '+' . $lastName) . '&background=667eea&color=fff&size=200&font-size=0.4';
                            }
                            ?>
            
            <div class="avatar-container">
                            <img src="<?= htmlspecialchars($profileImage) ?>" 
                                 alt="Profile" 
                                 class="profile-avatar" 
                                 id="profileAvatar"
                     onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($member['first_name'] . '+' . $member['last_name']) ?>&background=667eea&color=fff&size=200&font-size=0.4';">
                
                <div class="avatar-overlay">
                    <button class="avatar-edit-btn" id="editAvatarBtn" title="Change Profile Picture">
                                    <i class="fas fa-camera"></i>
                                </button>
                            </div>
                        </div>
                        
            <!-- Hidden form for avatar upload -->
                        <form id="avatarUploadForm" method="POST" enctype="multipart/form-data" style="display: none;">
                            <input type="hidden" name="upload_avatar" value="1">
                            <input type="file" name="avatar" id="avatarFileInput" accept="image/*">
                        </form>
                        </div>
                        
        <div class="profile-info-section">
            <h1 class="profile-name"><?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?></h1>
            <p class="profile-email"><?= htmlspecialchars($member['email']) ?></p>
            <div class="profile-badges">
                <span class="badge membership-badge"><?= $membership_status ?> Member</span>
                <span class="badge status-badge active">Active</span>
                        </div>
            <div class="profile-stats">
                <div class="stat-item">
                    <span class="stat-value"><?= $total_workouts ?></span>
                    <span class="stat-label">Workouts</span>
                    </div>
                <div class="stat-item">
                    <span class="stat-value"><?= $attendance_percentage ?>%</span>
                    <span class="stat-label">Attendance</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value"><?= $join_date ?></span>
                    <span class="stat-label">Joined</span>
            </div>
        </div>
                </div>
            </div>

    <!-- Profile Navigation Tabs -->
    <div class="profile-tabs">
        <button class="tab-btn active" data-tab="personal" title="Personal Info">
            <i class="fas fa-user"></i>
        </button>
        <button class="tab-btn" data-tab="edit" title="Edit Profile">
            <i class="fas fa-edit"></i>
        </button>
        <button class="tab-btn" data-tab="qr" title="QR Code">
            <i class="fas fa-qrcode"></i>
        </button>
    </div>

    <!-- Tab Content -->
    <div class="tab-content">
        <!-- Personal Info Tab -->
        <div id="personal" class="tab-pane active">
            <div class="info-grid">
                <div class="info-card">
                                <div class="info-icon">
                        <i class="fas fa-id-card"></i>
                                </div>
                                <div class="info-content">
                        <h4>Member ID</h4>
                        <p><?= htmlspecialchars($member['member_id']) ?></p>
                                </div>
                            </div>
                            
                <div class="info-card">
                                <div class="info-icon">
                                    <i class="fas fa-phone"></i>
                                </div>
                                <div class="info-content">
                                    <h4>Phone</h4>
                                    <p><?= htmlspecialchars($member['phone'] ?: 'Not provided') ?></p>
                                </div>
                            </div>
                            
                <div class="info-card">
                                <div class="info-icon">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <div class="info-content">
                                    <h4>Address</h4>
                                    <p><?= htmlspecialchars($member['address'] ?: 'Not provided') ?></p>
                                </div>
                            </div>
                            
                <div class="info-card">
                                <div class="info-icon">
                                    <i class="fas fa-venus-mars"></i>
                                </div>
                                <div class="info-content">
                                    <h4>Gender</h4>
                                    <p><?= htmlspecialchars(ucfirst($member['gender'] ?: 'Not specified')) ?></p>
                        </div>
                    </div>
                    
                <div class="info-card">
                                <div class="info-icon">
                                    <i class="fas fa-birthday-cake"></i>
                                </div>
                                <div class="info-content">
                                    <h4>Age</h4>
                                    <p><?= htmlspecialchars($member['age'] ?: 'Not specified') ?></p>
                        </div>
                    </div>
                </div>
                
            </div>
            
        <!-- Edit Profile Tab -->
        <div id="edit" class="tab-pane">
            <div class="edit-form-container">
                <form method="POST" class="edit-form">
                            <input type="hidden" name="update_profile" value="1">
                            
                    <div class="form-header">
                        <h3>Edit Profile</h3>
                        <p>Update your personal information and preferences.</p>
                    </div>

                    <div class="form-section">
                        <h4>Basic Information</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="firstName">First Name *</label>
                                <input type="text" id="firstName" name="firstName" 
                                           value="<?= htmlspecialchars($member['first_name']) ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="lastName">Last Name *</label>
                                <input type="text" id="lastName" name="lastName" 
                                           value="<?= htmlspecialchars($member['last_name']) ?>" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="email">Email Address *</label>
                                <input type="email" id="email" name="email" 
                                           value="<?= htmlspecialchars($member['email']) ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="gender">Gender</label>
                                <select id="gender" name="gender">
                                        <option value="">Select Gender</option>
                                        <option value="male" <?= ($member['gender'] === 'male') ? 'selected' : '' ?>>Male</option>
                                        <option value="female" <?= ($member['gender'] === 'female') ? 'selected' : '' ?>>Female</option>
                                        <option value="other" <?= ($member['gender'] === 'other') ? 'selected' : '' ?>>Other</option>
                                    </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone">Phone</label>
                                <input type="tel" id="phone" name="phone" 
                                           value="<?= htmlspecialchars($member['phone'] ?? '') ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="age">Age</label>
                                <input type="number" id="age" name="age" min="0" max="150"
                                           value="<?= htmlspecialchars($member['age'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h4>Contact Details</h4>
                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" rows="3"><?= htmlspecialchars($member['address'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Changes
                        </button>
                    </div>
                </form>
                
                <!-- Password Change Section -->
                <div class="password-change-section">
                    <div class="form-header">
                        <h3>Change Password</h3>
                        <p>Update your account password for security.</p>
                    </div>
                    
                    <form method="POST" class="password-form">
                        <input type="hidden" name="change_password" value="1">
                        
                        <div class="form-section">
                            <div class="form-group">
                                <label for="current_password">Current Password *</label>
                                <input type="password" id="current_password" name="current_password" required>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="new_password">New Password *</label>
                                    <input type="password" id="new_password" name="new_password" minlength="6" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="confirm_password">Confirm New Password *</label>
                                    <input type="password" id="confirm_password" name="confirm_password" minlength="6" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-key me-2"></i>Change Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
            
        <!-- QR Code Tab -->
        <div id="qr" class="tab-pane">
            <div class="qr-container">
                            <div class="qr-code" id="qrCode"></div>
                <p class="qr-description">Scan this QR code at the gym for quick check-in</p>
                            <div class="qr-actions">
                    <button class="btn btn-outline-primary" id="downloadQrBtn">
                        <i class="fas fa-download me-2"></i>Download
                                </button>
                    <button class="btn btn-outline-secondary" id="printQrBtn">
                        <i class="fas fa-print me-2"></i>Print
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

<!-- Enhanced Styles -->
<style>
/* Profile-specific CSS variables - scoped to avoid conflicts */
.profile-container {
    --profile-primary: #667eea;
    --profile-primary-dark: #5a6fd8;
    --profile-secondary: #764ba2;
    --profile-accent: #f093fb;
    --profile-success: #10b981;
    --profile-warning: #f59e0b;
    --profile-danger: #ef4444;
    --profile-dark: #1e293b;
    --profile-light: #f8fafc;
    --profile-gray: #64748b;
    --profile-border: rgba(255, 255, 255, 0.1);
    --profile-glass: rgba(255, 255, 255, 0.05);
    --profile-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    --profile-shadow-lg: 0 20px 40px rgba(0, 0, 0, 0.15);
}

.profile-container {
    width: 100%;
    padding: 2rem;
    transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1.2);
}

/* Adjust container when sidebar is hidden */
body.sidebar-hidden .profile-container {
    width: 100%;
    padding: 2rem 3rem;
}

/* Profile Header */
.profile-header {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 2rem;
    padding: 3rem;
    margin-bottom: 2rem;
}

.profile-avatar-section {
        position: relative;
}

.avatar-container {
    position: relative;
        border-radius: 50%;
    overflow: hidden;
    box-shadow: var(--profile-shadow-lg);
}

.profile-avatar {
    width: 200px;
    height: 200px;
    object-fit: cover;
    border: 6px solid rgba(255, 255, 255, 0.9);
    transition: all 0.3s ease;
}

.avatar-overlay {
        position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
        display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: all 0.3s ease;
}

.avatar-container:hover .avatar-overlay {
    opacity: 1;
}

.avatar-edit-btn {
    background: var(--profile-primary);
        color: white;
    border: none;
    width: 50px;
    height: 50px;
    border-radius: 50%;
        cursor: pointer;
    transition: all 0.3s ease;
    font-size: 1.2rem;
}

.avatar-edit-btn:hover {
    background: var(--profile-primary-dark);
    transform: scale(1.1);
}

.profile-info-section {
    flex: 1;
    }

    .profile-name {
    font-size: 3rem;
        font-weight: 800;
    margin-bottom: 0.5rem;
    background: linear-gradient(135deg, var(--profile-primary), var(--profile-secondary));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

.profile-email {
        font-size: 1.1rem;
    color: var(--profile-gray);
    margin-bottom: 1.5rem;
}

.profile-badges {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
}

.badge {
    padding: 0.5rem 1rem;
    border-radius: 20px;
        font-size: 0.9rem;
        font-weight: 600;
}

.membership-badge {
    background: linear-gradient(135deg, var(--profile-primary), var(--profile-secondary));
    color: white;
}

.status-badge.active {
    background: var(--profile-success);
    color: white;
}

.profile-stats {
        display: flex;
    gap: 2rem;
}

.stat-item {
        text-align: center;
}

.stat-value {
    display: block;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--profile-primary);
}

.stat-label {
    font-size: 0.9rem;
    color: var(--profile-gray);
    text-transform: uppercase;
    letter-spacing: 1px;
}

/* Profile Tabs */
.profile-tabs {
        display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
    background: var(--profile-glass);
    backdrop-filter: blur(20px);
        border-radius: 16px;
    padding: 0.5rem;
    border: 1px solid var(--profile-border);
}

.tab-btn {
    flex: 1;
    padding: 1rem 1.5rem;
    background: transparent;
    border: none;
    border-radius: 12px;
    color: var(--profile-gray);
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 1.8rem;
}

.tab-btn:hover {
        color: white;
    background: rgba(255, 255, 255, 0.1);
}

.tab-btn.active {
    background: var(--profile-primary);
        color: white;
    box-shadow: var(--profile-shadow);
}

/* Tab Content */
.tab-content {
    padding: 2rem;
}

.tab-pane {
        display: none;
    }

.tab-pane.active {
        display: block;
    animation: fadeIn 0.3s ease;
}

/* Info Grid */
.info-grid {
        display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1.5rem;
    margin-bottom: 2rem;
}

.info-card {
    display: flex;
    align-items: center;
    gap: 1rem;
        padding: 1.5rem;
    background: rgba(255, 255, 255, 0.05);
        border-radius: 16px;
    border: 1px solid var(--profile-border);
    transition: all 0.3s ease;
}

.info-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--profile-shadow-lg);
}

.info-icon {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, var(--profile-primary), var(--profile-secondary));
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
}

.info-content h4 {
    font-size: 0.9rem;
    color: var(--profile-gray);
        margin-bottom: 0.5rem;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.info-content p {
    font-size: 1.1rem;
        font-weight: 600;
    color: white;
    margin: 0;
    }

/* Progress Section */
.progress-section h3 {
        color: white;
    margin-bottom: 1rem;
    }

    .progress-container {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 12px;
        padding: 1.5rem;
    }

.progress-info {
        display: flex;
        justify-content: space-between;
        margin-bottom: 1rem;
        color: white;
    font-weight: 600;
    }

    .progress-bar {
        height: 12px;
        background: rgba(255, 255, 255, 0.1);
    border-radius: 6px;
        overflow: hidden;
    }

    .progress-fill {
        height: 100%;
    background: linear-gradient(90deg, var(--profile-success), var(--profile-primary));
    border-radius: 6px;
    transition: width 1s ease;
}

/* Profile Info */
.profile-info-container {
    padding: 2rem 0;
}

/* Edit Form */
.edit-form-container {
    max-width: 1500px;
    margin: 0 auto;
    padding: 2rem;
    background: rgba(255, 255, 255, 0.02);
    border-radius: 16px;
    border: 1px solid var(--profile-border);
}

/* Password Change Section */
.password-change-section {
    margin-top: 3rem;
    padding: 2rem;
    background: rgba(255, 255, 255, 0.02);
    border-radius: 16px;
    border: 1px solid var(--profile-border);
    border-left: 4px solid var(--profile-warning);
}

.form-header {
    text-align: center;
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--profile-border);
}

.form-header h3 {
    font-size: 1.8rem;
    color: white;
    margin-bottom: 0.5rem;
}

.form-header p {
    font-size: 1rem;
    color: var(--profile-gray);
}

.form-section {
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--profile-border);
}

.form-section:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.form-section h4 {
    font-size: 1.2rem;
    color: var(--profile-primary);
    margin-bottom: 1.5rem;
    padding-bottom: 0.8rem;
    border-bottom: 1px solid var(--profile-border);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 1rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    color: white;
    font-weight: 600;
    font-size: 0.9rem;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 0.8rem 1rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--profile-border);
    border-radius: 8px;
    color: white;
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--profile-primary);
    box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
    background: rgba(255, 255, 255, 0.08);
}

.form-group input::placeholder {
    color: var(--profile-gray);
}

.form-actions {
    text-align: center;
    margin-top: 2rem;
    display: flex;
    gap: 1rem;
    justify-content: center;
}

/* QR Code */
.qr-container {
    text-align: center;
    padding: 2rem 0;
}

.qr-code {
    width: 250px;
    height: 250px;
    margin: 0 auto 2rem;
    background: white;
    border-radius: 16px;
    padding: 20px;
    box-shadow: var(--profile-shadow);
}

.qr-description {
    color: var(--profile-gray);
    margin-bottom: 2rem;
    font-size: 1.1rem;
}

.qr-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
}

/* Buttons */
.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    font-size: 1rem;
}

.btn-primary {
    background: var(--profile-primary);
    color: white;
}

.btn-primary:hover {
    background: var(--profile-primary-dark);
    transform: translateY(-2px);
    box-shadow: var(--profile-shadow);
}

.btn-outline-primary {
    background: transparent;
    color: var(--profile-primary);
    border: 2px solid var(--profile-primary);
}

.btn-outline-primary:hover {
    background: var(--profile-primary);
    color: white;
}

.btn-outline-secondary {
    background: transparent;
    color: var(--profile-gray);
    border: 2px solid var(--profile-gray);
}

.btn-outline-secondary:hover {
    background: var(--profile-gray);
    color: white;
}

.btn-warning {
    background: var(--profile-warning);
    color: white;
}

.btn-warning:hover {
    background: #d97706;
    transform: translateY(-2px);
    box-shadow: var(--profile-shadow);
}

/* Animations */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

/* Responsive Design */
@media (max-width: 768px) {
    .profile-header {
        flex-direction: column;
        text-align: center;
        gap: 2rem;
    }
    
    .profile-name {
        font-size: 2.5rem;
    }
    
    .profile-stats {
        justify-content: center;
    }
    
    .profile-tabs {
        flex-direction: column;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 576px) {
    .profile-container {
        padding: 1rem;
    }
    
    .profile-header {
        padding: 2rem;
    }
    
    .profile-avatar {
        width: 150px;
        height: 150px;
    }
    
        .profile-name {
            font-size: 2rem;
        }
    }
</style>

<!-- Scripts -->
<script src="https://cdn.rawgit.com/davidshimjs/qrcodejs/gh-pages/qrcode.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize QR Code
        new QRCode(document.getElementById("qrCode"), {
            text: "FIT_TRACK_MEMBER_ID:<?= htmlspecialchars($member['member_id']) ?>",
            width: 200,
            height: 200,
            colorDark: "#000",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        });

    // Tab functionality
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabPanes = document.querySelectorAll('.tab-pane');

    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const targetTab = this.dataset.tab;
            
            // Remove active class from all buttons and panes
            tabBtns.forEach(b => b.classList.remove('active'));
            tabPanes.forEach(p => p.classList.remove('active'));
            
            // Add active class to clicked button and corresponding pane
            this.classList.add('active');
            document.getElementById(targetTab).classList.add('active');
        });
    });

    // Avatar upload functionality
        document.getElementById('editAvatarBtn').addEventListener('click', function() {
            document.getElementById('avatarFileInput').click();
        });
        
        document.getElementById('avatarFileInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            // Validate file type
            const validTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!validTypes.includes(file.type)) {
                alert('Please select a valid image (JPEG, PNG, GIF)');
                return;
            }
            
            // Validate file size (max 5MB)
            if (file.size > 5 * 1024 * 1024) {
                alert('Image size should be less than 5MB');
                return;
            }
            
            // Submit the form
            document.getElementById('avatarUploadForm').submit();
        });
        
        // Download QR code
        document.getElementById('downloadQrBtn').addEventListener('click', function() {
            const canvas = document.querySelector('#qrCode canvas');
            const dataURL = canvas.toDataURL('image/png');
            
            const link = document.createElement('a');
            link.download = 'FIT_TRACK_Membership_QR.png';
            link.href = dataURL;
            link.click();
        });
        
        // Print QR code
        document.getElementById('printQrBtn').addEventListener('click', function() {
        window.print();
        });
        
        // Animate progress bar
        setTimeout(() => {
            document.querySelector('.progress-fill').style.width = '<?= $attendance_percentage ?>%';
        }, 500);
        
        // Password confirmation validation
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        
        if (newPassword && confirmPassword) {
            function validatePassword() {
                if (newPassword.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Passwords do not match');
                } else {
                    confirmPassword.setCustomValidity('');
                }
            }
            
            newPassword.addEventListener('change', validatePassword);
            confirmPassword.addEventListener('keyup', validatePassword);
        }

        // BMI Calculator functionality
        document.getElementById('calculateBmi').addEventListener('click', function() {
            const height = parseFloat(document.getElementById('height').value);
            const weight = parseFloat(document.getElementById('weight').value);
            
            if (height > 0 && weight > 0) {
                const heightMeters = height / 100;
                const bmi = weight / (heightMeters * heightMeters);
                const roundedBmi = Math.round(bmi * 10) / 10;
                
                let category = '';
                let categoryClass = '';
                
                if (bmi < 18.5) {
                    category = 'Underweight';
                    categoryClass = 'warning';
                } else if (bmi < 24.9) {
                    category = 'Normal Weight';
                    categoryClass = 'success';
                } else if (bmi < 29.9) {
                    category = 'Overweight';
                    categoryClass = 'warning';
                } else {
                    category = 'Obese';
                    categoryClass = 'danger';
                }
                
                document.getElementById('bmiNumber').textContent = roundedBmi;
                document.getElementById('bmiCategory').textContent = category;
                document.getElementById('bmiCategory').className = `bmi-category ${categoryClass}`;
                document.getElementById('bmiResult').style.display = 'block';
                
                // Animate the BMI number
                const bmiNumber = document.getElementById('bmiNumber');
                bmiNumber.style.transform = 'scale(1.2)';
                setTimeout(() => {
                    bmiNumber.style.transform = 'scale(1)';
                }, 200);
            } else {
                alert('Please enter valid height and weight values.');
            }
        });

        // Weekly Activity Chart
        const ctx = document.getElementById('weeklyActivityChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    datasets: [{
                        label: 'Workout Duration (hours)',
                        data: [1.5, 2.0, 1.0, 2.5, 1.5, 3.0, 1.0],
                        backgroundColor: [
                            'rgba(102, 126, 234, 0.8)',
                            'rgba(118, 75, 162, 0.8)',
                            'rgba(240, 147, 251, 0.8)',
                            'rgba(16, 185, 129, 0.8)',
                            'rgba(245, 158, 11, 0.8)',
                            'rgba(239, 68, 68, 0.8)',
                            'rgba(59, 130, 246, 0.8)'
                        ],
                        borderColor: [
                            'rgba(102, 126, 234, 1)',
                            'rgba(118, 75, 162, 1)',
                            'rgba(240, 147, 251, 1)',
                            'rgba(16, 185, 129, 1)',
                            'rgba(245, 158, 11, 1)',
                            'rgba(239, 68, 68, 1)',
                            'rgba(59, 130, 246, 1)'
                        ],
                        borderWidth: 2,
                        borderRadius: 8,
                        borderSkipped: false,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(255, 255, 255, 0.1)',
                                drawBorder: false
                            },
                            ticks: {
                                color: 'rgba(255, 255, 255, 0.7)',
                                font: {
                                    size: 12
                                }
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: 'rgba(255, 255, 255, 0.7)',
                                font: {
                                    size: 12
                                }
                            }
                        }
                    }
                }
            });
        }

        // Listen for sidebar toggle events
        const sidebarToggle = document.getElementById('sidebarToggle');
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                // Add a small delay to ensure the sidebar state is updated
                setTimeout(() => {
                    const body = document.body;
                    if (body.classList.contains('sidebar-hidden')) {
                        // Sidebar is hidden, adjust profile container padding
                        document.querySelector('.profile-container').style.padding = '2rem 3rem';
                    } else {
                        // Sidebar is visible, reset profile container padding
                        document.querySelector('.profile-container').style.padding = '2rem';
                    }
                }, 100);
            });
        }
});
</script>

<?php endif; ?>

<?php include 'components/footer.php'; ?>