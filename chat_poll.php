<?php
// ============ جلب الرسائل الجديدة (polling) ============
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/db.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'يجب تسجيل الدخول']);
    exit;
}

$conversation_id = (int)($_GET['conversation_id'] ?? 0);
$after_id        = (int)($_GET['after'] ?? 0);
$user_id         = (int)$_SESSION['user_id'];

if ($conversation_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'conversation_id مطلوب']);
    exit;
}

// التحقق من صلاحية المستخدم
$stmt = $conn->prepare("
    SELECT c.user_id, c.center_id, c.clinic_id, c.photographer_id, c.is_admin_chat,
           ce.owner_id AS center_owner,
           cl.owner_id AS clinic_owner,
           pg.owner_id AS studio_owner
    FROM conversations c
    LEFT JOIN centers ce       ON ce.id = c.center_id
    LEFT JOIN clinics cl       ON cl.id = c.clinic_id
    LEFT JOIN photographers pg ON pg.id = c.photographer_id
    WHERE c.id = ?
");
$stmt->execute([$conversation_id]);
$conv = $stmt->fetch();

if (!$conv) {
    http_response_code(404);
    echo json_encode(['error' => 'المحادثة غير موجودة']);
    exit;
}

$viewer_type = null;
if (!empty($conv['is_admin_chat']) && (int)$conv['user_id'] === $user_id) {
    $viewer_type = 'user';
} elseif ((int)$conv['user_id'] === $user_id) {
    $viewer_type = 'user';
} elseif ($conv['center_id'] && (int)$conv['center_owner'] === $user_id) {
    $viewer_type = 'center';
} elseif ($conv['clinic_id'] && (int)$conv['clinic_owner'] === $user_id) {
    $viewer_type = 'clinic';
} elseif ($conv['photographer_id'] && (int)$conv['studio_owner'] === $user_id) {
    $viewer_type = 'photographer';
}

if ($viewer_type === null) {
    http_response_code(403);
    echo json_encode(['error' => 'غير مصرح']);
    exit;
}

// جلب الرسائل الأحدث من after_id
$q = $conn->prepare("
    SELECT id, sender_type, body, attachment_path, attachment_type, attachment_meta, created_at
    FROM messages
    WHERE conversation_id = ? AND id > ?
    ORDER BY id ASC
    LIMIT 100
");
$q->execute([$conversation_id, $after_id]);
$messages = $q->fetchAll();

// تحديد الرسائل الواردة كمقروءة
if (!empty($conv['is_admin_chat'])) {
    $mark = $conn->prepare("UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_type = 'admin' AND is_read = 0");
    $mark->execute([$conversation_id]);
} else {
    $target_type = $conv['center_id'] ? 'center' : ($conv['clinic_id'] ? 'clinic' : 'photographer');
    $opposite = $viewer_type === 'user' ? $target_type : 'user';
    $mark = $conn->prepare("UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_type = ? AND is_read = 0");
    $mark->execute([$conversation_id, $opposite]);
}

echo json_encode([
    'ok' => true,
    'messages' => $messages,
], JSON_UNESCAPED_UNICODE);
