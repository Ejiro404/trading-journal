<?php
// /trading-journal/logout.php
if (session_status() === PHP_SESSION_NONE) session_start();

// Clear session data
$_SESSION = [];

// Clear session cookie (best practice)
if (ini_get("session.use_cookies")) {
  $p = session_get_cookie_params();
  setcookie(session_name(), "", time() - 42000,
    $p["path"], $p["domain"], $p["secure"], $p["httponly"]
  );
}

// Destroy session
session_destroy();

// Redirect to login with a flag
header("Location: /trading-journal/auth/login.php?logged_out=1");
exit;
