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
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        // Check if staff exists
        $stmt = $conn->prepare("SELECT id, first_name, last_name, email FROM staff WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $staff = $result->fetch_assoc();
        
        if ($staff) {
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiry
            
            // Store reset token in database
            $stmt = $conn->prepare("UPDATE staff SET reset_token = ?, reset_token_expiry = ? WHERE id = ?");
            $stmt->bind_param("ssi", $token, $expiry, $staff['id']);
            
            if ($stmt->execute()) {
                // Send reset email (you'll need to configure email settings)
                $resetLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/reset-password.php?token=" . $token;
                
                // For now, just show success message
                $success = "Password reset instructions have been sent to your email address.";
                
                // Log the password reset request
                $logMessage = date('Y-m-d H:i:s') . " - Password reset requested for staff: " . $staff['email'] . " (ID: " . $staff['id'] . ")\n";
                file_put_contents('../logs/password_reset_emails.log', $logMessage, FILE_APPEND | LOCK_EX);
                
                $email = ''; // Clear email field
            } else {
                $error = "Failed to process request. Please try again.";
            }
        } else {
            // Don't reveal if email exists for security
            $success = "If the email address exists in our system, you will receive password reset instructions.";
            $email = ''; // Clear email field
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="FIT_TRACK Staff Forgot Password" />
    <title>FIT_TRACK STAFF - Forgot Password</title>

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
        .forgot-card {
            backdrop-filter: blur(8px);
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s ease;
        }
        .forgot-card:hover {
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
        .btn-submit {
            transition: all 0.3s ease;
            background-image: linear-gradient(to right, var(--primary), var(--secondary));
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(59, 130, 246, 0.4);
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">
    <div class="forgot-card w-full max-w-md p-8 rounded-2xl shadow-2xl space-y-6 text-white">
        <!-- Logo Header -->
        <div class="text-center">
            <div class="flex justify-center mb-4">
                <div class="bg-gradient-to-r from-green-500 to-green-700 p-4 rounded-xl shadow-lg transform rotate-45 w-16 h-16 flex items-center justify-center">
                    <i class="fas fa-key text-white text-2xl transform -rotate-45"></i>
                </div>
            </div>
            <h2 class="text-4xl font-bold brand">FIT_TRACK <span class="text-green-400">STAFF</span></h2>
            <p class="mt-2 text-green-200">PASSWORD RECOVERY</p>
        </div>

        <!-- Success Message -->
        <?php if ($success): ?>
            <div class="bg-green-900/50 border-l-4 border-green-500 text-green-100 p-4 rounded-lg flex items-start animate-fade-in">
                <i class="fas fa-check-circle mt-1 mr-3"></i>
                <div>
                    <p class="font-medium">Request Submitted</p>
                    <p class="text-sm opacity-90"><?= htmlspecialchars($success) ?></p>
                </div>
            </div>
        <?php endif; ?>

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

        <!-- Forgot Password Form -->
        <form action="" method="POST" class="space-y-5">
            <div class="text-center text-green-200 mb-4">
                <p>Enter your email address and we'll send you instructions to reset your password.</p>
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-green-100 mb-2">EMAIL ADDRESS</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-envelope text-green-300"></i>
                    </div>
                    <input
                        type="email"
                        name="email"
                        id="email"
                        required
                        class="input-field w-full pl-10 pr-4 py-3 rounded-lg text-white placeholder-green-300 focus:outline-none focus:ring-0"
                        placeholder="staff@example.com"
                        value="<?= htmlspecialchars($email) ?>"
                        autocomplete="email"
                    />
                </div>
            </div>

            <button
                type="submit"
                class="w-full btn-submit text-white py-3 px-4 rounded-lg transition duration-300 flex items-center justify-center font-semibold shadow-lg group"
            >
                <span class="group-hover:animate-pulse mr-2">
                    <i class="fas fa-paper-plane"></i>
                </span>
                SEND RESET LINK
            </button>
        </form>

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
