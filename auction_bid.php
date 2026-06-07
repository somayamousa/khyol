<?php
require_once 'config/db.php';
require_once 'includes/notify.php';
header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['ok'=>false, 'error'=>'يجب تسجيل الدخول']);
    exit;
}

$auction_id = (int)($_POST['auction_id'] ?? 0);
$amount = (float)($_POST['amount'] ?? 0);
$user_id = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM auctions WHERE id = ?");
$stmt->execute([$auction_id]);
$auction = $stmt->fetch();

if (!$auction) { echo json_encode(['ok'=>false,'error'=>'مزاد غير موجود']); exit; }

// تحقق من الحالة
if ($auction['status'] !== 'live' || strtotime($auction['ends_at']) <= time()) {
    echo json_encode(['ok'=>false,'error'=>'المزاد غير مباشر حالياً']);
    exit;
}
if ((int)$auction['seller_id'] === $user_id) {
    echo json_encode(['ok'=>false,'error'=>'لا يمكنك المزايدة على مزادك']);
    exit;
}

// تحقق من دفع العربون قبل أول مزايدة
if ((float)$auction['deposit_pct'] > 0) {
    $dep_check = $conn->prepare("SELECT id FROM auction_deposits WHERE auction_id=? AND user_id=?");
    $dep_check->execute([$auction_id, $user_id]);
    if (!$dep_check->fetch()) {
        $dep_amount = round(((float)$auction['starting_price'] * (float)$auction['deposit_pct'] / 100));
        echo json_encode(['ok'=>false,'error'=>'يجب دفع العربون أولاً للمشاركة في المزاد','need_deposit'=>true,'deposit_url'=>'auction_deposit.php?id='.$auction_id,'deposit_amount'=>$dep_amount]);
        exit;
    }
}

$current = (float)($auction['current_bid'] ?: $auction['starting_price']);
$min_next = $current + (float)($auction['min_increment'] ?: 100);

$is_buyout = 0;
if ($auction['buyout_price'] && $amount >= (float)$auction['buyout_price']) {
    $amount = (float)$auction['buyout_price'];
    $is_buyout = 1;
} elseif ($amount < $min_next) {
    echo json_encode(['ok'=>false,'error'=>'الحد الأدنى للمزايدة: ' . number_format($min_next,0) . ' ₪']);
    exit;
}

try {
    $conn->beginTransaction();
    $ins = $conn->prepare("INSERT INTO auction_bids (auction_id, bidder_id, amount, is_buyout) VALUES (?, ?, ?, ?)");
    $ins->execute([$auction_id, $user_id, $amount, $is_buyout]);
    $bid_id = (int)$conn->lastInsertId();

    $upd_data = [$amount, $user_id, $auction_id];
    $upd_sql = "UPDATE auctions SET current_bid=?, leading_bidder_id=?, bids_count = bids_count + 1";
    if ($is_buyout) {
        $upd_sql .= ", status='ended', winner_id=?, winner_amount=?, ends_at=NOW()";
        $upd_data = [$amount, $user_id, $user_id, $amount, $auction_id];
    }
    $upd_sql .= " WHERE id=?";
    $conn->prepare($upd_sql)->execute($upd_data);

    // إشعار للبائع
    send_notification(
        $conn,
        (int)$auction['seller_id'],
        '💸 مزايدة جديدة على ' . $auction['title'],
        'مزايدة بمبلغ ' . number_format($amount,0) . ' ₪' . ($is_buyout ? ' (شراء فوري)' : ''),
        'auction',
        '🔨',
        'auction.php?id=' . $auction_id
    );

    // إشعار للفائز عند الشراء الفوري
    if ($is_buyout) {
        send_notification(
            $conn,
            $user_id,
            '🏆 مبروك! أنهيت المزاد بشراء فوري',
            'اشتريت "' . $auction['title'] . '" بمبلغ ' . number_format($amount, 0) . ' ₪. سيتواصل معك البائع قريباً.',
            'auction',
            '🏆',
            'auction.php?id=' . $auction_id
        );
    }

    // إشعار للمزايد السابق إذا تم تجاوزه
    if (!$is_buyout && $auction['leading_bidder_id'] && (int)$auction['leading_bidder_id'] !== $user_id) {
        send_notification(
            $conn,
            (int)$auction['leading_bidder_id'],
            '⚠️ تم تجاوز مزايدتك',
            'تمت المزايدة بمبلغ أعلى على "' . $auction['title'] . '". زد مزايدتك للبقاء في الصدارة!',
            'auction',
            '⚠️',
            'auction.php?id=' . $auction_id
        );
    }

    $conn->commit();
    echo json_encode(['ok'=>true, 'bid_id'=>$bid_id, 'current_bid'=>$amount, 'is_buyout'=>$is_buyout]);
} catch (Throwable $e) {
    $conn->rollBack();
    echo json_encode(['ok'=>false,'error'=>'فشل تسجيل المزايدة']);
}
