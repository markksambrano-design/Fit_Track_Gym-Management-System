<?php
// Set timezone to Philippines for correct time display
date_default_timezone_set('Asia/Manila');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is already logged in and redirect accordingly
if (isset($_SESSION['member_logged_in'])) {
    header('Location: member/dashboard.php');
    exit;
} elseif (isset($_SESSION['staff_logged_in'])) {
    header('Location: staff/dashboard.php');
    exit;
} elseif (isset($_SESSION['admin_logged_in'])) {
    header('Location: admin/index.php');
    exit;
}

// Handle any error messages
$error = $_SESSION['error'] ?? '';
unset($_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RVG Power Build · unified portal</title>
    
    <!-- Favicon -->
    <link rel="icon" href="assets/img/FIT.png" type="image/png" />
    
    <!-- Font Awesome 6 (free icons) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Google Fonts: Inter + Bebas Neue (clean, modern) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300..700&family=Bebas+Neue&display=swap" rel="stylesheet">
    
    <!-- your existing local CSS files (optimized, loading, style) – kept for compatibility -->
    <link rel="stylesheet" href="assets/css/optimized.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        /* ----- design system (refined, modern, dark/glass with background image) ----- */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            /* Background image from your original code - Unsplash gym image */
            background: linear-gradient(rgba(6, 14, 24, 0.75), rgba(6, 14, 24, 0.85)), 
                        url('https://images.unsplash.com/photo-1571902943202-507ec2618e8f?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1075&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        
        .glass-master {
            width: 100%;
            max-width: 1300px;
            background: rgba(10, 18, 28, 0.65);
            backdrop-filter: blur(16px) saturate(200%);
            -webkit-backdrop-filter: blur(16px) saturate(200%);
            border-radius: 3.5rem;
            padding: 2.8rem 2.5rem;
            border: 1px solid rgba(72, 120, 200, 0.2);
            box-shadow: 0 40px 70px -15px #000000cc, inset 0 0 0 1px rgba(255,255,255,0.02);
        }
        
        /* header area */
        .top-brand {
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            justify-content: space-between;
            margin-bottom: 2.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(110, 150, 230, 0.25);
        }
        
        .logo-gym h1 {
            font-family: 'Bebas Neue', cursive;
            font-size: 3rem;
            font-weight: 400;
            letter-spacing: 3px;
            background: linear-gradient(130deg, #f0f5ff, #adc6ff);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            line-height: 1.1;
        }
        
        .logo-gym span {
            font-size: 0.9rem;
            font-weight: 400;
            color: #7f92b0;
            margin-left: 12px;
            border: 1px solid rgba(255,255,255,0.1);
            padding: 0.2rem 1rem;
            border-radius: 30px;
            background: #0d1622cc;
        }
        
        .access-pill {
            background: rgba(20, 35, 58, 0.7);
            border-radius: 60px;
            padding: 0.5rem 1.8rem;
            font-size: 0.95rem;
            font-weight: 500;
            color: #b7cdff;
            border: 1px solid rgba(255,255,255,0.05);
            backdrop-filter: blur(4px);
        }
        
        .access-pill i {
            margin-right: 8px;
            color: #4e8cff;
        }
        
        /* error styling */
        .error-modern {
            background: rgba(200, 60, 60, 0.2);
            border-left: 4px solid #ff5a5a;
            border-radius: 18px;
            padding: 1.2rem 1.8rem;
            margin-bottom: 2rem;
            backdrop-filter: blur(10px);
            color: #ffc9c9;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 400;
            animation: slideError 0.3s ease;
            border: 1px solid rgba(255, 100, 100, 0.2);
        }
        
        @keyframes slideError { 
            0% { opacity:0; transform:translateY(-10px); } 
            100% { opacity:1; transform:translateY(0); } 
        }
        
        /* role cards grid */
        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 2rem;
            margin: 2.2rem 0 1rem;
        }
        
        .role-elevated {
            background: rgba(12, 22, 34, 0.7);
            backdrop-filter: blur(8px);
            border-radius: 2.5rem;
            padding: 2.2rem 1.8rem 2rem 1.8rem;
            border: 1px solid rgba(255, 255, 255, 0.03);
            transition: all 0.25s ease;
            box-shadow: 0 25px 35px -15px #000000;
            text-decoration: none;
            color: white;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
        }
        
        .role-elevated:hover {
            transform: translateY(-8px);
            border-color: rgba(255,255,255,0.1);
            background: rgba(20, 34, 50, 0.8);
            box-shadow: 0 30px 45px -15px #000000f0;
        }
        
        /* icon disk */
        .icon-spot {
            width: 74px;
            height: 74px;
            border-radius: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.4rem;
            margin-bottom: 2rem;
            background: rgba(0,0,0,0.4);
            border: 1px solid rgba(255,255,255,0.08);
            box-shadow: 0 10px 15px -5px black;
        }
        
        /* role‑specific icon tints */
        .member-card .icon-spot { color: #ffb347; background: linear-gradient(145deg, #2b2116, #191207); }
        .staff-card .icon-spot { color: #4fd1a0; background: linear-gradient(145deg, #1b352b, #0f201a); }
        .admin-card .icon-spot { color: #c58aff; background: linear-gradient(145deg, #2a1e38, #171127); }
        
        .role-elevated h2 {
            font-size: 2rem;
            font-weight: 600;
            letter-spacing: -0.5px;
            margin-bottom: 0.3rem;
        }
        
        .role-desc {
            color: #9dafcf;
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 2rem;
            border-left: 2px solid rgba(255,255,255,0.1);
            padding-left: 1rem;
            flex: 1;
        }
        
        /* modern login button (glass, arrow animation) */
        .login-trigger {
            display: inline-flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.9rem 1.8rem;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 60px;
            color: white;
            font-weight: 550;
            text-decoration: none;
            transition: all 0.15s;
            margin-top: auto;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 12px #0000004d;
            width: 100%;
            cursor: pointer;
        }
        
        .login-trigger i {
            margin-left: 12px;
            font-size: 0.95rem;
            transition: transform 0.2s;
            color: #c0d2f0;
        }
        
        .login-trigger:hover {
            background: rgba(45, 100, 200, 0.2);
            border-color: rgba(255, 255, 255, 0.1);
        }
        
        .login-trigger:hover i {
            transform: translateX(6px);
            color: white;
        }
        
        .member-card .login-trigger:hover { background: rgba(245, 158, 11, 0.18); border-color: #f6b83e; }
        .staff-card .login-trigger:hover  { background: rgba(16, 185, 129, 0.18); border-color: #2ccb9a; }
        .admin-card .login-trigger:hover  { background: rgba(139, 92, 246, 0.18); border-color: #b28aff; }
        
        /* subtle footer */
        .footer-note {
            margin-top: 2.5rem;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 12px;
            color: #4e6385;
            font-size: 0.8rem;
            border-top: 1px solid rgba(255,255,255,0.05);
            padding-top: 1.5rem;
        }
        
        .footer-note i { color: #3a5f8f; }
        .flex-center { display: flex; align-items: center; }
        .gap-2 { gap: 0.5rem; }
        .text-white { color: #fff; }
        .mb-1 { margin-bottom: 0.25rem; }
        .mt-2 { margin-top: 0.5rem; }
        .btn-ripple { position: relative; overflow: hidden; }
        
        .ripple-effect {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.5);
            transform: scale(0);
            animation: rippleAnim 0.5s linear;
            pointer-events: none;
        }
        
        @keyframes rippleAnim { 
            to { transform: scale(4); opacity: 0; } 
        }

        /* mobile */
        @media (max-width: 700px) {
            .glass-master { padding: 1.8rem; }
            .top-brand { flex-direction: column; align-items: flex-start; gap: 1rem; }
        }
        
        /* loading overlay removed for speed */
    </style>
</head>
<body>
    <div class="glass-master">
        <!-- header with refined layout -->
        <div class="top-brand">
            <div class="logo-gym flex-center gap-2">
                <h1>RVG POWER BUILD</h1>
                <span>GYM</span>
            </div>
            <div class="access-pill">
                <i class="fas fa-key"></i> choose access level
            </div>
        </div>

        <!-- error alert (if any) -->
        <?php if ($error): ?>
            <div class="error-modern">
                <i class="fas fa-shield-exclamation" style="font-size: 1.6rem; opacity:0.8;"></i>
                <div>
                    <strong style="font-weight:600;">Authentication</strong>
                    <div style="font-size:0.9rem; margin-top:4px;"><?= htmlspecialchars($error) ?></div>
                </div>
            </div>
        <?php endif; ?>

        <!-- role cards (improved icons, clearer UI) -->
        <div class="card-grid">
            <!-- MEMBER CARD -->
            <a href="member/login.php" class="role-elevated member-card">
                <div class="icon-spot">
                    <i class="fas fa-user-check"></i>  <!-- more specific than generic user -->
                </div>
                <h2>MEMBER</h2>
                <div class="role-desc">
                    <i class="fas fa-circle" style="font-size:0.4rem; color:#ffb347; vertical-align:middle; margin-right:8px;"></i>
                    personal dashboard, workouts & progress
                </div>
                <div class="login-trigger btn-ripple">
                    <span>MEMBER LOGIN</span>
                    <i class="fas fa-arrow-right"></i>
                </div>
                <div style="margin-top: 0.8rem; font-size:0.7rem; color:#3f5480;">
                    <i class="far fa-id-card"></i> secured member area
                </div>
            </a>

            <!-- STAFF CARD -->
            <a href="staff/login.php" class="role-elevated staff-card">
                <div class="icon-spot">
                    <i class="fas fa-clipboard-list"></i>  <!-- staff operations -->
                </div>
                <h2>STAFF</h2>
                <div class="role-desc">
                    <i class="fas fa-circle" style="font-size:0.4rem; color:#4fd1a0; vertical-align:middle; margin-right:8px;"></i>
                    schedules, member management & ops
                </div>
                <div class="login-trigger btn-ripple">
                    <span>STAFF LOGIN</span>
                    <i class="fas fa-arrow-right"></i>
                </div>
                <div style="margin-top: 0.8rem; font-size:0.7rem; color:#3f5480;">
                    <i class="fas fa-id-badge"></i> employee portal
                </div>
            </a>

            <!-- ADMIN CARD -->
            <a href="admin/index.php?page=login" class="role-elevated admin-card">
                <div class="icon-spot">
                    <i class="fas fa-crown"></i>  <!-- admin / control -->
                </div>
                <h2>ADMIN</h2>
                <div class="role-desc">
                    <i class="fas fa-circle" style="font-size:0.4rem; color:#c58aff; vertical-align:middle; margin-right:8px;"></i>
                    full system config & analytics
                </div>
                <div class="login-trigger btn-ripple">
                    <span>ADMIN LOGIN</span>
                    <i class="fas fa-arrow-right"></i>
                </div>
                <div style="margin-top: 0.8rem; font-size:0.7rem; color:#3f5480;">
                    <i class="fas fa-lock"></i> superuser environment
                </div>
            </a>
        </div>

        <!-- footer with date & system credit -->
        <div class="footer-note">
            <i class="fas fa-bolt"></i>
            <span>RVG · FIT_TRACK SYSTEMS</span>
            <span style="opacity:0.5;">|</span>
            <span><i class="far fa-calendar-alt"></i> <?= date('Y'); ?></span>
        </div>
    </div>

    <!-- minimal javascript: only ripple + instant navigation (no overlay delay) -->
    <script>
        (function() {
            // ripple effect for login triggers (just visual)
            function createRipple(event, btn) {
                const rect = btn.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const ripple = document.createElement('span');
                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = (event.clientX - rect.left - size/2) + 'px';
                ripple.style.top = (event.clientY - rect.top - size/2) + 'px';
                ripple.classList.add('ripple-effect');
                btn.appendChild(ripple);
                setTimeout(() => ripple.remove(), 500);
            }

            // attach ripple to all .btn-ripple (the login spans)
            document.querySelectorAll('.login-trigger').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();  // prevent link inside a link? we let card handle navigation.
                    e.stopPropagation(); // but we still want ripple
                    createRipple(e, btn);
                    // after small visual feedback, navigate via parent anchor
                    const card = btn.closest('.role-elevated');
                    if (card && card.href) {
                        // tiny delay so ripple is visible (optional, but fast)
                        setTimeout(() => {
                            window.location.href = card.href;
                        }, 60);
                    }
                });
            });

            // also allow card click (but we want consistent behaviour)
            // we will disable card's native click if login-trigger is clicked, but overall navigation ok.
            const cards = document.querySelectorAll('.role-elevated');
            cards.forEach(card => {
                card.addEventListener('click', (e) => {
                    // if the click originated from .login-trigger, it's already handled.
                    if (e.target.closest('.login-trigger')) return;
                    // otherwise navigate directly
                    window.location.href = card.href;
                });
            });

            // also keep original session redirect logic (already php)
            // no loading overlay – faster UX
        })();
    </script>
    <!-- include your legacy JS files if needed (optimized.js etc) but they're not required for this design -->
    <script src="assets/js/optimized.js"></script>
    <script src="assets/js/loading.js"></script>
    <!-- note: loading overlay is removed; we use instant navigation -->
</body>
</html>