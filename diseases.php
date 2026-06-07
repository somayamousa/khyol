<?php
$page_title = 'أمراض الخيل والعناية';
require_once 'config/db.php';

$tab = $_GET['tab'] ?? 'diseases';
$category = $_GET['cat'] ?? '';
$id = (int)($_GET['id'] ?? 0);

// عرض تفاصيل مرض/نصيحة محددة
if ($id > 0 && $tab === 'disease') {
    $stmt = $conn->prepare("SELECT * FROM horse_diseases WHERE id = ? AND is_published = 1");
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    if ($item) {
        include 'includes/header.php';
        $sev_colors = ['mild'=>'#27ae60','moderate'=>'#f39c12','severe'=>'#e67e22','emergency'=>'#c0392b'];
        $sev_labels = ['mild'=>'خفيف','moderate'=>'متوسط','severe'=>'شديد','emergency'=>'⚠️ طارئ'];
        $cat_labels = ['digestive'=>'هضمي','respiratory'=>'تنفسي','skin'=>'جلدي','musculoskeletal'=>'عظام/عضلات','infectious'=>'معدٍ','reproductive'=>'تناسلي','neurological'=>'عصبي','other'=>'متفرقة'];
        ?>
        <div class="page-header">
            <div class="container">
                <h1><?= $item['icon'] ?> <?= sanitize($item['name']) ?> <?php if ($item['name_en']): ?><small style="opacity:.7;font-size:18px;">(<?= sanitize($item['name_en']) ?>)</small><?php endif; ?></h1>
                <div style="margin-top:12px; display:flex; gap:10px; flex-wrap:wrap;">
                    <span class="status-badge" style="background:rgba(0,0,0,.15);color:white;padding:6px 14px;"><?= $cat_labels[$item['category']] ?? '' ?></span>
                    <span class="status-badge" style="background:<?= $sev_colors[$item['severity']] ?>;color:white;padding:6px 14px;"><?= $sev_labels[$item['severity']] ?></span>
                </div>
            </div>
        </div>
        <div class="container" style="padding: 40px 0; max-width: 900px;">
            <a href="diseases.php" class="btn btn-outline" style="margin-bottom: 20px;">← الرجوع للقائمة</a>
            <?php if ($item['summary']): ?>
                <div class="info-card" style="background: rgba(201,162,39,0.06); border-right: 4px solid var(--gold);">
                    <p style="font-size: 17px; line-height: 1.9; color: var(--text-dark);"><?= nl2br(sanitize($item['summary'])) ?></p>
                </div>
            <?php endif; ?>
            <?php
            $sections = [
                'symptoms' => ['🚨 الأعراض', '#e74c3c'],
                'causes' => ['🔍 الأسباب', '#3498db'],
                'treatment' => ['💊 العلاج', '#27ae60'],
                'prevention' => ['🛡️ الوقاية', '#9b59b6'],
                'when_to_call_vet' => ['📞 متى تتصل بالطبيب البيطري؟', '#c0392b']
            ];
            foreach ($sections as $key => [$title, $color]):
                if (empty($item[$key])) continue;
            ?>
                <div class="disease-section" style="border-right: 4px solid <?= $color ?>;">
                    <h3 style="color: <?= $color ?>;"><?= $title ?></h3>
                    <div class="disease-text"><?= nl2br(sanitize($item[$key])) ?></div>
                </div>
            <?php endforeach; ?>
            <div class="info-card" style="text-align:center; background: rgba(231,76,60,0.06); border:1px solid rgba(231,76,60,0.3);">
                <h3 style="color: var(--red);">⚠️ تنبيه</h3>
                <p style="line-height: 1.8;">هذه المعلومات للاسترشاد فقط ولا تغني عن استشارة الطبيب البيطري المختص. عند الشك دائماً اتصل بطبيب فوراً.</p>
                <a href="clinics.php" class="btn btn-primary" style="margin-top: 12px;">🩺 احجز موعد بيطري</a>
            </div>
        </div>
        <?php include 'includes/footer.php'; ?>
        <?php
        exit;
    }
}

// عرض تفاصيل نصيحة عناية
if ($id > 0 && $tab === 'tip') {
    $stmt = $conn->prepare("SELECT * FROM horse_care_tips WHERE id = ? AND is_published = 1");
    $stmt->execute([$id]);
    $tip = $stmt->fetch();
    if ($tip) {
        include 'includes/header.php';
        ?>
        <div class="page-header">
            <div class="container">
                <h1><?= $tip['icon'] ?> <?= sanitize($tip['title']) ?></h1>
            </div>
        </div>
        <div class="container" style="padding: 40px 0; max-width: 900px;">
            <a href="diseases.php?tab=care" class="btn btn-outline" style="margin-bottom: 20px;">← الرجوع للقائمة</a>
            <?php if ($tip['summary']): ?>
                <div class="info-card" style="background: rgba(201,162,39,0.06); border-right: 4px solid var(--gold);">
                    <p style="font-size: 17px; line-height: 1.9; color: var(--text-dark);"><?= nl2br(sanitize($tip['summary'])) ?></p>
                </div>
            <?php endif; ?>
            <div class="info-card">
                <div class="disease-text" style="font-size: 16px; line-height: 2;"><?= nl2br(sanitize($tip['body'])) ?></div>
            </div>
        </div>
        <?php include 'includes/footer.php'; ?>
        <?php
        exit;
    }
}

// قوائم
$where = "WHERE is_published = 1";
$params = [];
if ($category && $tab === 'diseases') {
    $where .= " AND category = ?";
    $params[] = $category;
} elseif ($category && $tab === 'care') {
    $where .= " AND category = ?";
    $params[] = $category;
}

if ($tab === 'care') {
    $items_stmt = $conn->prepare("SELECT * FROM horse_care_tips $where ORDER BY id DESC");
} else {
    $items_stmt = $conn->prepare("SELECT * FROM horse_diseases $where ORDER BY (severity='emergency') DESC, (severity='severe') DESC, name ASC");
}
$items_stmt->execute($params);
$items = $items_stmt->fetchAll();

include 'includes/header.php';
?>

<div class="page-header">
    <div class="container">
        <h1>🩺 أمراض الخيل والعناية</h1>
        <p>دليل صحي شامل بإشراف الأطباء البيطريين — للاسترشاد فقط</p>
    </div>
</div>

<div class="container" style="padding: 30px 0;">
    <!-- تبديل بين المقالات وصحة الخيول -->
    <div style="display:flex; gap:10px; margin-bottom:24px;">
        <a href="articles.php" class="btn btn-outline" style="flex:1; text-align:center;">📚 المقالات</a>
        <a href="diseases.php" class="btn btn-primary" style="flex:1; text-align:center;">🩺 صحة الخيول</a>
    </div>

    <!-- تبويبات -->
    <div class="d-tabs">
        <a href="diseases.php?tab=diseases" class="d-tab <?= $tab==='diseases'?'active':'' ?>">🩺 الأمراض</a>
        <a href="diseases.php?tab=care" class="d-tab <?= $tab==='care'?'active':'' ?>">🐎 العناية اليومية</a>
    </div>

    <!-- فلاتر التصنيف -->
    <?php if ($tab === 'diseases'):
        $cats = ['digestive'=>'🤢 هضمي','respiratory'=>'🫁 تنفسي','skin'=>'🦟 جلدي','musculoskeletal'=>'🦴 عظام','infectious'=>'🦠 معدٍ','other'=>'🩺 متفرقة'];
    ?>
    <div class="d-filters">
        <a href="diseases.php?tab=diseases" class="d-filter <?= $category===''?'active':'' ?>">الكل</a>
        <?php foreach ($cats as $k => $v): ?>
            <a href="diseases.php?tab=diseases&cat=<?= $k ?>" class="d-filter <?= $category===$k?'active':'' ?>"><?= $v ?></a>
        <?php endforeach; ?>
    </div>
    <?php else:
        $cats = ['feeding'=>'🌾 تغذية','grooming'=>'🪮 تنظيف','exercise'=>'🏋️ تمرين','hooves'=>'🦶 حوافر','dental'=>'🦷 أسنان','seasonal'=>'🌤️ موسمي','first_aid'=>'🚑 إسعاف','stable'=>'🏠 إسطبل'];
    ?>
    <div class="d-filters">
        <a href="diseases.php?tab=care" class="d-filter <?= $category===''?'active':'' ?>">الكل</a>
        <?php foreach ($cats as $k => $v): ?>
            <a href="diseases.php?tab=care&cat=<?= $k ?>" class="d-filter <?= $category===$k?'active':'' ?>"><?= $v ?></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- شبكة العناصر -->
    <?php if (empty($items)): ?>
        <div class="empty-state">
            <span style="font-size: 64px;">📋</span>
            <p>لا توجد عناصر في هذا القسم بعد</p>
        </div>
    <?php else: ?>
        <div class="diseases-grid">
            <?php foreach ($items as $it):
                if ($tab === 'diseases') {
                    $sev_colors = ['mild'=>'#27ae60','moderate'=>'#f39c12','severe'=>'#e67e22','emergency'=>'#c0392b'];
                    $sev_labels = ['mild'=>'خفيف','moderate'=>'متوسط','severe'=>'شديد','emergency'=>'طارئ'];
                    $sev = $it['severity'];
                    $link = "diseases.php?tab=disease&id={$it['id']}";
                } else {
                    $sev_colors = []; $sev = null;
                    $link = "diseases.php?tab=tip&id={$it['id']}";
                }
            ?>
            <a href="<?= $link ?>" class="disease-card" style="text-decoration:none;">
                <div class="dc-icon"><?= $it['icon'] ?></div>
                <div class="dc-body">
                    <h3><?= sanitize($it['name'] ?? $it['title']) ?></h3>
                    <?php if (!empty($it['summary'])): ?>
                        <p><?= sanitize(mb_substr($it['summary'], 0, 110)) ?>...</p>
                    <?php endif; ?>
                    <?php if ($sev): ?>
                        <span class="dc-sev" style="background: <?= $sev_colors[$sev] ?>;"><?= $sev_labels[$sev] ?></span>
                    <?php endif; ?>
                </div>
                <div class="dc-arrow">←</div>
            </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
