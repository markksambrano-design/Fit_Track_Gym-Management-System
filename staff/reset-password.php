<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../includes/db.php';

// Redirect if already logged in
if (isset($_SESSION['staff_logged_in'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';
$token = $_GET['token'] ?? '';

if (empty($token)) {
    header('Location: login.php');
    exit;
}

// Verify token and check expiry
$stmt = $conn->prepare("SELECT id, email, first_name, last_name FROM staff WHERE reset_token = ? AND reset_token_expiry > NOW()");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();
$staff = $result->fetch_assoc();

if (!$staff) {
    $error = "Invalid or expired reset token. Please request a new password reset.";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($password)) {
        $error = "Password is required.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Hash new password and update database
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("UPDATE staff SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?");
        $stmt->bind_param("si", $hashedPassword, $staff['id']);
        
        if ($stmt->execute()) {
            $success = "Password has been reset successfully! You can now login with your new password.";
            
            // Log the password reset
            $logMessage = date('Y-m-d H:i:s') . " - Password reset completed for staff: " . $staff['email'] . " (ID: " . $staff['id'] . ")\n";
            file_put_contents('../logs/password_reset_emails.log', $logMessage, FILE_APPEND | LOCK_EX);
            
            // Clear token after successful reset
            $token = '';
        } else {
            $error = "Failed to reset password. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="FIT_TRACK Staff Reset Password" />
    <title>FIT_TRACK STAFF - Reset Password</title>

    <!-- Favicon -->
    <link rel="icon" href="../assets/img/FIT.png" type="image/png" />

    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Bebas+Neue&display=swap" rel="stylesheet" />

    <style>
        :root {
            --primary: #3B82F6;
            --secondary: #1E3A8A;
            --accent: #EF4444;
            --dark: #111827;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(rgba(15, 23, 42, 0.9), rgba(15, 23, 42, 0.9)), 
                        url('https://images.unsplash.com/photo-1571902943202-507ec2618e8f?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1075&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            min-height: 100vh;
        }
        .brand {
            font-family: 'Bebas Neue', cursive;
            letter-spacing: 2px;
        }
        .reset-card {
            backdrop-filter: blur(8px);
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s ease;
        }
        .reset-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        .input-field {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s ease;
        }
        .input-field:focus {
            background: rgba(255,255,255,0.1);
            box-shadow: 0 0 0 3px rgba(59,130,246,0.3);
        }
        .btn-reset {
            transition: all 0.3s ease;
            background-image: linear-gradient(to right, var(--primary), var(--secondary));
        }
        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(59, 130, 246, 0.4);
        }
        .password-toggle {
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">
    <div class="reset-card w-full max-w-md p-8 rounded-2xl shadow-2xl space-y-6 text-white">
        <!-- Logo Header -->
        <div class="text-center">
            <div class="flex justify-center mb-4">
                <div class="bg-gradient-to-r from-green-500 to-green-700 p-4 rounded-xl shadow-lg transform rotate-45 w-16 h-16 flex items-center justify-center">
                    <i class="fas fa-lock text-white text-2xl transform -rotate-45"></i>
                </div>
            </div>
            <h2 class="text-4xl font-bold brand">FIT_TRACK <span class="text-green-400">STAFF</span></h2>
            <p class="mt-2 text-green-200">RESET PASSWORD</p>
        </div>

        <?php if ($staff): ?>
            <!-- Staff Info -->
            <div class="text-center text-green-200 mb-4">
                <p>Hello, <strong><?= htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']) ?></strong></p>
                <p class="text-sm"><?= htmlspecialchars($staff['email']) ?></p>
            </div>
        <?php endif; ?>

        <!-- Success Message -->
        <?php if ($success): ?>
            <div class="bg-green-900/50 border-l-4 border-green-500 text-green-100 p-4 rounded-lg flex items-start animate-fade-in">
                <i class="fas fa-check-circle mt-1 mr-3"></i>
                <div>
                    <p class="font-medium">Password Reset Successful!</p>
                    <p class="text-sm opacity-90"><?= htmlspecialchars($success) ?></p>
                </div>
            </div>
            <div class="text-center">
                <a href="login.php" class="btn-reset inline-block text-white py-3 px-6 rounded-lg transition duration-300 font-semibold">
                    <i class="fas fa-sign-in-alt mr-2"></i>
                    Go to Login
                </a>
            </div>
        <?php else: ?>
            <!-- Error Message -->
            <?php if ($error): ?>
                <div class="bg-red-900/50 border-l-4 border-red-500 text-red-100 p-4 rounded-lg flex items-start animate-fade-in">
                    <i class="fas fa-exclamation-triangle mt-1 mr-3"></i>
                    <div>
                        <p class="font-medium">Error</p>
                        <p class="text-sm opacity-90"><?= htmlspecialchars($error) ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($staff): ?>
                <!-- Reset Password Form -->
                <form action="" method="POST" class="space-y-5">
                    <div>
                        <label for="password" class="block text-sm font-medium text-green-100 mb-2">NEW PASSWORD</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-key text-green-300"></i>
                            </div>
                            <input
                                type="password"
                                name="password"
                                id="password"
                                required
                                class="input-field w-full pl-10 pr-10 py-3 rounded-lg text-white placeholder-green-300 focus:outline-none focus:ring-0"
                                placeholder="••••••••"
                                autocomplete="new-password"
                                minlength="6"
                            />
                            <span class="password-toggle absolute text-green-300" onclick="togglePassword('password')">
                                <i class="fas fa-eye" id="toggleIcon1"></i>
                            </span>
                        </div>
                    </div>

                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-green-100 mb-2">CONFIRM PASSWORD</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-key text-green-300"></i>
                            </div>
                            <input
                                type="password"
                                name="confirm_password"
                                id="confirm_password"
                                required
                                class="input-field w-full pl-10 pr-10 py-3 rounded-lg text-white placeholder-green-300 focus:outline-none focus:ring-0"
                                placeholder="••••••••"
                                autocomplete="new-password"
                                minlength="6"
                            />
                            <span class="password-toggle absolute text-green-300" onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye" id="toggleIcon2"></i>
                            </span>
                        </div>
                    </div>

                    <button
                        type="submit"
                        class="w-full btn-reset text-white py-3 px-4 rounded-lg transition duration-300 flex items-center justify-center font-semibold shadow-lg group"
                    >
                        <span class="group-hover:animate-pulse mr-2">
                            <i class="fas fa-save"></i>
                        </span>
                        RESET PASSWORD
                    </button>
                </form>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Back to Login -->
        <div class="text-center">
            <a href="login.php" class="text-green-300 hover:text-green-200 font-medium">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to Login
            </a>
        </div>

        <!-- Footer -->
        <div class="text-center text-xs text-green-300/70 mt-6">
            <div class="flex items-center justify-center space-x-2">
                <i class="fas fa-lock"></i>
                <span>Secure Staff Portal • v2.0</span>
            </div>
            <div class="mt-1">&copy; <?= date('Y'); ?> FIT_TRACK SYSTEM</div>
        </div>
    </div>

    <!-- Watermark -->
    <div class="fixed bottom-4 right-4 text-xs text-green-400/30">
        <?= htmlspecialchars($_SERVER['SERVER_NAME']) ?> • <?= date('H:i') ?>
    </div>

    <script>
        // Toggle password visibility
        function togglePassword(fieldId) {
            const passwordInput = document.getElementById(fieldId);
            const toggleIcon = document.getElementById(fieldId === 'password' ? 'toggleIcon1' : 'toggleIcon2');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Add animation class to messages
        document.addEventListener('DOMContentLoaded', function() {
            const messages = document.querySelectorAll('.bg-green-900/50, .bg-red-900/50');
            messages.forEach(function(message) {
                message.classList.add('animate-fade-in');
            });
        });
    </script>
</body>
</html>
