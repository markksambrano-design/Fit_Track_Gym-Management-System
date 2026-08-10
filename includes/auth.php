<?php
// Authentication functions for FIT_TRACK

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if admin is logged in
 */
function isAdminLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Check if member is logged in
 */
function isMemberLoggedIn() {
    return isset($_SESSION['member_logged_in']) && $_SESSION['member_logged_in'] === true;
}

/**
 * Check if staff is logged in
 */
function isStaffLoggedIn() {
    return isset($_SESSION['staff_logged_in']) && $_SESSION['staff_logged_in'] === true;
}

/**
 * Get current admin ID
 */
function getCurrentAdminId() {
    return $_SESSION['admin_id'] ?? null;
}

/**
 * Get current member ID
 */
function getCurrentMemberId() {
    return $_SESSION['member_id'] ?? null;
}

/**
 * Get current staff ID
 */
function getCurrentStaffId() {
    return $_SESSION['staff_id'] ?? null;
}

/**
 * Logout current user
 */
function logout() {
    // Clear all session variables
    $_SESSION = array();
    
    // Destroy the session
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    
    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
}

/**
 * Require admin authentication
 */
function requireAdminAuth() {
    if (!isAdminLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

/**
 * Require member authentication
 */
function requireMemberAuth() {
    if (!isMemberLoggedIn()) {
        header('Location: member/login.php');
        exit();
    }
}

/**
 * Require staff authentication
 */
function requireStaffAuth() {
    if (!isStaffLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

/**
 * Generate secure random token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Hash password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify password
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Set remember me token
 */
function setRememberToken($userId, $userType = 'admin') {
    global $conn;
    
    $token = generateToken();
    $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
    
    $table = $userType . 's'; // admins, members, staff
    $idField = $userType . '_id';
    
    $stmt = $conn->prepare("UPDATE $table SET remember_token = ?, token_expiry = ? WHERE id = ?");
    $stmt->bind_param("ssi", $token, $expiry, $userId);
    $stmt->execute();
    
    // Set cookie
    setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/');
    setcookie('user_type', $userType, time() + (30 * 24 * 60 * 60), '/');
}

/**
 * Check remember me token
 */
function checkRememberToken() {
    global $conn;
    
    if (!isset($_COOKIE['remember_token']) || !isset($_COOKIE['user_type'])) {
        return false;
    }
    
    $token = $_COOKIE['remember_token'];
    $userType = $_COOKIE['user_type'];
    $table = $userType . 's';
    $idField = $userType . '_id';
    
    $stmt = $conn->prepare("SELECT id FROM $table WHERE remember_token = ? AND token_expiry > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $_SESSION[$idField] = $user['id'];
        return true;
    }
    
    return false;
}

/**
 * Clear remember me token
 */
function clearRememberToken($userId, $userType = 'admin') {
    global $conn;
    
    $table = $userType . 's';
    
    $stmt = $conn->prepare("UPDATE $table SET remember_token = NULL, token_expiry = NULL WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    
    // Clear cookies
    setcookie('remember_token', '', time() - 3600, '/');
    setcookie('user_type', '', time() - 3600, '/');
}
?>
