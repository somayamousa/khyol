<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) { redirect('login.php'); }
try {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
} catch (Throwable $e) {}
$back = $_SERVER['HTTP_REFERER'] ?? 'index.php';
redirect($back);
