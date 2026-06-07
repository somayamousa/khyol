<?php
/**
 * helpers بسيطة لإنشاء إشعار للمستخدم
 * usage: send_notification($conn, $user_id, 'عنوان', 'محتوى', 'event', '🏆', 'event.php?id=5');
 */

if (!function_exists('send_notification')) {
    function send_notification(PDO $conn, int $user_id, string $title, string $body = '', string $type = 'general', string $icon = '🔔', string $link = '') {
        try {
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, body, icon, link, type) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $title, $body, $icon, $link, $type]);
            return (int)$conn->lastInsertId();
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('get_public_notifications')) {
    function get_public_notifications(PDO $conn, int $limit = 4): array {
        try {
            $rows = $conn->query("SELECT id, title, icon, color, link FROM public_notifications WHERE is_active = 1 ORDER BY sort_order ASC, id DESC LIMIT $limit")->fetchAll();
            return $rows ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}
