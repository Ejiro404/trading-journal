<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

function require_login(): void {
  if (empty($_SESSION['user_id'])) {
    header("Location: /trading-journal/auth/login.php");
    exit;
  }
}

function e(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
