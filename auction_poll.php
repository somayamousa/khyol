<?php
require_once 'config/db.php';
require_once 'includes/notify.php';
header('Content-Type: application/json; charset=utf-8');

$auction_id = (int)($_GET['id'] ?? 0);
$after = (int)($_GET['after'] ?? 0);

$conn->prepare("UPDATE auctions SET status='ended' WHERE id=? AND status='live' AND ends_at <= NOW()")->execute([$auction_id]);

$a_stmt = $conn->prepare("SELECT current_bid, bids_count, status, leading_bidder_id, winner_id, winner_amount, title FROM auctions WHERE id = ?");
$a_stmt->execute([$auction_id]);
$a = $a_stmt->fetch();
if (!$a) { echo json_encode(['ok'=>false]); exit; }

// إرسال إشعار الفائز مرة واحدة عند انتهاء المزاد بالوقت
if ($a['status'] === 'ended' && !$a['winner_id'] && $a['leading_bidder_id']) {
    $conn->prepare("UPDATE auctions SET winner_id=?, winner_amount=? WHERE id=? AND winner_id IS NULL")
        ->execute([$a['leading_bidder_id'], $a['current_bid'], $auction_id]);
    if ($conn->rowCount() > 0) {
        send_notification(
            $conn,
            (int)$a['leading_bidder_id'],
            '🏆 مبروك! ربحت المزاد',
            'فزت بمزاد "' . $a['title'] . '" بمبلغ ' . number_format($a['current_bid'], 0) . ' ₪. سيتم التواصل معك قريباً.',
            'auction',
            '🏆',
            'auction.php?id=' . $auction_id
        );
    }
}

$b_stmt = $conn->prepare("
    SELECT b.id, b.amount, b.is_buyout, b.created_at, u.full_name AS name, u.avatar
    FROM auction_bids b
    JOIN users u ON u.id = b.bidder_id
    WHERE b.auction_id = ? AND b.id > ?
    ORDER BY b.id ASC
    LIMIT 30
");
$b_stmt->execute([$auction_id, $after]);
$rows = $b_stmt->fetchAll();

$new_bids = array_map(function($r) {
    return [
        'id' => (int)$r['id'],
        'amount' => (float)$r['amount'],
        'is_buyout' => (int)$r['is_buyout'],
        'name' => $r['name'],
        'avatar' => $r['avatar'],
        'time' => date('H:i', strtotime($r['created_at']))
    ];
}, $rows);

echo json_encode([
    'ok' => true,
    'current_bid' => (float)($a['current_bid'] ?: 0),
    'bids_count' => (int)$a['bids_count'],
    'status' => $a['status'],
    'new_bids' => array_reverse($new_bids) // أحدث أولاً
]);
