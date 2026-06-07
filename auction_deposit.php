<?php
$page_title = 'دفع العربون';
require_once 'config/db.php';
require_once 'includes/notify.php';

if (!isLoggedIn()) redirect('login.php?redirect=auctions');

$id      = (int)($_GET['id'] ?? 0);
$type    = ($_GET['type'] ?? '') === 'winner' ? 'winner' : 'pre';
$user_id = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT a.*, u.full_name AS seller_name, w.full_name AS winner_name
    FROM auctions a
    JOIN users u ON u.id = a.seller_id
    LEFT JOIN users w ON w.id = a.winner_id
    WHERE a.id = ?
");
$stmt->execute([$id]);
$auction = $stmt->fetch();

if (!$auction) redirect('auctions.php');
if ((int)$auction['seller_id'] === $user_id) redirect('auction.php?id=' . $id);

$dep_pct = (float)$auction['deposit_pct'];
if ($dep_pct <= 0) redirect('auction.php?id=' . $id);

if ($type === 'winner') {
    // عربون الفائز: بعد انتهاء المزاد
    if ($auction['status'] !== 'ended') redirect('auction.php?id=' . $id);
    if ((int)$auction['winner_id'] !== $user_id) redirect('auction.php?id=' . $id);
    if ($auction['deposit_paid']) redirect('auction.php?id=' . $id . '&paid=1');
    $dep_amount    = round((float)$auction['winner_amount'] * $dep_pct / 100, 2);
    $deposit_label = 'عربون الفوز';
    $success_msg   = 'سيتم إشعار البائع لإتمام الصفقة معك.';
    $success_btn   = ['href' => 'auction.php?id=' . $id, 'label' => 'العودة للمزاد'];
} else {
    // عربون المشاركة: قبل المزايدة
    if (!in_array($auction['status'], ['live', 'scheduled'])) redirect('auction.php?id=' . $id);
    $already = $conn->prepare("SELECT id FROM auction_deposits WHERE auction_id=? AND user_id=?");
    $already->execute([$id, $user_id]);
    if ($already->fetch()) redirect('auction.php?id=' . $id . '&dep=1');
    $dep_amount    = round((float)$auction['starting_price'] * $dep_pct / 100, 2);
    $deposit_label = 'عربون المشاركة';
    $success_msg   = 'يمكنك الآن المزايدة على هذا المزاد.';
    $success_btn   = ['href' => 'auction.php?id=' . $id, 'label' => '🔨 ابدأ المزايدة'];
}

$error   = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $method        = sanitize($_POST['pay_method'] ?? '');
    $card_number   = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
    $card_name     = sanitize($_POST['card_name'] ?? '');
    $card_expiry   = sanitize($_POST['card_expiry'] ?? '');
    $card_cvv      = preg_replace('/\D/', '', $_POST['card_cvv'] ?? '');
    $wallet_number = preg_replace('/\D/', '', $_POST['wallet_number'] ?? '');

    if (!in_array($method, ['card', 'wallet'])) {
        $error = 'اختر طريقة الدفع';
    } elseif ($method === 'card') {
        if (strlen($card_number) < 16) $error = 'رقم البطاقة غير صحيح';
        elseif (!$card_name)           $error = 'أدخل اسم حامل البطاقة';
        elseif (!$card_expiry)         $error = 'أدخل تاريخ الانتهاء';
        elseif (strlen($card_cvv) < 3) $error = 'رمز CVV غير صحيح';
    } elseif ($method === 'wallet') {
        if (strlen($wallet_number) < 9) $error = 'رقم المحفظة غير صحيح';
    }

    if (!$error) {
        if ($type === 'winner') {
            $upd = $conn->prepare("UPDATE auctions SET deposit_paid=1, deposit_amount=? WHERE id=? AND winner_id=? AND deposit_paid=0");
            $upd->execute([$dep_amount, $id, $user_id]);
            $done = $upd->rowCount() > 0;
            if ($done) {
                send_notification($conn, (int)$auction['seller_id'],
                    '💳 الفائز دفع العربون!',
                    $auction['winner_name'] . ' دفع عربون الفوز (' . number_format($dep_amount, 0) . ' ₪) لمزاد "' . $auction['title'] . '" — يمكنك التواصل معه لإتمام الصفقة.',
                    'auction', '💰', 'auction.php?id=' . $id
                );
            }
        } else {
            $ins = $conn->prepare("INSERT IGNORE INTO auction_deposits (auction_id, user_id, amount, pay_method) VALUES (?, ?, ?, ?)");
            $ins->execute([$id, $user_id, $dep_amount, $method]);
            $done = $conn->lastInsertId() > 0;
            if ($done) {
                send_notification($conn, (int)$auction['seller_id'],
                    '💳 مزايد جديد دفع العربون',
                    'شخص دفع عربون المشاركة (' . number_format($dep_amount, 0) . ' ₪) في مزاد "' . $auction['title'] . '".',
                    'auction', '💰', 'auction.php?id=' . $id
                );
            }
        }

        if ($done) {
            $success = true;
        } else {
            $error = 'تم الدفع مسبقاً أو حدث خطأ';
        }
    }
}

include 'includes/header.php';
?>

<div class="page-header">
    <div class="container">
        <h1>💳 <?= $deposit_label ?></h1>
        <p><?= $type === 'winner' ? 'تأكيد فوزك بدفع عربون الصفقة' : 'ادفع العربون لتتمكن من المزايدة' ?></p>
    </div>
</div>

<div class="container" style="padding: 40px 20px; max-width: 640px;">

<?php if ($success): ?>
    <div class="booking-card" style="text-align:center; padding: 50px 30px;">
        <div style="font-size:64px; margin-bottom:16px;">✅</div>
        <h2 style="color: var(--gold); margin-bottom:10px;">تم دفع العربون بنجاح!</h2>
        <p style="color: var(--text-dark); margin-bottom:6px;">
            دفعت <strong><?= $deposit_label ?></strong> بمبلغ <strong><?= number_format($dep_amount, 0) ?> ₪</strong>
        </p>
        <p style="color:#888; font-size:13px; margin-bottom:30px;"><?= $success_msg ?></p>
        <a href="<?= $success_btn['href'] ?>" class="btn btn-primary"><?= $success_btn['label'] ?></a>
    </div>
<?php else: ?>

    <div class="booking-card" style="margin-bottom:24px;">
        <h3 style="color:var(--gold); margin-bottom:14px;">📋 تفاصيل العربون</h3>

        <?php if ($type === 'pre'): ?>
        <div style="background:rgba(201,162,39,0.08); border-right:3px solid var(--gold); padding:12px 16px; border-radius:8px; margin-bottom:16px; font-size:13px; line-height:1.8; color:#1a1510;">
            🔒 العربون يُدفع مرة واحدة قبل أول مزايدة ويثبت جدية عرضك.
        </div>
        <?php endif; ?>

        <div style="display:grid; gap:8px; font-size:14px; color:#1a1510;">
            <div style="display:flex; justify-content:space-between;">
                <span>المزاد</span>
                <strong><?= sanitize($auction['title']) ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between;">
                <span><?= $type === 'winner' ? 'سعر الفوز' : 'سعر البداية' ?></span>
                <strong><?= number_format($type === 'winner' ? $auction['winner_amount'] : $auction['starting_price'], 0) ?> ₪</strong>
            </div>
            <div style="display:flex; justify-content:space-between; border-top:1px solid rgba(201,162,39,0.2); padding-top:10px; margin-top:4px;">
                <span>نسبة العربون</span>
                <strong><?= $dep_pct ?>%</strong>
            </div>
            <div style="display:flex; justify-content:space-between; font-size:18px;">
                <span><?= $deposit_label ?></span>
                <strong style="color:var(--gold);"><?= number_format($dep_amount, 0) ?> ₪</strong>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="mc-alert error" style="margin-bottom:20px;">⚠️ <?= $error ?></div>
    <?php endif; ?>

    <div class="booking-card">
        <h3 style="color:var(--gold); margin-bottom:20px;">💳 اختر طريقة الدفع</h3>
        <form method="POST" id="depositForm">
            <div style="display:flex; gap:12px; margin-bottom:24px;">
                <label class="pay-method-btn active" id="lbl-card">
                    <input type="radio" name="pay_method" value="card" checked onchange="switchMethod('card')">
                    <span>💳 بطاقة بنكية</span>
                </label>
                <label class="pay-method-btn" id="lbl-wallet">
                    <input type="radio" name="pay_method" value="wallet" onchange="switchMethod('wallet')">
                    <span>📱 محفظة إلكترونية</span>
                </label>
            </div>

            <div id="card-fields">
                <div class="form-group">
                    <label>رقم البطاقة</label>
                    <input type="text" name="card_number" class="form-control" placeholder="0000 0000 0000 0000" maxlength="19" oninput="fmtCard(this)" style="direction:ltr; text-align:left; letter-spacing:2px;">
                </div>
                <div class="form-group">
                    <label>اسم حامل البطاقة</label>
                    <input type="text" name="card_name" class="form-control" placeholder="الاسم كما يظهر على البطاقة">
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                    <div class="form-group">
                        <label>تاريخ الانتهاء</label>
                        <input type="text" name="card_expiry" class="form-control" placeholder="MM/YY" maxlength="5" oninput="fmtExpiry(this)" style="direction:ltr; text-align:left;">
                    </div>
                    <div class="form-group">
                        <label>CVV</label>
                        <input type="text" name="card_cvv" class="form-control" placeholder="123" maxlength="4" style="direction:ltr; text-align:left;">
                    </div>
                </div>
            </div>

            <div id="wallet-fields" style="display:none;">
                <div class="form-group">
                    <label>رقم المحفظة (جوال)</label>
                    <input type="text" name="wallet_number" class="form-control" placeholder="05xxxxxxxx" maxlength="15">
                </div>
                <p style="color:#888; font-size:13px; margin-top:-8px; margin-bottom:16px;">
                    سيتم خصم المبلغ من محفظتك الإلكترونية المرتبطة بهذا الرقم.
                </p>
            </div>

            <div style="background:rgba(201,162,39,0.08); border:1px solid rgba(201,162,39,0.3); border-radius:10px; padding:14px; margin-bottom:20px; font-size:13px; color:#1a1510; line-height:1.8;">
                ⚠️ هذا نظام دفع تجريبي. لا يتم خصم أي مبلغ حقيقي.<br>
                المبلغ المراد دفعه: <strong><?= number_format($dep_amount, 0) ?> ₪</strong>
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="font-size:16px; padding:14px;">
                💳 تأكيد الدفع (<?= number_format($dep_amount, 0) ?> ₪)
            </button>
            <a href="auction.php?id=<?= $id ?>" class="btn btn-outline btn-block" style="margin-top:10px; text-align:center; display:block;">
                العودة للمزاد
            </a>
        </form>
    </div>
<?php endif; ?>
</div>

<style>
.pay-method-btn {
    flex:1; border:2px solid rgba(201,162,39,0.3); border-radius:10px;
    padding:14px; text-align:center; cursor:pointer; transition:all 0.2s;
    color:#1a1510 !important; font-size:14px; font-weight:600;
}
.pay-method-btn input[type=radio] { display:none; }
.pay-method-btn.active { border-color:var(--gold); background:rgba(201,162,39,0.12); }
.pay-method-btn span { color:#1a1510 !important; }
#depositForm label, #depositForm .form-group label { color:#1a1510 !important; }
#depositForm .form-control { color:#1a1510 !important; background:#fafaf7 !important; }
#depositForm .form-control::placeholder { color:#888 !important; }
.booking-card h3 { color:var(--gold) !important; }
.booking-card div, .booking-card span, .booking-card strong, .booking-card p { color:#1a1510; }
</style>

<script>
function switchMethod(m) {
    document.getElementById('card-fields').style.display   = m === 'card'   ? '' : 'none';
    document.getElementById('wallet-fields').style.display = m === 'wallet' ? '' : 'none';
    document.getElementById('lbl-card').classList.toggle('active',   m === 'card');
    document.getElementById('lbl-wallet').classList.toggle('active', m === 'wallet');
}
function fmtCard(el) {
    let v = el.value.replace(/\D/g,'').substring(0,16);
    el.value = v.replace(/(.{4})/g,'$1 ').trim();
}
function fmtExpiry(el) {
    let v = el.value.replace(/\D/g,'').substring(0,4);
    if (v.length >= 3) v = v.substring(0,2) + '/' + v.substring(2);
    el.value = v;
}
</script>

<?php include 'includes/footer.php'; ?>
