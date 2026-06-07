<?php
$page_title = 'المتجر';
require_once 'config/db.php';

$category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$search = isset($_GET['q']) ? sanitize($_GET['q']) : '';

$categories = $conn->query("SELECT * FROM categories")->fetchAll();

$query = "SELECT p.*, c.name AS category_name, u.full_name AS seller_name FROM products p LEFT JOIN categories c ON p.category_id = c.id LEFT JOIN users u ON u.id = p.seller_id WHERE 1=1";
$params = [];

if ($category_id > 0) {
    $query .= " AND p.category_id = ?";
    $params[] = $category_id;
}

if (!empty($search)) {
    $query .= " AND p.name LIKE ?";
    $params[] = "%$search%";
}

$query .= " ORDER BY p.featured DESC, p.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

$current_cat = $category_id ? $conn->query("SELECT name FROM categories WHERE id=$category_id")->fetchColumn() : null;

include 'includes/header.php';
?>

<div class="page-header">
    <div class="container">
        <h1>🏇 متجر الفروسية</h1>
        <p><?= $current_cat ? sanitize($current_cat) : 'كل ما تحتاجه لعالم الخيول' ?></p>
    </div>
</div>

<div class="container">
    <div class="shop-layout">
        <aside class="filter-sidebar">
            <h3>🔍 البحث</h3>
            <form method="GET" style="margin-bottom: 25px;">
                <input type="text" name="q" class="form-control" placeholder="ابحث عن منتج..." value="<?= sanitize($search) ?>">
                <?php if ($category_id): ?>
                    <input type="hidden" name="category" value="<?= $category_id ?>">
                <?php endif; ?>
            </form>

            <h3>الفئات</h3>
            <ul>
                <li><a href="shop.php" class="<?= $category_id==0 ? 'active' : '' ?>">📦 جميع المنتجات</a></li>
                <?php foreach ($categories as $cat): ?>
                <li>
                    <a href="shop.php?category=<?= $cat['id'] ?>" class="<?= $category_id==$cat['id'] ? 'active' : '' ?>">
                        <?= $cat['icon'] ?> <?= sanitize($cat['name']) ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </aside>

        <main>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                <p style="color: var(--text-dark-muted);">عدد المنتجات: <strong style="color: var(--gold-dark);"><?= count($products) ?></strong></p>
            </div>

            <?php if (empty($products)): ?>
            <div class="empty-state">
                <div class="big-icon">🐎</div>
                <h3>لا توجد منتجات</h3>
                <p>لم يتم العثور على منتجات في هذا القسم</p>
            </div>
            <?php else: ?>
            <div class="products-grid">
                <?php foreach ($products as $p): ?>
                <div class="product-card">
                    <div class="product-image">
                        <?php if ($p['old_price']): ?>
                        <span class="product-badge">-<?= round((($p['old_price']-$p['price'])/$p['old_price'])*100) ?>%</span>
                        <?php elseif ($p['featured']): ?>
                        <span class="product-badge featured">مميز</span>
                        <?php endif; ?>
                        <a href="product.php?id=<?= $p['id'] ?>">
                            <img src="<?= sanitize($p['image']) ?>" alt="<?= sanitize($p['name']) ?>" loading="lazy">
                        </a>
                    </div>
                    <div class="product-info">
                        <div class="product-category"><?= sanitize($p['category_name']) ?></div>
                        <div class="product-name">
                            <a href="product.php?id=<?= $p['id'] ?>"><?= sanitize($p['name']) ?></a>
                        </div>
                        <?php if (!empty($p['seller_name'])): ?>
                        <div style="font-size:12px;color:#888;margin-bottom:4px;">🏪 <?= sanitize($p['seller_name']) ?></div>
                        <?php endif; ?>
                        <div class="product-rating">
                            <?= str_repeat('⭐', (int)round($p['rating'])) ?>
                            <span style="color: var(--gray); font-size: 13px;">(<?= $p['rating'] ?>)</span>
                        </div>
                        <div class="product-price-row">
                            <div class="product-price">
                                <span class="price-current"><?= number_format($p['price'], 0) ?></span>
                                <span class="currency">₪</span>
                                <?php if ($p['old_price']): ?>
                                <span class="price-old"><?= number_format($p['old_price'], 0) ?></span>
                                <?php endif; ?>
                            </div>
                            <form method="POST" action="cart.php" style="margin:0;">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                <input type="hidden" name="redirect" value="shop.php?added=1<?= $category_id?'&category='.$category_id:'' ?><?= $search?'&q='.urlencode($search):'' ?>">
                                <button class="btn-add-cart" title="أضف إلى السلة">+</button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php if (isset($_GET['added'])): ?>
<div class="toast">
    <div class="toast-icon">✅</div>
    <div class="toast-content">
        <strong>تمت الإضافة إلى السلة</strong>
        <span>تم إضافة المنتج بنجاح - <a href="cart.php" style="color:var(--gold);">عرض السلة</a></span>
    </div>
    <button class="toast-close" onclick="this.parentElement.classList.remove('show')">×</button>
</div>
<script>
document.querySelectorAll('.toast').forEach(t => {
    setTimeout(() => t.classList.add('show'), 100);
    setTimeout(() => t.classList.remove('show'), 4500);
});
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
