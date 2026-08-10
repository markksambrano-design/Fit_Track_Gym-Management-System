<?php
$page_title = "Registration";
include 'components/header.php';
include '../includes/db.php';

$errors = [];
$success = '';
$activeForm = isset($_GET['form']) ? $_GET['form'] : 'member'; // Default to member form

// Debug: Check what form is active
error_log("Active form: " . $activeForm);
error_log("GET form parameter: " . ($_GET['form'] ?? 'not set'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['form_type']) && $_POST['form_type'] === 'member') {
        // Member registration processing
        $firstName = trim($_POST['mFirstName'] ?? '');
        $lastName = trim($_POST['mLastName'] ?? '');
        $email = trim($_POST['mEmail'] ?? '');
        $phone = trim($_POST['mPhone'] ?? '');
        $password = $_POST['mPassword'] ?? '';
        $confirmPassword = $_POST['mConfirmPassword'] ?? '';
        $membershipType = $_POST['mMembershipType'] ?? '';
        $membershipDuration = $_POST['mMembershipDuration'] ?? null;
        $joinDate = $_POST['mJoinDate'] ?? date('Y-m-d');
        $address = trim($_POST['mAddress'] ?? '');
        $gender = !empty($_POST['mGender']) ? $_POST['mGender'] : null;
        $age = $_POST['mAge'] ?? '';
        $withTrainees = $_POST['mWithTrainees'] ?? '';

        // Validation
        if (empty($firstName)) $errors['mFirstName'] = 'First name is required.';
        if (empty($lastName)) $errors['mLastName'] = 'Last name is required.';
        if (empty($email)) {
            $errors['mEmail'] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['mEmail'] = 'Invalid email format.';
        }
        if (!empty($phone) && !preg_match('/^[0-9+\-\s]*$/', $phone)) {
            $errors['mPhone'] = 'Invalid phone number.';
        }
        if (strlen($password) < 8) {
            $errors['mPassword'] = 'Password must be at least 8 characters.';
        }
        if ($password !== $confirmPassword) {
            $errors['mConfirmPassword'] = 'Passwords do not match.';
        }
        // Validate membership type
        $validMembershipTypes = ['regular', 'student', 'vip'];
        if (!in_array($membershipType, $validMembershipTypes)) {
            $errors['mMembershipType'] = 'Please select a valid membership type.';
        } else {
            // Ensure membership type is valid for database
            $membershipType = in_array($membershipType, $validMembershipTypes) ? $membershipType : 'regular';
        }
        
        // Validate duration
        if (empty($membershipDuration)) {
            $errors['mMembershipDuration'] = 'Please select membership duration.';
        } elseif ($membershipType === 'vip') {
            // VIP can only have 6 or 12 months duration
            if (!in_array($membershipDuration, ['6', '12'])) {
                $errors['mMembershipDuration'] = 'VIP membership is only available for 6 months or 1 year.';
            }
        } elseif (!in_array($membershipDuration, ['1', '3', '6', '12'])) {
            $errors['mMembershipDuration'] = 'Please select a valid duration.';
        }
        
        // Validate trainees option - VIP automatically includes trainees
        if ($membershipType === 'vip') {
            $withTrainees = 'with'; // Force VIP to always have trainees
        } elseif (!in_array($withTrainees, ['with', 'without'])) {
            $errors['mWithTrainees'] = 'Please select trainees option.';
        }
        if (!empty($age) && (!is_numeric($age) || $age < 1 || $age > 120)) {
            $errors['mAge'] = 'Age must be a valid number between 1 and 120.';
        }

        // Validate terms agreement - terms are optional, but if agreed, require contact info
        if (isset($_POST['mTerms'])) {
            // If terms agreed, require phone and address
            if (empty($phone)) {
                $errors['mPhone'] = 'Phone is required when agreeing to terms.';
            }
            if (empty($address)) {
                $errors['mAddress'] = 'Address is required when agreeing to terms.';
            }
        }

        // Handle photo upload
        $photoPath = null;
        if (isset($_FILES['mPhoto']) && $_FILES['mPhoto']['error'] !== UPLOAD_ERR_NO_FILE) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if ($_FILES['mPhoto']['error'] === UPLOAD_ERR_OK) {
                if (in_array($_FILES['mPhoto']['type'], $allowedTypes)) {
                    $uploadDir = '../uploads/member_photos/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    $fileName = uniqid() . '_' . basename($_FILES['mPhoto']['name']);
                    $targetFile = $uploadDir . $fileName;

                    if (move_uploaded_file($_FILES['mPhoto']['tmp_name'], $targetFile)) {
                        $photoPath = $fileName;
                    } else {
                        $errors['mPhoto'] = 'Failed to upload photo.';
                    }
                } else {
                    $errors['mPhoto'] = 'Invalid photo file type. Only JPG, PNG, GIF allowed.';
                }
            } else {
                $errors['mPhoto'] = 'Error uploading photo.';
            }
        }

        // If no errors, insert into DB
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $memberId = 'MEM-' . date('Y') . '-' . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
                
                // Generate QR code data
                $qrCodeData = "FIT_TRACK_MEMBER_ID:" . $memberId;
                
                // Calculate expired date
                $expiredDate = null;
                if ($membershipType === 'session') {
                    $expiredDate = date('Y-m-d', strtotime($joinDate . ' +1 day'));
                } elseif (in_array($membershipType, ['regular', 'student', 'vip']) && $membershipDuration) {
                    $expiredDate = date('Y-m-d', strtotime($joinDate . ' +' . $membershipDuration . ' months'));
                } else {
                    $expiredDate = date('Y-m-d', strtotime($joinDate . ' +30 days'));
                }
                
                // Calculate payroll based on membership type and duration
                $payroll = 0;
                if ($membershipType === 'regular') {
                    switch ($membershipDuration) {
                        case '1':
                            $payroll = 1000; // 1 month - ₱1,000
                            break;
                        case '3':
                            $payroll = 2700; // 3 months - ₱2,700 (₱900/month)
                            break;
                        case '6':
                            $payroll = 4800; // 6 months - ₱4,800 (₱800/month)
                            break;
                        case '12':
                            $payroll = 8400; // 1 year - ₱8,400 (₱700/month)
                            break;
                        default:
                            $payroll = 1000;
                    }
                } elseif ($membershipType === 'student') {
                    switch ($membershipDuration) {
                        case '1':
                            $payroll = 700; // 1 month - ₱700
                            break;
                        case '3':
                            $payroll = 1800; // 3 months - ₱1,800 (₱600/month)
                            break;
                        case '6':
                            $payroll = 3000; // 6 months - ₱3,000 (₱500/month)
                            break;
                        case '12':
                            $payroll = 4800; // 1 year - ₱4,800 (₱400/month)
                            break;
                        default:
                            $payroll = 700;
                    }
                } elseif ($membershipType === 'vip') {
                    switch ($membershipDuration) {
                        case '1':
                            $payroll = 1500; // 1 month - ₱1,500
                            break;
                        case '3':
                            $payroll = 4200; // 3 months - ₱4,200 (₱1,400/month)
                            break;
                        case '6':
                            $payroll = 7800; // 6 months - ₱7,800 (₱1,300/month)
                            break;
                        case '12':
                            $payroll = 14400; // 1 year - ₱14,400 (₱1,200/month)
                            break;
                        default:
                            $payroll = 1500;
                    }
                }
                
                // Add trainees fee if selected
                if ($withTrainees === 'with') {
                    $payroll += 500; // Additional ₱500 for trainees
                }
                
                // Ensure membership type is valid for database ENUM
                $validTypes = ['regular', 'student', 'vip'];
                $safeMembershipType = in_array($membershipType, $validTypes) ? $membershipType : 'regular';
                
                // Calculate training_package based on membership type and duration
                $trainingPackage = null;
                if ($membershipType === 'vip') {
                    $trainingPackage = ($membershipDuration == 6) ? 24 : (($membershipDuration == 12) ? 32 : 32);
                } elseif (in_array($membershipType, ['regular', 'student']) && $membershipDuration) {
                    switch ($membershipDuration) {
                        case 1: $trainingPackage = 8; break;
                        case 3: $trainingPackage = 18; break;
                        case 6: $trainingPackage = 24; break;
                        case 12: $trainingPackage = 32; break;
                        default: $trainingPackage = 8; break;
                    }
                } elseif ($membershipType === 'session') {
                    $trainingPackage = 1;
                }
                
                $stmt = $pdo->prepare("INSERT INTO members 
                    (member_id, first_name, last_name, email, phone, password, membership_type, 
                    membership_duration, join_date, expired_date, address, photo, gender, age, with_trainees, qr_code_data, terms_agreed, training_package) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->execute([
                    $memberId,
                    $firstName,
                    $lastName,
                    $email,
                    $phone ?: null,
                    $hashedPassword,
                    $safeMembershipType,
                    $membershipDuration,
                    $joinDate,
                    $expiredDate,
                    $address ?: null,
                    $photoPath,
                    $gender ?: null,
                    $age ?: null,
                    $withTrainees,
                    $qrCodeData,
                    isset($_POST['mTerms']) ? 1 : 0,
                    $trainingPackage
                ]);
                
                $memberDbId = $pdo->lastInsertId();
                
                // Insert into member_payroll table
                if ($payroll > 0) {
                    $stmt = $pdo->prepare("INSERT INTO member_payroll 
                        (member_id, membership_type, amount, payment_date, status) 
                        VALUES (?, ?, ?, ?, ?)");
                    
                    $stmt->execute([
                        $memberDbId,
                        $membershipType,
                        $payroll,
                        $joinDate,
                        'pending'
                    ]);
                }
                
                // Create training sessions if member registered with trainees
                if ($withTrainees === 'with') {
                    // Create initial training sessions (one session per week for the membership duration)
                    // Note: trainer_id is set to NULL - trainers will be assigned later manually
                    $sessionStmt = $pdo->prepare("
                        INSERT INTO training_sessions 
                        (member_id, trainer_id, session_date, session_time, notes, status) 
                        VALUES (?, NULL, ?, ?, ?, 'booked')
                    ");
                    
                    // Calculate number of sessions based on membership duration
                    $numSessions = 1; // Default 1 session
                    if ($membershipDuration) {
                        switch ($membershipDuration) {
                            case '1': $numSessions = 8; break; // 1 month = 8 sessions
                            case '3': $numSessions = 18; break; // 3 months = 18 sessions
                            case '6': $numSessions = 24; break; // 6 months = 24 sessions
                            case '12': $numSessions = 32; break; // 1 year = 32 sessions
                        }
                    }
                    
                    // Create sessions without trainer assignment
                    $sessionsCreated = 0;
                    $startDate = date('Y-m-d'); // Always start from today
                    
                    for ($i = 0; $i < $numSessions && $sessionsCreated < $numSessions; $i++) {
                        $sessionDate = date('Y-m-d', strtotime($startDate . ' +' . ($i * 7) . ' days')); // Weekly sessions
                        $sessionTime = '10:00:00'; // Default 10 AM
                        
                        try {
                            $sessionStmt->execute([
                                $memberDbId,
                                $sessionDate,
                                $sessionTime,
                                'Training session slot - Trainer to be assigned'
                            ]);
                            $sessionsCreated++;
                        } catch (PDOException $e) {
                            // Continue if session creation fails (maybe date conflict)
                            continue;
                        }
                    }
                }
                
                $pdo->commit();
                
                $success = "Member registered successfully! Member ID: $memberId - QR Code has been generated and stored. Payroll amount: ₱$payroll";
                $newMemberId = $memberDbId;
                $_POST = []; // Clear form
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                if ($e->errorInfo[1] == 1062) {
                    $errors['mEmail'] = 'Email already registered.';
                } else {
                    die("Database error: " . $e->getMessage());
                }
            }
        }
    } elseif (isset($_POST['form_type']) && $_POST['form_type'] === 'staff') {
        // Staff registration with payroll processing
        $firstName = trim($_POST['sFirstName'] ?? '');
        $lastName = trim($_POST['sLastName'] ?? '');
        $email = trim($_POST['sEmail'] ?? '');
        $phone = trim($_POST['sPhone'] ?? '');
        $password = $_POST['sPassword'] ?? '';
        $confirmPassword = $_POST['sConfirmPassword'] ?? '';
        $hireDate = $_POST['sHireDate'] ?? date('Y-m-d');
        $address = trim($_POST['sAddress'] ?? '');
        $gender = !empty($_POST['sGender']) ? $_POST['sGender'] : null;
        $age = $_POST['sAge'] ?? '';
        $schedule = $_POST['sSchedule'] ?? '';
        // Payroll is tracked hourly so that 4 hours = ₱250 and 8 hours = ₱500
        $salary = 50; // Default hourly rate for payroll calculation
        $bankName = null; // Bank name not collected during registration
        $accountNumber = null; // Account number not collected during registration
        $taxId = null; // Tax ID not collected during registration
        $employmentType = $_POST['sEmploymentType'] ?? 'wholeday';

        // Validation
        if (empty($firstName)) $errors['sFirstName'] = 'First name is required.';
        if (empty($lastName)) $errors['sLastName'] = 'Last name is required.';
        if (empty($email)) {
            $errors['sEmail'] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['sEmail'] = 'Invalid email format.';
        }
        if (!empty($phone) && !preg_match('/^[0-9+\-\s]*$/', $phone)) {
            $errors['sPhone'] = 'Invalid phone number.';
        }
        if (strlen($password) < 8) {
            $errors['sPassword'] = 'Password must be at least 8 characters.';
        }
        if ($password !== $confirmPassword) {
            $errors['sConfirmPassword'] = 'Passwords do not match.';
        }
        if (!empty($salary) && !is_numeric($salary)) {
            $errors['sSalary'] = 'Salary must be a valid number.';
        }
        if (!empty($age) && (!is_numeric($age) || $age < 1 || $age > 120)) {
            $errors['sAge'] = 'Age must be a valid number between 1 and 120.';
        }
        if ($employmentType === 'half day' && empty($schedule)) {
            $errors['sSchedule'] = 'Schedule is required for half day employment.';
        } elseif ($employmentType === 'half day' && !in_array($schedule, ['morning', 'afternoon'])) {
            $errors['sSchedule'] = 'Please select a valid schedule.';
        }

        // Handle photo upload
        $photoPath = null;
        if (isset($_FILES['sPhoto']) && $_FILES['sPhoto']['error'] !== UPLOAD_ERR_NO_FILE) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if ($_FILES['sPhoto']['error'] === UPLOAD_ERR_OK) {
                if (in_array($_FILES['sPhoto']['type'], $allowedTypes)) {
                    $uploadDir = '../uploads/staff_photos/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    $fileName = uniqid() . '_' . basename($_FILES['sPhoto']['name']);
                    $targetFile = $uploadDir . $fileName;

                    if (move_uploaded_file($_FILES['sPhoto']['tmp_name'], $targetFile)) {
                        $photoPath = $fileName;
                    } else {
                        $errors['sPhoto'] = 'Failed to upload photo.';
                    }
                } else {
                    $errors['sPhoto'] = 'Invalid photo file type. Only JPG, PNG, GIF allowed.';
                }
            } else {
                $errors['sPhoto'] = 'Error uploading photo.';
            }
        }

        // If no errors, insert into DB
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                // Generate staff ID
                $staffId = 'STAFF-' . date('Y') . '-' . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                // Generate QR code data
                $qrCodeData = "FIT_TRACK_STAFF_ID:" . $staffId;
                
                // Insert into staff table
                $stmt = $pdo->prepare("INSERT INTO staff 
                    (staff_id, first_name, last_name, email, phone, password, 
                    hire_date, address, photo, gender, age, schedule, qr_code_data) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->execute([
                    $staffId,
                    $firstName,
                    $lastName,
                    $email,
                    $phone ?: null,
                    $hashedPassword,
                    $hireDate,
                    $address ?: null,
                    $photoPath,
                    $gender ?: null,
                    $age ?: null,
                    $schedule ?: null,
                    $qrCodeData
                ]);
                
                $staffDbId = $pdo->lastInsertId();
                
                // Insert into payroll table with default hourly rate of ₱62.50
                $stmt = $pdo->prepare("INSERT INTO payroll 
                    (staff_id, salary, bank_name, account_number, tax_id, employment_type) 
                    VALUES (?, ?, ?, ?, ?, ?)");
                
                $stmt->execute([
                    $staffDbId,
                    $salary ?: 50, // Use ₱50/hour as default if empty
                    $bankName ?: null,
                    $accountNumber ?: null,
                    $taxId ?: null,
                    $employmentType
                ]);
                
                $pdo->commit();
                
                $success = "Staff registered successfully! Staff ID: $staffId - QR Code has been generated and stored.";
                $activeForm = 'staff';
                $_POST = []; // Clear form
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                if ($e->errorInfo[1] == 1062) {
                    $errors['sEmail'] = 'Email already registered.';
                } else {
                    die("Database error: " . $e->getMessage());
                }
            }
        } else {
            $activeForm = 'staff';
        }
    }
    
    // Ensure activeForm is set correctly after form submission
    if (isset($_POST['form_type'])) {
        $activeForm = $_POST['form_type'];
    }
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"></h1>
        <div class="btn-group" role="group">
            <a href="register.php?form=member" class="btn btn-<?= $activeForm === 'member' ? 'primary' : 'outline-primary' ?>">
                <i class="fas fa-user me-2"></i>Member Registration
            </a>
            <a href="register.php?form=staff" class="btn btn-<?= $activeForm === 'staff' ? 'primary' : 'outline-primary' ?>">
                <i class="fas fa-user-tie me-2"></i>Staff Registration
            </a>
        </div>
    </div>
    


    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php if (isset($memberId)): ?>
            <!-- QR Code Display Section for Members -->
            <div class="card shadow mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-qrcode me-2"></i>Member QR Code Generated</h5>
                </div>
                <div class="card-body text-center">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="qr-code-container mb-3">
                                <div id="memberQrCode" class="qr-code-display"></div>
                                <p class="text-muted mt-2">Scan this QR code for gym access</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="member-info">
                                <h6>Member Details:</h6>
                                <p><strong>Name:</strong> <?= htmlspecialchars($firstName . ' ' . $lastName) ?></p>
                                <p><strong>Member ID:</strong> <?= htmlspecialchars($memberId) ?></p>
                                <p><strong>Membership:</strong> <?= htmlspecialchars(ucfirst($membershipType)) ?></p>
                                <p><strong>Join Date:</strong> <?= htmlspecialchars(date('M d, Y', strtotime($joinDate))) ?></p>
                                <hr>
                                <div class="payroll-info">
                                    <h6 class="text-success"><i class="fas fa-money-bill-wave me-2"></i>Payroll Information</h6>
                                    <p><strong>Membership Fee:</strong> <span class="text-success fw-bold">₱<?= $payroll ?></span></p>
                                    <p><strong>Status:</strong> <span class="badge bg-warning">Pending Payment</span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="qr-actions mt-3">
                        <button class="btn btn-primary me-2" id="downloadQrBtn">
                            <i class="fas fa-download me-2"></i>Download QR Code
                        </button>
                        <button class="btn btn-outline-primary me-2" id="printQrBtn">
                            <i class="fas fa-print me-2"></i>Print Membership Card
                        </button>
                        <button class="btn btn-success" id="emailQrBtn">
                            <i class="fas fa-envelope me-2"></i>Email to Member
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($staffId)): ?>
            <!-- QR Code Display Section for Staff -->
            <div class="card shadow mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-qrcode me-2"></i>Staff QR Code Generated</h5>
                </div>
                <div class="card-body text-center">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="qr-code-container mb-3">
                                <div id="staffQrCode" class="qr-code-display"></div>
                                <p class="text-muted mt-2">Scan this QR code for staff access</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="staff-info">
                                <h6>Staff Details:</h6>
                                <p><strong>Name:</strong> <?= htmlspecialchars($firstName . ' ' . $lastName) ?></p>
                                <p><strong>Staff ID:</strong> <?= htmlspecialchars($staffId) ?></p>
                                <p><strong>Hire Date:</strong> <?= htmlspecialchars(date('M d, Y', strtotime($hireDate))) ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="qr-actions mt-3">
                        <button class="btn btn-primary me-2" id="downloadStaffQrBtn">
                            <i class="fas fa-download me-2"></i>Download QR Code
                        </button>
                        <button class="btn btn-outline-primary me-2" id="printStaffQrBtn">
                            <i class="fas fa-print me-2"></i>Print Staff ID Card
                        </button>
                        <button class="btn btn-success" id="emailStaffQrBtn">
                            <i class="fas fa-envelope me-2"></i>Email to Staff
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-12">
            <?php if ($activeForm === 'member'): ?>
                <!-- Member Registration Form -->
                <div class="card shadow mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-user me-2"></i>Member Registration Form</h5>
                    </div>
                    <div class="card-body">
                        <form id="memberForm" method="POST" enctype="multipart/form-data" novalidate>
                            <input type="hidden" name="form_type" value="member">
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="mFirstName" class="form-label">First Name *</label>
                                    <input type="text" class="form-control <?= isset($errors['mFirstName']) ? 'is-invalid' : '' ?>" 
                                        name="mFirstName" id="mFirstName" 
                                        value="<?= htmlspecialchars($_POST['mFirstName'] ?? '') ?>" required>
                                    <div class="invalid-feedback"><?= $errors['mFirstName'] ?? '' ?></div>
                                </div>
                                <div class="col-md-6">
                                    <label for="mLastName" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control <?= isset($errors['mLastName']) ? 'is-invalid' : '' ?>" 
                                        name="mLastName" id="mLastName" 
                                        value="<?= htmlspecialchars($_POST['mLastName'] ?? '') ?>" required>
                                    <div class="invalid-feedback"><?= $errors['mLastName'] ?? '' ?></div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="mEmail" class="form-label">Email *</label>
                                    <input type="email" class="form-control <?= isset($errors['mEmail']) ? 'is-invalid' : '' ?>" 
                                        name="mEmail" id="mEmail" 
                                        value="<?= htmlspecialchars($_POST['mEmail'] ?? '') ?>" required>
                                    <div class="invalid-feedback"><?= $errors['mEmail'] ?? '' ?></div>
                                </div>
                                <div class="col-md-6">
                                    <label for="mPhone" class="form-label">Phone</label>
                                    <input type="tel" class="form-control <?= isset($errors['mPhone']) ? 'is-invalid' : '' ?>" 
                                        name="mPhone" id="mPhone" 
                                        value="<?= htmlspecialchars($_POST['mPhone'] ?? '') ?>">
                                    <div class="invalid-feedback"><?= $errors['mPhone'] ?? '' ?></div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="mPassword" class="form-label">Password *</label>
                                    <input type="password" class="form-control <?= isset($errors['mPassword']) ? 'is-invalid' : '' ?>" 
                                        name="mPassword" id="mPassword" required>
                                    <div class="invalid-feedback"><?= $errors['mPassword'] ?? '' ?></div>
                                </div>
                                <div class="col-md-6">
                                    <label for="mConfirmPassword" class="form-label">Confirm Password *</label>
                                    <input type="password" class="form-control <?= isset($errors['mConfirmPassword']) ? 'is-invalid' : '' ?>" 
                                        name="mConfirmPassword" id="mConfirmPassword" required>
                                    <div class="invalid-feedback"><?= $errors['mConfirmPassword'] ?? '' ?></div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="mMembershipType" class="form-label">Membership Type *</label>
                                    <select class="form-select <?= isset($errors['mMembershipType']) ? 'is-invalid' : '' ?>" 
                                        name="mMembershipType" id="mMembershipType" required>
                                        <option value="">Select Type</option>
                                        <option value="regular" <?= (($_POST['mMembershipType'] ?? '') === 'regular') ? 'selected' : '' ?>>Regular Membership</option>
                                        <option value="student" <?= (($_POST['mMembershipType'] ?? '') === 'student') ? 'selected' : '' ?>>Student Membership</option>
                                        <option value="vip" <?= (($_POST['mMembershipType'] ?? '') === 'vip') ? 'selected' : '' ?>>VIP Membership</option>
                                    </select>
                                    <div class="invalid-feedback"><?= $errors['mMembershipType'] ?? '' ?></div>
                                </div>
                                <div class="col-md-4">
                                    <label for="mMembershipDuration" class="form-label">Duration *</label>
                                    <select class="form-select <?= isset($errors['mMembershipDuration']) ? 'is-invalid' : '' ?>" 
                                        name="mMembershipDuration" id="mMembershipDuration" required>
                                        <option value="">Select Duration</option>
                                        <option value="1" <?= (($_POST['mMembershipDuration'] ?? '') === '1') ? 'selected' : '' ?>>1 Month</option>
                                        <option value="3" <?= (($_POST['mMembershipDuration'] ?? '') === '3') ? 'selected' : '' ?>>3 Months</option>
                                        <option value="6" <?= (($_POST['mMembershipDuration'] ?? '') === '6') ? 'selected' : '' ?>>6 Months</option>
                                        <option value="12" <?= (($_POST['mMembershipDuration'] ?? '') === '12') ? 'selected' : '' ?>>1 Year</option>
                                    </select>
                                    <div class="invalid-feedback"><?= $errors['mMembershipDuration'] ?? '' ?></div>
                                </div>
                                <div class="col-md-4">
                                    <label for="mJoinDate" class="form-label">Join Date</label>
                                    <input type="date" class="form-control" 
                                        name="mJoinDate" id="mJoinDate" 
                                        value="<?= htmlspecialchars($_POST['mJoinDate'] ?? date('Y-m-d')) ?>">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="mGender" class="form-label">Gender</label>
                                    <select class="form-select" name="mGender" id="mGender">
                                        <option value="">Select Gender</option>
                                        <option value="male" <?= (($_POST['mGender'] ?? '') === 'male') ? 'selected' : '' ?>>Male</option>
                                        <option value="female" <?= (($_POST['mGender'] ?? '') === 'female') ? 'selected' : '' ?>>Female</option>
                                        <option value="other" <?= (($_POST['mGender'] ?? '') === 'other') ? 'selected' : '' ?>>Other</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="mAge" class="form-label">Age</label>
                                    <input type="number" class="form-control <?= isset($errors['mAge']) ? 'is-invalid' : '' ?>" 
                                        name="mAge" id="mAge" min="1" max="120" 
                                        value="<?= htmlspecialchars($_POST['mAge'] ?? '') ?>">
                                    <div class="invalid-feedback"><?= $errors['mAge'] ?? '' ?></div>
                                </div>
                                <div class="col-md-3">
                                    <label for="mWithTrainees" class="form-label">Trainees Option <span id="traineesRequired">*</span></label>
                                    <select class="form-select <?= isset($errors['mWithTrainees']) ? 'is-invalid' : '' ?>" 
                                        name="mWithTrainees" id="mWithTrainees" required>
                                        <option value="">Select Option</option>
                                        <option value="with" <?= (($_POST['mWithTrainees'] ?? '') === 'with') ? 'selected' : '' ?>>With Trainees</option>
                                        <option value="without" <?= (($_POST['mWithTrainees'] ?? '') === 'without') ? 'selected' : '' ?>>Without Trainees</option>
                                    </select>
                                    <div class="invalid-feedback"><?= $errors['mWithTrainees'] ?? '' ?></div>
                                    <small id="vipTraineesNote" class="text-muted" style="display: none;"><i class="fas fa-info-circle"></i> VIP membership includes trainees automatically</small>
                                </div>
                                <div class="col-md-3">
                                    <label for="mPhoto" class="form-label">Photo</label>
                                    <input class="form-control <?= isset($errors['mPhoto']) ? 'is-invalid' : '' ?>" 
                                        type="file" name="mPhoto" id="mPhoto" accept="image/*">
                                    <div class="invalid-feedback"><?= $errors['mPhoto'] ?? '' ?></div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="mAddress" class="form-label">Address</label>
                                <textarea class="form-control <?= isset($errors['mAddress']) ? 'is-invalid' : '' ?>" name="mAddress" id="mAddress" rows="2"><?= htmlspecialchars($_POST['mAddress'] ?? '') ?></textarea>
                                <div class="invalid-feedback"><?= $errors['mAddress'] ?? '' ?></div>
                            </div>

                            <!-- Payroll Information Section -->
                            <div class="row mb-3">
                                <div class="col-12">
                                    <div class="alert alert-info" id="membershipFeesSection" style="display: none;">
                                        <h6 class="alert-heading"><i class="fas fa-money-bill-wave me-2"></i>Membership Fees</h6>
                                        <div id="pricingDisplay">
                                            <div class="row">
                                                <div class="col-md-12" id="regularPricing" style="display: none;">
                                                    <strong>Regular Membership:</strong>
                                                    <ul class="list-unstyled mt-2">
                                                        <li>1 Month: ₱1,000</li>
                                                        <li>3 Months: ₱2,700 (₱900/month)</li>
                                                        <li>6 Months: ₱4,800 (₱800/month)</li>
                                                        <li>1 Year: ₱8,400 (₱700/month)</li>
                                                    </ul>
                                                </div>
                                                <div class="col-md-12" id="studentPricing" style="display: none;">
                                                    <strong>Student Membership:</strong>
                                                    <ul class="list-unstyled mt-2">
                                                        <li>1 Month: ₱700</li>
                                                        <li>3 Months: ₱1,800 (₱600/month)</li>
                                                        <li>6 Months: ₱3,000 (₱500/month)</li>
                                                        <li>1 Year: ₱4,800 (₱400/month)</li>
                                                    </ul>
                                                </div>
                                                <div class="col-md-12" id="vipPricing" style="display: none;">
                                                    <strong class="text-warning"><i class="fas fa-crown me-1"></i>VIP Membership:</strong>
                                                    <ul class="list-unstyled mt-2">
                                                        <li>6 Months: ₱7,800 (₱1,300/month)</li>
                                                        <li>1 Year: ₱14,400 (₱1,200/month)</li>
                                                    </ul>
                                                    <small class="text-info"><i class="fas fa-star me-1"></i>Includes trainees automatically</small>
                                                    <hr class="my-2">
                                                    <div class="vip-benefits mt-2">
                                                        <strong class="text-warning small">VIP Exclusive Benefits:</strong>
                                                        <ul class="list-unstyled small mt-1 mb-0">
                                                            <li><i class="fas fa-check-circle text-success me-1"></i>Free Nutrition Consultation</li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div id="selectedPrice" class="mt-3 p-2 bg-light rounded" style="display: none;">
                                            <strong>Selected Plan:</strong> <span id="priceAmount"></span>
                                            <div id="traineesFee" class="mt-2" style="display: none;">
                                                <strong>Trainees Fee:</strong> <span class="text-warning fw-bold">+₱500</span>
                                            </div>
                                            <div id="totalPrice" class="mt-2" style="display: none;">
                                                <strong>Total Amount:</strong> <span id="totalAmount" class="text-success fw-bold"></span>
                                            </div>
                                        </div>
                                        <small class="text-muted">Fees will be automatically calculated based on the selected membership type, duration, and trainees option.</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Terms and Policy Agreement -->
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input <?= isset($errors['mTerms']) ? 'is-invalid' : '' ?>" 
                                    name="mTerms" id="mTerms">
                                <label class="form-check-label" for="mTerms">
                                    I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms and Conditions</a> and <a href="#" data-bs-toggle="modal" data-bs-target="#privacyModal">Privacy Policy</a>
                                </label>
                                <div class="invalid-feedback"><?= $errors['mTerms'] ?? '' ?></div>
                            </div>

                            <button type="submit" class="btn btn-primary">Register Member</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($activeForm === 'staff'): ?>
                <!-- Staff Registration Form -->
                <div class="card shadow mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-user-tie me-2"></i>Staff Registration Form</h5>
                    </div>
                    <div class="card-body">
                        <form id="staffForm" method="POST" enctype="multipart/form-data" novalidate>
                            <input type="hidden" name="form_type" value="staff">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="sFirstName" class="form-label">First Name *</label>
                                    <input type="text" class="form-control <?= isset($errors['sFirstName']) ? 'is-invalid' : '' ?>" 
                                        name="sFirstName" id="sFirstName" 
                                        value="<?= htmlspecialchars($_POST['sFirstName'] ?? '') ?>" required>
                                    <div class="invalid-feedback"><?= $errors['sFirstName'] ?? '' ?></div>
                                </div>
                                <div class="col-md-6">
                                    <label for="sLastName" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control <?= isset($errors['sLastName']) ? 'is-invalid' : '' ?>" 
                                        name="sLastName" id="sLastName" 
                                        value="<?= htmlspecialchars($_POST['sLastName'] ?? '') ?>" required>
                                    <div class="invalid-feedback"><?= $errors['sLastName'] ?? '' ?></div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="sEmail" class="form-label">Email *</label>
                                    <input type="email" class="form-control <?= isset($errors['sEmail']) ? 'is-invalid' : '' ?>" 
                                        name="sEmail" id="sEmail" 
                                        value="<?= htmlspecialchars($_POST['sEmail'] ?? '') ?>" required>
                                    <div class="invalid-feedback"><?= $errors['sEmail'] ?? '' ?></div>
                                </div>
                                <div class="col-md-6">
                                    <label for="sPhone" class="form-label">Phone</label>
                                    <input type="tel" class="form-control <?= isset($errors['sPhone']) ? 'is-invalid' : '' ?>" 
                                        name="sPhone" id="sPhone" 
                                        value="<?= htmlspecialchars($_POST['sPhone'] ?? '') ?>">
                                    <div class="invalid-feedback"><?= $errors['sPhone'] ?? '' ?></div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="sPassword" class="form-label">Password *</label>
                                    <input type="password" class="form-control <?= isset($errors['sPassword']) ? 'is-invalid' : '' ?>" 
                                        name="sPassword" id="sPassword" required>
                                    <div class="invalid-feedback"><?= $errors['sPassword'] ?? '' ?></div>
                                </div>
                                <div class="col-md-6">
                                    <label for="sConfirmPassword" class="form-label">Confirm Password *</label>
                                    <input type="password" class="form-control <?= isset($errors['sConfirmPassword']) ? 'is-invalid' : '' ?>" 
                                        name="sConfirmPassword" id="sConfirmPassword" required>
                                    <div class="invalid-feedback"><?= $errors['sConfirmPassword'] ?? '' ?></div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label for="sHireDate" class="form-label">Hire Date</label>
                                    <input type="date" class="form-control" 
                                        name="sHireDate" id="sHireDate" 
                                        value="<?= htmlspecialchars($_POST['sHireDate'] ?? date('Y-m-d')) ?>">
                                </div>
                                <div class="col-md-2">
                                    <label for="sGender" class="form-label">Gender</label>
                                    <select class="form-select" name="sGender" id="sGender">
                                        <option value="">Select Gender</option>
                                        <option value="male" <?= (($_POST['sGender'] ?? '') === 'male') ? 'selected' : '' ?>>Male</option>
                                        <option value="female" <?= (($_POST['sGender'] ?? '') === 'female') ? 'selected' : '' ?>>Female</option>
                                        <option value="other" <?= (($_POST['sGender'] ?? '') === 'other') ? 'selected' : '' ?>>Other</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="sAge" class="form-label">Age</label>
                                    <input type="number" class="form-control <?= isset($errors['sAge']) ? 'is-invalid' : '' ?>" 
                                        name="sAge" id="sAge" min="1" max="120" 
                                        value="<?= htmlspecialchars($_POST['sAge'] ?? '') ?>">
                                    <div class="invalid-feedback"><?= $errors['sAge'] ?? '' ?></div>
                                </div>
                                <div class="col-md-2">
                                    <label for="sPhoto" class="form-label">Photo</label>
                                    <input class="form-control <?= isset($errors['sPhoto']) ? 'is-invalid' : '' ?>" 
                                        type="file" name="sPhoto" id="sPhoto" accept="image/*">
                                    <div class="invalid-feedback"><?= $errors['sPhoto'] ?? '' ?></div>
                                </div>
                                <div class="col-md-3">
                                    <label for="sEmploymentType" class="form-label">Employment Type</label>
                                    <select class="form-select" name="sEmploymentType" id="sEmploymentType" required>
                                        <option value="wholeday" <?= (($_POST['sEmploymentType'] ?? 'wholeday') === 'wholeday') ? 'selected' : '' ?>>Whole Day</option>
                                        <option value="half day" <?= (($_POST['sEmploymentType'] ?? '') === 'half day') ? 'selected' : '' ?>>Half Day</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mb-3" id="scheduleContainer" style="display: none;">
                                <div class="col-md-3">
                                    <label for="sSchedule" class="form-label">Schedule *</label>
                                    <select class="form-select <?= isset($errors['sSchedule']) ? 'is-invalid' : '' ?>" 
                                        name="sSchedule" id="sSchedule">
                                        <option value="">Select Schedule</option>
                                        <option value="morning" <?= (($_POST['sSchedule'] ?? '') === 'morning') ? 'selected' : '' ?>>Morning (7AM - 12PM, 5hrs)</option>
                                        <option value="afternoon" <?= (($_POST['sSchedule'] ?? '') === 'afternoon') ? 'selected' : '' ?>>Afternoon (1PM - 6PM, 5hrs)</option>
                                    </select>
                                    <div class="invalid-feedback"><?= $errors['sSchedule'] ?? '' ?></div>
                                </div>
                            </div>

                            <div class="mb-3" style="margin-top: 0.5rem !important;">
                                <label for="sAddress" class="form-label">Address</label>
                                <textarea class="form-control" name="sAddress" id="sAddress" rows="2"><?= htmlspecialchars($_POST['sAddress'] ?? '') ?></textarea>
                            </div>

                            <hr class="my-4">
                            <h5 class="mb-4"><i class="fas fa-money-bill-wave me-2"></i>Payroll Information</h5>
                            
                            <!-- Payroll Information Display -->
                            <div class="card mb-4" style="background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%); border: 2px solid #3B82F6;">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6 class="fw-bold text-primary mb-3">
                                                <i class="fas fa-money-bill-wave me-2"></i>Staff Payroll Rates:
                                            </h6>
                                            <div class="payroll-info-item">
                                                <span class="payroll-label">Hourly Rate:</span>
                                                <span class="payroll-amount">₱50/hour</span>
                                            </div>
                                            <div class="payroll-info-item">
                                                <span class="payroll-label">Full Day (10 hours):</span>
                                                <span class="payroll-amount">₱500</span>
                                            </div>
                                            <div class="payroll-info-item">
                                                <span class="payroll-label">Half Day (5 hours):</span>
                                                <span class="payroll-amount">₱250</span>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <h6 class="fw-bold text-success mb-3">
                                                <i class="fas fa-info-circle me-2"></i>How It Works:
                                            </h6>
                                            <div class="payroll-info-item">
                                                <span class="payroll-label">QR Code Scan:</span>
                                                <span class="payroll-amount">Time In/Out</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <small class="text-muted">
                                            <i class="fas fa-lightbulb me-1"></i>
                                            Staff earn ₱50 per hour worked. Full day (10 hours) = ₱500, Half day (5 hours) = ₱250. 
                                            Payroll is automatically calculated based on actual hours worked using QR code attendance.
                                        </small>
                                    </div>
                                </div>
                            </div>
                            

                            <button type="submit" class="btn btn-primary">Register Staff</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- QR Code Styles -->
<style>
    .qr-code-container {
        padding: 20px;
        background: #f8f9fa;
        border-radius: 10px;
        border: 2px dashed #dee2e6;
    }
    
    /* Remove spacing between form fields */
    .row.mb-3 {
        margin-bottom: 0.5rem !important;
    }
    
    .form-label {
        margin-bottom: 0.25rem !important;
    }
    
    .form-control, .form-select {
        margin-bottom: 0.25rem !important;
    }
    
    .invalid-feedback {
        margin-top: 0.25rem !important;
    }
    
    .payroll-info-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid rgba(59, 130, 246, 0.2);
    }
    
    .payroll-info-item:last-child {
        border-bottom: none;
    }
    
    .payroll-label {
        font-weight: 500;
        color: #495057;
    }
    
    .payroll-amount {
        font-weight: bold;
        color: #28a745;
    }
    
    .qr-code-display {
        width: 200px;
        height: 200px;
        margin: 0 auto;
        background: white;
        border-radius: 8px;
        padding: 15px;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    
    .member-info, .staff-info {
        text-align: left;
        padding: 20px;
        background: #f8f9fa;
        border-radius: 10px;
        border: 1px solid #dee2e6;
    }
    
    .member-info h6, .staff-info h6 {
        color: #495057;
        margin-bottom: 15px;
        font-weight: 600;
    }
    
    .member-info p, .staff-info p {
        margin-bottom: 8px;
        color: #6c757d;
    }
    
    .member-info strong, .staff-info strong {
        color: #495057;
    }
    
    .qr-actions {
        border-top: 1px solid #dee2e6;
        padding-top: 20px;
    }
    
    .qr-actions .btn {
        margin: 5px;
    }
    
    /* VIP Benefits Styling */
    .vip-benefits {
        background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
        padding: 10px;
        border-radius: 8px;
        border: 2px solid #ffc107;
        margin-top: 10px;
    }
    
    .vip-benefits strong {
        color: #856404;
        font-weight: 600;
    }
    
    .vip-benefits ul li {
        padding: 3px 0;
        color: #856404;
        font-size: 0.85rem;
    }
    
    .vip-benefits ul li i {
        font-size: 0.75rem;
    }
    
    .text-warning {
        color: #ffc107 !important;
    }
</style>

<!-- Terms and Privacy Policy Modals -->
<div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="termsModalLabel">Terms and Conditions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h6>1. Membership Agreement</h6>
                <p>By registering for membership at FITTRACK Gym, you agree to abide by all gym rules and regulations.</p>
                
                <h6>2. Payment Terms</h6>
                <p>All membership fees are non-refundable. Payment is required before accessing gym facilities.</p>
                
                <h6>3. Code of Conduct</h6>
                <p>Members must maintain appropriate behavior and respect all equipment and other members.</p>
                
                <h6>4. Health and Safety</h6>
                <p>Members acknowledge that they are physically fit to use gym equipment. Consult a physician before starting any exercise program.</p>
                
                <h6>5. Membership Cancellation</h6>
                <p>Membership can be cancelled with 30 days written notice. No refunds will be provided for unused membership time.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="privacyModal" tabindex="-1" aria-labelledby="privacyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="privacyModalLabel">Privacy Policy</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h6>1. Information Collection</h6>
                <p>We collect personal information including name, email, phone, and photo for membership management and facility access.</p>
                
                <h6>2. Data Usage</h6>
                <p>Your information is used solely for gym operations, attendance tracking, and communication purposes.</p>
                
                <h6>3. Data Security</h6>
                <p>We implement appropriate security measures to protect your personal information from unauthorized access.</p>
                
                <h6>4. Data Sharing</h6>
                <p>Your personal information will not be sold, traded, or shared with third parties without your consent.</p>
                
                <h6>5. Data Retention</h6>
                <p>Personal information is retained for the duration of your membership and for legal compliance purposes.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- QR Code Script -->
<script src="https://cdn.rawgit.com/davidshimjs/qrcodejs/gh-pages/qrcode.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Debug: Log the current form
        console.log('Current active form:', '<?= $activeForm ?>');
        console.log('URL form parameter:', '<?= $_GET['form'] ?? 'none' ?>');
        
        // Duration field is always required for regular memberships
        
        // Dynamic payroll display based on membership type and duration
        const membershipTypeSelect = document.getElementById('mMembershipType');
        const membershipDurationSelect = document.getElementById('mMembershipDuration');
        const withTraineesSelect = document.getElementById('mWithTrainees');
        const selectedPriceDiv = document.getElementById('selectedPrice');
        const priceAmountSpan = document.getElementById('priceAmount');
        const traineesFeeDiv = document.getElementById('traineesFee');
        const totalPriceDiv = document.getElementById('totalPrice');
        const totalAmountSpan = document.getElementById('totalAmount');
        const vipTraineesNote = document.getElementById('vipTraineesNote');
        const traineesRequired = document.getElementById('traineesRequired');
        const membershipFeesSection = document.getElementById('membershipFeesSection');
        
        // Function to show/hide membership fees section and filter by selected type
        function toggleMembershipFees() {
            const selectedType = membershipTypeSelect.value;
            const regularPricing = document.getElementById('regularPricing');
            const studentPricing = document.getElementById('studentPricing');
            const vipPricing = document.getElementById('vipPricing');
            
            if (selectedType && selectedType !== '') {
                membershipFeesSection.style.display = 'block';
                
                // Hide all pricing columns first
                if (regularPricing) regularPricing.style.display = 'none';
                if (studentPricing) studentPricing.style.display = 'none';
                if (vipPricing) vipPricing.style.display = 'none';
                
                // Show only the selected membership type pricing
                if (selectedType === 'regular' && regularPricing) {
                    regularPricing.style.display = 'block';
                } else if (selectedType === 'student' && studentPricing) {
                    studentPricing.style.display = 'block';
                } else if (selectedType === 'vip' && vipPricing) {
                    vipPricing.style.display = 'block';
                }
            } else {
                membershipFeesSection.style.display = 'none';
                // Hide all pricing columns
                if (regularPricing) regularPricing.style.display = 'none';
                if (studentPricing) studentPricing.style.display = 'none';
                if (vipPricing) vipPricing.style.display = 'none';
            }
        }
        
        // Function to handle VIP membership special rules
        function handleVIPMembership() {
            const selectedType = membershipTypeSelect.value;
            
            if (selectedType === 'vip') {
                // Automatically set trainees to "with"
                withTraineesSelect.value = 'with';
                withTraineesSelect.disabled = true;
                withTraineesSelect.style.backgroundColor = '#e9ecef';
                vipTraineesNote.style.display = 'block';
                traineesRequired.style.display = 'none';
                
                // Filter duration options - only show 6 and 12 months for VIP
                const durationOptions = membershipDurationSelect.querySelectorAll('option');
                durationOptions.forEach(option => {
                    if (option.value === '6' || option.value === '12' || option.value === '') {
                        option.style.display = 'block';
                    } else {
                        option.style.display = 'none';
                        // If a non-VIP duration is selected, reset to empty
                        if (option.selected && !['6', '12'].includes(option.value)) {
                            membershipDurationSelect.value = '';
                        }
                    }
                });
            } else {
                // Reset trainees field for non-VIP memberships
                withTraineesSelect.disabled = false;
                withTraineesSelect.style.backgroundColor = '';
                vipTraineesNote.style.display = 'none';
                traineesRequired.style.display = 'inline';
                
                // Show all duration options for non-VIP
                const durationOptions = membershipDurationSelect.querySelectorAll('option');
                durationOptions.forEach(option => {
                    option.style.display = 'block';
                });
            }
            
            // Update price display
            updatePriceDisplay();
        }
        
        const pricingData = {
            regular: {
                '1': 1000,
                '3': 2700,
                '6': 4800,
                '12': 8400
            },
            student: {
                '1': 700,
                '3': 1800,
                '6': 3000,
                '12': 4800
            },
            vip: {
                '1': 1500,
                '3': 4200,
                '6': 7800,
                '12': 14400
            }
        };
        
        function updatePriceDisplay() {
            const selectedType = membershipTypeSelect.value;
            const selectedDuration = membershipDurationSelect.value;
            const selectedTrainees = withTraineesSelect.value;
            
            if (selectedType && selectedDuration && pricingData[selectedType] && pricingData[selectedType][selectedDuration]) {
                const basePrice = pricingData[selectedType][selectedDuration];
                // VIP always includes trainees, so always add 500
                const isVIP = selectedType === 'vip';
                const traineesFee = (selectedTrainees === 'with' || isVIP) ? 500 : 0;
                const totalPrice = basePrice + traineesFee;
                
                const durationText = selectedDuration === '1' ? '1 Month' : 
                                   selectedDuration === '3' ? '3 Months' : 
                                   selectedDuration === '6' ? '6 Months' : '1 Year';
                const typeText = selectedType === 'regular' ? 'Regular' : 
                               selectedType === 'student' ? 'Student' : 
                               selectedType === 'vip' ? 'VIP' : 'Membership';
                
                priceAmountSpan.innerHTML = `${typeText} Membership - ${durationText}: <span class="text-success fw-bold">₱${basePrice.toLocaleString()}</span>`;
                
                // Show/hide trainees fee (always show for VIP)
                if (selectedTrainees === 'with' || isVIP) {
                    traineesFeeDiv.style.display = 'block';
                    totalPriceDiv.style.display = 'block';
                    totalAmountSpan.innerHTML = `₱${totalPrice.toLocaleString()}`;
                    const traineesFeeStrong = traineesFeeDiv.querySelector('strong');
                    if (traineesFeeStrong) {
                        if (isVIP) {
                            traineesFeeStrong.innerHTML = 'Trainees Fee (Included):';
                        } else {
                            traineesFeeStrong.innerHTML = 'Trainees Fee:';
                        }
                    }
                } else {
                    traineesFeeDiv.style.display = 'none';
                    totalPriceDiv.style.display = 'none';
                }
                
                selectedPriceDiv.style.display = 'block';
            } else {
                selectedPriceDiv.style.display = 'none';
            }
        }
        
        if (membershipTypeSelect && membershipDurationSelect && withTraineesSelect) {
            // Handle VIP membership changes and show/hide fees section
            membershipTypeSelect.addEventListener('change', function() {
                toggleMembershipFees();
                handleVIPMembership();
            });
            
            // Initialize on page load - check if there's already a selected type
            toggleMembershipFees();
            handleVIPMembership();
            
            membershipDurationSelect.addEventListener('change', updatePriceDisplay);
            withTraineesSelect.addEventListener('change', updatePriceDisplay);
            
            // Ensure VIP trainees value is submitted even if field is disabled
            const memberForm = document.getElementById('memberForm');
            if (memberForm) {
                memberForm.addEventListener('submit', function(e) {
                    const selectedType = membershipTypeSelect.value;
                    if (selectedType === 'vip') {
                        // Enable the field temporarily so it submits with the value
                        withTraineesSelect.disabled = false;
                        withTraineesSelect.value = 'with';
                    }
                });
            }
        }
        
        <?php if (isset($success) && isset($memberId)): ?>
        // Generate QR Code for the newly registered member
        new QRCode(document.getElementById("memberQrCode"), {
            text: "FIT_TRACK_MEMBER_ID:<?= htmlspecialchars($memberId) ?>",
            width: 180,
            height: 180,
            colorDark: "#000000ff",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        });
        
        // Download QR Code
        document.getElementById('downloadQrBtn').addEventListener('click', function() {
            const canvas = document.querySelector('#memberQrCode canvas');
            const dataURL = canvas.toDataURL('image/png');
            
            const link = document.createElement('a');
            link.download = 'FIT_TRACK_Membership_<?= htmlspecialchars($memberId) ?>.png';
            link.href = dataURL;
            link.click();
        });
        
        // Print QR Code
        document.getElementById('printQrBtn').addEventListener('click', function() {
            const printBtn = this;
            const originalText = printBtn.innerHTML;
            printBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Preparing...';
            printBtn.disabled = true;
            
            setTimeout(() => {
                printBtn.innerHTML = '<i class="fas fa-check"></i> Ready to Print';
                setTimeout(() => {
                    printBtn.innerHTML = originalText;
                    printBtn.disabled = false;
                }, 2000);
            }, 1500);
        });
        
        // Email QR Code (placeholder functionality)
        document.getElementById('emailQrBtn').addEventListener('click', function() {
            const emailBtn = this;
            const originalText = emailBtn.innerHTML;
            emailBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            emailBtn.disabled = true;
            
            setTimeout(() => {
                emailBtn.innerHTML = '<i class="fas fa-check"></i> Email Sent!';
                setTimeout(() => {
                    emailBtn.innerHTML = originalText;
                    emailBtn.disabled = false;
                }, 2000);
            }, 1500);
        });
        <?php endif; ?>
        
        // Staff employment type schedule toggle
        const employmentTypeSelect = document.getElementById('sEmploymentType');
        const scheduleContainer = document.getElementById('scheduleContainer');
        const scheduleSelect = document.getElementById('sSchedule');
        
        function toggleScheduleField() {
            const selectedType = employmentTypeSelect.value;
            if (selectedType === 'half day') {
                scheduleContainer.style.display = 'block';
                scheduleSelect.required = true;
            } else {
                scheduleContainer.style.display = 'none';
                scheduleSelect.required = false;
                scheduleSelect.value = '';
            }
        }
        
        if (employmentTypeSelect) {
            employmentTypeSelect.addEventListener('change', toggleScheduleField);
            // Initialize on page load
            toggleScheduleField();
        }
        
        <?php if (isset($success) && isset($staffId)): ?>
        // Generate QR Code for the newly registered staff
        new QRCode(document.getElementById("staffQrCode"), {
            text: "FIT_TRACK_STAFF_ID:<?= htmlspecialchars($staffId) ?>",
            width: 180,
            height: 180,
            colorDark: "#000000ff",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        });
        
        // Download Staff QR Code
        document.getElementById('downloadStaffQrBtn').addEventListener('click', function() {
            const canvas = document.querySelector('#staffQrCode canvas');
            const dataURL = canvas.toDataURL('image/png');
            
            const link = document.createElement('a');
            link.download = 'FIT_TRACK_Staff_ID_<?= htmlspecialchars($staffId) ?>.png';
            link.href = dataURL;
            link.click();
        });
        
        // Print Staff QR Code
        document.getElementById('printStaffQrBtn').addEventListener('click', function() {
            const printBtn = this;
            const originalText = printBtn.innerHTML;
            printBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Preparing...';
            printBtn.disabled = true;
            
            setTimeout(() => {
                printBtn.innerHTML = '<i class="fas fa-check"></i> Ready to Print';
                setTimeout(() => {
                    printBtn.innerHTML = originalText;
                    printBtn.disabled = false;
                }, 2000);
            }, 1500);
        });
        
        // Email Staff QR Code (placeholder functionality)
        document.getElementById('emailStaffQrBtn').addEventListener('click', function() {
            const emailBtn = this;
            const originalText = emailBtn.innerHTML;
            emailBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            emailBtn.disabled = true;
            
            setTimeout(() => {
                emailBtn.innerHTML = '<i class="fas fa-check"></i> Email Sent!';
                setTimeout(() => {
                    emailBtn.innerHTML = originalText;
                    emailBtn.disabled = false;
                }, 2000);
            }, 1500);
        });
        <?php endif; ?>
    });
</script>

<?php include 'components/footer.php'; ?>