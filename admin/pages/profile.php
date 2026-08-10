<?php
$page_title = 'Profile';
include '../includes/db.php';
include 'components/header.php';

// Get admin data
$admin_id = $_SESSION['admin_id'] ?? null;
$admin_data = null;

if ($admin_id) {
    $stmt = $conn->prepare("SELECT * FROM admins WHERE id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin_data = $result->fetch_assoc();
}

// Handle profile update
$update_message = '';
$update_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $photo = $admin_data['photo'] ?? null;
    
    // Handle photo upload
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
        $file_name = $_FILES['profile_photo']['name'];
        $file_tmp = $_FILES['profile_photo']['tmp_name'];
        $file_size = $_FILES['profile_photo']['size'];
        $file_type = $_FILES['profile_photo']['type'];
        
        // Validate file type
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        if (!in_array($file_type, $allowed_types) || !in_array($file_extension, $allowed_extensions)) {
            $update_error = "Only image files (JPEG, PNG, GIF, WebP) are allowed.";
        } elseif ($file_size > 5 * 1024 * 1024) {
            $update_error = "File size must not exceed 5MB.";
        } else {
            // Create uploads directory if it doesn't exist
            $upload_dir = '../uploads/admin_photos/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate unique filename
            $new_filename = 'admin_' . $admin_id . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file_tmp, $upload_path)) {
                // Delete old photo if it exists
                if (!empty($admin_data['photo']) && file_exists($upload_dir . $admin_data['photo'])) {
                    unlink($upload_dir . $admin_data['photo']);
                }
                $photo = $new_filename;
            } else {
                $update_error = "Failed to upload photo. Please try again.";
            }
        }
    }
    
    // Validate input
    if (empty($name) || empty($email)) {
        $update_error = "Name and email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $update_error = "Please enter a valid email address.";
    } elseif (empty($update_error)) {
        // Check if email already exists for other admins
        $stmt = $conn->prepare("SELECT id FROM admins WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $admin_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $update_error = "Email address is already in use.";
        } else {
            // Update admin profile
            $stmt = $conn->prepare("UPDATE admins SET name = ?, email = ?, photo = ? WHERE id = ?");
            $stmt->bind_param("sssi", $name, $email, $photo, $admin_id);
            
            if ($stmt->execute()) {
                $update_message = "Profile updated successfully!";
                // Update session data
                $_SESSION['admin_name'] = $name;
                $_SESSION['admin_email'] = $email;
                // Refresh admin data
                $stmt = $conn->prepare("SELECT * FROM admins WHERE id = ?");
                $stmt->bind_param("i", $admin_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $admin_data = $result->fetch_assoc();
            } else {
                $update_error = "Failed to update profile. Please try again.";
            }
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = trim($_POST['current_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $update_error = "All password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $update_error = "New passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $update_error = "Password must be at least 6 characters long.";
    } elseif (!password_verify($current_password, $admin_data['password'])) {
        $update_error = "Current password is incorrect.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE admins SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $admin_id);
        
        if ($stmt->execute()) {
            $update_message = "Password changed successfully!";
        } else {
            $update_error = "Failed to change password. Please try again.";
        }
    }
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <!-- Profile Header -->
            <div class="card mb-4">
                <div class="card-header d-flex align-items-center">
                    <div class="me-3 position-relative">
                        <?php if (!empty($admin_data['photo']) && file_exists('../uploads/admin_photos/' . $admin_data['photo'])): ?>
                            <img src="../uploads/admin_photos/<?php echo htmlspecialchars($admin_data['photo']); ?>" 
                                 alt="Profile" 
                                 class="rounded-circle" 
                                 style="width: 80px; height: 80px; object-fit: cover;">
                        <?php else: ?>
                            <div class="bg-gradient-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                <i class="fas fa-user-tie fa-2x text-white"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                                         <div>
                         <h4 class="mb-1"><?php echo htmlspecialchars($admin_data['name'] ?? 'Ranath V. Goyena'); ?></h4>
                         <p class="text-muted mb-0">Gym Owner</p>
                         <small class="text-muted">Member since: <?php echo date('F Y', strtotime($admin_data['created_at'] ?? 'now')); ?></small>
                     </div>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if ($update_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($update_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($update_error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($update_error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Profile Information -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-user-edit me-2"></i>
                                Profile Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="profile_photo" class="form-label">Profile Picture</label>
                                    <div class="mb-2">
                                        <?php if (!empty($admin_data['photo']) && file_exists('../uploads/admin_photos/' . $admin_data['photo'])): ?>
                                            <img src="../uploads/admin_photos/<?php echo htmlspecialchars($admin_data['photo']); ?>" 
                                                 alt="Current Profile" 
                                                 class="img-thumbnail rounded" 
                                                 style="max-width: 150px; height: auto;">
                                        <?php else: ?>
                                            <div class="bg-light rounded p-3 text-center" style="width: 150px; height: 150px; display: flex; align-items: center; justify-content: center;">
                                                <span class="text-muted">No photo</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <input type="file" class="form-control" id="profile_photo" name="profile_photo" accept="image/*">
                                    <div class="form-text">Upload JPG, PNG, GIF or WebP. Max size: 5MB</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="name" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($admin_data['name'] ?? ''); ?>" required>
                                </div>
                                
                                                                 <div class="mb-3">
                                     <label for="email" class="form-label">Email Address</label>
                                     <input type="email" class="form-control" id="email" name="email" 
                                            value="<?php echo htmlspecialchars($admin_data['email'] ?? ''); ?>" required>
                                 </div>
                                
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>
                                    Update Profile
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-lock me-2"></i>
                                Change Password
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" 
                                           minlength="6" required>
                                    <div class="form-text">Password must be at least 6 characters long.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                                
                                <button type="submit" name="change_password" class="btn btn-warning">
                                    <i class="fas fa-key me-2"></i>
                                    Change Password
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Account Statistics -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-bar me-2"></i>
                                Account Statistics
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6">
                                    <div class="border-end">
                                        <h4 class="text-primary mb-1">
                                            <?php 
                                            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM members");
                                            $stmt->execute();
                                            $result = $stmt->get_result();
                                            $members_count = $result->fetch_assoc()['total'];
                                            echo $members_count;
                                            ?>
                                        </h4>
                                        <small class="text-muted">Total Members</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <h4 class="text-success mb-1">
                                        <?php 
                                        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM staff");
                                        $stmt->execute();
                                        $result = $stmt->get_result();
                                        $staff_count = $result->fetch_assoc()['total'];
                                        echo $staff_count;
                                        ?>
                                    </h4>
                                    <small class="text-muted">Total Staff</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.bg-gradient-primary {
    background: linear-gradient(135deg, #3B82F6 0%, #1E3A8A 100%);
}

.form-control {
    background-color: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: white;
}

.form-control:focus {
    background-color: rgba(255, 255, 255, 0.1);
    border-color: #3B82F6;
    color: white;
    box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
}

.form-control::file-selector-button {
    background-color: #3B82F6;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    margin-right: 10px;
    transition: background-color 0.2s;
}

.form-control::file-selector-button:hover {
    background-color: #1E3A8A;
}

.form-control::placeholder {
    color: rgba(255, 255, 255, 0.5);
}

.form-label {
    color: rgba(255, 255, 255, 0.8);
    font-weight: 500;
}

.form-text {
    color: rgba(255, 255, 255, 0.6);
}

.btn-primary {
    background: linear-gradient(135deg, #3B82F6 0%, #1E3A8A 100%);
    border: none;
}

.btn-warning {
    background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
    border: none;
    color: white;
}

.text-muted {
    color: rgba(255, 255, 255, 0.6) !important;
}

.border-end {
    border-color: rgba(255, 255, 255, 0.1) !important;
}
</style>

<?php include 'components/footer.php'; ?>
