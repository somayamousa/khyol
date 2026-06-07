<?php
require_once 'config/db.php';

$id = (int)($_GET['id'] ?? 0);

// تحديث حالة المزاد
$conn->prepare("UPDATE auctions SET status='live' WHERE id=? AND status='scheduled' AND starts_at <= NOW() AND ends_at > NOW()")->execute([$id]);
$conn->prepare("UPDATE auctions SET status='ended' WHERE id=? AND status IN ('scheduled','live') AND ends_at <= NOW()")->execute([$id]);

$stmt = $conn->prepare("
    SELECT a.*, u.full_name AS seller_name, u.phone AS seller_phone, w.full_name AS winner_name,
           h.name AS horse_name, h.breed AS horse_breed, h.gender AS horse_gender, h.birth_date AS horse_birth
    FROM auctions a
    JOIN users u ON u.id = a.seller_id
    LEFT JOIN users w ON w.id = a.winner_id
    LEFT JOIN horses h ON h.id = a.horse_id
    WHERE a.id = ?
");
$stmt->execute([$id]);
$auction = $stmt->fetch();
if (!$auction) redirect('auctions.php');

// إذا انتهى ولم يُحدد فائز بعد (الـ poll عادةً يسبق هذا، لكن كـ fallback)
if ($auction['status'] === 'ended' && !$auction['winner_id'] && $auction['leading_bidder_id']) {
    require_once 'includes/notify.php';
    $upd = $conn->prepare("UPDATE auctions SET winner_id=?, winner_amount=? WHERE id=? AND winner_id IS NULL");
    $upd->execute([$auction['leading_bidder_id'], $auction['current_bid'], $id]);
    if ($upd->rowCount() > 0) {
        $dep_msg = '';
        if ((float)$auction['deposit_pct'] > 0) {
            $dep_amt = round($auction['current_bid'] * $auction['deposit_pct'] / 100, 0);
            $dep_msg = ' يرجى دفع عربون الفوز (' . number_format($dep_amt, 0) . ' ₪) لتأكيد الصفقة.';
        }
        send_notification(
            $conn,
            (int)$auction['leading_bidder_id'],
            '🏆 مبروك! ربحت المزاد',
            'فزت بمزاد "' . $auction['title'] . '" بمبلغ ' . number_format($auction['current_bid'], 0) . ' ₪.' . $dep_msg,
            'auction',
            '🏆',
            'auction.php?id=' . $id
        );
    }
    $refresh = $conn->prepare("
        SELECT a.*, u.full_name AS seller_name, u.phone AS seller_phone, w.full_name AS winner_name,
               h.name AS horse_name, h.breed AS horse_breed, h.gender AS horse_gender, h.birth_date AS horse_birth
        FROM auctions a
        JOIN users u ON u.id = a.seller_id
        LEFT JOIN users w ON w.id = a.winner_id
        LEFT JOIN horses h ON h.id = a.horse_id
        WHERE a.id = ?
    ");
    $refresh->execute([$id]);
    $auction = $refresh->fetch();
}

$page_title = $auction['title'];

$bids_stmt = $conn->prepare("
    SELECT b.*, u.full_name AS bidder_name, u.avatar
    FROM auction_bids b
    JOIN users u ON u.id = b.bidder_id
    WHERE b.auction_id = ?
    ORDER BY b.id DESC
    LIMIT 30
");
$bids_stmt->execute([$id]);
$bids = $bids_stmt->fetchAll();

$is_live = $auction['status'] === 'live';
$is_seller = isLoggedIn() && (int)$_SESSION['user_id'] === (int)$auction['seller_id'];
$min_next_bid = ($auction['current_bid'] ?: $auction['starting_price']) + ($auction['min_increment'] ?: 100);

include 'includes/header.php';
?>

<div class="container" style="padding: 30px 20px;">
    <h1 style="color: var(--text-dark); margin-bottom: 8px; font-size: 28px;">🔨 ساحة المزاد</h1>
    <div class="auction-room">
        <!-- العمود الأيمن: الصورة والمعلومات -->
        <div class="ar-left">
            <div class="ar-image-wrap">
                <?php if ($is_live): ?>
                    <span class="ac-badge-live" style="position:absolute;top:14px;right:14px;font-size:14px;padding:8px 14px;">⦿ LIVE</span>
                <?php elseif ($auction['status'] === 'ended'): ?>
                    <span class="ac-badge-ended" style="position:absolute;top:14px;right:14px;font-size:14px;padding:8px 14px;">🏁 انتهى</span>
                <?php else: ?>
                    <span class="ac-badge-upcoming" style="position:absolute;top:14px;right:14px;font-size:14px;padding:8px 14px;">⏳ قادم</span>
                <?php endif; ?>
                <img src="<?= sanitize($auction['main_image']) ?>" alt="<?= sanitize($auction['title']) ?>">
            </div>
            <div class="ar-details">
                <h2><?= sanitize($auction['title']) ?></h2>
                <p style="color: var(--text-dark); line-height: 2;"><?= nl2br(sanitize($auction['description'])) ?></p>
                <?php if ($auction['horse_name']): ?>
                    <div class="ar-horse-info">
                        <strong>🐎 الفرس:</strong> <?= sanitize($auction['horse_name']) ?>
                        <?php if ($auction['horse_breed']): ?> — <?= sanitize($auction['horse_breed']) ?><?php endif; ?>
                        <?php if ($auction['horse_birth']): ?> — <?= (int)((time()-strtotime($auction['horse_birth']))/(365.25*86400)) ?> سنوات<?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if ($auction['city']): ?>
                    <div class="ar-seller">
                        <strong>📍 المدينة:</strong> <?= sanitize($auction['city']) ?>
                    </div>
                <?php endif; ?>
                <div class="ar-seller">
                    <strong>البائع:</strong> <?= sanitize($auction['seller_name']) ?>
                </div>
            </div>
        </div>

        <!-- العمود الأيسر: العداد والمزايدة والسجل -->
        <div class="ar-right">
            <!-- العداد التنازلي -->
            <div class="ar-countdown" id="ar-countdown" data-end="<?= $auction['ends_at'] ?>" data-start="<?= $auction['starts_at'] ?>">
                <div class="cd-block"><span class="cd-num" data-cd="d">00</span><small>يوم</small></div>
                <div class="cd-sep">:</div>
                <div class="cd-block"><span class="cd-num" data-cd="h">00</span><small>ساعة</small></div>
                <div class="cd-sep">:</div>
                <div class="cd-block"><span class="cd-num" data-cd="m">00</span><small>دقيقة</small></div>
                <div class="cd-sep">:</div>
                <div class="cd-block"><span class="cd-num" data-cd="s">00</span><small>ثانية</small></div>
            </div>

            <!-- صندوق المزايدة -->
            <div class="ar-bid-box">
                <div class="ar-bid-stats">
                    <div>
                        <small>السعر الحالي</small>
                        <strong id="ar-current"><?= number_format($auction['current_bid'] ?: $auction['starting_price'], 0) ?> ₪</strong>
                    </div>
                    <div>
                        <small>الحد الأدنى للزيادة</small>
                        <strong><?= number_format($auction['min_increment'] ?: 100, 0) ?> ₪</strong>
                    </div>
                </div>

                <?php if ($auction['status'] === 'ended'): ?>
                    <?php if ($auction['winner_id']): ?>
                        <?php
                            $dep_pct    = (float)$auction['deposit_pct'];
                            $dep_amount = $dep_pct > 0 ? round((float)$auction['winner_amount'] * $dep_pct / 100, 2) : 0;
                            $is_winner  = isLoggedIn() && (int)$_SESSION['user_id'] === (int)$auction['winner_id'];
                        ?>
                        <div class="alert alert-success" style="margin-bottom:0; text-align:center;">
                            🏆 الفائز: <strong><?= sanitize($auction['winner_name']) ?></strong><br>
                            بمبلغ <strong><?= number_format($auction['winner_amount'], 0) ?> ₪</strong>

                            <?php if ($is_winner && $dep_pct > 0): ?>
                                <?php if ($auction['deposit_paid']): ?>
                                    <div style="margin-top:10px; background:#1a7a45; color:#fff; padding:8px 16px; border-radius:8px; font-size:14px; display:inline-block;">
                                        ✅ تم دفع العربون
                                    </div>
                                <?php else: ?>
                                    <div style="margin-top:8px; font-size:13px; color:#555;">
                                        العربون المطلوب: <strong><?= $dep_pct ?>% = <?= number_format($dep_amount, 0) ?> ₪</strong>
                                    </div>
                                    <div style="margin-top:10px;">
                                        <a href="auction_deposit.php?id=<?= $id ?>&type=winner" class="btn btn-primary" style="font-size:14px; padding:10px 20px;">
                                            💳 ادفع العربون الآن (<?= number_format($dep_amount, 0) ?> ₪)
                                        </a>
                                    </div>
                                <?php endif; ?>
                            <?php elseif ($is_seller): ?>
                                <?php if ($dep_pct > 0 && !$auction['deposit_paid']): ?>
                                    <div style="margin-top:6px; font-size:12px; color:#e67e22;">⏳ بانتظار دفع العربون من الفائز</div>
                                <?php elseif ($dep_pct > 0 && $auction['deposit_paid']): ?>
                                    <div style="margin-top:6px; font-size:12px; color:#27ae60;">✅ الفائز دفع العربون</div>
                                <?php endif; ?>
                                <div style="margin-top:10px;">
                                    <a href="chat.php?winner_id=<?= (int)$auction['winner_id'] ?>" class="btn btn-primary" style="font-size:13px; padding:6px 14px;">💬 التواصل مع الفائز</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info" style="margin-bottom:0;">انتهى المزاد بدون مزايدات</div>
                    <?php endif; ?>
                <?php elseif (!isLoggedIn()): ?>
                    <a href="login.php?redirect=auctions" class="btn btn-primary btn-block">🔐 سجّل الدخول للمزايدة</a>
                <?php elseif ($is_seller): ?>
                    <div class="alert alert-info" style="margin-bottom:0;">⚠️ لا يمكنك المزايدة على مزادك</div>
                <?php elseif ($is_live): ?>
                    <?php
                        // هل دفع المستخدم العربون المسبق؟
                        $need_deposit = false;
                        $pre_dep_amount = 0;
                        if ((float)$auction['deposit_pct'] > 0) {
                            $dep_check = $conn->prepare("SELECT id FROM auction_deposits WHERE auction_id=? AND user_id=?");
                            $dep_check->execute([$id, (int)$_SESSION['user_id']]);
                            if (!$dep_check->fetch()) {
                                $need_deposit = true;
                                $pre_dep_amount = round((float)$auction['starting_price'] * (float)$auction['deposit_pct'] / 100);
                            }
                        }
                    ?>
                    <?php if ($need_deposit): ?>
                        <div style="text-align:center; padding:16px;">
                            <div style="font-size:32px; margin-bottom:10px;">🔒</div>
                            <p style="color:var(--text-dark); font-size:14px; margin-bottom:16px; line-height:1.7;">
                                يجب دفع عربون المشاركة <strong style="color:var(--gold);"><?= number_format($pre_dep_amount, 0) ?> ₪</strong>
                                (<strong><?= (float)$auction['deposit_pct'] ?>%</strong> من سعر البداية)
                                قبل المزايدة، لضمان جدية عرضك.
                            </p>
                            <a href="auction_deposit.php?id=<?= $id ?>" class="btn btn-primary btn-block">
                                💳 ادفع العربون للمشاركة
                            </a>
                        </div>
                    <?php else: ?>
                    <form id="bidForm" method="POST" action="auction_bid.php">
                        <input type="hidden" name="auction_id" value="<?= $id ?>">
                        <button type="submit" name="amount" value="<?= $min_next_bid ?>" class="btn btn-primary btn-block">
                            مزايدة بـ <?= number_format($min_next_bid, 0) ?> ₪
                        </button>
                        <div class="ar-custom-bid">
                            <input type="number" name="custom_amount" min="<?= $min_next_bid ?>" step="<?= $auction['min_increment'] ?>" placeholder="مبلغ مخصص" class="form-control" id="customAmt">
                            <button type="button" id="customBidBtn" class="btn btn-outline">⚖️ موافق</button>
                        </div>
                        <?php if ($auction['buyout_price']): ?>
                            <button type="submit" name="amount" value="<?= $auction['buyout_price'] ?>" class="btn btn-primary btn-block" style="background: linear-gradient(135deg,#e74c3c,#c0392b); color: white; margin-top:10px;">
                                ⚡ شراء فوري بـ <?= number_format($auction['buyout_price'], 0) ?> ₪
                            </button>
                        <?php endif; ?>
                    </form>
                    <?php endif; // end need_deposit check ?>
                <?php else: ?>
                    <div class="alert alert-info" style="margin-bottom:0;">⏳ سيبدأ المزاد بتاريخ <?= date('d/m/Y H:i', strtotime($auction['starts_at'])) ?></div>
                <?php endif; ?>
            </div>

            <!-- سجل المزايدات -->
            <div class="ar-bids-history">
                <div class="ar-bids-head">
                    <strong>📜 سجل المزايدات</strong>
                    <span id="ar-bids-count"><?= (int)$auction['bids_count'] ?> مزايدة</span>
                </div>
                <div class="ar-bids-list" id="bidsList">
                    <?php if (empty($bids)): ?>
                        <div class="ar-bid-empty">لا توجد مزايدات بعد. كن أول من يزايد!</div>
                    <?php else: foreach ($bids as $b): ?>
                        <div class="ar-bid-item" data-bid-id="<?= $b['id'] ?>">
                            <img src="<?= sanitize($b['avatar'] ?: 'assets/images/default-avatar.png') ?>" onerror="this.src='assets/images/default-avatar.png'" class="ar-avatar">
                            <span class="ar-name"><?= sanitize($b['bidder_name']) ?></span>
                            <span class="ar-amt"><?= number_format($b['amount'], 0) ?> ₪</span>
                            <span class="ar-time"><?= date('H:i', strtotime($b['created_at'])) ?></span>
                            <?php if ($b['is_buyout']): ?><span class="ar-buyout">⚡ شراء فوري</span><?php endif; ?>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const auctionId = <?= $id ?>;
const isLive = <?= $is_live ? 'true' : 'false' ?>;

// === عداد تنازلي ===
const cd = document.getElementById('ar-countdown');
function tick() {
    const end = new Date(cd.dataset.end.replace(' ', 'T'));
    const diff = Math.max(0, (end - new Date()) / 1000);
    const d = Math.floor(diff / 86400);
    const h = Math.floor((diff % 86400) / 3600);
    const m = Math.floor((diff % 3600) / 60);
    const s = Math.floor(diff % 60);
    cd.querySelector('[data-cd="d"]').textContent = String(d).padStart(2,'0');
    cd.querySelector('[data-cd="h"]').textContent = String(h).padStart(2,'0');
    cd.querySelector('[data-cd="m"]').textContent = String(m).padStart(2,'0');
    cd.querySelector('[data-cd="s"]').textContent = String(s).padStart(2,'0');
    if (diff <= 0 && isLive) { setTimeout(()=>location.reload(), 2000); }
}
setInterval(tick, 1000);
tick();

// === مزايدة مخصصة ===
const customBtn = document.getElementById('customBidBtn');
if (customBtn) {
    customBtn.addEventListener('click', () => {
        const v = parseFloat(document.getElementById('customAmt').value);
        if (!v) { alert('أدخل مبلغ المزايدة'); return; }
        const fd = new FormData();
        fd.append('auction_id', auctionId);
        fd.append('amount', v);
        submitBid(fd);
    });
}

// === إرسال المزايدة عبر AJAX ===
const bidForm = document.getElementById('bidForm');
if (bidForm) {
    bidForm.addEventListener('submit', e => {
        e.preventDefault();
        const fd = new FormData(bidForm);
        const submitter = e.submitter;
        if (submitter && submitter.name === 'amount') fd.set('amount', submitter.value);
        submitBid(fd);
    });
}

async function submitBid(fd) {
    try {
        const res = await fetch('auction_bid.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (!data.ok) { alert(data.error || 'حدث خطأ'); return; }
        document.getElementById('ar-current').textContent = Number(data.current_bid).toLocaleString('en') + ' ₪';
    } catch (err) { alert('خطأ في الاتصال'); }
}

// === polling لسجل المزايدات ===
let lastBidId = <?= $bids[0]['id'] ?? 0 ?>;
async function pollBids() {
    if (!isLive) return;
    try {
        const res = await fetch(`auction_poll.php?id=${auctionId}&after=${lastBidId}`);
        const data = await res.json();
        if (!data.ok) return;
        if (data.current_bid) {
            document.getElementById('ar-current').textContent = Number(data.current_bid).toLocaleString('en') + ' ₪';
            document.getElementById('ar-bids-count').textContent = data.bids_count + ' مزايدة';
        }
        if (data.new_bids && data.new_bids.length) {
            const list = document.getElementById('bidsList');
            const empty = list.querySelector('.ar-bid-empty');
            if (empty) empty.remove();
            data.new_bids.forEach(b => {
                const div = document.createElement('div');
                div.className = 'ar-bid-item ar-bid-new';
                div.dataset.bidId = b.id;
                div.innerHTML = `
                    <img src="${b.avatar || 'assets/images/default-avatar.png'}" class="ar-avatar" onerror="this.src='assets/images/default-avatar.png'">
                    <span class="ar-name">${b.name}</span>
                    <span class="ar-amt">${Number(b.amount).toLocaleString('en')} ₪</span>
                    <span class="ar-time">${b.time}</span>
                    ${b.is_buyout ? '<span class="ar-buyout">⚡ شراء فوري</span>' : ''}
                `;
                list.insertBefore(div, list.firstChild);
                lastBidId = Math.max(lastBidId, b.id);
            });
        }
    } catch (e) { /* تجاهل */ }
}
setInterval(pollBids, 3000);
</script>

<?php include 'includes/footer.php'; ?>
