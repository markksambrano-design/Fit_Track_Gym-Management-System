<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
 // MySQLi connection in $conn
include '../includes/db.php';
// Redirect if already logged in
if (isset($_SESSION['member_logged_in'])) {
    header('Location: dashboard.php');
    exit;
}

// Initialize variables
$error = '';
$email = '';
$login_attempts = $_SESSION['login_attempts'] ?? 0;
$last_attempt = $_SESSION['last_attempt'] ?? 0;
$lockout_time = 300; // 5 minutes in seconds

// Check if account is temporarily locked
if ($login_attempts >= 5 && (time() - $last_attempt) < $lockout_time) {
    $remaining_time = $lockout_time - (time() - $last_attempt);
    $error = "Too many failed attempts. Please try again in " . ceil($remaining_time / 60) . " minutes.";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process login form
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    // Validate inputs
    if (empty($email)) {
        $error = "Email is required.";
    } elseif (empty($password)) {
        $error = "Password is required.";
    } else {
        // Prepare and execute query to get member by email
        $stmt = $conn->prepare("SELECT * FROM members WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $member = $result->fetch_assoc();

        if ($member) {
            if (password_verify($password, $member['password'])) {
                // Reset login attempts on successful login
                unset($_SESSION['login_attempts']);
                unset($_SESSION['last_attempt']);

                // Set session variables
                $_SESSION['member_logged_in'] = true;
                $_SESSION['member_id'] = $member['id'];
                $_SESSION['member_name'] = $member['first_name'] . ' ' . $member['last_name'];
                $_SESSION['member_email'] = $member['email'];
                $_SESSION['member_data'] = $member;

                // Set remember me cookie if checked
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $expiry = time() + 60 * 60 * 24 * 30; // 30 days
                    
                    // Store token in database
                    $stmt = $conn->prepare("UPDATE members SET remember_token = ?, token_expiry = ? WHERE id = ?");
                    $expiry_date = date('Y-m-d H:i:s', $expiry);
                    $stmt->bind_param("ssi", $token, $expiry_date, $member['id']);
                    $stmt->execute();
                    
                    // Set cookie
                    setcookie('remember_token', $token, $expiry, '/', '', true, true);
                }

                // Update last login
                $stmt = $conn->prepare("UPDATE members SET last_login = NOW() WHERE id = ?");
                $stmt->bind_param("i", $member['id']);
                $stmt->execute();

                // Redirect to dashboard
                header('Location: dashboard.php');
                exit;
            } else {
                // Increment failed login attempts
                $_SESSION['login_attempts'] = $login_attempts + 1;
                $_SESSION['last_attempt'] = time();
                
                $error = "Invalid email or password.";
                $remaining_attempts = 5 - ($login_attempts + 1);
                if ($remaining_attempts > 0) {
                    $error .= " You have $remaining_attempts attempts remaining.";
                } else {
                    $error .= " Account temporarily locked. Please try again later.";
                }
            }
        } else {
            // Don't reveal if email exists for security
            $error = "Invalid email or password.";
            $_SESSION['login_attempts'] = $login_attempts + 1;
            $_SESSION['last_attempt'] = time();
        }
    }
}

// Check for remember me cookie
if (empty($error) && !isset($_SESSION['member_logged_in']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    
    $stmt = $conn->prepare("SELECT * FROM members WHERE remember_token = ? AND token_expiry > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $member = $result->fetch_assoc();
    
    if ($member) {
        $_SESSION['member_logged_in'] = true;
        $_SESSION['member_id'] = $member['id'];
        $_SESSION['member_name'] = $member['first_name'] . ' ' . $member['last_name'];
        $_SESSION['member_email'] = $member['email'];
        $_SESSION['member_data'] = $member;
        
        header('Location: dashboard.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="RVG Power Build Member Login Portal" />
    <title>RVG Power Build MEMBER - Login</title>

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
            min-height: 100vh;
        }
        
        .brand {
            font-family: 'Bebas Neue', sans-serif;
            letter-spacing: 3px;
            background: linear-gradient(45deg, #fff, #E0E7FF);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 0 30px rgba(255, 255, 255, 0.5);
        }
        
        .login-card {
            backdrop-filter: blur(20px);
            background: linear-gradient(135deg, 
                rgba(255, 255, 255, 0.15) 0%, 
                rgba(255, 255, 255, 0.05) 100%);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.25),
                0 0 0 1px rgba(255, 255, 255, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
        }
        
        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            animation: shimmer 3s infinite;
        }
        
        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }
        
        .gym-stats-card {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            box-shadow: 0 10px 30px rgba(59, 130, 246, 0.3);
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: rotate(45deg) translateY(0px); }
            50% { transform: rotate(45deg) translateY(-10px); }
        }
        
        .input-field {
            background: rgba(75, 85, 99, 0.9);
            border: 1px solid rgba(55, 65, 81, 0.8);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            color: #f9fafb;
        }
        
        .input-field:focus {
            background: rgba(75, 85, 99, 0.9);
            border-color: var(--primary);
            box-shadow: 
                0 0 0 4px rgba(59, 130, 246, 0.2),
                0 0 20px rgba(59, 130, 246, 0.1);
            transform: translateY(-2px);
        }
        
        .input-field:hover {
            background: rgba(75, 85, 99, 0.95);
            border-color: rgba(55, 65, 81, 0.9);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-primary:hover::before {
            left: 100%;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(59, 130, 246, 0.4);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .error-card {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.1));
            border: 1px solid rgba(239, 68, 68, 0.3);
            backdrop-filter: blur(10px);
            animation: slideIn 0.5s ease-out;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .checkbox-custom {
            appearance: none;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 4px;
            background: rgba(255, 255, 255, 0.05);
            position: relative;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .checkbox-custom:checked {
            background: var(--primary);
            border-color: var(--primary);
        }
        
        .checkbox-custom:checked::after {
            content: '✓';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-weight: bold;
            font-size: 12px;
        }
        
        .link-hover {
            position: relative;
            transition: all 0.3s ease;
        }
        
        .link-hover::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--primary);
            transition: width 0.3s ease;
        }
        
        .link-hover:hover::after {
            width: 100%;
        }
        
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">
    <div class="login-card w-full max-w-md p-8 rounded-2xl shadow-2xl space-y-6">
        <!-- Logo Header -->
        <div class="text-center">
            <div class="flex justify-center mb-4">
                <div class="gym-stats-card p-4 rounded-xl shadow-lg transform rotate-45 w-16 h-16 flex items-center justify-center">
                    <i class="fas fa-dumbbell text-white text-2xl transform -rotate-45"></i>
                </div>
            </div>
            <h2 class="text-4xl font-bold brand text-white">RVG POWER BUILD</h2>
            <p class="mt-2 gym-subtitle text-white">MEMBER PORTAL</p>
        </div>

        <!-- Error Message -->
        <?php if ($error): ?>
            <div class="error-card p-4 rounded-lg flex items-start">
                <i class="fas fa-exclamation-triangle mt-1 mr-3 text-red-400"></i>
                <div>
                    <p class="font-medium text-red-200">Authentication Error</p>
                    <p class="text-sm text-red-300 opacity-90"><?= htmlspecialchars($error) ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form action="" method="POST" class="space-y-5">
            <div>
                <label for="email" class="block text-sm font-medium text-blue-100 mb-2">EMAIL</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-envelope text-blue-300"></i>
                    </div>
                    <input 
                        type="email" 
                        name="email" 
                        id="email"
                        required 
                        class="input-field w-full pl-10 pr-4 py-3 rounded-lg text-white placeholder-blue-300 focus:outline-none focus:ring-0"
                        placeholder="member@example.com"
                        value="<?= htmlspecialchars($email) ?>"
                        autocomplete="email"
                        <?= ($login_attempts >= 5 && (time() - $last_attempt) < $lockout_time) ? 'disabled' : '' ?>
                    >
                </div>
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-blue-100 mb-2">PASSWORD</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-key text-blue-300"></i>
                    </div>
                    <input 
                        type="password" 
                        name="password" 
                        id="password"
                        required 
                        class="input-field w-full pl-10 pr-10 py-3 rounded-lg text-white placeholder-blue-300 focus:outline-none focus:ring-0"
                        placeholder="••••••••"
                        autocomplete="current-password"
                        <?= ($login_attempts >= 5 && (time() - $last_attempt) < $lockout_time) ? 'disabled' : '' ?>
                    >
                    <button 
                        type="button"
                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-blue-300 hover:text-white transition-colors"
                        onclick="togglePassword()"
                    >
                        <i class="fas fa-eye" id="toggleIcon"></i>
                    </button>
                </div>
            </div>

            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <input 
                        type="checkbox" 
                        id="remember"
                        name="remember" 
                        class="checkbox-custom"
                    >
                    <label for="remember" class="ml-2 block text-sm text-blue-200 cursor-pointer">Remember me</label>
                </div>
                <a href="forgot-password.php" class="text-sm text-blue-300 hover:text-blue-100 font-medium link-hover">Forgot password?</a>
            </div>

            <button 
                type="submit" 
                class="btn-primary w-full text-white py-4 px-6 rounded-lg flex items-center justify-center font-semibold shadow-lg group"
                <?= ($login_attempts >= 5 && (time() - $last_attempt) < $lockout_time) ? 'disabled' : '' ?>
            >
                <span class="group-hover:animate-pulse mr-2">
                    <i class="fas fa-sign-in-alt"></i>
                </span> 
                LOGIN
            </button>
        </form>

        <!-- Back to Portal Button -->
        <div class="text-center mt-6">
            <a href="../portal.php" class="inline-flex items-center px-4 py-2 text-sm font-medium text-blue-300 hover:text-white bg-transparent border border-blue-300 hover:border-blue-200 rounded-lg transition-all duration-300 hover:bg-blue-500/10">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to Portal
            </a>
        </div>

        <!-- Footer -->
        <div class="text-center text-xs text-blue-300/70 mt-6">
            <div class="flex items-center justify-center space-x-2">
                <i class="fas fa-lock"></i>
                <span>Secure Member Portal • v2.0</span>
            </div>
            <div class="mt-1">&copy; <?= date('Y'); ?> FIT_TRACK SYSTEMS</div>
        </div>
    </div>

    <!-- Watermark -->
    <div class="fixed bottom-4 right-4 text-xs text-blue-400/30">
        <?= $_SERVER['SERVER_NAME'] ?> • <?= date('H:i') ?>
    </div>

    <!-- Enhanced JavaScript for interactivity -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Form submission with loading state
            const form = document.querySelector('form');
            const submitBtn = document.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            
            form.addEventListener('submit', function(e) {
                // Add loading state
                submitBtn.innerHTML = '<span class="loading mr-2"></span>Authenticating...';
                submitBtn.disabled = true;
                
                // Re-enable button after 3 seconds (in case of slow response)
                setTimeout(() => {
                    submitBtn.innerHTML = originalBtnText;
                    submitBtn.disabled = false;
                }, 3000);
            });
            
            // Input focus animations
            const inputs = document.querySelectorAll('.input-field');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('transform', 'scale-105');
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.classList.remove('transform', 'scale-105');
                });
            });
        });
        
        // Toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
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
        
        // Add ripple effect to button
        document.addEventListener('DOMContentLoaded', function() {
            const submitBtn = document.querySelector('button[type="submit"]');
            submitBtn.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.cssText = `
                    position: absolute;
                    width: ${size}px;
                    height: ${size}px;
                    left: ${x}px;
                    top: ${y}px;
                    background: rgba(255, 255, 255, 0.3);
                    border-radius: 50%;
                    transform: scale(0);
                    animation: ripple 0.6s linear;
                    pointer-events: none;
                `;
                
                this.appendChild(ripple);
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
            
            // Add CSS for ripple animation
            const style = document.createElement('style');
            style.textContent = `
                @keyframes ripple {
                    to {
                        transform: scale(4);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);
        });
    </script>
</body>
</html>
 
