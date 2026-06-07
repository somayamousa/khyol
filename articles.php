<?php
$page_title = 'المعرفة والمقالات';
require_once 'config/db.php';

$cat_slug = $_GET['cat'] ?? '';
$search = trim($_GET['q'] ?? '');

$categories = $conn->query("SELECT c.*, COUNT(a.id) AS count FROM article_categories c LEFT JOIN articles a ON a.category_id = c.id GROUP BY c.id ORDER BY c.id")->fetchAll();

$active_cat = null;
foreach ($categories as $c) {
    if ($c['slug'] === $cat_slug) { $active_cat = $c; break; }
}

$sql = "SELECT a.*, c.name AS cat_name, c.slug AS cat_slug, c.icon AS cat_icon FROM articles a LEFT JOIN article_categories c ON c.id = a.category_id WHERE 1=1";
$params = [];
if ($active_cat) { $sql .= " AND a.category_id = ?"; $params[] = $active_cat['id']; }
if ($search) { $sql .= " AND (a.title LIKE ? OR a.content LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
$sql .= " ORDER BY a.featured DESC, a.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$articles = $stmt->fetchAll();

$featured = $conn->query("SELECT a.*, c.name AS cat_name, c.slug AS cat_slug, c.icon AS cat_icon FROM articles a LEFT JOIN article_categories c ON c.id = a.category_id WHERE a.featured = 1 ORDER BY a.views DESC LIMIT 3")->fetchAll();

include 'includes/header.php';
?>

<div class="page-header">
    <div class="container">
        <h1>📚 مكتبة المعرفة</h1>
        <p>كل ما تحتاج معرفته عن تربية الخيول والعناية بها</p>
    </div>
</div>

<div class="container" style="padding: 30px 0;">
    <!-- تبديل بين المقالات وصحة الخيول -->
    <div style="display:flex; gap:10px; margin-bottom:24px;">
        <a href="articles.php" class="btn btn-primary" style="flex:1; text-align:center;">📚 المقالات</a>
        <a href="diseases.php" class="btn btn-outline" style="flex:1; text-align:center;">🩺 صحة الخيول</a>
    </div>

    <!-- شريط البحث -->
    <form method="GET" class="filter-bar">
        <input type="search" name="q" value="<?= sanitize($search) ?>" placeholder="🔍 ابحث في المقالات..." style="flex: 1;">
        <?php if ($active_cat): ?>
            <input type="hidden" name="cat" value="<?= sanitize($active_cat['slug']) ?>">
        <?php endif; ?>
        <button type="submit" class="btn btn-primary">بحث</button>
    </form>

    <!-- التصنيفات -->
    <div class="article-categories">
        <a href="articles.php" class="article-cat <?= !$active_cat ? 'active' : '' ?>">
            <span class="cat-icon">📚</span>
            <strong>الكل</strong>
        </a>
        <?php foreach ($categories as $c): ?>
            <a href="articles.php?cat=<?= sanitize($c['slug']) ?>" class="article-cat <?= $active_cat && $active_cat['id'] == $c['id'] ? 'active' : '' ?>">
                <span class="cat-icon"><?= $c['icon'] ?></span>
                <strong><?= sanitize($c['name']) ?></strong>
                <small><?= $c['count'] ?> مقال</small>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (!$active_cat && !$search && !empty($featured)): ?>
    <section style="margin: 40px 0;">
        <h2 style="color: var(--gold);">⭐ المقالات المميزة</h2>
        <div class="articles-grid articles-featured">
            <?php foreach ($featured as $a): ?>
                <a href="article.php?id=<?= $a['id'] ?>" class="article-card article-card-featured">
                    <div class="article-card-body">
                        <?php if ($a['cat_name']): ?><span class="article-badge"><?= $a['cat_icon'] ?> <?= sanitize($a['cat_name']) ?></span><?php endif; ?>
                        <h3><?= sanitize($a['title']) ?></h3>
                        <p><?= sanitize($a['excerpt']) ?></p>
                        <div class="article-footer">
                            <span class="article-read">اقرأ ←</span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <h2 style="color: var(--text-dark); margin-top: 40px;">
        <?= $active_cat ? $active_cat['icon'] . ' ' . sanitize($active_cat['name']) : ($search ? 'نتائج البحث' : '📖 كل المقالات') ?>
        <small style="color: var(--text-dark-muted); font-weight: normal;">(<?= count($articles) ?> مقال)</small>
    </h2>

    <?php if (empty($articles)): ?>
        <div class="empty-state">
            <span style="font-size: 64px;">📖</span>
            <p>لا توجد مقالات في هذا التصنيف بعد</p>
        </div>
    <?php else: ?>
        <div class="articles-grid">
            <?php foreach ($articles as $a): ?>
                <a href="article.php?id=<?= $a['id'] ?>" class="article-card">
                    <div class="article-card-body">
                        <?php if ($a['cat_name']): ?><span class="article-badge"><?= $a['cat_icon'] ?> <?= sanitize($a['cat_name']) ?></span><?php endif; ?>
                        <h3><?= sanitize($a['title']) ?></h3>
                        <p><?= sanitize(mb_substr($a['excerpt'] ?: $a['content'], 0, 100)) ?>...</p>
                        <div class="article-footer">
                            <span class="article-read">اقرأ ←</span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
