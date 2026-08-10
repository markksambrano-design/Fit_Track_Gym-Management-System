<?php
// Redirect to the correct URL format
$form = isset($_GET['form']) ? $_GET['form'] : 'member';
header("Location: index.php?page=register&form=" . urlencode($form));
exit;
?>

<!-- Removed position field from registration -->
