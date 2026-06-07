<?php
$page_title = 'حجز جلسة تصوير';
require_once 'config/db.php';
require_once 'includes/notify.php';

if (!isLoggedIn()) redirect('login.php?redirect=photoshoots');

$pkg_id = (int)($_GET['package'] ?? 0);
$pkg_stmt = $conn->prepare("
    SELECT p.*, c.name AS center_name, c.city AS center_city
    FROM photoshoot_packages p
    LEFT JOIN centers c ON c.id = p.center_id
    WHERE p.id = ? AND p.is_active = 1
");
$pkg_stmt->execute([$pkg_id]);
$pkg = $pkg_stmt->fetch();
if (!$pkg) redirect('photoshoots.php');

$horses_stmt = $conn->prepare("SELECT id, name FROM horses WHERE owner_id = ?");
$horses_stmt->execute([$_SESSION['user_id']]);
$user_horses = $horses_stmt->fetchAll();

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'] ?? '';
    $time = $_POST['time'] ?? '';
    $horse_id = (int)($_POST['horse_id'] ?? 0) ?: null;
    $raw_outfits = is_array($_POST['outfit_choices'] ?? null) ? $_POST['outfit_choices'] : [];
    $max_allowed = (int)($pkg['max_outfit_choices'] ?? 1);
    if (count($raw_outfits) > $max_allowed) {
        $raw_outfits = array_slice($raw_outfits, 0, $max_allowed);
    }
    $outfits = $raw_outfits ? implode(',', $raw_outfits) : null;
    $notes = sanitize($_POST['notes'] ?? '');

    if (!$date || !$time) {
        $error = 'الرجاء اختيار التاريخ والوقت';
    } elseif (strtotime($date) < strtotime(date('Y-m-d'))) {
        $error = 'لا يمكن الحجز لتاريخ سابق';
    } elseif (!empty($pkg['photographer_id'])) {
        $conflict = $conn->prepare("
            SELECT COUNT(*) FROM photoshoot_bookings pb
            JOIN photoshoot_packages pk ON pk.id = pb.package_id
            WHERE pk.photographer_id = ? AND pb.session_date = ? AND pb.session_time = ? AND pb.status != 'cancelled'
        ");
        $conflict->execute([$pkg['photographer_id'], $date, $time]);
        if ($conflict->fetchColumn() > 0) $error = 'هذا الوقت محجوز مسبقاً، الرجاء اختيار وقت آخر.';
    }
    if (empty($error)) {
        $ins = $conn->prepare("INSERT INTO photoshoot_bookings (user_id, package_id, horse_id, session_date, session_time, outfit_choices, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($ins->execute([$_SESSION['user_id'], $pkg_id, $horse_id, $date, $time, $outfits, $notes])) {
            $success = true;
            $booking_id = (int)$conn->lastInsertId();

            // إشعار للعميل
            send_notification(
                $conn,
                (int)$_SESSION['user_id'],
                'تم حجز جلسة التصوير',
                'باقة "' . $pkg['title'] . '" بتاريخ ' . $date . ' الساعة ' . substr($time,0,5),
                'photoshoot',
                '📸',
                'account.php?tab=photoshoots'
            );

            // إشعار للمصور
            if (!empty($pkg['photographer_id'])) {
                $photographer_owner = $conn->prepare("SELECT owner_id FROM photographers WHERE id = ?");
                $photographer_owner->execute([$pkg['photographer_id']]);
                $pg_owner_id = (int)$photographer_owner->fetchColumn();
                if ($pg_owner_id) {
                    send_notification(
                        $conn,
                        $pg_owner_id,
                        'حجز جديد! 📸',
                        'حجز جلسة "' . $pkg['title'] . '" بتاريخ ' . $date . ' الساعة ' . substr($time,0,5),
                        'photoshoot',
                        '📅',
                        'my-studio.php?tab=sessions'
                    );
                }
            }
        } else {
            $error = 'حدث خطأ أثناء الحجز';
        }
    }
}

include 'includes/header.php';

$outfit_options = array_map('trim', preg_split('/[,،]+/u', $pkg['free_outfits'] ?? ''));
?>

<div class="page-header">
    <div class="container">
        <h1>📸 حجز جلسة تصوير</h1>
    </div>
</div>

<div class="container">
    <div class="booking-card">
        <?php if ($success): ?>
            <div style="text-align:center; padding: 30px;">
                <div style="font-size:80px;">✅</div>
                <h2 style="color: var(--gold);">تم حجز الجلسة!</h2>
                <p>سيتم التواصل معك لتأكيد التفاصيل.</p>
                <a href="photoshoots.php" class="btn btn-outline" style="color:#1a1510 !important;">باقات أخرى</a>
            </div>
        <?php else: ?>
            <h2 style="color: var(--gold); text-align:center;">📷 <?= sanitize($pkg['title']) ?></h2>
            <div class="booking-summary">
                <p><strong>السعر:</strong> <span style="color: var(--gold); font-weight:900;"><?= number_format($pkg['price'],0) ?> ₪</span></p>
                <p><strong>المدة:</strong> <?= (int)$pkg['duration_minutes'] ?> دقيقة</p>
                <p><strong>عدد الصور:</strong> <?= (int)$pkg['photos_count'] ?></p>
                <?php if ($pkg['free_outfits']): ?><p>🎁 ملابس مجانية: <?= sanitize($pkg['free_outfits']) ?></p><?php endif; ?>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">⚠️ <?= $error ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>📅 التاريخ</label>
                        <input type="date" name="date" id="psDate" required min="<?= date('Y-m-d') ?>" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>🕐 الوقت</label>
                        <select name="time" id="psTime" required class="form-control">
                            <option value="">اختر تاريخاً أولاً</option>
                        </select>
                        <small id="psSlotHint" style="color:var(--gold);font-size:12px;margin-top:4px;display:none;">
                            ⚠️ الأوقات الرمادية محجوزة مسبقاً
                        </small>
                    </div>
                </div>

                <script>
                (function(){
                    var dateEl = document.getElementById('psDate');
                    var timeEl = document.getElementById('psTime');
                    var hint   = document.getElementById('psSlotHint');
                    var pgId   = <?= (int)($pkg['photographer_id'] ?? 0) ?>;
                    var allSlots = <?php
                        $slots = [];
                        for ($h = 8; $h <= 20; $h++) $slots[] = sprintf('%02d:00', $h);
                        echo json_encode($slots);
                    ?>;

                    function buildSlots(booked) {
                        timeEl.innerHTML = '';
                        var hasBooked = booked.length > 0;
                        allSlots.forEach(function(s) {
                            var isBooked = booked.indexOf(s) !== -1;
                            var opt = document.createElement('option');
                            opt.value = s;
                            opt.textContent = isBooked ? s + ' — محجوز' : s;
                            opt.disabled = isBooked;
                            if (isBooked) opt.style.color = '#aaa';
                            timeEl.appendChild(opt);
                        });
                        hint.style.display = hasBooked ? 'block' : 'none';
                    }

                    dateEl.addEventListener('change', function() {
                        var d = this.value;
                        if (!d) { timeEl.innerHTML = '<option value="">اختر تاريخاً أولاً</option>'; return; }
                        if (!pgId) { buildSlots([]); return; }
                        fetch('get_booked_slots.php?type=photoshoot&id=' + pgId + '&date=' + d)
                            .then(function(r){ return r.json(); })
                            .then(function(booked){ buildSlots(booked); })
                            .catch(function(){ buildSlots([]); });
                    });
                })();
                </script>

                <div class="form-group">
                    <label>🐎 اختر فرسك (اختياري)</label>
                    <select name="horse_id" class="form-control">
                        <option value="">— يخصص من المركز —</option>
                        <?php foreach ($user_horses as $h): ?>
                            <option value="<?= $h['id'] ?>"><?= sanitize($h['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php
                $max_outfit = (int)($pkg['max_outfit_choices'] ?? 1);
                $valid_outfits = array_values(array_filter($outfit_options, 'trim'));

                /* Map outfit names to icons — fallback to 👕 */
                $outfit_icons = [
                    'كيوت'   => '👗',
                    'بوت'    => '👢',
                    'قبعة'   => '🤠',
                    'جاكيت'  => '🧥',
                    'بلوزة'  => '👚',
                    'فستان'  => '👘',
                    'عباءة'  => '🥻',
                    'طاقية'  => '🧢',
                    'حزام'   => '🪢',
                    'وشاح'   => '🧣',
                    'قفاز'   => '🧤',
                    'سترة'   => '🦺',
                ];
                function outfit_icon($name, $map) {
                    foreach ($map as $k => $v) {
                        if (mb_strpos($name, $k) !== false) return $v;
                    }
                    return '🎁';
                }

                if ($valid_outfits):
                ?>
                <div class="form-group outfit-section">
                    <div class="outfit-header">
                        <div class="outfit-header-title">
                            <span class="outfit-title-icon">🎽</span>
                            <span>اختر الملابس المجانية</span>
                        </div>
                        <div class="outfit-pill" id="outfitPill">
                            تم اختيار <span id="outfitChosen">0</span> / <?= $max_outfit ?>
                        </div>
                    </div>
                    <p class="outfit-hint">
                        <?= $max_outfit > 1
                            ? 'انقر على البطاقات لاختيار حتى <strong>' . $max_outfit . '</strong> قطع مجانية'
                            : 'انقر على البطاقة لاختيار قطعة واحدة مجانية' ?>
                    </p>

                    <!-- Hidden inputs written by JS -->
                    <div id="outfitHiddenInputs"></div>

                    <div class="outfit-grid" id="outfitGrid" data-max="<?= $max_outfit ?>">
                        <?php foreach ($valid_outfits as $idx => $o):
                            $icon = outfit_icon($o, $outfit_icons);
                            $safe = sanitize($o);
                        ?>
                        <div class="outfit-card" data-value="<?= $safe ?>" tabindex="0" role="button" aria-pressed="false">
                            <div class="outfit-card-badge">✓</div>
                            <div class="outfit-card-icon"><?= $icon ?></div>
                            <div class="outfit-card-name"><?= $safe ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>📝 ملاحظات</label>
                    <textarea name="notes" rows="3" class="form-control" placeholder="مثلاً: أفضّل خلفية طبيعية، أحضر ابني..."></textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-block">✅ تأكيد الحجز</button>
            </form>

            <style>
            /* ── Outfit section header ── */
            .outfit-section { margin-top: 8px; }
            .outfit-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 6px;
            }
            .outfit-header-title {
                display: flex;
                align-items: center;
                gap: 7px;
                font-weight: 700;
                font-size: 15px;
                color: inherit;
            }
            .outfit-title-icon { font-size: 18px; line-height: 1; }
            .outfit-pill {
                font-size: 12px;
                font-weight: 700;
                padding: 3px 13px;
                border-radius: 30px;
                background: rgba(201,162,39,0.13);
                color: var(--gold, #c9a227);
                border: 1px solid rgba(201,162,39,0.35);
                white-space: nowrap;
                transition: background 0.2s;
            }
            .outfit-pill.full {
                background: rgba(201,162,39,0.25);
                color: var(--gold, #c9a227);
            }
            .outfit-hint {
                font-size: 12.5px;
                color: rgba(255,255,255,0.45);
                margin: 0 0 14px;
                line-height: 1.5;
            }
            [data-theme='light'] .outfit-hint { color: #7a6e60; }

            /* ── Grid ── */
            .outfit-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
                gap: 12px;
            }

            /* ── Individual card ── */
            .outfit-card {
                position: relative;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 10px;
                padding: 20px 12px 16px;
                border-radius: 14px;
                border: 2px solid rgba(201,162,39,0.18);
                background: rgba(255,255,255,0.04);
                cursor: pointer;
                user-select: none;
                transition: border-color 0.2s, background 0.2s, box-shadow 0.2s, transform 0.15s;
                outline: none;
            }
            .outfit-card:hover:not(.disabled) {
                border-color: rgba(201,162,39,0.5);
                background: rgba(201,162,39,0.07);
                transform: translateY(-2px);
            }
            .outfit-card:focus-visible {
                box-shadow: 0 0 0 3px rgba(201,162,39,0.4);
            }
            .outfit-card.selected {
                border-color: var(--gold, #c9a227);
                background: rgba(201,162,39,0.12);
                box-shadow: 0 0 18px rgba(201,162,39,0.25), 0 4px 16px rgba(0,0,0,0.25);
                transform: translateY(-2px);
            }
            .outfit-card.disabled {
                opacity: 0.35;
                cursor: not-allowed;
                pointer-events: none;
            }

            /* Selected badge (top-left corner) */
            .outfit-card-badge {
                position: absolute;
                top: 8px;
                left: 8px;
                width: 22px;
                height: 22px;
                border-radius: 50%;
                background: var(--gold, #c9a227);
                color: #0d0a05;
                font-size: 13px;
                font-weight: 900;
                display: flex;
                align-items: center;
                justify-content: center;
                opacity: 0;
                transform: scale(0.5);
                transition: opacity 0.2s, transform 0.2s;
            }
            .outfit-card.selected .outfit-card-badge {
                opacity: 1;
                transform: scale(1);
            }

            /* Icon */
            .outfit-card-icon {
                font-size: 36px;
                line-height: 1;
                filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
                transition: transform 0.2s;
            }
            .outfit-card.selected .outfit-card-icon {
                transform: scale(1.1);
            }

            /* Name */
            .outfit-card-name {
                font-size: 13px;
                font-weight: 700;
                text-align: center;
                line-height: 1.3;
                color: #1a1510;
            }
            [data-theme='light'] .outfit-card {
                background: rgba(0,0,0,0.025);
                border-color: rgba(201,162,39,0.2);
            }
            [data-theme='light'] .outfit-card:hover:not(.disabled) {
                background: rgba(201,162,39,0.06);
            }
            [data-theme='light'] .outfit-card.selected {
                background: rgba(201,162,39,0.1);
            }
            [data-theme='light'] .outfit-card-name { color: #1a1510; }
            </style>

            <script>
            (function() {
                var grid = document.getElementById('outfitGrid');
                if (!grid) return;
                var max   = parseInt(grid.dataset.max) || 1;
                var pill  = document.getElementById('outfitPill');
                var span  = document.getElementById('outfitChosen');
                var hidden = document.getElementById('outfitHiddenInputs');
                var selected = [];

                function syncHidden() {
                    hidden.innerHTML = '';
                    selected.forEach(function(v) {
                        var inp = document.createElement('input');
                        inp.type  = 'hidden';
                        inp.name  = 'outfit_choices[]';
                        inp.value = v;
                        hidden.appendChild(inp);
                    });
                }

                function render() {
                    var count = selected.length;
                    if (span) span.textContent = count;
                    if (pill) pill.classList.toggle('full', count >= max);

                    grid.querySelectorAll('.outfit-card').forEach(function(card) {
                        var val = card.dataset.value;
                        var isSel = selected.indexOf(val) !== -1;
                        card.classList.toggle('selected', isSel);
                        card.setAttribute('aria-pressed', isSel ? 'true' : 'false');
                        if (!isSel && count >= max) {
                            card.classList.add('disabled');
                        } else {
                            card.classList.remove('disabled');
                        }
                    });
                    syncHidden();
                }

                grid.querySelectorAll('.outfit-card').forEach(function(card) {
                    function toggle() {
                        var val = card.dataset.value;
                        var idx = selected.indexOf(val);
                        if (idx !== -1) {
                            selected.splice(idx, 1);
                        } else if (selected.length < max) {
                            selected.push(val);
                        }
                        render();
                    }
                    card.addEventListener('click', toggle);
                    card.addEventListener('keydown', function(e) {
                        if (e.key === ' ' || e.key === 'Enter') { e.preventDefault(); toggle(); }
                    });
                });

                render();
            })();
            </script>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
