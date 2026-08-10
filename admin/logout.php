<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Updated logout process to reflect changes in position handling
session_unset();
session_destroy();
header('Location: index.php?page=login');
exit;
?>