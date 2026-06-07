<?php
require_once 'config/db.php';

if (!isLoggedIn()) {
    if (!empty($_GET['ajax'])) { http_response_code(401); exit; }
    redirect('login.php');
}

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")
         ->execute([$id, (int)$_SESSION['user_id']]);
}

// طلب AJAX — أعد OK فقط
if (!empty($_GET['ajax'])) {
    echo 'ok';
    exit;
}

// طلب عادي — وجّه للرابط
$redirect = $_GET['redirect'] ?? '';
$dest = ($redirect && strpos($redirect, '//') === false) ? $redirect : 'index.php';
redirect($dest);
