<?php
$page_title = 'المحادثات';
require_once 'config/db.php';

if (!isLoggedIn()) redirect('login.php?redirect=chat');

$user_id = (int)$_SESSION['user_id'];

// الأعمال التي يملكها المستخدم (لتحديد الأدوار)
$_oc = $conn->prepare("SELECT id FROM centers WHERE owner_id = ?"); $_oc->execute([$user_id]);
$own_centers = array_column($_oc->fetchAll(), 'id');
$_ocl = $conn->prepare("SELECT id FROM clinics WHERE owner_id = ?"); $_ocl->execute([$user_id]);
$own_clinics = array_column($_ocl->fetchAll(), 'id');
$_os = $conn->prepare("SELECT id FROM photographers WHERE owner_id = ?"); $_os->execute([$user_id]);
$own_studios = array_column($_os->fetchAll(), 'id');

$active_conversation_id = 0;

// === دالة مساعدة: إنشاء/فتح محادثة مع هدف (center/clinic/photographer) ===
function open_or_create_conversation($conn, $user_id, $type, $target_id) {
    $col_map = ['center' => 'center_id', 'clinic' => 'clinic_id', 'photographer' => 'photographer_id'];
    $tbl_map = ['center' => 'centers', 'clinic' => 'clinics', 'photographer' => 'photographers'];
    if (!isset($col_map[$type])) return 0;
    $col = $col_map[$type];
    $tbl = $tbl_map[$type];

    $chk = $conn->prepare("SELECT owner_id FROM $tbl WHERE id = ?");
    $chk->execute([$target_id]);
    $owner_id = (int)$chk->fetchColumn();
    if (!$owner_id || $owner_id === $user_id) return 0;

    $find = $conn->prepare("SELECT id FROM conversations WHERE user_id = ? AND $col = ?");
    $find->execute([$user_id, $target_id]);
    $existing = $find->fetchColumn();
    if ($existing) return (int)$existing;

    $ins = $conn->prepare("INSERT INTO conversations (user_id, $col) VALUES (?, ?)");
    $ins->execute([$user_id, $target_id]);
    return (int)$conn->lastInsertId();
}

// === فتح/إنشاء محادثة مع مستخدم آخر (user-to-user) ===
if (isset($_GET['user_id'])) {
    $other_id = (int)$_GET['user_id'];
    if ($other_id && $other_id !== $user_id) {
        // تحقق من وجود المستخدم
        $chk = $conn->prepare("SELECT id FROM users WHERE id = ?");
        $chk->execute([$other_id]);
        if ($chk->fetchColumn()) {
            // البحث عن محادثة موجودة بين الطرفين
            $find = $conn->prepare("
                SELECT id FROM conversations
                WHERE other_user_id IS NOT NULL
                  AND ((user_id = ? AND other_user_id = ?) OR (user_id = ? AND other_user_id = ?))
                LIMIT 1
            ");
            $find->execute([$user_id, $other_id, $other_id, $user_id]);
            $existing = $find->fetchColumn();
            if ($existing) {
                redirect("chat.php?c=$existing");
            } else {
                $ins = $conn->prepare("INSERT INTO conversations (user_id, other_user_id) VALUES (?, ?)");
                $ins->execute([$user_id, $other_id]);
                $new_cid = (int)$conn->lastInsertId();
                redirect("chat.php?c=$new_cid");
            }
        }
    }
    redirect('chat.php');
}

// === فتح/إنشاء محادثة مع هدف محدد ===
foreach (['center', 'clinic', 'photographer'] as $type) {
    if (isset($_GET[$type])) {
        $cid = open_or_create_conversation($conn, $user_id, $type, (int)$_GET[$type]);
        redirect($cid ? "chat.php?c=$cid" : 'chat.php');
    }
}

if (isset($_GET['c'])) {
    $active_conversation_id = (int)$_GET['c'];
}

// === جلب كل محادثات المستخدم (كزبون + كمالك لأي عمل + محادثات الأدمن + user-to-user) ===
$conds = ['c.user_id = ?', 'c.other_user_id = ?'];
$params = [$user_id, $user_id];
if ($own_centers) {
    $ph = implode(',', array_fill(0, count($own_centers), '?'));
    $conds[] = "c.center_id IN ($ph)";
    $params = array_merge($params, $own_centers);
}
if ($own_clinics) {
    $ph = implode(',', array_fill(0, count($own_clinics), '?'));
    $conds[] = "c.clinic_id IN ($ph)";
    $params = array_merge($params, $own_clinics);
}
if ($own_studios) {
    $ph = implode(',', array_fill(0, count($own_studios), '?'));
    $conds[] = "c.photographer_id IN ($ph)";
    $params = array_merge($params, $own_studios);
}
$where = implode(' OR ', $conds);

$list = $conn->prepare("
    SELECT
        c.id, c.user_id, c.center_id, c.clinic_id, c.photographer_id, c.other_user_id, c.is_admin_chat, c.last_message_at,
        ce.name AS center_name, ce.image AS center_image, ce.owner_id AS center_owner_id,
        cl.name AS clinic_name, cl.image AS clinic_image, cl.owner_id AS clinic_owner_id,
        pg.studio_name AS studio_name, pg.image AS studio_image, pg.owner_id AS studio_owner_id,
        u.full_name AS customer_name, u.avatar AS customer_avatar,
        ou.full_name AS other_user_name, ou.avatar AS other_user_avatar,
        (SELECT body FROM messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_message,
        0 AS unread_count
    FROM conversations c
    LEFT JOIN centers ce       ON ce.id = c.center_id
    LEFT JOIN clinics cl       ON cl.id = c.clinic_id
    LEFT JOIN photographers pg ON pg.id = c.photographer_id
    JOIN users u ON u.id = c.user_id
    LEFT JOIN users ou ON ou.id = c.other_user_id
    WHERE $where
    ORDER BY c.last_message_at DESC
");
$list->execute($params);
$conversations = $list->fetchAll();

// === تحديد دور المستخدم في كل محادثة + اسم/صورة الطرف الآخر + unread ===
function conv_meta($row, $user_id) {
    $meta = ['type' => null, 'role' => null, 'other_name' => '', 'other_image' => '', 'target_label' => ''];
    if (!empty($row['is_admin_chat'])) {
        $meta['type'] = 'admin';
        $meta['role'] = 'user';
        $meta['target_label'] = '👑 إدارة المنصة';
        $meta['other_name'] = 'إدارة خيول';
        $meta['other_image'] = 'assets/images/logo.png';
        return $meta;
    }
    if ($row['center_id']) {
        $meta['type'] = 'center';
        $meta['target_label'] = '🏇 مركز';
        if ((int)$row['user_id'] === $user_id) {
            $meta['role'] = 'user';
            $meta['other_name'] = $row['center_name'];
            $meta['other_image'] = $row['center_image'];
        } elseif ((int)$row['center_owner_id'] === $user_id) {
            $meta['role'] = 'center';
            $meta['other_name'] = $row['customer_name'];
            $meta['other_image'] = $row['customer_avatar'];
        }
    } elseif ($row['clinic_id']) {
        $meta['type'] = 'clinic';
        $meta['target_label'] = '🩺 عيادة';
        if ((int)$row['user_id'] === $user_id) {
            $meta['role'] = 'user';
            $meta['other_name'] = $row['clinic_name'];
            $meta['other_image'] = $row['clinic_image'];
        } elseif ((int)$row['clinic_owner_id'] === $user_id) {
            $meta['role'] = 'clinic';
            $meta['other_name'] = $row['customer_name'];
            $meta['other_image'] = $row['customer_avatar'];
        }
    } elseif ($row['photographer_id']) {
        $meta['type'] = 'photographer';
        $meta['target_label'] = '📸 استوديو';
        if ((int)$row['user_id'] === $user_id) {
            $meta['role'] = 'user';
            $meta['other_name'] = $row['studio_name'];
            $meta['other_image'] = $row['studio_image'];
        } elseif ((int)$row['studio_owner_id'] === $user_id) {
            $meta['role'] = 'photographer';
            $meta['other_name'] = $row['customer_name'];
            $meta['other_image'] = $row['customer_avatar'];
        }
    } elseif (!empty($row['other_user_id'])) {
        $meta['type'] = 'user';
        $meta['target_label'] = '🐎 مزاد';
        $meta['role'] = 'user';
        // حدد الطرف الآخر بناءً على من أنت
        if ((int)$row['user_id'] === $user_id) {
            // أنت المبادر (user_id)، الطرف الآخر هو other_user_id
            $meta['other_name']  = $row['other_user_name']  ?? '';
            $meta['other_image'] = $row['other_user_avatar'] ?? '';
        } else {
            // أنت الطرف الآخر (other_user_id)، المبادر هو user_id
            $meta['other_name']  = $row['customer_name']  ?? '';
            $meta['other_image'] = $row['customer_avatar'] ?? '';
        }
    }
    return $meta;
}

// حساب unread ديناميكياً
foreach ($conversations as &$cvr) {
    $m = conv_meta($cvr, $user_id);
    if (!$m['role']) { $cvr['unread_count'] = 0; continue; }
    if ($m['type'] === 'admin') {
        $cnt = $conn->prepare("SELECT COUNT(*) FROM messages WHERE conversation_id = ? AND is_read = 0 AND sender_type = 'admin'");
        $cnt->execute([$cvr['id']]);
    } elseif ($m['type'] === 'user') {
        // user-to-user: رسائل الطرف الآخر غير مقروءة
        $cnt = $conn->prepare("SELECT COUNT(*) FROM messages WHERE conversation_id = ? AND is_read = 0 AND sender_id != ?");
        $cnt->execute([$cvr['id'], $user_id]);
    } else {
        $opp = $m['role'] === 'user' ? $m['type'] : 'user';
        $cnt = $conn->prepare("SELECT COUNT(*) FROM messages WHERE conversation_id = ? AND is_read = 0 AND sender_type = ?");
        $cnt->execute([$cvr['id'], $opp]);
    }
    $cvr['unread_count'] = (int)$cnt->fetchColumn();
}
unset($cvr);

// === المحادثة النشطة ===
$active = null;
$active_role = null;
$active_type = null;
$active_other_name = '';
$active_other_image = '';
$active_target_label = '';

if ($active_conversation_id > 0) {
    foreach ($conversations as $c) {
        if ((int)$c['id'] === $active_conversation_id) {
            $active = $c;
            break;
        }
    }
    if ($active) {
        $m = conv_meta($active, $user_id);
        $active_role = $m['role'];
        $active_type = $m['type'];
        $active_other_name = $m['other_name'];
        $active_other_image = $m['other_image'];
        $active_target_label = $m['target_label'];
    }
}

// === جلب رسائل المحادثة النشطة ===
$messages = [];
if ($active) {
    $mq = $conn->prepare("SELECT id, sender_id, sender_type, body, attachment_path, attachment_type, attachment_meta, created_at FROM messages WHERE conversation_id = ? ORDER BY id ASC");
    $mq->execute([$active_conversation_id]);
    $messages = $mq->fetchAll();

    // تحديد الرسائل الواردة كمقروءة
    if ($active_type === 'admin') {
        $mark = $conn->prepare("UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_type = 'admin' AND is_read = 0");
        $mark->execute([$active_conversation_id]);
    } elseif ($active_type === 'user') {
        $mark = $conn->prepare("UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_id != ? AND is_read = 0");
        $mark->execute([$active_conversation_id, $user_id]);
    } else {
        $opposite = $active_role === 'user' ? $active_type : 'user';
        $mark = $conn->prepare("UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_type = ? AND is_read = 0");
        $mark->execute([$active_conversation_id, $opposite]);
    }
}

include 'includes/header.php';
?>

<div class="container" style="padding: 30px 0;">
    <div class="chat-layout">

        <!-- قائمة المحادثات -->
        <aside class="chat-list-panel">
            <div class="chat-list-header">
                <h3>💬 المحادثات</h3>
                <span class="chat-list-count"><?= count($conversations) ?></span>
            </div>

            <?php if (empty($conversations)): ?>
                <div class="chat-empty-list">
                    <span style="font-size: 48px;">💬</span>
                    <p>لا توجد محادثات بعد</p>
                    <a href="centers.php" class="btn btn-primary">تصفّح المراكز</a>
                </div>
            <?php else: ?>
                <div class="chat-list">
                    <?php foreach ($conversations as $c):
                        $m = conv_meta($c, $user_id);
                        if (!$m['role']) continue;
                        $is_owner_side = $m['role'] !== 'user';
                        $is_active = (int)$c['id'] === $active_conversation_id;
                    ?>
                    <a href="chat.php?c=<?= $c['id'] ?>" class="chat-list-item <?= $is_active ? 'active' : '' ?>">
                        <img src="<?= sanitize($m['other_image'] ?: 'assets/images/default-avatar.png') ?>" alt="" onerror="this.src='assets/images/hero.jpg'">
                        <div class="chat-list-item-info">
                            <div class="chat-list-item-top">
                                <strong><?= sanitize($m['other_name']) ?></strong>
                                <?php if (!empty($c['unread_count'])): ?>
                                    <span class="chat-unread-badge"><?= $c['unread_count'] ?></span>
                                <?php endif; ?>
                            </div>
                            <span class="chat-list-item-preview">
                                <small style="color: var(--gold);"><?= $m['target_label'] ?><?= $is_owner_side ? ' • 👤 زبون' : '' ?></small><br>
                                <?= sanitize(mb_substr($c['last_message'] ?? 'لا توجد رسائل', 0, 50)) ?>
                            </span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </aside>

        <!-- نافذة المحادثة -->
        <main class="chat-main-panel">
            <?php if (!$active): ?>
                <div class="chat-empty-state">
                    <span style="font-size: 72px;">💬</span>
                    <h2>اختر محادثة للبدء</h2>
                    <p>اختر محادثة من القائمة أو ابدأ محادثة جديدة من صفحة المركز</p>
                </div>
            <?php else: ?>
                <header class="chat-conv-header">
                    <img src="<?= sanitize($active_other_image ?: 'assets/images/default-avatar.png') ?>" alt="" onerror="this.src='assets/images/hero.jpg'">
                    <div>
                        <h3><?= sanitize($active_other_name) ?></h3>
                        <small>
                            <?= $active_target_label ?>
                            <?php if ($active_role !== 'user'): ?> • 👤 زبون<?php endif; ?>
                        </small>
                    </div>
                </header>

                <div id="chat-messages" class="chat-messages" data-conversation-id="<?= $active_conversation_id ?>" data-role="<?= $active_role ?>">
                    <?php if (empty($messages)): ?>
                        <div class="chat-no-messages">
                            <p>👋 ابدأ المحادثة بإرسال أول رسالة</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($messages as $m):
                            // تحديد ما إذا كانت الرسالة من المستخدم الحالي
                            $is_mine = false;
                            if ($active_type === 'user') {
                                // في محادثات user-to-user، استخدم sender_id
                                $is_mine = (int)$m['sender_id'] === $user_id;
                            } else {
                                // في محادثات الخدمات، استخدم sender_type
                                $is_mine = $m['sender_type'] === $active_role;
                            }
                        ?>
                        <div class="chat-msg <?= $is_mine ? 'mine' : 'theirs' ?>" data-msg-id="<?= $m['id'] ?>">
                            <div class="chat-bubble">
                                <?php if ($m['attachment_type'] === 'image'): ?>
                                    <a href="<?= sanitize($m['attachment_path']) ?>" target="_blank">
                                        <img src="<?= sanitize($m['attachment_path']) ?>" class="chat-att-image" alt="">
                                    </a>
                                <?php elseif ($m['attachment_type'] === 'video'): ?>
                                    <video src="<?= sanitize($m['attachment_path']) ?>" controls preload="metadata" class="chat-att-video"></video>
                                <?php elseif ($m['attachment_type'] === 'audio'): ?>
                                    <audio src="<?= sanitize($m['attachment_path']) ?>" controls class="chat-att-audio"></audio>
                                <?php elseif ($m['attachment_type'] === 'file'): ?>
                                    <a href="<?= sanitize($m['attachment_path']) ?>" target="_blank" class="chat-att-file">
                                        📎 <?= sanitize($m['attachment_meta'] ?: 'ملف') ?>
                                    </a>
                                <?php endif; ?>
                                <?php if ($m['body']): ?>
                                    <div class="chat-text"><?= nl2br(sanitize($m['body'])) ?></div>
                                <?php endif; ?>
                                <span class="chat-time"><?= date('H:i', strtotime($m['created_at'])) ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <form id="chat-form" class="chat-form" enctype="multipart/form-data">
                    <input type="hidden" id="chat-conv-id" value="<?= $active_conversation_id ?>">
                    <div class="chat-tools">
                        <label class="chat-tool-btn" title="صورة">
                            📷
                            <input type="file" id="imgInput" accept="image/*" hidden>
                        </label>
                        <label class="chat-tool-btn" title="فيديو">
                            🎬
                            <input type="file" id="vidInput" accept="video/*" hidden>
                        </label>
                        <label class="chat-tool-btn" title="ملف">
                            📎
                            <input type="file" id="fileInput" accept=".pdf" hidden>
                        </label>
                    </div>
                    <textarea id="chat-input" placeholder="اكتب رسالتك..." rows="1" maxlength="2000"></textarea>
                    <button type="submit" class="btn btn-primary" id="sendBtn">➤ إرسال</button>
                </form>
                <div id="chat-preview" class="chat-preview" hidden></div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php if ($active): ?>
<script>
(function () {
    const conversationId = <?= $active_conversation_id ?>;
    const role = <?= json_encode($active_role) ?>;
    const conversationType = <?= json_encode($active_type) ?>;
    const currentUserId = <?= $user_id ?>;
    const messagesEl = document.getElementById('chat-messages');
    const form = document.getElementById('chat-form');
    const input = document.getElementById('chat-input');

    function scrollToBottom() {
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function lastMessageId() {
        const items = messagesEl.querySelectorAll('[data-msg-id]');
        return items.length ? parseInt(items[items.length - 1].dataset.msgId, 10) : 0;
    }

    function formatTime(dateStr) {
        const d = new Date(dateStr.replace(' ', 'T'));
        return d.getHours().toString().padStart(2, '0') + ':' + d.getMinutes().toString().padStart(2, '0');
    }

    function escapeHtml(s) {
        return s.replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    function renderAttachment(msg) {
        if (!msg.attachment_path) return '';
        const safe = escapeHtml(msg.attachment_path);
        switch (msg.attachment_type) {
            case 'image': return `<a href="${safe}" target="_blank"><img src="${safe}" class="chat-att-image" alt=""></a>`;
            case 'video': return `<video src="${safe}" controls preload="metadata" class="chat-att-video"></video>`;
            case 'audio': return `<audio src="${safe}" controls class="chat-att-audio"></audio>`;
            case 'file':  return `<a href="${safe}" target="_blank" class="chat-att-file">📎 ${escapeHtml(msg.attachment_meta || 'ملف')}</a>`;
        }
        return '';
    }

    function appendMessage(msg) {
        if (messagesEl.querySelector(`[data-msg-id="${msg.id}"]`)) return;
        const noMsg = messagesEl.querySelector('.chat-no-messages');
        if (noMsg) noMsg.remove();
        // في محادثات user-to-user، استخدم sender_id؛ وإلا استخدم sender_type
        const isMine = conversationType === 'user' ? msg.sender_id === currentUserId : msg.sender_type === role;
        const div = document.createElement('div');
        div.className = 'chat-msg ' + (isMine ? 'mine' : 'theirs');
        div.dataset.msgId = msg.id;
        const bodyHtml = msg.body ? `<div class="chat-text">${escapeHtml(msg.body).replace(/\n/g,'<br>')}</div>` : '';
        div.innerHTML = `<div class="chat-bubble">${renderAttachment(msg)}${bodyHtml}<span class="chat-time">${formatTime(msg.created_at)}</span></div>`;
        messagesEl.appendChild(div);
        scrollToBottom();
    }

    // === المرفقات ===
    let pendingFile = null; // {file, type}
    const previewEl = document.getElementById('chat-preview');
    function setPending(file, type) {
        pendingFile = { file, type };
        const url = URL.createObjectURL(file);
        let previewHtml = '';
        if (type === 'image') previewHtml = `<img src="${url}" style="max-height:80px;border-radius:8px;">`;
        else if (type === 'video') previewHtml = `🎬 <span>${file.name}</span>`;
        else if (type === 'audio') previewHtml = `🎤 <audio src="${url}" controls></audio>`;
        else previewHtml = `📎 <span>${file.name}</span>`;
        previewEl.hidden = false;
        previewEl.innerHTML = previewHtml + ` <button type="button" id="cancelAtt" class="chat-tool-btn" style="font-size:14px;">✕</button>`;
        document.getElementById('cancelAtt').onclick = clearPending;
    }
    function clearPending() {
        pendingFile = null;
        previewEl.hidden = true;
        previewEl.innerHTML = '';
        document.getElementById('imgInput').value = '';
        document.getElementById('vidInput').value = '';
        document.getElementById('fileInput').value = '';
    }
    document.getElementById('imgInput').addEventListener('change', e => { if (e.target.files[0]) setPending(e.target.files[0], 'image'); });
    document.getElementById('vidInput').addEventListener('change', e => { if (e.target.files[0]) setPending(e.target.files[0], 'video'); });
    document.getElementById('fileInput').addEventListener('change', e => { if (e.target.files[0]) setPending(e.target.files[0], 'file'); });

    // === إرسال رسالة (نص و/أو مرفق) ===
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const body = input.value.trim();
        if (!body && !pendingFile) return;

        const fd = new FormData();
        fd.append('conversation_id', conversationId);
        if (body) fd.append('body', body);
        if (pendingFile) fd.append('attachment', pendingFile.file);

        const sendBtn = document.getElementById('sendBtn');
        sendBtn.disabled = true;
        const oldText = sendBtn.textContent;
        sendBtn.textContent = 'يرسل...';

        try {
            const res = await fetch('chat_send.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (!data.ok) {
                alert(data.error || 'تعذر الإرسال');
                return;
            }
            input.value = '';
            input.style.height = 'auto';
            clearPending();
            appendMessage(data.message);
        } catch (err) {
            alert('خطأ في الاتصال');
        } finally {
            sendBtn.disabled = false;
            sendBtn.textContent = oldText;
        }
    });

    // Auto-resize textarea
    input.addEventListener('input', () => {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 120) + 'px';
    });

    // Enter للإرسال، Shift+Enter لسطر جديد
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            form.requestSubmit();
        }
    });

    // Polling كل 3 ثواني للرسائل الجديدة
    async function poll() {
        try {
            const res = await fetch(`chat_poll.php?conversation_id=${conversationId}&after=${lastMessageId()}`);
            const data = await res.json();
            if (data.ok && data.messages.length) {
                data.messages.forEach(appendMessage);
            }
        } catch (err) { /* ignore */ }
    }
    setInterval(poll, 3000);

    scrollToBottom();
})();
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
