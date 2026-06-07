<?php
require_once __DIR__ . '/../config/db.php';

$cart_count = 0;
$unread_chat_count = 0;
$user_notifications = [];
if (isLoggedIn()) {
    $stmt = $conn->prepare("SELECT SUM(quantity) FROM cart WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $cart_count = (int)($stmt->fetchColumn() ?: 0);

    $u_check = $conn->prepare("
        SELECT COUNT(*)
        FROM messages m
        JOIN conversations c ON c.id = m.conversation_id
        LEFT JOIN centers ce       ON ce.id = c.center_id
        LEFT JOIN clinics cl       ON cl.id = c.clinic_id
        LEFT JOIN photographers pg ON pg.id = c.photographer_id
        WHERE m.is_read = 0
          AND (
              (c.user_id = ? AND m.sender_type IN ('center','clinic','photographer'))
              OR (ce.owner_id = ? AND m.sender_type = 'user')
              OR (cl.owner_id = ? AND m.sender_type = 'user')
              OR (pg.owner_id = ? AND m.sender_type = 'user')
          )
    ");
    $u_check->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
    $unread_chat_count = (int)$u_check->fetchColumn();

    // إشعارات المستخدم (إن وجد جدول notifications)
    try {
        $n_stmt = $conn->prepare("SELECT id, title, body, link, icon, is_read, created_at FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY id DESC LIMIT 8");
        $n_stmt->execute([$_SESSION['user_id']]);
        $user_notifications = $n_stmt->fetchAll();
    } catch (Throwable $e) {
        $user_notifications = [];
    }

    // عداد إشعارات الحجوزات غير المقروءة (قبول أو رفض)
    try {
        $bk_notif = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type IN ('booking_confirmed','booking_rejected') AND is_read = 0");
        $bk_notif->execute([$_SESSION['user_id']]);
        $unread_booking_notifs = (int)$bk_notif->fetchColumn();
    } catch (Throwable $e) {
        $unread_booking_notifs = 0;
    }
}

$unread_notifications = 0;
foreach ($user_notifications as $n) {
    if (!$n['is_read']) $unread_notifications++;
}
if (!isset($unread_booking_notifs)) $unread_booking_notifs = 0;

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? $page_title . ' | ' : '' ?>KHYOL - منصة الفروسية</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
    <?php if (!empty($extra_css)): foreach ((array)$extra_css as $__css): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($__css, ENT_QUOTES) ?>?v=<?= filemtime(__DIR__ . '/../' . $__css) ?>">
    <?php endforeach; endif; ?>
    <script>
        // تطبيق التيمة قبل تحميل الصفحة لمنع الوميض
        (function() {
            var saved = localStorage.getItem('khyol-theme') || 'dark';
            document.documentElement.setAttribute('data-theme', saved);
        })();
    </script>
</head>
<body>
<svg xmlns="http://www.w3.org/2000/svg" style="position:absolute;width:0;height:0;overflow:hidden" aria-hidden="true" focusable="false">
  <defs>
    <linearGradient id="khyol-gold" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#f1d063"/>
      <stop offset="50%" stop-color="#e5bf3d"/>
      <stop offset="100%" stop-color="#8b7510"/>
    </linearGradient>
    <symbol id="i-home" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
      <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
    </symbol>
    <symbol id="i-pin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
      <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>
    </symbol>
    <symbol id="i-clinic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
      <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>
      <path d="M3.22 12H9.5l.5-1 2 4.5 2-7 1.5 3.5h5.27"/>
    </symbol>
    <symbol id="i-horse" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
      <path d="M19 4 21 2v5h-4"/><path d="M14 7c2 0 4.5 1 5 3 .5 2 0 4-1 5l-3 3v4h-3v-5l-3-1-2 2v4H5v-5l-2-2c-1-1-1-3 0-4 1-1 3-1 4 0l1 1h3l3-5Z"/>
    </symbol>
    <symbol id="i-market" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
      <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>
    </symbol>
    <symbol id="i-gavel" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
      <path d="m14 13-7.5 7.5a2.12 2.12 0 0 1-3-3L11 10"/><path d="m16 16 6-6"/><path d="m8 8 6-6"/><path d="m9 7 8 8"/><path d="m21 11-8-8"/>
    </symbol>
    <symbol id="i-flag" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
      <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/>
    </symbol>
    <symbol id="i-book" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
      <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
    </symbol>
    <symbol id="i-store" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
      <path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7"/><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><path d="M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4"/><path d="M2 7h20"/>
    </symbol>
    <symbol id="i-phone" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
      <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
    </symbol>
    <symbol id="i-login" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/>
    </symbol>
    <symbol id="i-user-plus" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/>
    </symbol>
    <symbol id="i-cart" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
    </symbol>
    <symbol id="i-chat" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
      <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
    </symbol>
    <symbol id="i-bell" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
      <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>
    </symbol>
    <symbol id="i-user" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
    </symbol>
    <symbol id="i-logout" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
    </symbol>
    <symbol id="khyol-horse" viewBox="0 0 286.824 286.824">
      <path fill="url(#khyol-gold)" d="M280.239,64.986c-0.563-4.185-0.203-8.303-0.04-12.71c-1.671-1.031-1.072-3.331-1.925-5.332c-0.848,2.681,0.401,5.108,0.365,7.698c-1.727-0.371-1.63-2.214-2.904-2.859c-0.447,1.127-0.482,1.996,0.061,3.214c0.828,1.843,1.117,3.936,1.514,5.946c0.594,3.021,1.106,6.053,1.595,9.095c0.116,0.701,0.02,1.437,0,2.164c-0.092,3.752,1.208,7.251,1.935,10.861c1.173,5.804,2.396,11.644,1.188,17.616c-0.7,3.458-2.112,6.536-5.017,8.775c-0.858,0.66-1.569,1.513-2.371,2.239c-1.89,1.711-3.87,2.859-6.562,1.646c-0.894-0.401-2.005-0.447-3.016-0.437c-3.22,0.031-5.139-1.371-6.338-4.291c-0.857-2.112-1.65-4.144-1.203-6.474c0.061-0.33-0.097-0.721-0.224-1.051c-1.731-4.57-3.397-9.161-6.17-13.269c-1.035-1.533-1.66-3.356-2.427-5.067c-1.188-2.676-2.656-5.124-4.91-7.094c-2.113-1.853-3.972-3.935-4.245-6.957c-0.031-0.33-0.219-0.655-0.473-1.366c-0.949,1.285-1.889,2.239-2.452,3.382c-2.824,5.753-5.602,11.537-8.277,17.362c-1.564,3.407-3.007,6.88-4.326,10.384c-0.519,1.376-0.777,2.935-0.757,4.408c0.091,6.774,0.355,13.543,1.406,20.271c0.549,3.484-0.355,6.978-2.346,9.882c-3.692,5.373-6.688,11.263-11.497,15.823c-0.873,0.833-0.954,1.671-0.518,2.772c2.138,5.403,4.107,10.872,6.357,16.225c1.29,3.057,2.98,5.94,4.545,8.881c1.96,3.677,4.007,7.312,5.946,10.999c0.351,0.67,0.563,1.503,0.559,2.255c-0.021,3.021-0.127,6.048-0.249,9.069c-0.071,1.808-0.7,3.396-1.889,4.799c-2.361,2.798-4.108,5.977-5.652,9.277c-1.919,4.107-4.123,8.099-5.809,12.304c-3.026,7.551-1.396,5.321-7.271,10.72c-2.702,2.482-4.484,5.51-6.068,8.734c-0.528,1.061-1.229,2.092-2.052,2.944c-1.635,1.696-3.392,3.275-5.129,4.88c-2.768,2.55-2.909,5.14-0.716,8.176c1.3,1.798,2.244,3.85,3.306,5.708c-0.259,1.071-1.021,1.33-1.792,1.478c-0.823,0.152-1.682,0.259-2.524,0.238c-4.235-0.121-8.46-0.273-12.694-0.451c-1.336-0.062-4.393-2.61-4.683-3.905c-0.635-2.818-1.822-5.372-3.447-7.744c-0.549-0.797-1.066-1.625-1.549-2.468c-1.306-2.311-1.691-4.636-0.736-7.287c0.706-1.96,0.898-4.128,1.163-6.226c0.508-4.017,1.01-8.048,1.3-12.091c0.314-4.275,0.538-8.576,0.492-12.867c-0.021-2.143-0.533-4.342-1.183-6.403c-0.736-2.331-0.858-4.682-0.473-6.992c0.731-4.403,0.482-8.825,0.335-13.218c-0.198-5.561-0.762-11.101-1.168-16.656c-0.269-3.677-0.497-7.363-0.843-11.034c-0.168-1.858-0.305-1.808-2.184-1.65c-2.585,0.218-5.185,0.396-7.779,0.462c-8.049,0.193-16.098,0.376-24.148,0.447c-6.073,0.051-12.078-0.641-17.986-2.082c-1.881-0.457-3.748-0.971-5.621-1.447c-5.583-1.427-10.555-4.169-15.396-7.195c-1.127-0.706-2.268-1.392-3.41-2.077c-0.66-0.401-1.295-0.462-1.647,0.365c-2.356,5.505-5.2,10.826-6.563,16.702c-0.957,4.123-1.554,8.327-2.366,12.481c-0.208,1.056-0.536,2.107-0.935,3.103c-1.3,3.255-2.798,6.438-3.943,9.739c-0.602,1.737-0.785,3.661-0.899,5.515c-0.249,3.986-1.32,7.749-2.866,11.385c-0.8,1.89-1.211,3.799-0.774,5.835c1.274,5.981,2.476,11.974,3.834,17.931c0.685,3.006,1.439,6.027,2.536,8.896c1.592,4.169,3.245,8.353,5.949,11.979c0.571,0.771,1.305,1.427,1.965,2.133c1.772,1.899,3.587,3.758,5.304,5.703c1.354,1.528,2.09,3.376,2.506,5.367c0.328,1.568,0.183,2.107-1.371,2.33c-2.143,0.311-4.332,0.371-6.5,0.351c-3.021-0.025-6.042-0.279-9.069-0.325c-1.021-0.015-1.775-0.335-2.335-1.122c-1.618-2.249-3.42-4.407-4.735-6.829c-0.901-1.656-1.125-3.677-1.607-5.546c-0.673-2.569,0.27-5.053-0.396-7.627c-0.363-1.406-0.391-2.625-0.759-4.032c-0.368-1.401-0.487-2.884-0.993-4.234c-1.711-4.596-3.052-9.537-3.91-14.29c-0.625-3.447-1.25-5.915-1.999-9.343c-0.378-1.732-1.092-3.986-1.835-6.373c-0.061-0.203-0.19-0.538-0.285-0.822c-0.896-2.636-1.8-5.267-2.673-7.907c-0.686-2.077-0.521-4.128,0-6.251c1.32-5.402,2.531-10.836,3.697-16.274c0.406-1.884,0.584-3.819,0.792-5.738c0.414-3.809-0.251-7.465-1.681-10.999c-0.183-0.447-0.234-0.949-0.427-1.392c-0.625-1.432-1.15-2.935-1.97-4.25c-1.404-2.255-2.095-2.204-3.572,0.03c-0.531,0.803-0.947,1.681-1.462,2.498c-0.835,1.32-1.668,2.646-2.572,3.916c-1.552,2.168-3.105,4.28-3.943,6.896c-0.561,1.757-1.836,3.275-2.506,5.012c-1.523,3.946-2.879,7.958-4.296,11.938c-0.919,2.59-1.47,5.189-1.117,8.003c0.134,1.092-0.417,2.352-0.899,3.428c-1.061,2.371-2.272,4.677-3.422,7.003c-0.076,0.162-0.114,0.35-0.213,0.502c-4.321,6.81-4.336,14.615-4.977,22.231c-0.368,4.393-0.348,8.831,0.355,13.229c0.15,0.944,0.041,1.935-0.028,2.899c-0.259,3.514,0.048,6.851,2.138,9.902c0.764,1.111,1.112,2.528,1.577,3.834c0.82,2.341,1.569,4.707,2.372,7.058c0.541,1.585,0.447,1.976-1.077,2.737c-3.567,1.793-7.348,2.407-11.291,1.787c-1.247-0.192-2.473-0.543-3.692-0.878c-1.013-0.284-1.415-0.97-1.305-2.062c0.292-2.818,0.5-5.652,0.759-8.476c0.028-0.294,0.089-0.619,0.233-0.868c0.818-1.427,0.673-2.925,0.328-4.413c-0.696-3.006-1.475-5.986-2.13-9.003c-0.188-0.854-0.203-1.808-0.025-2.666c0.944-4.615,2.084-9.191,2.942-13.817c0.571-3.082,0.848-6.23,1.138-9.358c0.386-4.104,0.375-8.201,0.068-12.324c-0.152-2.011,0.229-4.093,0.586-6.104c0.366-2.052,0.569-4.053,0.247-6.129c-0.251-1.605-0.351-3.25-0.391-4.875c-0.041-1.595,0.472-3.047,1.201-4.499c1.711-3.382,3.572-6.749,3.443-10.745c-0.043-1.179,0.015-2.331-1.358-2.864c-0.203-0.076-0.393-0.305-0.498-0.508c-2.074-3.94-5.669-6.581-8.503-9.877c-2.755-3.193-5.022-6.657-6.827-10.45c-0.333-0.69-0.551-1.311-1.379-1.722c-0.696-0.34-1.138-1.219-1.658-1.884c-2.625-3.366-3.94-7.287-4.113-11.451c-0.155-3.854,0.312-7.734,0.437-11.603c0.12-3.748,0.114-7.5,0.249-11.253c0.033-0.935,0.297-1.899,0.625-2.783c0.495-1.32,0.884-2.554,0.584-4.037c-0.297-1.437,0.16-2.772,1.076-4.067c0.909-1.295,1.396-2.899,2.037-4.382c0.574-1.315,1.036-2.687,1.666-3.971c0.531-1.082,1.17-2.123,1.881-3.103c0.988-1.356,2.029-2.687,3.161-3.926c1.031-1.117,2.049-2.178,2.554-3.687c0.229-0.681,0.838-1.335,1.439-1.777c1.508-1.102,2.892-2.25,4.177-3.666c1.043-1.163,3.042-1.925,4.664-2.016c3.722-0.208,7.482,0.096,11.22,0.198c3.11,0.092,6.147-0.218,9.08-1.371c5.235-2.056,10.483-4.098,15.752-6.088c2.874-1.087,5.906-1.341,8.942-1.204c11.04,0.539,21.96,1.564,32.599,4.885c9.858,3.078,19.999,4.9,30.371,4.844c3.189-0.015,6.375-0.589,9.564-0.924c0.3-0.03,0.617-0.066,0.883-0.198c2.963-1.478,5.959-2.904,8.856-4.514c0.782-0.432,1.239-1.427,1.884-2.127c0.889-0.98,1.768-1.976,2.737-2.864c0.803-0.731,1.838-1.214,2.585-1.99c3.509-3.662,8.059-4.642,12.781-4.591c4.778,0.051,9.079-1.462,13.253-3.356c1.858-0.838,3.367-2.524,4.896-3.971c3.255-3.087,6.759-5.845,10.415-8.45c4.972-3.539,9.684-7.454,14.675-10.968c4.104-2.884,8.557-5.169,13.168-7.236c3.747-1.681,7.586-1.853,11.496-2.056c3.682-0.193,7.338-0.152,11.024,0.294c2.671,0.32,5.434-0.152,8.15-0.183s5.469-0.203,8.14,0.183c1.579,0.229,3.113,1.29,4.504,2.219c2.326,1.559,3.789,1.711,6.176,0.264c1.914-1.158,3.823-2.315,5.768-3.407c0.498-0.284,1.153-0.432,1.727-0.401c1.473,0.081,1.89,0.95,1.407,2.346c-0.457,1.305-0.798,2.651-1.082,4.001c-0.36,1.696-0.853,3.189-2.452,4.24c-2.057,1.34-2.342,2.925-1.417,5.205c0.797,1.955,1.487,3.951,2.234,5.926c0.776,2.066,0.554,4.164,0.243,6.276c-0.375,2.514-0.686,5.032-1.046,7.546c-0.086,0.594-0.116,1.254-0.396,1.752C281.097,58.674,280.604,61.822,280.239,64.986z M185.784,253.653c0-0.716,0.021-1.117-0.005-1.514c-0.076-1.198-0.198-2.407-0.239-3.61c-0.076-1.924-0.097-3.854-0.147-5.783c-0.02-0.787-0.167-1.59-0.056-2.356c0.214-1.56,0.503-3.072,2.28-3.783c1.32-0.523,2.112-1.955,3.691-2.158c2.194-0.284,3.418-1.635,4.251-3.702c0.848-2.107,1.975-4.158,3.29-6.012c1.528-2.153,3.474-4.002,5.108-6.084c4.093-5.225,7.343-10.933,9.623-17.184c1.579-4.352,0.502-8.271-2.209-11.715c-1.706-2.168-3.859-3.981-5.824-5.937c-1.758-1.746-3.728-3.32-5.276-5.229c-2.94-3.611-5.632-7.42-8.45-11.126c-0.563-0.741-1.208-1.422-2.117-2.479c-0.355,1.528-0.67,2.56-0.822,3.605c-0.381,2.636-0.59,5.296-1.052,7.917c-1.097,6.256-2.3,12.496-2.604,18.854c-0.071,1.563-0.279,3.173-0.016,4.691c0.965,5.53,0.513,10.801-1.96,15.92c-0.909,1.879-1.533,3.966-1.889,6.027c-0.523,3.031-0.736,6.124-1.006,9.196c-0.335,3.809-0.492,7.632,0.563,11.359c0.793,2.783,1.676,5.54,2.596,8.287C183.875,251.891,184.392,252.922,185.784,253.653z M21.003,136.682c-2.003,2.91,1.137,16.433,4.659,18.372C23.796,148.788,20.757,143.136,21.003,136.682z"/>
    </symbol>
  </defs>
</svg>
<nav class="navbar" id="mainNavbar">
    <div class="nav-wrapper">

        <!-- اللوجو -->
        <a href="index.php" class="logo" aria-label="KHYOL - الرئيسية">
            <span class="logo-mark" aria-hidden="true">
                <svg width="40" height="40" aria-hidden="true"><use href="#khyol-horse"/></svg>
            </span>
            <span class="logo-text">
                <span class="logo-text-main">KHYOL</span>
                <span class="logo-text-sub">خيـــول</span>
            </span>
        </a>

        <?php if (empty($admin_page)): ?>
        <!-- روابط التنقل الرئيسية (5 روابط فقط) -->
        <ul class="nav-menu" id="navMenu">
            <li><a href="index.php" <?= $current_page=='index.php'?'class="active"':'' ?>>الرئيسية</a></li>
            <li><a href="horses_market.php" <?= in_array($current_page,['horses_market.php','horse_public.php'])?'class="active"':'' ?>>الخيول</a></li>
            <li><a href="shop.php" <?= in_array($current_page,['shop.php','product.php'])?'class="active"':'' ?>>المتجر</a></li>
            <li><a href="events.php" <?= in_array($current_page,['events.php','event.php'])?'class="active"':'' ?>>الفعاليات</a></li>
            <li><a href="contact.php" <?= $current_page=='contact.php'?'class="active"':'' ?>>تواصل</a></li>
            <!-- قائمة المزيد -->
            <li class="nav-dropdown-wrapper">
                <a href="#" class="nav-dropdown-toggle">المزيد</a>
                <ul class="nav-dropdown-menu">
                    <li><a href="centers.php" <?= in_array($current_page,['centers.php','center.php','book.php'])?'class="active"':'' ?>>المراكز</a></li>
                    <li><a href="clinics.php" <?= in_array($current_page,['clinics.php','clinic.php','appointments.php'])?'class="active"':'' ?>>البيطرة</a></li>
                    <li><a href="horses.php" <?= in_array($current_page,['horses.php','horse.php','horse_edit.php'])?'class="active"':'' ?>>خيولي</a></li>
                    <li><a href="auctions.php" <?= in_array($current_page,['auctions.php','auction.php','auction_create.php'])?'class="active"':'' ?>>المزاد</a></li>
                    <li><a href="photographers.php" <?= in_array($current_page,['photographers.php','photoshoots.php','photoshoot_book.php'])?'class="active"':'' ?>>التصوير</a></li>
                    <li><a href="boarding.php" <?= $current_page==='boarding.php'?'class="active"':'' ?>>الإيواء</a></li>
                    <li><a href="articles.php" <?= in_array($current_page,['articles.php','article.php','diseases.php'])?'class="active"':'' ?>>المقالات</a></li>
                </ul>
            </li>
        </ul>
        <?php else: ?>
        <div style="flex:1;"></div>
        <?php endif; ?>

        <!-- أزرار الأكشن -->
        <div class="nav-actions">
            <?php if (!empty($admin_page)): ?>
            <button type="button" class="admin-topbar-toggle" id="adminSidebarToggle" aria-label="فتح القائمة">☰</button>
            <?php endif; ?>
            <button type="button" class="nav-icon-btn" id="themeToggle" title="تبديل المظهر" aria-label="تبديل المظهر">
                <span class="theme-icon theme-icon-dark">🌙</span>
                <span class="theme-icon theme-icon-light">☀️</span>
            </button>

            <?php if (isLoggedIn()): ?>
                <div class="noti-wrapper">
                    <button type="button" class="nav-icon-btn" id="notiBtn" title="الإشعارات" aria-label="الإشعارات">
                        <svg width="18" height="18" aria-hidden="true"><use href="#i-bell"/></svg>
                        <?php if ($unread_notifications > 0): ?>
                            <span class="noti-badge"><?= $unread_notifications ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="noti-dropdown" id="notiDropdown" hidden>
                        <div class="noti-dropdown-head">
                            <strong>الإشعارات</strong>
                            <?php if (!empty($user_notifications)): ?>
                                <a href="notifications_mark_all.php" class="noti-mark">قراءة الكل</a>
                            <?php endif; ?>
                        </div>
                        <div class="noti-dropdown-list">
                            <?php if (empty($user_notifications)): ?>
                                <div class="noti-empty">لا توجد إشعارات بعد</div>
                            <?php else: foreach ($user_notifications as $n): ?>
                                <a href="<?= sanitize($n['link'] ?: 'index.php') ?>"
                                   class="noti-item <?= $n['is_read']?'':'unread' ?>"
                                   data-noti-id="<?= (int)$n['id'] ?>"
                                   onclick="markNotiRead(event, this)">
                                    <span class="noti-item-icon"><?= sanitize($n['icon'] ?: '🔔') ?></span>
                                    <span class="noti-item-body">
                                        <strong><?= sanitize($n['title']) ?></strong>
                                        <small><?= sanitize($n['body']) ?></small>
                                        <em><?= date('d/m H:i', strtotime($n['created_at'])) ?></em>
                                    </span>
                                </a>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
                            <script>
                            function markNotiRead(e, el) {
                                e.preventDefault();
                                var id   = el.dataset.notiId;
                                var dest = el.getAttribute('href');
                                fetch('notification_read.php?id=' + id + '&ajax=1').catch(function(){});
                                el.style.transition = 'opacity 0.2s';
                                el.style.opacity = '0';
                                setTimeout(function(){ el.remove(); updateNotiBadge(); }, 200);
                                setTimeout(function(){ window.location.href = dest; }, 250);
                            }
                            function updateNotiBadge() {
                                var unread = document.querySelectorAll('.noti-item.unread').length;
                                var badge  = document.querySelector('.noti-badge');
                                if (badge) {
                                    if (unread > 0) { badge.textContent = unread; }
                                    else { badge.remove(); }
                                }
                            }
                            </script>

            <a href="cart.php" class="nav-icon-btn" title="السلة" aria-label="السلة">
                <svg width="18" height="18" aria-hidden="true"><use href="#i-cart"/></svg>
                <?php if ($cart_count > 0): ?><span class="nav-badge"><?= $cart_count ?></span><?php endif; ?>
            </a>

            <?php if (isLoggedIn()): ?>
                <a href="chat.php" class="nav-icon-btn" title="المحادثات" aria-label="المحادثات">
                    <svg width="18" height="18" aria-hidden="true"><use href="#i-chat"/></svg>
                    <?php if ($unread_chat_count > 0): ?><span class="nav-badge"><?= $unread_chat_count ?></span><?php endif; ?>
                </a>
                <div class="nav-vdivider"></div>
                <?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?>
                    <a href="admin.php" class="nav-btn nav-btn-ghost"><span>👑</span></a>
                <?php endif; ?>
                <a href="account.php" class="nav-btn nav-btn-outline">
                    <svg width="15" height="15" aria-hidden="true"><use href="#i-user"/></svg>
                    <span>حسابي</span>
                </a>
                <a href="logout.php" class="nav-btn nav-btn-gold">
                    <span>خروج</span>
                </a>
            <?php else: ?>
                <div class="nav-vdivider"></div>
                <a href="login.php" class="nav-btn nav-btn-outline">
                    <svg width="15" height="15" aria-hidden="true"><use href="#i-login"/></svg>
                    <span>دخول</span>
                </a>
                <a href="register.php" class="nav-btn nav-btn-gold">
                    <svg width="15" height="15" aria-hidden="true"><use href="#i-user-plus"/></svg>
                    <span>حساب جديد</span>
                </a>
            <?php endif; ?>
        </div>

        <!-- زر الهامبرغر (موبايل) -->
        <button type="button" class="nav-toggle" id="navToggle" aria-label="القائمة" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>

    </div>
</nav>
<script>
(function() {
    var root = document.documentElement;
    var btn = document.getElementById('themeToggle');
    if (btn) {
        btn.addEventListener('click', function() {
            var next = root.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
            root.setAttribute('data-theme', next);
            localStorage.setItem('khyol-theme', next);
        });
    }
    var navbar = document.getElementById('mainNavbar');
    var navToggle = document.getElementById('navToggle');
    if (navbar && navToggle) {
        navToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            var isOpen = navbar.classList.toggle('open');
            navToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
        document.addEventListener('click', function(e) {
            if (navbar.classList.contains('open') && !navbar.contains(e.target)) {
                navbar.classList.remove('open');
                navToggle.setAttribute('aria-expanded', 'false');
            }
        });
    }
    // Dropdown menu toggle for "المزيد"
    var dropdownToggle = document.querySelector('.nav-dropdown-toggle');
    var dropdownMenu = document.querySelector('.nav-dropdown-menu');
    if (dropdownToggle && dropdownMenu) {
        dropdownToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            dropdownMenu.classList.toggle('show');
            dropdownToggle.setAttribute('aria-expanded', dropdownMenu.classList.contains('show') ? 'true' : 'false');
        });
        document.addEventListener('click', function(e) {
            if (!dropdownMenu.classList.contains('show')) return;
            var wrapper = document.querySelector('.nav-dropdown-wrapper');
            if (wrapper && !wrapper.contains(e.target)) {
                dropdownMenu.classList.remove('show');
                dropdownToggle.setAttribute('aria-expanded', 'false');
            }
        });
    }
    var notiBtn = document.getElementById('notiBtn');
    var dropdown = document.getElementById('notiDropdown');
    if (notiBtn && dropdown) {
        notiBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdown.hidden = !dropdown.hidden;
        });
        document.addEventListener('click', function(e) {
            if (!dropdown.hidden && !dropdown.contains(e.target) && e.target !== notiBtn) {
                dropdown.hidden = true;
            }
        });
    }
})();
</script>
