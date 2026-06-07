# 🛠️ التقنيات المستخدمة في منصة خيول
## Technologies Used in Khyol Platform

---

## ✅ المصطلحات المستخدمة **فعلاً** في المشروع

### 1️⃣ **PDO** - PHP Data Objects
**الاستخدام:** ✅ مستخدم بكثافة

```php
// من config/db.php
$conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
```

**أين يُستخدم:**
- في كل ملف PHP تقريباً للاتصال بقاعدة البيانات
- استخدام آمن مع دعم utf8mb4 للعربية

---

### 2️⃣ **Prepared Statements** - منع SQL Injection
**الاستخدام:** ✅ مستخدم بشكل واسع

```php
// مثال من account.php
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// مثال آخر من chat_poll.php
$q = $conn->prepare("
    SELECT id, sender_type, body, attachment_path 
    FROM messages
    WHERE conversation_id = ? AND id > ?
");
$q->execute([$conversation_id, $after_id]);
```

**الفائدة:** حماية من هجمات SQL Injection

---

### 3️⃣ **bcrypt** - تشفير كلمات المرور
**الاستخدام:** ✅ مستخدم

```php
// من register.php
$hashed = password_hash($password, PASSWORD_BCRYPT);
$stmt->execute([$full_name, $email, $hashed, $phone, $city]);

// من login.php
if ($user && password_verify($password, $user['password'])) {
    $_SESSION['user_id'] = $user['id'];
}
```

**الفائدة:** تشفير آمن لكلمات المرور بخوارزمية bcrypt

---

### 4️⃣ **JSON** - تبادل البيانات
**الاستخدام:** ✅ مستخدم في API

```php
// من chat_poll.php
header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'ok' => true,
    'messages' => $messages,
], JSON_UNESCAPED_UNICODE);
```

**الفائدة:** إرسال البيانات بصيغة JSON للعميل

---

### 5️⃣ **AJAX / Polling** - طلبات غير متزامنة
**الاستخدام:** ✅ مستخدم بكثافة

**ملفات تستخدمها:**
- `chat_poll.php` - جلب الرسائل الجديدة بشكل دوري
- `auction_poll.php` - تحديث حالة المزادات
- `chat_send.php` - إرسال الرسائل

**الآلية:**
- العميل يرسل طلب GET كل فترة زمنية
- الخادم يرد بـ JSON يحتوي على البيانات الجديدة

```javascript
// مثال من chat.php
function pollMessages() {
    fetch(`chat_poll.php?conversation_id=${convId}&after=${lastId}`)
        .then(r => r.json())
        .then(data => {
            // تحديث الرسائل
        });
}
setInterval(pollMessages, 2000); // كل ثانيتين
```

---

### 6️⃣ **CRUD Operations** - العمليات الأساسية
**الاستخدام:** ✅ مستخدم بشكل أساسي

**جميع جداول المشروع تدعم:**
- **Create** (الإنشاء) - INSERT
- **Read** (القراءة) - SELECT
- **Update** (التحديث) - UPDATE
- **Delete** (الحذف) - DELETE (محدود في بعض الجداول)

**أمثلة:**
```php
// CREATE - إضافة خيل جديد
INSERT INTO horses (owner_id, name, breed, age, ...)

// READ - عرض الخيول
SELECT * FROM horses WHERE owner_id = ?

// UPDATE - تحديث بيانات الخيل
UPDATE horses SET name = ?, breed = ? WHERE id = ?

// DELETE - حذف ملف (قليل الاستخدام)
DELETE FROM certificates WHERE id = ? AND horse_id = ?
```

---

### 7️⃣ **XSS Protection** - منع هجمات Cross-Site Scripting
**الاستخدام:** ✅ مستخدم

```php
// من config/db.php
function sanitize($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

// الاستخدام في كل ملف:
<?= sanitize($user['full_name']) ?>
<?= sanitize($horse['name']) ?>
```

**الفائدة:** تحويل الأحرف الخاصة إلى entities HTML

---

### 8️⃣ **Responsive Design** - تصميم متجاوب
**الاستخدام:** ✅ مستخدم

**من assets/css/style.css:**
```css
@media (max-width: 1280px) {
    /* أجهزة العرض الكبيرة */
}

@media (max-width: 1100px) {
    /* أجهزة العرض المتوسطة */
}

@media (max-width: 900px) {
    /* أجهزة تابلت */
}

@media (max-width: 560px) {
    /* الهواتف الذكية */
}
```

**يدعم:**
- شاشات سطح المكتب (1280px+)
- شاشات تابلت (900px-1280px)
- الهواتف الذكية (أقل من 900px)

---

### 9️⃣ **ERD** - مخطط العلاقات بين الكيانات
**الاستخدام:** ✅ موجود

**الملفات:**
- `class_diagram.puml` - بصيغة PlantUML
- `class_diagram_ar.puml` - نسخة عربية

**يوضح:**
- الجداول الـ 33
- العلاقات One-to-Many
- Foreign Keys
- Primary Keys

---

### 🔟 **Session Management** - إدارة الجلسات
**الاستخدام:** ✅ مستخدم

```php
// من config/db.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// التحقق من تسجيل الدخول
if (!isLoggedIn()) {
    redirect('login.php');
}
```

---

## ❌ المصطلحات **غير المستخدمة** أو محدودة

| المصطلح | الحالة | السبب |
|--------|--------|-------|
| **HTTPS** | ❌ لم يتم التطبيق | البيئة محلية (localhost) |
| **CSRF Token** | ⚠️ محدود | لم يتم تطبيق كامل |
| **Transaction** | ⚠️ محدود | لا توجد عمليات معقدة تحتاجها |
| **API REST** | ⚠️ جزئي | فقط polling API بسيط |
| **MVC** | ⚠️ غير كامل | الملفات مختلطة (logic + view) |
| **Regex** | ⚠️ محدود | فقط في التحقق من البريد الإلكتروني |

---

## 📊 ملخص الأمان

### ✅ آليات الحماية المطبقة:

1. **SQL Injection**: محمي ✅ (Prepared Statements)
2. **XSS**: محمي ✅ (htmlspecialchars)
3. **Password**: محمي ✅ (bcrypt)
4. **Session**: محمي ✅ (Session Management)

### ⚠️ نقاط يجب تحسينها:

1. **CSRF Tokens**: إضافة token عشوائي للنماذج
2. **HTTPS**: بعد الرفع للإنتاج
3. **Rate Limiting**: حماية من الهجمات الآلية
4. **Input Validation**: التحقق من صيغة البيانات

---

## 🔧 معلومات تقنية إضافية

### قاعدة البيانات:
- **DBMS**: MySQL
- **Charset**: utf8mb4 (دعم كامل للعربية والرموز)
- **Engine**: InnoDB (دعم Transactions)

### الترميز:
```php
header('Content-Type: text/html; charset=utf-8');
$conn->exec("SET NAMES utf8mb4");
$conn->exec("SET CHARACTER SET utf8mb4");
```

### الدوال الأساسية:
- `isLoggedIn()` - التحقق من تسجيل الدخول
- `sanitize()` - تنظيف المدخلات
- `redirect()` - إعادة توجيه
- `output_text()` - تحويل الترميز

---

**آخر تحديث:** 2026-05-23  
**الإصدار:** 1.0  
**الحالة:** منصة جاهزة للاستخدام
