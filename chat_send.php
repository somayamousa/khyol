<?php
// ============ إرسال رسالة في محادثة (نص + صوت/صورة/فيديو) ============
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/db.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'يجب تسجيل الدخول']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST only']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// نتعامل مع نوعين: JSON (للنص فقط) أو multipart (للميديا)
$is_multipart = strpos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') === 0;

if ($is_multipart) {
    $conversation_id = (int)($_POST['conversation_id'] ?? 0);
    $body = trim((string)($_POST['body'] ?? ''));
} else {
    $input = json_decode(file_get_contents('php://input'), true);
    $conversation_id = (int)($input['conversation_id'] ?? 0);
    $body = trim((string)($input['body'] ?? ''));
}

$body = mb_substr($body, 0, 2000);

if ($conversation_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'بيانات ناقصة']);
    exit;
}

// التحقق من صلاحية المستخدم
$stmt = $conn->prepare("
    SELECT c.id, c.user_id, c.other_user_id, c.center_id, c.clinic_id, c.photographer_id, c.is_admin_chat,
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
    echo json_encode(['ok' => false, 'error' => 'المحادثة غير موجودة']);
    exit;
}

$sender_type = null;
if (!empty($conv['is_admin_chat']) && (int)$conv['user_id'] === $user_id) {
    $sender_type = 'user';
} elseif (!empty($conv['other_user_id']) &&
          ((int)$conv['user_id'] === $user_id || (int)$conv['other_user_id'] === $user_id)) {
    // محادثة user-to-user
    $sender_type = 'user';
} elseif ((int)$conv['user_id'] === $user_id) {
    $sender_type = 'user';
} elseif ($conv['center_id'] && (int)$conv['center_owner'] === $user_id) {
    $sender_type = 'center';
} elseif ($conv['clinic_id'] && (int)$conv['clinic_owner'] === $user_id) {
    $sender_type = 'clinic';
} elseif ($conv['photographer_id'] && (int)$conv['studio_owner'] === $user_id) {
    $sender_type = 'photographer';
}
if ($sender_type === null) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'غير مصرح لك']);
    exit;
}

// معالجة المرفق
$attachment_path = null;
$attachment_type = null;
$attachment_meta = null;

if ($is_multipart && isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    $allowed = [
        'jpg' => 'image', 'jpeg' => 'image', 'png' => 'image', 'webp' => 'image', 'gif' => 'image',
        'mp4' => 'video', 'webm' => 'video', 'mov' => 'video',
        'mp3' => 'audio', 'm4a' => 'audio', 'ogg' => 'audio', 'wav' => 'audio',
        'pdf' => 'file'
    ];
    $orig_name = $_FILES['attachment']['name'];
    $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
    if (!isset($allowed[$ext])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'نوع ملف غير مدعوم']);
        exit;
    }
    $type = $allowed[$ext];
    $max_size = $type === 'video' ? 50 * 1024 * 1024 : ($type === 'audio' ? 15 * 1024 * 1024 : 8 * 1024 * 1024);
    if ($_FILES['attachment']['size'] > $max_size) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'حجم الملف كبير']);
        exit;
    }
    $dir = __DIR__ . '/assets/uploads/chat/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $fname = 'm_' . $conversation_id . '_' . $user_id . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
    if (move_uploaded_file($_FILES['attachment']['tmp_name'], $dir . $fname)) {
        $attachment_path = 'assets/uploads/chat/' . $fname;
        $attachment_type = $type;
        $attachment_meta = $orig_name;
    }
}

if ($body === '' && !$attachment_path) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'لا يمكن إرسال رسالة فارغة']);
    exit;
}

$ins = $conn->prepare("INSERT INTO messages (conversation_id, sender_id, sender_type, body, attachment_path, attachment_type, attachment_meta) VALUES (?, ?, ?, ?, ?, ?, ?)");
$ins->execute([$conversation_id, $user_id, $sender_type, $body ?: null, $attachment_path, $attachment_type, $attachment_meta]);
$message_id = (int)$conn->lastInsertId();

$conn->prepare("UPDATE conversations SET last_message_at = NOW() WHERE id = ?")->execute([$conversation_id]);

$fetch = $conn->prepare("SELECT id, sender_type, body, attachment_path, attachment_type, attachment_meta, created_at FROM messages WHERE id = ?");
$fetch->execute([$message_id]);
$msg = $fetch->fetch();

echo json_encode(['ok' => true, 'message' => $msg], JSON_UNESCAPED_UNICODE);
