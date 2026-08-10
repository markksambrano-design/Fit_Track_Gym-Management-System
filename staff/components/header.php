<?php
// Authentication check for staff pages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Skip authentication check if in debug mode
$debug_mode = $debug_mode ?? false;
if (!$debug_mode && (!isset($_SESSION['staff_logged_in']) || !$_SESSION['staff_logged_in'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes" />
  <title>RVG Power Build Staff - <?php echo $page_title ?? 'Dashboard'; ?></title>

  <!-- Favicon -->
  <link rel="icon" href="../assets/img/FIT.png" type="image/png" />

  <!-- Bootstrap + Font Awesome -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Bebas+Neue&display=swap" rel="stylesheet">
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

  <!-- Custom CSS -->
  <!-- <link href="../assets/css/staff.css" rel="stylesheet" /> -->
  
  <!-- Page-specific CSS -->
  <?php if (isset($page_title) && $page_title === 'Walk-In History'): ?>
  <link rel="stylesheet" href="../assets/css/staff/walk_in_history.css?v=<?php echo time(); ?>">
  <?php endif; ?>
  

  <style>
    body {
      margin: 0;
      margin-left: 280px; /* Match sidebar width */
      font-family: 'Poppins', sans-serif;
      background: linear-gradient(rgba(15, 23, 42, 0.95), rgba(15, 23, 42, 0.95)), 
                  url('https://images.unsplash.com/photo-1571902943202-507ec2618e8f?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1075&q=80');
      background-size: cover;
      background-position: center;
      background-attachment: fixed;
      color: #f8f9fc;
      transition: margin-left 0.4s cubic-bezier(0.25, 0.8, 0.25, 1.2);
    }

    body.sidebar-hidden {
      margin-left: 0;
    }

    .wrapper {
      display: flex;
    }

    .main {
      flex-grow: 1;
      margin-left: 0;
      min-height: 100vh;
      background-color: rgba(30, 41, 59, 0);
     
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
    }

    .dropdown-item {
      color: rgba(255, 255, 255, 0.8);
    }

    .dropdown-menu a:hover {
      background-color: rgba(34, 197, 94, 0.2);
      color: white;
    }

    .dropdown-divider {
      border-color: rgba(255, 255, 255, 0.1);
    }

    /* Sidebar styling removed to prevent conflicts */

    /* Card styling */
    .card {
      background-color: rgba(30, 41, 59, 0.7);
      border: 1px solid rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(5px);
      color: white;
    }

    .card-header {
      background-color: rgba(15, 23, 42, 0.5);
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
  </style>

  <!-- JS -->
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

  
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
                             <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                 <span class="me-2 d-none d-lg-inline text-gray-300 small fw-bold"><?php echo htmlspecialchars($_SESSION['staff_name'] ?? 'Staff Member'); ?></span>
                 <i class="fas fa-user-tie fa-lg text-green-400"></i>
               </a>
              <div class="dropdown-menu dropdown-menu-end shadow">
                <a class="dropdown-item" href="profile.php">
                  <i class="fas fa-user fa-sm fa-fw me-2 text-green-400"></i>
                  Profile
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

      <!-- Page Content -->
      <div class="container-fluid px-4">
        <!-- Your dashboard content starts here -->
