<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Authentication check for member pages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug session info
if (isset($_GET['debug'])) {
    echo "<pre>Session Debug: ";
    print_r($_SESSION);
    echo "</pre>";
}

if (!isset($_SESSION['member_logged_in']) || !$_SESSION['member_logged_in']) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>RVG Power Build Member - <?php echo $page_title ?? 'Dashboard'; ?></title>

  <!-- Favicon -->
  <link rel="icon" href="../assets/img/FIT.png" type="image/png" />

  <!-- Bootstrap + Font Awesome -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Bebas+Neue&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />

  <!-- Custom CSS paths -->
  <link href="../assets/css/member.css" rel="stylesheet" />
  <link href="../assets/css/member/sidebar.css" rel="stylesheet" />
  <link href="../assets/css/style.css" rel="stylesheet" />

  <style>
    :root {
      --sidebar-width: 280px;
      --sidebar-transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1.2);
    }

    body {
      margin: 0;
      margin-left: var(--sidebar-width);
      font-family: 'Poppins', sans-serif;
      background: linear-gradient(rgba(15, 23, 42, 0.95), rgba(15, 23, 42, 0.95)), 
                  url('https://images.unsplash.com/photo-1571902943202-507ec2618e8f?ixlib=rb-4.0.3&auto=format&fit=crop&w=1075&q=80');
      background-size: cover;
      background-position: center;
      background-attachment: fixed;
      color: #f8f9fc;
      transition: var(--sidebar-transition);
    }

    body.sidebar-hidden {
      margin-left: 0;
    }

    .wrapper {
      display: flex;
      min-height: 100vh;
    }

    .main {
      flex-grow: 1;
      min-height: 100vh;
      background-color: rgba(30, 41, 59, 0);
      transition: var(--sidebar-transition);
      width: calc(100% - var(--sidebar-width));
    }

    body.sidebar-hidden .main {
      width: 100%;
    }

    /* Dashboard container adjustments */
    .dashboard-container {
      transition: var(--sidebar-transition);
    }

    body.sidebar-hidden .dashboard-container {
      max-width: 100%;
    }

    /* Content grid adjustments */
    .content-grid {
      transition: var(--sidebar-transition);
    }

    /* Stats grid adjustments */
    .stats-grid {
      transition: var(--sidebar-transition);
    }

    /* Welcome section adjustments */
    .welcome-section {
      transition: var(--sidebar-transition);
    }

    @media (max-width: 992px) {
      body {
        margin-left: 0;
      }
      
      .main {
        width: 100%;
      }
      
      body.sidebar-hidden .main {
        width: 100%;
      }
    }

    .topbar {
      background-color: rgba(15, 23, 42, 0.8);
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
      padding: 10px 20px;
      color: white;
    }

    .topbar h1 {
      font-weight: 600;
      color: #f8f9fc;
      font-family: 'Bebas Neue', sans-serif;
      letter-spacing: 1px;
    }

    .navbar-nav .nav-link {
      color: rgba(255, 255, 255, 0.8);
    }

    .dropdown-menu {
      font-size: 0.9rem;
      background-color: rgba(15, 23, 42, 0.95);
      border: 1px solid rgba(255, 255, 255, 0.1);
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
    }

    .dropdown-item {
      color: rgba(255, 255, 255, 0.8);
      padding: 8px 20px;
      transition: all 0.3s ease;
    }

    .dropdown-item:hover {
      background-color: rgba(59, 130, 246, 0.2);
      color: white;
      transform: translateX(5px);
    }

    .dropdown-divider {
      border-color: rgba(255, 255, 255, 0.1);
      margin: 5px 0;
    }
    
    /* Ensure dropdown is visible */
    .dropdown-menu.show {
      display: block !important;
      opacity: 1 !important;
      visibility: visible !important;
    }
    
    /* Dropdown animation */
    .dropdown-menu {
      transform: translateY(-10px);
      opacity: 0;
      visibility: hidden;
      transition: all 0.3s ease;
    }
    
    .dropdown-menu.show {
      transform: translateY(0);
      opacity: 1;
      visibility: visible;
    }
    
    /* Mobile dropdown improvements */
    @media (max-width: 768px) {
      .dropdown-menu {
        position: fixed !important;
        top: 60px !important;
        right: 10px !important;
        left: 10px !important;
        width: auto !important;
        max-width: 300px;
        margin: 0 auto;
      }
    }

    /* Sidebar styles moved to external CSS file */

    /* Announcements Styling */
    .announcement-item {
      background-color: rgba(255, 255, 255, 0.05);
      border-radius: 8px;
      transition: all 0.3s ease;
    }

    .announcement-item:hover {
      background-color: rgba(255, 255, 255, 0.1);
      transform: translateX(5px);
    }

    .announcement-item .border-danger {
      border-left-color: #dc3545 !important;
    }

    .announcement-item .border-warning {
      border-left-color: #ffc107 !important;
    }

    .announcement-item .border-info {
      border-left-color: #0dcaf0 !important;
    }

    .card {
      background-color: rgba(30, 41, 59, 0.7);
      border: 1px solid rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(5px);
      color: white;
    }
  </style>

  <!-- JS -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  
  <script>
    // Initialize Bootstrap dropdowns
    document.addEventListener('DOMContentLoaded', function() {
      // Initialize all dropdowns
      var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
      var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
        return new bootstrap.Dropdown(dropdownToggleEl);
      });
      
      // Fallback dropdown functionality
      document.addEventListener('click', function(e) {
        var dropdownToggle = e.target.closest('.dropdown-toggle');
        var dropdownMenu = e.target.closest('.dropdown-menu');
        
        if (dropdownToggle) {
          e.preventDefault();
          e.stopPropagation();
          
          // Close all other dropdowns
          document.querySelectorAll('.dropdown-menu.show').forEach(function(menu) {
            if (menu !== dropdownToggle.nextElementSibling) {
              menu.classList.remove('show');
            }
          });
          
          // Toggle current dropdown
          var menu = dropdownToggle.nextElementSibling;
          menu.classList.toggle('show');
        } else if (!dropdownMenu) {
          // Close all dropdowns when clicking outside
          document.querySelectorAll('.dropdown-menu.show').forEach(function(menu) {
            menu.classList.remove('show');
          });
        }
      });
      
      // Close dropdowns on escape key
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
          document.querySelectorAll('.dropdown-menu.show').forEach(function(menu) {
            menu.classList.remove('show');
          });
        }
      });
    });
  </script>
</head>

<body>
  <div class="wrapper">
    <?php include 'sidebar.php'; ?>

    <div class="main">
      <!-- Topbar -->
      <nav class="navbar navbar-expand navbar-light topbar mb-4 shadow">
        <div class="container-fluid">
          <h1 class="h3 mb-0"><?php echo $page_title ?? 'Dashboard'; ?></h1>

          <ul class="navbar-nav ms-auto">
            <!-- User Info -->
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="me-2 d-none d-lg-inline text-gray-300 small fw-bold"><?php echo $_SESSION['member_name'] ?? 'Member'; ?></span>
                <i class="fas fa-user-circle fa-lg text-blue-400"></i>
                <i class="fas fa-chevron-down ms-1 text-blue-300" style="font-size: 12px;"></i>
              </a>
              <div class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="userDropdown">
                <a class="dropdown-item" href="profile.php">
                  <i class="fas fa-user fa-sm fa-fw me-2 text-blue-400"></i>
                  My Profile
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="logout.php">
                  <i class="fas fa-sign-out-alt fa-sm fa-fw me-2 text-red-400"></i>
                  Logout
                </a>
              </div>
            </li>
          </ul>
        </div>
      </nav>

      <!-- Page Content Container -->
      <div class="container-fluid px-4">
