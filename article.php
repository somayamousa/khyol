<?php
require_once 'config/db.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT a.*, c.name AS cat_name, c.slug AS cat_slug, c.icon AS cat_icon FROM articles a LEFT JOIN article_categories c ON c.id = a.category_id WHERE a.id = ?");
$stmt->execute([$id]);
$article = $stmt->fetch();

if (!$article) redirect('articles.php');

$conn->prepare("UPDATE articles SET views = views + 1 WHERE id = ?")->execute([$id]);

$page_title = $article['title'];

$related = [];
if ($article['category_id']) {
    $r = $conn->prepare("SELECT id, title, image, views FROM articles WHERE category_id = ? AND id != ? ORDER BY created_at DESC LIMIT 4");
    $r->execute([$article['category_id'], $id]);
    $related = $r->fetchAll();
}

include 'includes/header.php';
?>

<div class="container" style="padding: 30px 0; max-width: 900px;">
    <div style="margin-bottom: 20px;">
        <a href="articles.php" style="color: var(--gold);">← كل المقالات</a>
        <?php if ($article['cat_name']): ?>
            / <a href="articles.php?cat=<?= sanitize($article['cat_slug']) ?>" style="color: var(--gold);"><?= $article['cat_icon'] ?> <?= sanitize($article['cat_name']) ?></a>
        <?php endif; ?>
    </div>

    <article class="article-full" style="background:#ffffff; color:#000000;">
        <?php if ($article['cat_name']): ?>
            <span class="article-badge"><?= $article['cat_icon'] ?> <?= sanitize($article['cat_name']) ?></span>
        <?php endif; ?>
        <h1 style="margin: 15px 0;"><?= sanitize($article['title']) ?></h1>

        <div class="article-full-meta">
            <span>📅 <?= date('Y-m-d', strtotime($article['created_at'])) ?></span>
        </div>

        <?php if ($article['excerpt']): ?>
            <div class="article-excerpt" style="color:#000000;"><?= sanitize($article['excerpt']) ?></div>
        <?php endif; ?>

        <div class="article-content" style="color:#000000;">
            <?= nl2br(sanitize($article['content'])) ?>
        </div>
    </article>

    <?php if (!empty($related)): ?>
    <section style="margin-top: 60px;">
        <h2 style="color: var(--gold);">📖 مقالات ذات صلة</h2>
        <div class="articles-grid">
            <?php foreach ($related as $r): ?>
                <a href="article.php?id=<?= $r['id'] ?>" class="article-card">
                    <div class="article-card-body">
                        <h3><?= sanitize($r['title']) ?></h3>
                        <div class="article-footer">
                            <span class="article-read">اقرأ ←</span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
