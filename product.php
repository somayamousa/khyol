<?php
$page_title = 'تفاصيل المنتج';
require_once 'config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) redirect('shop.php');

$page_title = $product['name'];

// منتجات مشابهة
$related_stmt = $conn->prepare("SELECT * FROM products WHERE category_id = ? AND id != ? LIMIT 4");
$related_stmt->execute([$product['category_id'], $id]);
$related = $related_stmt->fetchAll();

include 'includes/header.php';
?>

<div class="container">
    <div style="padding: 20px 0; color: var(--text-dark-muted); font-size: 14px;">
        <a href="index.php" style="color: var(--text-dark-muted);">الرئيسية</a>
        <span style="margin: 0 6px;">←</span>
        <a href="shop.php" style="color: var(--text-dark-muted);">المتجر</a>
        <span style="margin: 0 6px;">←</span>
        <a href="shop.php?category=<?= $product['category_id'] ?>" style="color: var(--text-dark-muted);"><?= sanitize($product['category_name']) ?></a>
        <span style="margin: 0 6px;">←</span>
        <span style="color: var(--gold-dark); font-weight: 700;"><?= sanitize($product['name']) ?></span>
    </div>

    <div class="product-detail">
        <div class="product-gallery">
            <img src="<?= sanitize($product['image'] ?: 'assets/images/hero.jpg') ?>"
                 alt="<?= sanitize($product['name']) ?>"
                 onerror="this.src='assets/images/hero.jpg'">
        </div>

        <div class="product-details">
            <div class="product-category" style="margin-bottom: 10px; color: var(--gold-dark); font-weight: 700;"><?= sanitize($product['category_name']) ?></div>
            <h1><?= sanitize($product['name']) ?></h1>
            <div class="product-rating">
                <?= str_repeat('⭐', (int)round($product['rating'])) ?>
                <span style="font-size: 14px;">(<?= $product['rating'] ?> من 5)</span>
            </div>

            <div style="margin: 25px 0; display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                <span class="price-current"><?= number_format($product['price'], 0) ?> ₪</span>
                <?php if ($product['old_price']): ?>
                <span class="price-old" style="font-size: 20px;"><?= number_format($product['old_price'], 0) ?> ₪</span>
                <span style="background: var(--red); color: white; padding: 5px 12px; border-radius: 20px; font-size: 13px; font-weight: 700;">
                    وفر <?= number_format($product['old_price'] - $product['price'], 0) ?> ₪
                </span>
                <?php endif; ?>
            </div>

            <div class="description"><?= nl2br(sanitize($product['description'])) ?></div>

            <div style="display: flex; gap: 15px; flex-wrap: wrap; margin: 20px 0;">
                <div style="background: var(--bg-2); padding: 12px 20px; border-radius: 10px; border-right: 3px solid var(--gold); flex: 1; min-width: 140px;">
                    <small style="color: var(--text-dark-muted); display: block; margin-bottom: 3px;">المتوفر</small>
                    <div style="color: var(--text-dark); font-weight: 900; font-size: 16px;"><?= $product['stock'] ?> قطعة</div>
                </div>
                <div style="background: var(--bg-2); padding: 12px 20px; border-radius: 10px; border-right: 3px solid var(--gold); flex: 1; min-width: 140px;">
                    <small style="color: var(--text-dark-muted); display: block; margin-bottom: 3px;">الشحن</small>
                    <div style="color: var(--text-dark); font-weight: 900; font-size: 16px;">مجاني 🚚</div>
                </div>
            </div>

            <form method="POST" action="cart.php" style="margin-top: 30px;">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?= $product['id'] ?>">

                <div class="qty-control">
                    <span style="color: var(--text-dark); margin-left: 10px; font-weight: 700;">الكمية:</span>
                    <button type="button" onclick="changeQty(-1)">−</button>
                    <input type="number" name="quantity" id="qty" value="1" min="1" max="<?= $product['stock'] ?>" readonly>
                    <button type="button" onclick="changeQty(1)">+</button>
                </div>

                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <button type="submit" class="btn btn-primary" style="flex: 1; padding: 15px 30px; font-size: 16px;">
                        🛒 أضف إلى السلة
                    </button>
                    <a href="cart.php" class="btn btn-outline" style="padding: 15px 30px;">
                        عرض السلة
                    </a>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($related)): ?>
    <section class="section">
        <div class="section-title">
            <h2>منتجات مشابهة</h2>
        </div>
        <div class="products-grid">
            <?php foreach ($related as $p): ?>
            <div class="product-card">
                <div class="product-image">
                    <a href="product.php?id=<?= $p['id'] ?>">
                        <img src="<?= sanitize($p['image']) ?>" alt="<?= sanitize($p['name']) ?>" loading="lazy">
                    </a>
                </div>
                <div class="product-info">
                    <div class="product-name">
                        <a href="product.php?id=<?= $p['id'] ?>"><?= sanitize($p['name']) ?></a>
                    </div>
                    <div class="product-price-row">
                        <div class="product-price">
                            <span class="price-current"><?= number_format($p['price'], 0) ?></span>
                            <span class="currency">₪</span>
                        </div>
                        <form method="POST" action="cart.php" style="margin:0;">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                            <button class="btn-add-cart">+</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</div>

<script>
function changeQty(delta) {
    const input = document.getElementById('qty');
    const max = parseInt(input.max);
    let val = parseInt(input.value) + delta;
    if (val < 1) val = 1;
    if (val > max) val = max;
    input.value = val;
}
</script>

<?php include 'includes/footer.php'; ?>
