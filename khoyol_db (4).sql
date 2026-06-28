-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 07, 2026 at 12:34 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `khoyol_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_logs`
--

CREATE TABLE `admin_logs` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_logs`
--

INSERT INTO `admin_logs` (`id`, `admin_id`, `action`, `details`, `created_at`) VALUES
(1, 3, 'إزالة توثيق مركز', 'center_id=4', '2026-04-17 20:26:52'),
(2, 3, 'إزالة توثيق مركز', 'center_id=3', '2026-04-17 20:26:55'),
(3, 3, 'توثيق مركز', 'center_id=3', '2026-04-17 21:38:05'),
(4, 3, 'تغيير صلاحية', 'user_id=5 role=user', '2026-06-06 15:46:44'),
(5, 3, 'إزالة توثيق', 'photographer3@khoyol.test', '2026-06-06 15:46:51'),
(6, 3, 'توثيق مستخدم', 'photographer3@khoyol.test', '2026-06-06 15:46:58'),
(7, 3, 'تغيير صلاحية', 'user_id=5 role=center', '2026-06-06 15:50:16');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `type` enum('info','warning','success','danger') DEFAULT 'info',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `content`, `type`, `is_active`, `created_at`) VALUES
(1, 'مرحباً بكم في منصة خيول', 'يسعدنا انضمامكم إلى أكبر منصة للفروسية في فلسطين. تابعوا آخر العروض والفعاليات!', 'info', 1, '2026-04-17 20:07:23'),
(2, 'تحديث سياسة الإرجاع', 'تم تحديث سياسة إرجاع المنتجات. يرجى الاطلاع على السياسة الجديدة.', 'warning', 1, '2026-04-17 20:07:23');

-- --------------------------------------------------------

--
-- Table structure for table `articles`
--

CREATE TABLE `articles` (
  `id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `title` varchar(250) NOT NULL,
  `excerpt` text DEFAULT NULL,
  `content` mediumtext NOT NULL,
  `image` varchar(500) DEFAULT 'assets/images/articles/default.jpg',
  `author` varchar(150) DEFAULT '┘üÏ▒┘è┘é Ï«┘è┘ê┘ä',
  `views` int(11) DEFAULT 0,
  `featured` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `articles`
--

INSERT INTO `articles` (`id`, `category_id`, `title`, `excerpt`, `content`, `image`, `author`, `views`, `featured`, `created_at`) VALUES
(1, 2, 'المغص عند الخيل: الأسباب والعلاج', 'المغص من أخطر الأمراض التي تصيب الخيل ويحتاج إلى تدخل سريع.', 'المغص عند الخيل هو ألم في البطن قد يكون خفيفاً أو شديداً. أسبابه متعددة تشمل تراكم الغازات، انسداد الأمعاء، تلوي المعي، أو التهاب القولون.\n\nالأعراض:\n• التعرق المفرط\n• رفس البطن بالحوافر\n• التدحرج على الأرض\n• رفض الطعام\n• زيادة ضربات القلب\n\nالعلاج:\n1. اتصل بالطبيب البيطري فوراً\n2. امنع الخيل من الاستلقاء قدر الإمكان (قد يؤدي لالتواء الأمعاء)\n3. جهز الخيل للفحص والسونار\n4. قد يحتاج لسوائل وريدية أو جراحة في الحالات الشديدة\n\nالوقاية: تنظيم وجبات الأعلاف، مياه نظيفة دائماً، تجنب التغيير المفاجئ في الغذاء.', 'assets/images/articles/default.jpg', '┘üÏ▒┘è┘é Ï«┘è┘ê┘ä', 9, 1, '2026-04-18 15:38:35'),
(2, 3, 'النظام الغذائي الأمثل للخيل البالغ', 'تعرف على مكونات الوجبة المتوازنة لخيلك وكميات الأعلاف المناسبة.', 'التغذية السليمة أساس صحة الخيل. الخيل البالغ (500 كغ) يحتاج يومياً:\n\n• التبن/الحشيش: 1.5-2% من وزن الجسم (7-10 كغ)\n• الحبوب (شعير/شوفان): 2-4 كغ حسب النشاط\n• الماء: 30-50 لتر\n• الفيتامينات والمعادن: حسب الحاجة\n\nتوقيت الوجبات:\n- 3 وجبات موزعة على اليوم\n- تبن على الأقل 30 دقيقة قبل الحبوب\n- تجنب إطعامه مباشرة قبل أو بعد التمرين بساعة\n\nعلامات سوء التغذية: خشونة الفرو، فقدان الوزن، ضعف في التدريب.', 'assets/images/articles/default.jpg', '┘üÏ▒┘è┘é Ï«┘è┘ê┘ä', 2, 1, '2026-04-18 15:38:35'),
(3, 8, 'كيف تميز الخيل العربي الأصيل؟', 'المواصفات الشكلية والسلوكية التي تميز الخيل العربي الأصيل عن غيره.', 'الخيل العربي الأصيل له مواصفات مميزة:\n\n**الرأس:**\n• جبهة عريضة ومقعرة (profile مقعر)\n• عينان كبيرتان واسعتان\n• أنف واسع مع فتحتي أنف كبيرتين\n• أذنان صغيرتان منحنيتان\n\n**الجسم:**\n• رقبة طويلة مقوسة\n• ظهر قصير وقوي\n• ذيل مرفوع عالياً\n• 17 ضلعاً بدلاً من 18 (علامة مميزة!)\n\n**المقاييس:**\n• الارتفاع: 145-155 سم\n• الوزن: 400-500 كغ\n\n**الطبع:**\n• ذكاء عالٍ\n• حساسية للمحيط\n• ولاء لصاحبه\n\nللتأكد النهائي من الأصالة: شهادة النسب (pedigree) من منظمة معتمدة مثل WAHO أو الجمعيات الوطنية.', 'assets/images/articles/default.jpg', '┘üÏ▒┘è┘é Ï«┘è┘ê┘ä', 0, 1, '2026-04-18 15:38:35'),
(4, 4, 'مراحل حمل الفرس والرعاية المطلوبة', 'فترة حمل الفرس 11 شهراً تقريباً - تعرف على رعاية كل مرحلة.', 'حمل الفرس يستمر 335-345 يوماً (11 شهر تقريباً).\n\n**الثلث الأول (0-4 أشهر):**\n• تغذية طبيعية\n• تمرين خفيف\n• فحص بالسونار في الأسبوع 14\n\n**الثلث الثاني (4-8 أشهر):**\n• زيادة البروتين 10%\n• تطعيمات (EHV-1) في الشهر 5 و7 و9\n• تجنب الإجهاد\n\n**الثلث الثالث (8-11 شهر):**\n• زيادة الطاقة 20%\n• تقليل التمرين\n• تجهيز مكان الولادة\n• مراقبة علامات الولادة\n\n**علامات الولادة القريبة:**\n• انتفاخ الضرع قبل أسبوعين\n• ارتخاء الأربطة حول الذيل\n• تفرز الحلمات سائلاً شمعياً\n• عدم الارتياح قبل الولادة بساعات', 'assets/images/articles/default.jpg', '┘üÏ▒┘è┘é Ï«┘è┘ê┘ä', 7, 0, '2026-04-18 15:38:35'),
(5, 6, 'الروتين اليومي للعناية بالخيل', 'خطوات العناية اليومية التي يحتاجها كل مربي خيل.', 'الروتين اليومي يحافظ على صحة خيلك ويبني ثقته بك.\n\n**الصباح:**\n1. افحص الخيل بصرياً (عينان صافيتان، وقوف طبيعي، تنفس منتظم)\n2. نظف الإسطبل من الروث\n3. قدم الوجبة الأولى مع ماء نظيف\n4. مشط الفرو بالمشط المعدني ثم الناعم\n\n**بعد الظهر:**\n5. نظف الحوافر (استخدم المنقار)\n6. تفحص الحوافر من الشقوق والالتهابات\n7. قدم التمرين اليومي (30-60 دقيقة)\n\n**المساء:**\n8. استحمام إذا كان الجو دافئ (مرتين أسبوعياً)\n9. الوجبة الأخيرة\n10. فحص نهائي للإسطبل والمياه\n\n**أسبوعياً:**\n• قص الشعر الزائد حول الحوافر\n• تنظيف الأسنان بالنظر\n• تغيير فرشة الإسطبل', 'assets/images/articles/default.jpg', '┘üÏ▒┘è┘é Ï«┘è┘ê┘ä', 0, 0, '2026-04-18 15:38:35'),
(9, 1, 'أساسيات تربية الخيل: دليل المربي المبتدئ', 'كل ما تحتاج معرفته قبل أن تبدأ رحلتك في تربية الخيل من الإسطبل إلى التغذية والرعاية الصحية.', 'تربية الخيل من أعرق الموروثات العربية، وهي مسؤولية يومية تتطلب التزاماً حقيقياً. قبل اقتناء خيلك الأول، عليك إعداد البيئة المناسبة له.\n\nالإسطبل المثالي:\nيجب أن تكون مساحة كل خيل لا تقل عن ثلاثة أمتار في ثلاثة، مع تهوية ممتازة تمنع تراكم الأمونيا الناتجة عن الروث. الفرشة الجيدة من القش أو نشارة الخشب ضرورية لراحة الحوافر والمفاصل، وتُجدَّد يومياً. يجب أن يكون الإسطبل بعيداً عن الضوضاء والمثيرات المفاجئة لأن الخيل حساس للغاية للمحيط.\n\nالتغذية السليمة:\nالخيل البالغ يحتاج يومياً ما بين 1.5 إلى 2.5 بالمئة من وزنه علفاً خشناً كالبرسيم والشعير. الماء النظيف يجب أن يكون متاحاً في جميع الأوقات لأن الخيل يشرب ما بين 30 إلى 50 لتراً يومياً. لا تغيّر النظام الغذائي فجأة لأن ذلك يسبب المغص، بل اجعل التغيير تدريجياً على مدى أسبوع.\n\nالرعاية الصحية:\nيحتاج الخيل إلى تطعيم سنوي ضد أمراض الإنفلونزا والتيتانوس والحمى الغربية النيلية. الحوافر تُنظَّف يومياً وتُقلَّم كل ستة إلى ثمانية أسابيع من قِبَل حداد متخصص. الفحص البيطري الدوري كل ستة أشهر يكشف المشكلات مبكراً قبل تفاقمها.\n\nبناء العلاقة مع الخيل:\nالخيل حيوان اجتماعي يتذكر من أحسن إليه ومن آذاه. قضِ وقتاً يومياً بجانبه بدون هدف سوى التعود المتبادل. تحدث بصوت هادئ واستخدم حركات بطيئة لتجنب الإفزاع. المكافأة الفورية بعد أي سلوك صحيح تبني الثقة وتسرّع التعلم.', 'assets/images/articles/default.jpg', 'فريق خيول', 15, 1, '2026-06-07 09:51:00'),
(10, 5, 'أدوات العناية بالخيل: ما تحتاجه فعلاً', 'دليل عملي لأدوات العناية الأساسية التي يحتاجها كل مربي خيول، مع شرح طريقة الاستخدام الصحيح.', 'العناية اليومية بالخيل تتطلب مجموعة من الأدوات المتخصصة. معرفة وظيفة كل أداة وطريقة استخدامها الصحيحة تعكس احترافية المربي وتحافظ على صحة الخيل.\n\nأدوات التنظيف والتمشيط:\nفرشاة الجسم الصلبة تُستخدم أولاً لإزالة الطبقة الخارجية من الأوساخ والروث الجاف، وتتحرك دائماً في اتجاه نمو الشعر. بعدها تأتي الفرشاة الناعمة لتلميع الشعر وإزالة الغبار الدقيق وتعطي البريق للجلد. مشط العرف والذيل يُستخدم بحذر من الأسفل للأعلى لفك التشابكات دون شد. الخُف، وهو أداة خطاف الحافر، يُستخدم يومياً لإزالة الطين والحجارة من داخل الحافر قبل وبعد الركوب.\n\nأدوات الاستحمام:\nالإسفنج والدلو للغسيل الكامل الذي يُجرى عادة مرة إلى مرتين أسبوعياً في الصيف. كشطة الجلد المطاطية تُزيل الماء الزائد بعد الاستحمام وتسرّع الجفاف. الشامبو المخصص للخيل يحافظ على توازن حموضة الجلد ولا يُستبدل بالشامبو البشري.\n\nمعدات الحماية:\nبطانية الإسطبل ضرورية في الشتاء وتُختار حسب درجة الحرارة. القُبّعات الواقية للساقين تحمي أثناء التدريب والنقل. الربطة الرأسية تمنع الخيل من أكل الفرشة أو التلوي ليلاً في بعض الحالات.\n\nصيانة الأدوات:\nنظّف الفرش بعد كل استخدام بضربها على بعضها لإزالة الشعر والغبار، واغسلها بالصابون مرة أسبوعياً. الأدوات المشتركة بين خيول متعددة قد تنقل الأمراض الجلدية، لذا خصص لكل خيل أدواته.', 'assets/images/articles/default.jpg', 'فريق خيول', 17, 0, '2026-06-07 09:51:00'),
(11, 7, 'تدريب الخيل: من الخطام إلى الركوب', 'برنامج تدريبي متدرج ومدروس لتحويل خيل غير مدرب إلى شريك موثوق في ثلاثة أشهر.', 'تدريب الخيل فن قبل أن يكون علماً. المدرب الناجح يقرأ لغة جسد الخيل قبل أن يصدر أي أمر، ويعمل مع طبيعة الحيوان لا ضدها.\n\nالمرحلة الأولى: التأسيس (الأسبوع الأول إلى الثاني)\nابدأ بالاقتراب التدريجي من الخيل يومياً دون إجباره على قبولك. ضع يدك أمامه ليشمّها قبل لمسه. دع يدك تتحرك ببطء على رقبته وكتفه حتى يسترخي. هذه المرحلة تبني قاعدة الثقة التي يقوم عليها كل التدريب اللاحق. لا تستعجل ولا تعاقب على الخوف الطبيعي.\n\nالمرحلة الثانية: العمل الأرضي (الأسبوع الثالث إلى الثامن)\nعلّم الخيل الانقياد بالخطام والمشي بجانبك دون شد. درّبه على التوقف الفوري عند سحب الخطام وعلى التراجع خطوتين عند الضغط على صدره. تدريب الحلقة الدائرية يُعلّمه الخضوع للتوجيه من بُعد ويقوي عضلاته. اختبر تقبّله لوضع البطانية على ظهره والحركة حولها قبل إدخال السرج.\n\nالمرحلة الثالثة: السرج والركوب (الأسبوع التاسع إلى الثاني عشر)\nضع السرج بهدوء وشدّ حزامه تدريجياً على مدى أيام. اجعل الخيل يتحرك بالسرج في الحلقة حتى يتقبله تماماً. للركوب الأول استعن بمساعد يمسك الخيل. ضع وزنك على الرِّكاب أولاً قبل تمرير رِجلك. المشي فقط في الأسابيع الأولى وتجنب الطلب.\n\nمبادئ لا تُكسر:\nانهِ كل جلسة تدريب بنجاح ولو صغير حتى ينتهي الخيل بمشاعر إيجابية. لا تدرّب وأنت متوتر لأن الخيل يستشعر ذلك. جلسة واحدة يومياً لا تتجاوز ثلاثين دقيقة أفضل من ساعة متقطعة.', 'assets/images/articles/default.jpg', 'فريق خيول', 0, 0, '2026-06-07 09:51:00');

-- --------------------------------------------------------

--
-- Table structure for table `article_categories`
--

CREATE TABLE `article_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `icon` varchar(10) DEFAULT '­ƒôû',
  `description` varchar(300) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `article_categories`
--

INSERT INTO `article_categories` (`id`, `name`, `slug`, `icon`, `description`) VALUES
(1, 'أحكام التربية', 'rules', '📜', 'الأحكام والقوانين المتعلقة بتربية الخيول'),
(2, 'أمراض الخيل', 'diseases', '🩺', 'الأمراض الشائعة في الخيل وطرق الوقاية والعلاج'),
(3, 'تغذية الخيول', 'nutrition', '🌾', 'الأنظمة الغذائية حسب العمر والحالة'),
(4, 'مراحل الحمل', 'pregnancy', '🤰', 'رعاية الفرس الحامل ومراحل الولادة'),
(5, 'أدوات الخيل', 'tools', '🛠️', 'السروج واللجام وأدوات العناية'),
(6, 'طرق العناية', 'care', '🧴', 'العناية اليومية بالخيل'),
(7, 'التدريب', 'training', '🏇', 'تدريب الخيول على الطاعة والسباقات'),
(8, 'اكتشاف الأصيل', 'identify', '⭐', 'طرق تمييز الخيل العربي الأصيل');

-- --------------------------------------------------------

--
-- Table structure for table `auctions`
--

CREATE TABLE `auctions` (
  `id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `horse_id` int(11) DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `main_image` varchar(500) DEFAULT 'assets/images/horses/default.jpg',
  `starting_price` decimal(12,2) NOT NULL,
  `reserve_price` decimal(12,2) DEFAULT NULL,
  `buyout_price` decimal(12,2) DEFAULT NULL,
  `min_increment` decimal(10,2) DEFAULT 100.00,
  `deposit_pct` decimal(5,2) DEFAULT 10.00,
  `deposit_paid` tinyint(1) DEFAULT 0,
  `deposit_amount` decimal(12,2) DEFAULT NULL,
  `current_bid` decimal(12,2) DEFAULT NULL,
  `leading_bidder_id` int(11) DEFAULT NULL,
  `bids_count` int(11) DEFAULT 0,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  `status` enum('scheduled','live','ended','cancelled') DEFAULT 'scheduled',
  `winner_id` int(11) DEFAULT NULL,
  `winner_amount` decimal(12,2) DEFAULT NULL,
  `winner_notified` tinyint(1) DEFAULT 0,
  `featured` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `auctions`
--

INSERT INTO `auctions` (`id`, `seller_id`, `horse_id`, `title`, `description`, `main_image`, `starting_price`, `reserve_price`, `buyout_price`, `min_increment`, `deposit_pct`, `deposit_paid`, `deposit_amount`, `current_bid`, `leading_bidder_id`, `bids_count`, `starts_at`, `ends_at`, `status`, `winner_id`, `winner_amount`, `winner_notified`, `featured`, `created_at`) VALUES
(1, 1, NULL, 'كحيلان الثالث - عربي أصيل مسجل', 'فحل عربي أصيل عمره 5 سنوات، حالته الصحية ممتازة، مدرّب على القفز والكانتر. شهادة نسب موثقة.', 'assets/images/horses/h1.jpg', 12000.00, NULL, NULL, 500.00, 10.00, 0, NULL, 12500.00, NULL, 2, '2026-04-20 00:25:34', '2026-04-20 03:25:34', 'ended', NULL, NULL, 0, 1, '2026-04-19 21:25:34'),
(2, 5, NULL, 'بب', 'ييي', 'assets/images/horses/a_5_1780496920.jpg', 30000.00, 30000.00, NULL, 1000.00, 10.00, 1, 5800.00, 58000.00, 1, 14, '2026-06-03 16:27:00', '2026-06-06 16:27:00', 'ended', 1, 58000.00, 0, 0, '2026-06-03 14:28:40');

-- --------------------------------------------------------

--
-- Table structure for table `auction_bids`
--

CREATE TABLE `auction_bids` (
  `id` int(11) NOT NULL,
  `auction_id` int(11) NOT NULL,
  `bidder_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `is_buyout` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `auction_bids`
--

INSERT INTO `auction_bids` (`id`, `auction_id`, `bidder_id`, `amount`, `is_buyout`, `created_at`) VALUES
(1, 2, 1, 31000.00, 0, '2026-06-03 14:29:07'),
(2, 2, 1, 35000.00, 0, '2026-06-03 14:29:59'),
(3, 2, 1, 36000.00, 0, '2026-06-03 15:06:45'),
(4, 2, 1, 42000.00, 0, '2026-06-03 15:06:56'),
(5, 2, 1, 45000.00, 0, '2026-06-03 15:07:11'),
(6, 2, 1, 47000.00, 0, '2026-06-03 15:07:18'),
(7, 2, 1, 49000.00, 0, '2026-06-04 16:10:12'),
(8, 2, 1, 50000.00, 0, '2026-06-04 16:10:19'),
(9, 2, 1, 51000.00, 0, '2026-06-04 16:35:37'),
(10, 2, 1, 52000.00, 0, '2026-06-04 16:35:46'),
(11, 2, 1, 53000.00, 0, '2026-06-04 16:47:22'),
(12, 2, 1, 54000.00, 0, '2026-06-04 16:48:13'),
(13, 2, 1, 55000.00, 0, '2026-06-04 16:48:32'),
(14, 2, 1, 58000.00, 0, '2026-06-04 16:48:56');

-- --------------------------------------------------------

--
-- Table structure for table `boarding_agreements`
--

CREATE TABLE `boarding_agreements` (
  `id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `center_id` int(11) NOT NULL,
  `horse_id` int(11) NOT NULL,
  `monthly_fee` decimal(10,2) NOT NULL,
  `trainer_share_pct` decimal(5,2) DEFAULT 20.00,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('pending','active','ended','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `boarding_agreements`
--

INSERT INTO `boarding_agreements` (`id`, `owner_id`, `center_id`, `horse_id`, `monthly_fee`, `trainer_share_pct`, `start_date`, `end_date`, `notes`, `status`, `created_at`) VALUES
(1, 1, 7, 1, 300.00, 20.00, '2026-06-12', NULL, 'ا', 'pending', '2026-06-04 17:11:29'),
(2, 1, 9, 5, 500.00, 20.00, '2026-06-24', NULL, '', 'active', '2026-06-05 14:56:38');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `center_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `rider_level` enum('beginner','intermediate','advanced') DEFAULT 'beginner',
  `skills` varchar(255) DEFAULT NULL,
  `horse_id` int(11) DEFAULT NULL,
  `preferred_horse_name` varchar(150) DEFAULT NULL,
  `weather_notify` tinyint(1) DEFAULT 1,
  `booking_date` date NOT NULL,
  `booking_time` time NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('pending','confirmed','completed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `user_id`, `center_id`, `service_id`, `rider_level`, `skills`, `horse_id`, `preferred_horse_name`, `weather_notify`, `booking_date`, `booking_time`, `notes`, `status`, `created_at`) VALUES
(2, 1, 12, 48, 'beginner', NULL, NULL, '', 1, '2026-05-31', '09:30:00', '', 'confirmed', '2026-05-29 17:47:02'),
(3, 1, 9, 32, 'beginner', NULL, NULL, '', 1, '2026-06-11', '12:00:00', '', 'confirmed', '2026-06-05 14:53:31'),
(4, 1, 9, 33, 'beginner', NULL, NULL, '', 1, '2026-06-24', '14:00:00', '', 'cancelled', '2026-06-05 14:55:19'),
(5, 1, 9, 33, 'beginner', NULL, NULL, '', 1, '2026-06-24', '14:00:00', '', 'confirmed', '2026-06-05 14:55:45');

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `icon` varchar(50) DEFAULT '?'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `slug`, `icon`) VALUES
(1, 'سروج وركوب', 'saddles', '🏇'),
(2, 'ملابس الفروسية', 'clothing', '👕'),
(3, 'خوذ وحماية', 'helmets', '⛑️'),
(4, 'أدوات العناية', 'care', '🧴'),
(5, 'إكسسوارات الخيل', 'accessories', '🎀'),
(6, 'أعلاف ومكملات', 'food', '🌾');

-- --------------------------------------------------------

--
-- Table structure for table `centers`
--

CREATE TABLE `centers` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `city` varchar(50) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `image` varchar(500) DEFAULT NULL,
  `cover_image` varchar(500) DEFAULT NULL,
  `rating` decimal(2,1) DEFAULT 4.5,
  `reviews_count` int(11) DEFAULT 0,
  `opening_hours` varchar(100) DEFAULT '8:00 ص - 8:00 م',
  `featured` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `verified` tinyint(1) DEFAULT 0,
  `approval_status` enum('approved','pending','rejected') DEFAULT 'approved',
  `owner_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `centers`
--

INSERT INTO `centers` (`id`, `name`, `description`, `city`, `address`, `phone`, `email`, `image`, `cover_image`, `rating`, `reviews_count`, `opening_hours`, `featured`, `created_at`, `verified`, `approval_status`, `owner_id`) VALUES
(7, 'نادي الملكي للفروسية', 'نادي الملكي للفروسية أحد أعرق الأندية الفروسية في فلسطين، يقع في قلب رام الله. نقدم دروس الفروسية للمبتدئين والمحترفين، مع إسطبلات مجهزة بأحدث المعدات وطاقم تدريب متخصص.', 'رام الله', 'شارع الإرسال، رام الله', '+970 2 295 0000', 'info@almalki-horse.ps', 'assets/images/centers/c_7_1780669081.jpg', 'assets/images/centers/c1.jpg', 4.8, 127, '7:00 ص - 9:00 م', 1, '2026-04-23 18:26:01', 1, 'approved', 104),
(8, 'نادي النخيل للفروسية', 'نادي النخيل للفروسية في نابلس من أبرز مراكز تدريب الفروسية في الضفة الغربية. يضم أكثر من 30 حصاناً من سلالات عربية وأوروبية أصيلة، مع حلبات تدريب مفتوحة ومسقوفة وبرامج تدريب متدرجة للأطفال والبالغين.', 'نابلس', 'شارع حواره، نابلس', '+970 9 238 1234', 'info@nakheel-horses.ps', 'assets/images/centers/c1.jpg', 'assets/images/centers/c1.jpg', 4.7, 89, '7:00 ص - 8:00 م', 1, '2026-05-07 22:27:22', 1, 'approved', 105),
(9, 'مركز القدس للفروسية', 'مركز القدس للفروسية واحة خضراء في قلب محافظة القدس، يقدم برامج متكاملة في تعليم ركوب الخيل وتدريب القفز والفروسية الكلاسيكية. يشرف على التدريب نخبة من أمهر المدربين المعتمدين دولياً.', 'القدس', 'طريق بيت لحم، القدس', '+970 2 628 5678', 'contact@quds-equestrian.ps', 'assets/images/centers/c_9_1780669455.jpg', 'assets/images/centers/c1.jpg', 4.9, 214, '6:30 ص - 9:00 م', 1, '2026-05-07 22:27:22', 1, 'approved', 106),
(10, 'اسطبلات الجليل', 'اسطبلات الجليل تقع في الناصرة وتُعدّ من أضخم مرافق الفروسية في المنطقة. تتوفر لدينا خدمات إيواء الخيول، التدريب الاحترافي، جلسات العلاج بالخيل، وتنظيم رحلات الركوب في الطبيعة.', 'القدس', 'طريق الحجاز، الناصرة', '+970 4 655 9900', 'info@galilee-stables.ps', 'assets/images/centers/c_10_1780669153.jpg', 'assets/images/centers/c1.jpg', 4.6, 73, '7:00 ص - 7:00 م', 1, '2026-05-07 22:27:22', 1, 'approved', 107),
(11, 'فرسان الخليل', 'مركز فرسان الخليل متخصص في تربية وتدريب خيول الجمال والسباق. نوفر بيئة احترافية كاملة تشمل حلبة سباق، مسارات جمال، وقاعات تدريب نظرية. يُقام في مركزنا سنوياً مهرجان الفروسية الفلسطيني.', 'الخليل', 'منطقة الظاهرية، الخليل', '+970 2 222 7700', 'fursan@khalil-equestrian.ps', 'assets/images/centers/c1.jpg', 'assets/images/centers/c1.jpg', 4.5, 56, '8:00 ص - 8:00 م', 1, '2026-05-07 22:27:22', 1, 'approved', 108),
(12, 'نادي طولكرم الرياضي للفروسية', 'نادي طولكرم الرياضي للفروسية ينتمي لنخبة الأندية الرياضية المتكاملة في فلسطين. يتميز بملاعبه الخضراء الواسعة وحلبات القفز الدولية المواصفات، ويستضيف بانتظام بطولات محلية وإقليمية.', 'طولكرم', 'المنطقة الصناعية، طولكرم', '+970 9 267 4455', 'info@tulkarm-equestrian.ps', 'assets/images/centers/c_12_1780669215.jpg', 'assets/images/centers/c1.jpg', 4.4, 41, '7:00 ص - 9:00 م', 1, '2026-05-07 22:27:22', 1, 'approved', 109),
(13, 'مركز غزة للفروسية', 'مركز غزة للفروسية رمز الصمود والشغف بعالم الخيول في قطاع غزة. يقدم المركز دروساً يومية وبرامج صيفية للناشئين، ويحتضن مجموعة من أجمل الخيول العربية الأصيلة وسلالات الدم الحار.', 'غزة', 'حي الرمال، غزة', '+970 8 286 3300', 'info@gaza-equestrian.ps', 'assets/images/centers/c_13_1780669375.jpg', 'assets/images/centers/c1.jpg', 4.7, 98, '7:00 ص - 8:00 م', 1, '2026-05-07 22:27:22', 1, 'approved', 110),
(14, 'الملكي', 'مركز فروسية — يُرجى تحديث البيانات من لوحة المركز.', 'رام الله', 'رام الله', '00000000000000', 'c@test.com', 'assets/images/centers/c1.jpg', 'assets/images/centers/c1.jpg', 4.5, 0, '8:00 ص - 8:00 م', 0, '2026-06-06 15:50:16', 1, 'approved', 5);

-- --------------------------------------------------------

--
-- Table structure for table `clinics`
--

CREATE TABLE `clinics` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `vet_name` varchar(200) DEFAULT NULL,
  `specialization` varchar(200) DEFAULT NULL,
  `city` varchar(50) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `opening_hours` varchar(100) DEFAULT '8:00 ÏÁ - 5:00 ┘à',
  `image` varchar(500) DEFAULT 'assets/images/clinics/default.jpg',
  `rating` decimal(2,1) DEFAULT 4.5,
  `reviews_count` int(11) DEFAULT 0,
  `consultation_fee` decimal(10,2) DEFAULT 100.00,
  `emergency_available` tinyint(1) DEFAULT 0,
  `home_visit` tinyint(1) DEFAULT 0,
  `featured` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `owner_id` int(11) DEFAULT NULL,
  `approval_status` enum('pending','approved','rejected') DEFAULT 'approved',
  `verified` tinyint(1) DEFAULT 0,
  `license_number` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clinics`
--

INSERT INTO `clinics` (`id`, `name`, `description`, `vet_name`, `specialization`, `city`, `address`, `phone`, `email`, `opening_hours`, `image`, `rating`, `reviews_count`, `consultation_fee`, `emergency_available`, `home_visit`, `featured`, `created_at`, `owner_id`, `approval_status`, `verified`, `license_number`) VALUES
(1, 'عيادة الفرس البيطرية', 'عيادة متخصصة في علاج أمراض الخيل بأحدث المعدات', 'د. محمد أبو عرقوب', 'جراحة وطب باطني', 'رام الله', 'شارع الإرسال، مقابل البلدية', '0599123456', 'dr.abuarkoub@vet.ps', '8:00 ص - 5:00 م', 'assets/images/horses/h1.jpg', 4.5, 0, 150.00, 1, 1, 1, '2026-04-18 15:38:35', NULL, 'approved', 0, NULL),
(2, 'مستشفى الخيل التخصصي', 'مستشفى بيطري شامل للخيل مع قسم طوارئ 24 ساعة', 'د. سارة النجار', 'طب باطني وتوليد', 'الخليل', 'منطقة الظاهرية', '0598987654', 'info@horsevet.ps', '8:00 ص - 5:00 م', 'assets/images/horses/h2.jpg', 4.5, 0, 120.00, 1, 0, 1, '2026-04-18 15:38:35', NULL, 'approved', 0, NULL),
(3, 'عيادة النور البيطرية', 'خبرة 20 سنة في علاج الأمراض العظمية والرياضية', 'د. أحمد الحمد', 'أمراض عظام ورياضية', 'نابلس', 'شارع الرفيديا', '0597555123', 'alhamad@vet.ps', '8:00 ص - 5:00 م', 'assets/images/horses/h3.jpg', 4.5, 0, 100.00, 0, 1, 0, '2026-04-18 15:38:35', NULL, 'approved', 0, NULL),
(4, 'المركز البيطري الحديث', 'أحدث التقنيات في التشخيص والعلاج', 'د. ليلى خوري', 'طب عام وتغذية', 'بيت لحم', 'شارع المهد', '0596111222', 'modern@vet.ps', '8:00 ص - 5:00 م', 'assets/images/horses/h4.jpg', 4.5, 0, 130.00, 1, 1, 0, '2026-04-18 15:38:35', NULL, 'approved', 0, NULL),
(5, 'طبيبي', 'عيادة بيطرية جديدة — يُرجى تحديث الوصف من صفحة العيادة.', 'طبيبي', 'طب بيطري عام', 'جنين', 'جنين', '00000000000', 'cl@test.com', '8:00 ص - 5:00 م', 'assets/images/horses/h1.jpg', 4.5, 0, 100.00, 0, 0, 0, '2026-04-23 18:50:23', 6, 'approved', 0, '');

-- --------------------------------------------------------

--
-- Table structure for table `clinic_appointments`
--

CREATE TABLE `clinic_appointments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `clinic_id` int(11) NOT NULL,
  `horse_id` int(11) DEFAULT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `reason` varchar(200) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('pending','confirmed','completed','cancelled') DEFAULT 'pending',
  `is_emergency` tinyint(1) DEFAULT 0,
  `visit_summary` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clinic_appointments`
--

INSERT INTO `clinic_appointments` (`id`, `user_id`, `clinic_id`, `horse_id`, `appointment_date`, `appointment_time`, `reason`, `notes`, `status`, `is_emergency`, `visit_summary`, `created_at`) VALUES
(1, 1, 5, 1, '2026-04-29', '12:57:00', 'dff', 'ddd', 'completed', 0, 'جيد', '2026-04-23 18:54:51'),
(2, 1, 1, 1, '2026-06-18', '19:40:00', 'فحص', 'ةةةة', 'pending', 0, NULL, '2026-06-04 16:38:06'),
(3, 1, 5, 1, '2026-06-10', '19:57:00', 'فحص', 'سس', 'confirmed', 0, NULL, '2026-06-04 16:54:17'),
(4, 1, 5, 7, '2026-06-16', '11:00:00', 'dff', '', 'cancelled', 0, NULL, '2026-06-04 18:59:50');

-- --------------------------------------------------------

--
-- Table structure for table `clinic_services`
--

CREATE TABLE `clinic_services` (
  `id` int(11) NOT NULL,
  `clinic_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `duration` varchar(50) DEFAULT '30 دقيقة',
  `icon` varchar(20) DEFAULT '?',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `complaints`
--

CREATE TABLE `complaints` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `subject` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `type` enum('complaint','suggestion','inquiry') DEFAULT 'complaint',
  `status` enum('new','in_progress','resolved','closed') DEFAULT 'new',
  `admin_reply` text DEFAULT NULL,
  `replied_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `complaints`
--

INSERT INTO `complaints` (`id`, `user_id`, `full_name`, `email`, `phone`, `subject`, `message`, `type`, `status`, `admin_reply`, `replied_at`, `created_at`) VALUES
(1, NULL, 'سعيد الخطيب', 'saeed@test.com', '0591234567', 'تأخر في تأكيد الحجز', 'حجزت درس من 3 أيام ولسا ما جاني رد من المركز، الرجاء المتابعة.', 'complaint', 'new', NULL, NULL, '2026-04-17 19:59:18'),
(2, NULL, 'فاطمة عبدالله', 'fatema@test.com', '0592345678', 'اقتراح إضافة ميزة', 'ياريت تضيفوا إمكانية تقييم المدربين بشكل منفصل عن المركز.', 'suggestion', 'new', NULL, NULL, '2026-04-17 19:59:18'),
(3, NULL, 'خالد أبو عامر', 'khaled@test.com', '0593456789', 'مشكلة في الدفع', 'حاولت أدفع عبر البطاقة ولكن فشلت العملية مرتين.', 'complaint', 'in_progress', NULL, NULL, '2026-04-17 19:59:18'),
(4, NULL, 'رنا سمارة', 'rana@test.com', '0594567890', 'استفسار عن المعسكر الصيفي', 'ما هو السن الأدنى للمشاركة في معسكر الأطفال الصيفي؟', 'inquiry', 'resolved', NULL, NULL, '2026-04-17 19:59:18'),
(5, NULL, 'ياسر دراغمة', 'yasser@test.com', '0595678901', 'جودة المنتج', 'الخوذة اللي طلبتها وصلت وفيها عيب في الحزام، ممكن استبدال؟', 'complaint', 'new', NULL, NULL, '2026-04-17 19:59:18'),
(6, 5, 'الملكي', 'shaimaatariq17@gmail.com', '0000000000', 'ويب', 'استفسار', 'inquiry', 'new', NULL, NULL, '2026-06-03 16:48:18'),
(7, 1, 'محمد المستخدم', 'shaimaatariq17@gmail.com', '3456789876', 'ويب', 'سسسسسسسسسسس', 'inquiry', 'new', NULL, NULL, '2026-06-04 14:03:37');

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contact_messages`
--

INSERT INTO `contact_messages` (`id`, `full_name`, `email`, `phone`, `message`, `is_read`, `created_at`) VALUES
(2, 'ليلى حمدان', 'laila@test.com', '0592222222', 'ما هي طرق الدفع المتوفرة؟', 1, '2026-04-17 19:59:18'),
(3, 'عمر الشريف', 'omar@test.com', '0593333333', 'شكراً على هذه المنصة الرائعة، أتمنى التوفيق!', 1, '2026-04-17 19:59:18');

-- --------------------------------------------------------

--
-- Table structure for table `conversations`
--

CREATE TABLE `conversations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `center_id` int(11) DEFAULT NULL,
  `clinic_id` int(11) DEFAULT NULL,
  `photographer_id` int(11) DEFAULT NULL,
  `other_user_id` int(11) DEFAULT NULL,
  `last_message_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_admin_chat` tinyint(1) DEFAULT 0,
  `contact_message_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `conversations`
--

INSERT INTO `conversations` (`id`, `user_id`, `center_id`, `clinic_id`, `photographer_id`, `other_user_id`, `last_message_at`, `created_at`, `is_admin_chat`, `contact_message_id`) VALUES
(2, 6, 7, NULL, NULL, NULL, '2026-04-23 20:23:36', '2026-04-23 20:23:36', 0, NULL),
(3, 1, 7, NULL, NULL, NULL, '2026-05-07 22:23:05', '2026-05-07 22:23:05', 0, NULL),
(4, 1, NULL, NULL, NULL, NULL, '2026-06-04 14:03:37', '2026-06-04 14:03:37', 1, NULL),
(5, 5, NULL, NULL, NULL, 1, '2026-06-04 16:23:20', '2026-06-04 16:23:20', 0, NULL),
(6, 1, NULL, NULL, NULL, 6, '2026-06-04 16:53:56', '2026-06-04 16:53:56', 0, NULL),
(7, 1, 9, NULL, NULL, NULL, '2026-06-05 14:50:30', '2026-06-05 14:50:16', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `center_id` int(11) DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(500) DEFAULT NULL,
  `event_date` date NOT NULL,
  `event_time` time DEFAULT NULL,
  `location` varchar(200) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT 0.00,
  `max_participants` int(11) DEFAULT 50,
  `type` enum('competition','event','workshop') DEFAULT 'event',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `center_id`, `title`, `description`, `image`, `event_date`, `event_time`, `location`, `price`, `max_participants`, `type`, `created_at`) VALUES
(1, NULL, 'بطولة فلسطين لقفز الحواجز 2026', 'البطولة الوطنية السنوية لقفز الحواجز، مفتوحة للفرسان المحترفين من جميع المدن. جوائز قيمة وشهادات رسمية.', 'assets/images/events/e1.jpg', '2026-05-15', '14:00:00', 'رام الله - نادي الفرسان الذهبي', 150.00, 100, 'competition', '2026-04-17 19:37:19'),
(2, NULL, 'مسابقة الفارس الذهبي', 'مسابقة تنافسية على مستوى الضفة الغربية بجوائز مالية وكؤوس للفائزين.', 'assets/images/events/e2.jpg', '2026-06-10', '15:00:00', 'الخليل - مدرسة البطولة', 200.00, 80, 'competition', '2026-04-17 19:37:19'),
(3, NULL, 'ورشة تعليم الفروسية التراثية', 'ورشة تعليمية عن فنون الفروسية العربية الأصيلة مع أشهر المدربين.', 'assets/images/events/e3.jpg', '2026-05-20', '10:00:00', 'نابلس - إسطبلات الأصايل', 80.00, 30, 'workshop', '2026-04-17 19:37:19'),
(4, NULL, 'يوم الأسرة في النادي الملكي', 'يوم ترفيهي للعائلات يتضمن ركوب الخيل، ألعاب، وجبات، وأنشطة للأطفال.', 'assets/images/events/e4.jpg', '2026-05-25', '11:00:00', 'بيت لحم - النادي الملكي', 50.00, 200, 'event', '2026-04-17 19:37:19'),
(5, NULL, 'معسكر الخيل الصيفي للأطفال', 'معسكر صيفي لمدة أسبوع لتعليم الأطفال الفروسية في بيئة آمنة.', 'assets/images/events/e5.jpg', '2026-07-01', '09:00:00', 'جنين - مركز الفارس الصغير', 600.00, 40, 'workshop', '2026-04-17 19:37:19'),
(6, NULL, 'عرض الفروسية الكبير', 'عرض مبهر بمشاركة 20 فارساً يعرضون مهاراتهم في الفروسية والترويض.', 'assets/images/events/e6.jpg', '2026-06-22', '17:00:00', 'طولكرم - إسطبلات الأمير', 30.00, 500, 'event', '2026-04-17 19:37:19'),
(7, 9, 'السرعه', '', 'assets/images/events/e_9_1780671509.jpg', '2026-06-23', '19:57:00', 'مركز القدس للفروسية', 40.00, 50, 'competition', '2026-06-05 14:58:29');

-- --------------------------------------------------------

--
-- Table structure for table `event_registrations`
--

CREATE TABLE `event_registrations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `status` enum('registered','attended','cancelled') DEFAULT 'registered',
  `registered_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_registrations`
--

INSERT INTO `event_registrations` (`id`, `user_id`, `event_id`, `status`, `registered_at`) VALUES
(1, 1, 2, 'registered', '2026-05-29 18:27:22'),
(2, 1, 7, 'registered', '2026-06-05 14:58:51');

-- --------------------------------------------------------

--
-- Table structure for table `horses`
--

CREATE TABLE `horses` (
  `id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `breed` varchar(100) DEFAULT NULL,
  `gender` enum('male','female') NOT NULL,
  `birth_date` date DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `height_cm` decimal(5,1) DEFAULT NULL,
  `weight_kg` decimal(7,2) DEFAULT NULL,
  `registration_number` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `is_pure` tinyint(1) DEFAULT 0,
  `father_name` varchar(150) DEFAULT NULL,
  `mother_name` varchar(150) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `experience_details` text DEFAULT NULL,
  `health_status` enum('healthy','under_care','recovering','critical') DEFAULT 'healthy',
  `last_checkup_date` date DEFAULT NULL,
  `medical_notes` text DEFAULT NULL,
  `pedigree_label` varchar(120) DEFAULT NULL,
  `video_url` varchar(500) DEFAULT NULL,
  `main_image` varchar(500) DEFAULT 'assets/images/horses/default.jpg',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_for_sale` tinyint(1) DEFAULT 0,
  `sale_price` decimal(12,2) DEFAULT NULL,
  `sale_city` varchar(50) DEFAULT NULL,
  `sale_description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `horses`
--

INSERT INTO `horses` (`id`, `owner_id`, `name`, `breed`, `gender`, `birth_date`, `color`, `height_cm`, `weight_kg`, `registration_number`, `city`, `is_pure`, `father_name`, `mother_name`, `description`, `experience_details`, `health_status`, `last_checkup_date`, `medical_notes`, `pedigree_label`, `video_url`, `main_image`, `created_at`, `is_for_sale`, `sale_price`, `sale_city`, `sale_description`) VALUES
(1, 1, 'الأصيل', 'عربي أصيل', 'male', '2019-03-15', 'أشقر', 152.5, 450.00, 'WAHO-2019-0145', NULL, 1, 'الفارس الملكي', 'درة الشرق', 'خيل عربي أصيل بمواصفات ممتازة، رأس مميز وعيون واسعة، رقبة مقوسة طويلة، ذيل مرفوع. صحته ممتازة ومدرّب على الفروسية العربية.', 'شارك في 3 سباقات محلية - ميدالية فضية 2023 في مهرجان الخيول العربية برام الله - تدريب احترافي منذ عمر سنتين.', 'healthy', NULL, '', '', NULL, 'assets/images/horses/h1.jpg', '2026-04-18 16:31:32', 1, 45000.00, 'رام الله', 'للبيع لمحبي الخيول الأصيلة فقط. السعر قابل للتفاوض. البيع يشمل شهادة النسب الموثقة من WAHO وكل السجلات الصحية.'),
(3, 1, 'الفارس', 'إنجليزي', 'male', '2018-09-22', 'بني غامق', 162.0, 520.00, '', NULL, 0, '', '', 'خيل رياضي إنجليزي، ارتفاع ممتاز وبنية قوية. مناسب للقفز والسباقات.', 'تدريب احترافي على القفز - 5 مشاركات في بطولات القفز المحلية - مركز أول في بطولة نابلس 2024.', 'healthy', NULL, '', '', NULL, 'assets/images/horses/h3.jpg', '2026-04-18 16:31:32', 1, 28000.00, 'سلفيت', 'مناسب للفرسان المحترفين والمتدربين المتقدمين. يأتي مع سرج قفز أصلي ومعدات التدريب.'),
(5, 1, 'الملك', 'عربي أصيل', 'male', '2017-01-18', 'أدهم', 158.0, 490.00, 'WAHO-2017-0203', NULL, 1, 'ريح الصحراء', 'أميرة النخيل', 'خيل عربي أصيل من سلالة ملكية، نسبه موثق حتى الجيل السابع. مظهر مهيب وحركة رشيقة.', 'بطل مهرجان الخيول العربية الدولي 2022 - ميدالية ذهبية في الجمال - مشاركة في معارض دولية.', 'healthy', NULL, NULL, NULL, NULL, 'assets/images/horses/h5.jpg', '2026-04-18 16:31:32', 1, 85000.00, 'رام الله', 'للمستثمرين الجادين فقط. نسب موثق من WAHO بالجيل السابع. قابل للمشاركة في برامج التكاثر.'),
(7, 1, 'الأميرة', 'عربي', 'female', '2019-11-30', 'أشقر محمر', 150.0, 430.00, 'PHA-2019-0167', NULL, 0, 'الفارس', 'حلوة', 'فرس عربية جميلة، رياضية وذكية. مدربة على الفروسية التقليدية والاستعراض.', 'مشاركات في استعراضات الفروسية - تدريب متقدم على المهارات الاستعراضية.', 'healthy', NULL, NULL, NULL, NULL, 'assets/images/horses/h7.jpg', '2026-04-18 16:31:32', 1, 32000.00, 'رام الله', 'مثالية لنوادي الفروسية والاستعراضات. طبعها مميز وتتعلم بسرعة.');

-- --------------------------------------------------------

--
-- Table structure for table `horse_care_tips`
--

CREATE TABLE `horse_care_tips` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `category` enum('feeding','grooming','exercise','hooves','dental','seasonal','stable','first_aid') DEFAULT 'grooming',
  `icon` varchar(10) DEFAULT '­ƒÉÄ',
  `image` varchar(500) DEFAULT 'assets/images/articles/default.jpg',
  `summary` text DEFAULT NULL,
  `body` mediumtext DEFAULT NULL,
  `is_published` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `horse_care_tips`
--

INSERT INTO `horse_care_tips` (`id`, `title`, `category`, `icon`, `image`, `summary`, `body`, `is_published`, `created_at`) VALUES
(1, 'التغذية اليومية الصحيحة', 'feeding', '🌾', 'assets/images/articles/default.jpg', 'الفرس البالغ يحتاج 1.5-2.5% من وزنه يومياً من المادة الجافة، الأساس عشب جيد.', '🌱 العشب والقش:\n• 60-100% من الغذاء يجب أن يكون عشب أو قش جيد الجودة\n• قسّم على 3-5 وجبات صغيرة لتقليد الرعي الطبيعي\n• تجنب القش المتعفن أو المتربك\n\n🌾 الحبوب (إن لزم):\n• فقط للخيول العاملة بشدة\n• شعير، شوفان، أو علف مركّب\n• لا تتجاوز 500غ/100كغ من وزن الفرس في الوجبة الواحدة\n\n💧 الماء:\n• 25-55 لتر يومياً\n• ماء نظيف ومتاح دائماً\n• راقب الاستهلاك في الشتاء (قد يقل ويسبب مغص)\n\n🧂 الملح والمعادن:\n• قالب ملح/معادن متاح دائماً\n• 30-50 غ ملح يومياً للخيول العاملة', 1, '2026-04-19 21:25:34'),
(2, 'تنظيف الحوافر يومياً', 'hooves', '🦶', 'assets/images/articles/default.jpg', 'تنظيف الحوافر يومي ضروري للوقاية من العدوى والعرج، يأخذ 5 دقائق فقط.', '🛠️ الأدوات اللازمة:\n• خطاف الحافر (Hoof Pick)\n• فرشاة سلكية\n\n📋 الخطوات:\n1. ارفع الحافر بأمان (شد الرسغ بلطف)\n2. ابدأ من الكعب نحو الإصبع لإزالة الحجارة والأوساخ\n3. نظّف بعمق شقوق الحافر (أكثر مكان للعدوى)\n4. افحص باطن الحافر للجروح أو نتانة الرائحة\n5. افحص النعل والمسامير إن كان منعّلاً\n\n⏰ التوقيت:\n• قبل وبعد كل ركوب\n• مرة يومياً على الأقل\n• فحص أسبوعي شامل من الحدّاد\n\n⚠️ علامات الخطر:\n• رائحة كريهة (عفن)\n• تصدع أو فجوات في الحافر\n• حافر ساخن جداً\n• ميل في الوقفة', 1, '2026-04-19 21:25:34'),
(3, 'تنظيف الفرس (Grooming)', 'grooming', '🪮', 'assets/images/articles/default.jpg', 'التنظيف اليومي يبني الثقة مع الفرس، يفحص جسمه، ويحافظ على صحة الجلد.', '🧰 صندوق العدّة:\n• فرشاة قاسية (Curry comb)\n• فرشاة جسم متوسطة\n• فرشاة وجه ناعمة\n• مشط للعرف والذيل\n• خطاف للحوافر\n• إسفنجة للعينين والأنف\n\n📋 الترتيب:\n1. فرشاة قاسية بحركة دائرية لتفكيك الأوساخ\n2. فرشاة الجسم لإزالة الأوساخ بضربات قصيرة باتجاه الشعر\n3. فرشاة الوجه بلطف مع تجنب العين\n4. مشط العرف والذيل من الأسفل للأعلى\n5. تنظيف الحوافر\n6. مسح العينين والأنف بإسفنجة رطبة\n\n⏱️ الوقت: 15-20 دقيقة يومياً\n\n💡 فوائد:\n• فحص يومي لاكتشاف الجروح/التورمات\n• تحسين الدورة الدموية\n• تقوية الرابطة مع الفرس', 1, '2026-04-19 21:25:34'),
(4, 'العناية الموسمية بالشتاء', 'seasonal', '❄️', 'assets/images/articles/default.jpg', 'الشتاء يحتاج تعديلات في التغذية، التغطية، والتمرين لحماية الفرس.', '🧥 التغطية:\n• البطانية (Rug) للخيول المحلوقة أو الكبيرة\n• 100-300غم للأيام المعتدلة، 400+ في الصقيع\n• افحص يومياً تحت البطانية للتأكد من جفافها\n\n🌾 الغذاء:\n• زيادة الألياف (تنتج حرارة أثناء الهضم)\n• زيادة 10-25% من الكمية الإجمالية\n• ماء فاتر إن أمكن (يشجع على الشرب)\n\n💧 الماء:\n• تأكد من عدم تجمد الأحواض\n• راقب الاستهلاك (الجفاف الشتوي شائع)\n\n🏋️ التمرين:\n• استمر في التمرين الخفيف يومياً\n• إحماء أطول قبل العمل المكثف\n• جفّف الفرس جيداً بعد التمرين قبل تغطيته\n\n🦶 الحوافر:\n• قد تحتاج إزالة النعال أو نعال خاصة شتوية\n• تجنب الانزلاق على الجليد', 1, '2026-04-19 21:25:34'),
(5, 'العناية بالأسنان', 'dental', '🦷', 'assets/images/articles/default.jpg', 'فحص الأسنان كل 6-12 شهر ضروري، أسنان غير سليمة تسبب فقدان وزن وسلوك سيئ.', '⏰ التوقيت:\n• فحص أول قبل سن 5 (لاكتشاف أسنان الذئب)\n• فحص سنوي للخيول 5-15\n• فحص كل 6 أشهر للخيول فوق 15 سنة\n\n🚨 علامات مشاكل الأسنان:\n• إفلات الطعام من الفم (Quidding)\n• فقدان وزن غير مفسّر\n• إفرازات أنفية من جهة واحدة\n• مقاومة اللجام\n• ميل الرأس عند الأكل\n• رائحة كريهة من الفم\n\n🛠️ التطويش (Floating):\n• إجراء روتيني لتنعيم الزوايا الحادة\n• يقوم به طبيب أسنان بيطري متخصص\n• يحتاج تخدير خفيف\n\n💡 نصيحة: اطلب فحص الأسنان كجزء من الفحص السنوي العام.', 1, '2026-04-19 21:25:34'),
(6, 'الإسعافات الأولية الأساسية', 'first_aid', '🚑', 'assets/images/articles/default.jpg', 'كل صاحب خيل يجب أن يعرف الإسعافات الأولية الأساسية لإنقاذ فرسه.', '🧰 صندوق الإسعافات اللازم:\n• ضمادات وشاش معقم\n• مقص جراحي\n• ميزان حرارة (طبيعي 37-38°م)\n• سماعة طبية\n• مطهر (Betadine)\n• محلول ملحي معقم\n• كريم مضاد حيوي\n• مسحوق Bute (تحت إشراف بيطري)\n\n📊 العلامات الحيوية الطبيعية:\n• الحرارة: 37.0-38.3°م\n• النبض: 28-44 نبضة/د (راحة)\n• التنفس: 8-16 مرة/د\n• اللثة: وردية رطبة، تعود الوردية بـ 1-2 ثانية\n\n🩹 جروح بسيطة:\n1. اغسل بالماء البارد لإزالة الأوساخ\n2. عقّم بمحلول مخفف\n3. ضع ضمادة إن لزم\n4. راقب لمدة 24 ساعة\n\n🚨 اتصل بالطبيب فوراً عند:\n• جروح عميقة أو نزيف غزير\n• عرج شديد\n• علامات مغص\n• حمى فوق 39°م\n• تنفس صعب\n• امتناع عن الأكل أو الشرب لساعات', 1, '2026-04-19 21:25:34');

-- --------------------------------------------------------

--
-- Table structure for table `horse_certificates`
--

CREATE TABLE `horse_certificates` (
  `id` int(11) NOT NULL,
  `horse_id` int(11) NOT NULL,
  `clinic_id` int(11) DEFAULT NULL,
  `cert_type` enum('pedigree','competition','health','other') NOT NULL,
  `title` varchar(200) NOT NULL,
  `issue_date` date DEFAULT NULL,
  `issuer` varchar(200) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `horse_certificates`
--

INSERT INTO `horse_certificates` (`id`, `horse_id`, `clinic_id`, `cert_type`, `title`, `issue_date`, `issuer`, `file_path`, `notes`, `created_at`) VALUES
(1, 1, NULL, 'pedigree', 'شهادة نسب معتمدة', '2019-05-20', 'منظمة WAHO العالمية', NULL, 'شهادة نسب صادرة من الجهة الدولية المعتمدة', '2026-05-29 17:58:46'),
(2, 1, NULL, 'competition', 'بطولة الخيول العربية الأصيلة', '2023-10-15', 'الاتحاد الفلسطيني للفروسية', NULL, 'المركز الأول في فئة الجمال', '2026-05-29 17:58:46'),
(3, 1, NULL, 'health', 'فحص صحي شامل', '2024-12-01', 'د. محمد أبو راشد', NULL, 'نتائج تحليل DNA سليمة', '2026-05-29 17:58:46'),
(6, 3, NULL, 'competition', 'سباق الخيول الوطني', '2024-09-05', 'نادي الفروسية الفلسطيني', NULL, 'المركز الثاني', '2026-05-29 17:58:46'),
(7, 3, NULL, 'health', 'فحص صحي دوري', '2025-01-15', 'د. أحمد نصر', NULL, 'جميع المؤشرات طبيعية', '2026-05-29 17:58:46'),
(8, 5, NULL, 'pedigree', 'شهادة نسب WAHO معتمدة', '2017-03-25', 'World Arabian Horse Organization', NULL, 'تسجيل دولي معتمد من WAHO', '2026-05-29 17:58:46'),
(9, 5, NULL, 'competition', 'بطولة القدس للفروسية', '2022-11-10', 'الاتحاد الفلسطيني للفروسية', NULL, 'جائزة أفضل خيل عربي', '2026-05-29 17:58:46'),
(10, 5, NULL, 'competition', 'بطولة رام الله الدولية', '2023-06-18', 'الاتحاد العربي للفروسية', NULL, 'المركز الأول', '2026-05-29 17:58:46'),
(11, 5, NULL, 'health', 'فحص صحي شامل', '2024-12-20', 'د. سامي أبو عمر', NULL, 'نتائج تحليل DNA سليمة', '2026-05-29 17:58:46'),
(12, 7, NULL, 'competition', 'كأس فلسطين للفروسية', '2023-08-22', 'الاتحاد الفلسطيني', NULL, 'المركز الثالث', '2026-05-29 17:58:46'),
(13, 7, NULL, 'health', 'فحص صحي سنوي', '2024-10-05', 'د. علي الحسن', NULL, 'نتائج ممتازة', '2026-05-29 17:58:46');

-- --------------------------------------------------------

--
-- Table structure for table `horse_diseases`
--

CREATE TABLE `horse_diseases` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `name_en` varchar(150) DEFAULT NULL,
  `category` enum('digestive','respiratory','skin','musculoskeletal','infectious','reproductive','neurological','other') DEFAULT 'other',
  `severity` enum('mild','moderate','severe','emergency') DEFAULT 'moderate',
  `icon` varchar(10) DEFAULT '­ƒ®║',
  `image` varchar(500) DEFAULT 'assets/images/articles/default.jpg',
  `summary` text DEFAULT NULL,
  `symptoms` mediumtext DEFAULT NULL,
  `causes` mediumtext DEFAULT NULL,
  `treatment` mediumtext DEFAULT NULL,
  `prevention` mediumtext DEFAULT NULL,
  `when_to_call_vet` text DEFAULT NULL,
  `is_published` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `horse_diseases`
--

INSERT INTO `horse_diseases` (`id`, `name`, `name_en`, `category`, `severity`, `icon`, `image`, `summary`, `symptoms`, `causes`, `treatment`, `prevention`, `when_to_call_vet`, `is_published`, `created_at`) VALUES
(1, 'المغص', 'Colic', 'digestive', 'emergency', '🤢', 'assets/images/articles/default.jpg', 'ألم بطني شديد شائع جداً وقد يكون قاتلاً إذا لم يُعالَج بسرعة. السبب الأول للوفاة عند الخيل.', '• تململ مستمر وحفر بالأرض\n• النظر إلى الجناب أو ركله\n• الاستلقاء المتكرر والتقلب\n• فقدان الشهية\n• تعرق غزير\n• ارتفاع نبض القلب فوق 60/د\n• قلة أو انعدام التبرز', '• تغير مفاجئ في الغذاء\n• عدم شرب ماء كافٍ\n• ابتلاع رمل من الأرض\n• ديدان معوية\n• إجهاد أو نقل مفاجئ\n• إعطاء حبوب باردة بعد التمرين', '• اتصل بالطبيب البيطري فوراً\n• امنع الخيل من الأكل\n• امش به ببطء (لا تتركه يستلقي بقوة)\n• قد يحتاج تخدير، أنبوب أنفي معدي، أو جراحة في الحالات الشديدة', '• تغيير تدريجي للأكل\n• ماء نظيف دائم متاح\n• تغذية بالعشب باستمرار وتجنب الحبوب الزائدة\n• برنامج تخلص من الديدان كل 8-12 أسبوع\n• تجنب الأكل على الرمل', 'فوراً عند ظهور أي من علامات المغص. التأخير ساعة واحدة قد يعني الفرق بين الحياة والموت.', 1, '2026-04-19 21:25:34'),
(2, 'انفلونزا الخيل', 'Equine Influenza', 'respiratory', 'moderate', '🤧', 'assets/images/articles/default.jpg', 'مرض فيروسي تنفسي شديد العدوى ينتشر بسرعة بين الخيول.', '• حمى مرتفعة (39-41°م)\n• سعال جاف متكرر\n• إفرازات أنفية صافية ثم سميكة\n• فقدان الشهية والخمول\n• تورم الغدد الليمفاوية\n• انتفاخ الأرجل أحياناً', '• فيروس Equine Influenza A\n• ينتشر بالرذاذ التنفسي\n• معدات مشتركة ملوثة\n• اختلاط بخيول مصابة', '• راحة تامة 3 أسابيع لكل أسبوع حمى\n• مضادات حيوية لمنع المضاعفات البكتيرية\n• خافضات حرارة (تحت إشراف الطبيب)\n• تهوية جيدة وتغذية رطبة', '• تطعيم سنوي إجباري\n• عزل الخيول الجديدة 2-3 أسابيع\n• معدات منفصلة لكل خيل\n• تطهير الإسطبلات دورياً', 'عند ظهور حمى مع سعال أو إفرازات أنفية، خاصة إذا كان هناك تفشٍ في المنطقة.', 1, '2026-04-19 21:25:34'),
(3, 'عفن الحوافر', 'Thrush', 'musculoskeletal', 'moderate', '🦶', 'assets/images/articles/default.jpg', 'عدوى بكتيرية لاهوائية تصيب الحافر، شائعة في الإسطبلات الرطبة.', '• رائحة كريهة جداً من الحافر\n• إفرازات سوداء من شق الحافر\n• تليين أنسجة الحافر\n• عرج خفيف إلى متوسط\n• حساسية عند تنظيف الحافر', '• إسطبل رطب وغير نظيف\n• براز وبول متراكم\n• قلة تنظيف الحوافر\n• قص حوافر غير سليم', '• تنظيف يومي عميق للحافر\n• محاليل مطهرة (يود مخفف، Coppertox)\n• قص الأنسجة الميتة من الحافر\n• إبقاء الحافر جافاً\n• ضمادات في الحالات الشديدة', '• تنظيف الحوافر يومياً\n• إسطبل جاف ونظيف\n• قص حوافر دوري كل 6-8 أسابيع\n• تربينة جافة', 'إذا استمر العرج أو ظهر صديد، أو إذا انتشرت العدوى لأنسجة عميقة.', 1, '2026-04-19 21:25:34'),
(4, 'التهاب صفيحة الحافر', 'Laminitis', 'musculoskeletal', 'severe', '🚨', 'assets/images/articles/default.jpg', 'التهاب الصفائح داخل الحافر، حالة طارئة قد تؤدي لإعاقة دائمة.', '• وقفة كلاسيكية: إرجاع الوزن للأرجل الخلفية\n• عرج شديد خاصة على أرض صلبة\n• حافر ساخن جداً للمس\n• نبض قوي في الشرايين الرقمية\n• امتناع عن الحركة\n• ارتجاف وتعرق', '• إفراط في الأعشاب الربيعية\n• حبوب زائدة (نشويات)\n• زيادة وزن (سمنة)\n• تسمم دموي من حالات أخرى\n• متلازمة كوشينغ\n• حمل مفرط على رِجل واحدة', '• استدعاء طارئ للطبيب البيطري\n• تبريد الحوافر بالماء البارد فوراً\n• دواء مضاد للالتهاب (Bute)\n• راحة في فراش عميق ناعم\n• تعديل النعل (heart-bar shoes)', '• إدارة وزن الفرس\n• الحد من العشب الأخضر الطازج\n• تقسيم الحبوب على وجبات صغيرة\n• فحص دوري لمتلازمات الأيض', 'فوراً. كل ساعة تأخير تزيد من احتمالية ضرر دائم في الحافر.', 1, '2026-04-19 21:25:34'),
(5, 'السعار الجلدي', 'Sweet Itch', 'skin', 'mild', '🦟', 'assets/images/articles/default.jpg', 'حساسية موسمية من لدغات الذباب الصغير، شائعة جداً بين الخيول العربية.', '• حكة شديدة جداً\n• حك مستمر على أي سطح\n• فقدان الشعر بمنطقة الذيل والعرف\n• قشور وجروح من الحك\n• تهيج جلدي محمر\n• تفاقم في الصيف', '• حساسية للعاب ذباب Culicoides\n• نشاط الحشرات في فجر/غسق\n• مناطق رطبة قرب البرك\n• استعداد وراثي (الخيول العربية الأكثر عرضة)', '• كريمات مضادة للحكة (corticosteroids)\n• مضادات هيستامين فموية\n• شامبوهات مهدئة\n• أغطية واقية كاملة (Sweet Itch rugs)\n• طاردات حشرات قوية', '• تربينات بعيدة عن المياه\n• إغلاق الإسطبلات قبل الغسق\n• مراوح قوية في الإسطبل\n• شبكات على النوافذ\n• استخدام طاردات يومية', 'إذا تطورت جروح ملتهبة مع صديد، أو إذا فقد الفرس وزنه من الإجهاد.', 1, '2026-04-19 21:25:34'),
(6, 'الكزاز (التيتانوس)', 'Tetanus', 'infectious', 'emergency', '🚨', 'assets/images/articles/default.jpg', 'مرض بكتيري قاتل يدخل عبر الجروح، الخيول حساسة جداً له.', '• تشنج عضلي\n• صعوبة فتح الفم (Lockjaw)\n• ذيل مرفوع وصلب\n• فتحات أنف متسعة\n• أذنين منتصبتين بقوة\n• حساسية شديدة للضوء والصوت\n• صعوبة في البلع', '• بكتيريا Clostridium tetani\n• تدخل عبر جرح حتى لو صغير\n• شائعة في التربة والبراز\n• الجروح العميقة المغلقة الأخطر', '• استدعاء طارئ للطبيب\n• مصل مضاد للسموم\n• مضادات حيوية (بنسلين)\n• مرخيات عضلات\n• تغذية أنبوبية إذا تعذر البلع\n• عناية في غرفة هادئة مظلمة', '• تطعيم أساسي + جرعة سنوية\n• تعزيز فوري بعد أي جرح\n• تنظيف الجروح فوراً\n• تجنب الأرضيات الحادة', 'فوراً عند أي جرح في خيل غير مطعّم، أو عند ظهور أي أعراض عصبية.', 1, '2026-04-19 21:25:34');

-- --------------------------------------------------------

--
-- Table structure for table `horse_health_records`
--

CREATE TABLE `horse_health_records` (
  `id` int(11) NOT NULL,
  `horse_id` int(11) NOT NULL,
  `clinic_id` int(11) DEFAULT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `record_type` enum('disease','checkup','treatment','note') NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `record_date` date NOT NULL,
  `vet_name` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `horse_health_records`
--

INSERT INTO `horse_health_records` (`id`, `horse_id`, `clinic_id`, `appointment_id`, `record_type`, `title`, `description`, `record_date`, `vet_name`, `created_at`) VALUES
(1, 1, 5, 1, 'disease', 'ءءءء', 'ييييي', '2026-04-23', 'طبيبي', '2026-04-23 19:04:16');

-- --------------------------------------------------------

--
-- Table structure for table `horse_images`
--

CREATE TABLE `horse_images` (
  `id` int(11) NOT NULL,
  `horse_id` int(11) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `caption` varchar(200) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `horse_purchase_requests`
--

CREATE TABLE `horse_purchase_requests` (
  `id` int(11) NOT NULL,
  `horse_id` int(11) NOT NULL,
  `buyer_id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `offer_price` decimal(10,2) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `status` enum('pending','accepted','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `seller_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `horse_vaccinations`
--

CREATE TABLE `horse_vaccinations` (
  `id` int(11) NOT NULL,
  `horse_id` int(11) NOT NULL,
  `clinic_id` int(11) DEFAULT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `vaccine_name` varchar(200) NOT NULL,
  `vaccine_date` date NOT NULL,
  `next_due` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `horse_videos`
--

CREATE TABLE `horse_videos` (
  `id` int(11) NOT NULL,
  `horse_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL DEFAULT '┘ü┘èÏ»┘è┘ê',
  `file_path` varchar(500) NOT NULL,
  `thumb_path` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `sender_type` enum('user','center','clinic','photographer','admin') NOT NULL,
  `body` text DEFAULT NULL,
  `attachment_path` varchar(500) DEFAULT NULL,
  `attachment_type` enum('image','video','audio','file') DEFAULT NULL,
  `attachment_meta` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `conversation_id`, `sender_id`, `sender_type`, `body`, `attachment_path`, `attachment_type`, `attachment_meta`, `is_read`, `created_at`) VALUES
(3, 4, 1, 'user', '【استفسار】 ويب\nسسسسسسسسسسس', NULL, NULL, NULL, 0, '2026-06-04 14:03:37'),
(4, 7, 1, 'user', 'للل', NULL, NULL, NULL, 1, '2026-06-05 14:50:21'),
(5, 7, 106, 'center', 'بب', NULL, NULL, NULL, 1, '2026-06-05 14:50:30');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `body` varchar(500) DEFAULT NULL,
  `icon` varchar(20) DEFAULT '­ƒöö',
  `link` varchar(255) DEFAULT NULL,
  `type` varchar(50) DEFAULT 'general',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `body`, `icon`, `link`, `type`, `is_read`, `created_at`) VALUES
(1, 1, 'تم استلام طلب الحجز', 'حصة في نادي طولكرم الرياضي للفروسية بتاريخ 2026-05-31 الساعة 09:30 — الفرس: سيتم تخصيص الفرس لاحقاً', '🏇', 'account.php?tab=bookings', 'booking', 1, '2026-05-29 17:47:02'),
(2, 5, '💸 مزايدة جديدة على بب', 'مزايدة بمبلغ 31,000 ₪', '🔨', 'auction.php?id=2', 'auction', 0, '2026-06-03 14:29:07'),
(3, 5, '💸 مزايدة جديدة على بب', 'مزايدة بمبلغ 35,000 ₪', '🔨', 'auction.php?id=2', 'auction', 0, '2026-06-03 14:29:59'),
(4, 5, '💸 مزايدة جديدة على بب', 'مزايدة بمبلغ 36,000 ₪', '🔨', 'auction.php?id=2', 'auction', 0, '2026-06-03 15:06:45'),
(5, 5, '💸 مزايدة جديدة على بب', 'مزايدة بمبلغ 42,000 ₪', '🔨', 'auction.php?id=2', 'auction', 0, '2026-06-03 15:06:56'),
(6, 5, '💸 مزايدة جديدة على بب', 'مزايدة بمبلغ 45,000 ₪', '🔨', 'auction.php?id=2', 'auction', 0, '2026-06-03 15:07:11'),
(7, 5, '💸 مزايدة جديدة على بب', 'مزايدة بمبلغ 47,000 ₪', '🔨', 'auction.php?id=2', 'auction', 0, '2026-06-03 15:07:18'),
(8, 5, '💸 مزايدة جديدة على بب', 'مزايدة بمبلغ 49,000 ₪', '🔨', 'auction.php?id=2', 'auction', 0, '2026-06-04 16:10:12'),
(9, 5, '💸 مزايدة جديدة على بب', 'مزايدة بمبلغ 50,000 ₪', '🔨', 'auction.php?id=2', 'auction', 0, '2026-06-04 16:10:19'),
(10, 5, '💸 مزايدة جديدة على بب', 'مزايدة بمبلغ 51,000 ₪', '🔨', 'auction.php?id=2', 'auction', 0, '2026-06-04 16:35:37'),
(11, 5, '💸 مزايدة جديدة على بب', 'مزايدة بمبلغ 52,000 ₪', '🔨', 'auction.php?id=2', 'auction', 0, '2026-06-04 16:35:46'),
(12, 5, '💸 مزايدة جديدة على بب', 'مزايدة بمبلغ 53,000 ₪', '🔨', 'auction.php?id=2', 'auction', 0, '2026-06-04 16:47:22'),
(13, 5, '💸 مزايدة جديدة على بب', 'مزايدة بمبلغ 54,000 ₪', '🔨', 'auction.php?id=2', 'auction', 0, '2026-06-04 16:48:13'),
(14, 5, '💸 مزايدة جديدة على بب', 'مزايدة بمبلغ 55,000 ₪', '🔨', 'auction.php?id=2', 'auction', 0, '2026-06-04 16:48:32'),
(15, 5, '💸 مزايدة جديدة على بب', 'مزايدة بمبلغ 58,000 ₪', '🔨', 'auction.php?id=2', 'auction', 0, '2026-06-04 16:48:56'),
(16, 1, 'تم إرسال طلب الإيواء', 'طلبك تحت المراجعة من المركز. سنخبرك بقرارهم قريباً.', '🏇', 'account.php?tab=boarding', 'boarding', 1, '2026-06-04 17:11:29'),
(17, 1, 'تم حجز جلسة التصوير', 'باقة \"باقة الفارس الأنيق\" بتاريخ 2026-06-15 الساعة 08:00', '📸', 'account.php?tab=photoshoots', 'photoshoot', 1, '2026-06-04 17:33:44'),
(18, 1, 'تم حجز جلسة التصوير', 'باقة \"باقة الفارس الأنيق\" بتاريخ 2026-06-12 الساعة 10:00', '📸', 'account.php?tab=photoshoots', 'photoshoot', 1, '2026-06-04 17:58:38'),
(19, 1, 'تم حجز جلسة التصوير', 'باقة \"باقة الفارس الأنيق\" بتاريخ 2026-06-10 الساعة 09:00', '📸', 'account.php?tab=photoshoots', 'photoshoot', 1, '2026-06-04 18:27:50'),
(20, 1, 'تم حجز جلسة التصوير', 'باقة \"باقة الفارس الأنيق\" بتاريخ 2026-06-10 الساعة 10:00', '📸', 'account.php?tab=photoshoots', 'photoshoot', 0, '2026-06-04 18:36:12'),
(21, 101, 'حجز جديد! 📸', 'حجز جلسة \"باقة الفارس الأنيق\" بتاريخ 2026-06-10 الساعة 10:00', '📅', 'my-studio.php?tab=sessions', 'photoshoot', 1, '2026-06-04 18:36:12'),
(22, 1, 'تم حجز جلسة التصوير', 'باقة \"باقة الفارس الأنيق\" بتاريخ 2026-06-24 الساعة 08:00', '📸', 'account.php?tab=photoshoots', 'photoshoot', 0, '2026-06-04 18:39:49'),
(23, 101, 'حجز جديد! 📸', 'حجز جلسة \"باقة الفارس الأنيق\" بتاريخ 2026-06-24 الساعة 08:00', '📅', 'my-studio.php?tab=sessions', 'photoshoot', 1, '2026-06-04 18:39:49'),
(24, 1, 'تم تأكيد جلستك 📸', 'وافق المصور على جلسة \"باقة الفارس الأنيق\" بتاريخ 24/06/2026 الساعة 08:00', '✅', 'account.php?tab=photoshoots', 'photoshoot', 1, '2026-06-04 18:40:13'),
(25, 1, 'تم حجز موعد في العيادة', '\"طبيبي\" بتاريخ 2026-06-16 الساعة 11:00 — dff', '🏥', 'appointments.php', 'clinic', 1, '2026-06-04 18:59:50'),
(26, 6, 'موعد جديد! 🏥', 'حجز موعد في \"طبيبي\" بتاريخ 2026-06-16 الساعة 11:00 — dff', '📅', 'my-clinic.php?tab=appointments', 'clinic', 1, '2026-06-04 18:59:50'),
(27, 1, '✅ تم قبول حجزك', 'تم تأكيد حجزك في 31/05/2026 — نراك قريباً!', '✅', 'account.php?tab=bookings', 'booking_confirmed', 1, '2026-06-05 14:20:03'),
(28, 1, 'تم استلام طلب الحجز', 'حصة في مركز القدس للفروسية بتاريخ 2026-06-11 الساعة 12:00 — الفرس: سيتم تخصيص الفرس لاحقاً', '🏇', 'account.php?tab=bookings', 'booking', 0, '2026-06-05 14:53:31'),
(29, 1, 'تم استلام طلب الحجز', 'حصة في مركز القدس للفروسية بتاريخ 2026-06-24 الساعة 14:00 — الفرس: سيتم تخصيص الفرس لاحقاً', '🏇', 'account.php?tab=bookings', 'booking', 0, '2026-06-05 14:55:19'),
(30, 106, 'حجز جديد! 📅', 'محمد المستخدم حجز \"درس الفروسية الكلاسيكية\" بتاريخ 2026-06-24 الساعة 14:00', '📩', 'my-center.php?tab=bookings&bk_status=pending', 'booking', 1, '2026-06-05 14:55:19'),
(31, 1, '✅ تم قبول حجزك', 'تم تأكيد حجزك في 11/06/2026 — نراك قريباً!', '✅', 'account.php?tab=bookings', 'booking_confirmed', 1, '2026-06-05 14:55:32'),
(32, 1, '❌ تم رفض حجزك', 'للأسف، تم رفض حجزك بتاريخ 24/06/2026. يمكنك الحجز في موعد آخر.', '❌', 'account.php?tab=bookings', 'booking_rejected', 1, '2026-06-05 14:55:36'),
(33, 1, 'تم استلام طلب الحجز', 'حصة في مركز القدس للفروسية بتاريخ 2026-06-24 الساعة 14:00 — الفرس: سيتم تخصيص الفرس لاحقاً', '🏇', 'account.php?tab=bookings', 'booking', 0, '2026-06-05 14:55:45'),
(34, 106, 'حجز جديد! 📅', 'محمد المستخدم حجز \"درس الفروسية الكلاسيكية\" بتاريخ 2026-06-24 الساعة 14:00', '📩', 'my-center.php?tab=bookings&bk_status=pending', 'booking', 1, '2026-06-05 14:55:45'),
(35, 1, 'تم إرسال طلب الإيواء', 'طلبك تحت المراجعة من المركز. سنخبرك بقرارهم قريباً.', '🏇', 'account.php?tab=boarding', 'boarding', 0, '2026-06-05 14:56:38'),
(36, 106, 'طلب إيواء جديد! 🏇', 'طلب إيواء للفرس \"الملك\" في \"مركز القدس للفروسية\" ابتداءً من 2026-06-24', '📩', 'my-center.php?tab=boarding', 'boarding', 1, '2026-06-05 14:56:38'),
(37, 1, '✅ تم قبول طلب الإيواء', 'تم قبول إيواء فرسك \"الملك\" في مركز القدس للفروسية', '✅', 'account.php?tab=boarding', 'boarding', 0, '2026-06-05 14:57:05'),
(38, 1, '✅ تم قبول حجزك', 'تم تأكيد حجزك في 24/06/2026 — نراك قريباً!', '✅', 'account.php?tab=bookings', 'booking_confirmed', 0, '2026-06-05 14:57:13'),
(39, 106, '??? ????! ??', '?????? ? ??? ??????', '??', 'my-center.php?tab=bookings&bk_status=pending', 'booking', 1, '2026-06-05 15:02:34'),
(40, 106, 'تسجيل جديد في فعالية! 🏆', 'محمد المستخدم سجّل في \"السرعه\" — المشاركون الآن: 1 / 50', '🎉', 'my-center.php?tab=events', 'event', 1, '2026-06-05 15:03:25'),
(41, 5, '💳 الفائز دفع العربون!', 'محمد المستخدم دفع العربون (5,800 ₪) لمزاد \"بب\" — يمكنك التواصل معه لإتمام الصفقة.', '💰', 'auction.php?id=2', 'auction', 1, '2026-06-06 15:43:06'),
(42, 103, 'تم إزالة توثيق حسابك', 'تم سحب شارة التوثيق من حسابك من قِبل الإدارة.', '⚠️', 'contact.php', 'admin', 0, '2026-06-06 15:46:51'),
(43, 103, 'تم توثيق حسابك ✓', 'تهانينا! تم منح حسابك شارة التوثيق الرسمية.', '✅', 'account.php', 'admin', 0, '2026-06-06 15:46:58'),
(44, 5, 'تمت ترقيتك إلى مركز فروسية ✅', 'يمكنك الآن إدارة مركزك من خلال لوحة التحكم.', '🏇', 'my-center.php', 'system', 0, '2026-06-06 15:50:16'),
(45, 3, 'طلب جديد في متجرك! 🛒', 'محمد المستخدم اشترى من متجرك — طلب #ORD-20260606-88360', '📦', 'my-shop.php?tab=sales', 'order', 0, '2026-06-06 15:51:36'),
(46, 106, 'طلب جديد في متجرك! 🛒', 'محمد المستخدم اشترى من متجرك — طلب #ORD-20260607-81212', '📦', 'my-shop.php?tab=sales', 'order', 0, '2026-06-07 09:33:38'),
(47, 106, 'طلب جديد في متجرك! 🛒', 'محمد المستخدم اشترى من متجرك — طلب #ORD-20260607-B82C0', '📦', 'my-shop.php?tab=sales', 'order', 0, '2026-06-07 09:44:42'),
(48, 1, 'تم تأكيد طلبك ✅', 'البائع أكّد طلبك رقم #ORD-20260607-B82C0 — سيتم الشحن قريباً', '📦', 'order.php?id=5', 'order', 0, '2026-06-07 09:45:06'),
(49, 1, 'طلبك في الطريق إليك 🚚', 'تم شحن طلبك رقم #ORD-20260607-B82C0 — سيصلك قريباً', '🚚', 'order.php?id=5', 'order', 0, '2026-06-07 09:45:52');

-- --------------------------------------------------------

--
-- Table structure for table `offers`
--

CREATE TABLE `offers` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `discount_percent` int(11) DEFAULT 0,
  `image` varchar(500) DEFAULT NULL,
  `center_id` int(11) DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `offers`
--

INSERT INTO `offers` (`id`, `title`, `description`, `discount_percent`, `image`, `center_id`, `valid_until`, `created_at`) VALUES
(1, 'خصم الربيع - 30% على جميع الدروس', 'عرض حصري بمناسبة الربيع، خصم 30% على جميع دروس الفروسية في نادي الفرسان الذهبي.', 30, 'assets/images/offers/o1.jpg', 7, '2026-05-31', '2026-04-17 19:37:19'),
(2, 'باقة عائلية بسعر خاص', 'وفر 40% عند حجز باقة دروس لأربعة أفراد من العائلة في النادي الملكي.', 40, 'assets/images/offers/o2.jpg', 8, '2026-06-15', '2026-04-17 19:37:19'),
(3, 'معسكر الأطفال الصيفي - خصم 25%', 'سجل طفلك مبكراً في المعسكر الصيفي واحصل على خصم 25%.', 25, 'assets/images/offers/o3.jpg', 9, '2026-06-01', '2026-04-17 19:37:19'),
(4, 'باقة الفارس المحترف', 'خصم 20% على باقة 10 دروس احترافية في مدرسة البطولة.', 20, 'assets/images/offers/o4.jpg', 10, '2026-05-20', '2026-04-17 19:37:19'),
(5, 'دروس الفروسية التراثية - عرض خاص', 'خصم 15% على دورة تعليم الفروسية التراثية في إسطبلات الأصايل.', 15, 'assets/images/offers/o5.jpg', 11, '2026-05-30', '2026-04-17 19:37:19'),
(6, 'خصم العملاء الجدد', 'خصم 50% على أول درس للعملاء الجدد في جميع المراكز المشاركة.', 50, 'assets/images/offers/o6.jpg', 12, '2026-12-31', '2026-04-17 19:37:19');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `order_number` varchar(30) NOT NULL,
  `user_id` int(11) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `shipping` decimal(10,2) DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `city` varchar(50) NOT NULL,
  `address` text NOT NULL,
  `notes` text DEFAULT NULL,
  `payment_method` enum('cash','card') DEFAULT 'cash',
  `status` enum('pending','processing','shipped','delivered','cancelled') DEFAULT 'pending',
  `seller_confirmed` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `order_number`, `user_id`, `subtotal`, `shipping`, `total`, `full_name`, `phone`, `city`, `address`, `notes`, `payment_method`, `status`, `seller_confirmed`, `created_at`) VALUES
(1, 'ORD-20260417-A75E8', 1, 1300.00, 0.00, 1300.00, 'محمد المستخدم', '0591111111', 'رام الله', 'edshth\r\nssss', '', 'card', 'pending', 0, '2026-04-17 20:25:24'),
(2, 'ORD-20260417-189A9', 1, 1010.00, 0.00, 1010.00, 'محمد المستخدم', '0591111111', 'رام الله', 'edshth\r\nssss', 'ffffff', 'cash', 'pending', 0, '2026-04-17 21:33:41'),
(3, 'ORD-20260606-88360', 1, 650.00, 0.00, 650.00, 'محمد المستخدم', '0591111111', 'رام الله', 'edshth\r\nssss', 'ببب', 'cash', 'pending', 0, '2026-06-06 15:51:36'),
(4, 'ORD-20260607-81212', 1, 200.00, 30.00, 230.00, 'محمد المستخدم', '0591111111', 'رام الله', 'edshth\r\nssss', '', 'card', 'cancelled', 0, '2026-06-07 09:33:38'),
(5, 'ORD-20260607-B82C0', 1, 250.00, 30.00, 280.00, 'محمد المستخدم', '0591111111', 'رام الله', 'edshth\r\nssss', '', 'card', 'shipped', 1, '2026-06-07 09:44:42');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(200) NOT NULL,
  `product_image` varchar(500) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `quantity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `product_image`, `price`, `quantity`) VALUES
(1, 1, 1, 'سرج جلدي فاخر - صناعة إيطالية', 'assets/images/products/p1.jpg', 650.00, 2),
(2, 2, 1, 'سرج جلدي فاخر - صناعة إيطالية', 'assets/images/products/p1.jpg', 650.00, 1),
(3, 2, 2, 'خوذة فروسية احترافية سوداء', 'assets/images/products/p2.jpg', 180.00, 2),
(4, 3, 1, 'سرج جلدي فاخر - صناعة إيطالية', 'assets/images/products/p1.jpg', 650.00, 1),
(5, 4, 13, 'سرج', 'assets/images/products/p_106_1780824763.jpg', 200.00, 1),
(6, 5, 13, 'سرج', 'assets/images/products/p_106_1780824763.jpg', 200.00, 1),
(7, 5, 14, 'خوذه', 'assets/images/products/p_106_1780825420.jpg', 50.00, 1);

-- --------------------------------------------------------

--
-- Table structure for table `photographers`
--

CREATE TABLE `photographers` (
  `id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `studio_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `city` varchar(50) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `image` varchar(500) DEFAULT 'assets/images/horses/h1.jpg',
  `cover_image` varchar(500) DEFAULT 'assets/images/hero.jpg',
  `instagram` varchar(150) DEFAULT NULL,
  `portfolio_url` varchar(255) DEFAULT NULL,
  `years_experience` int(11) DEFAULT 1,
  `rating` decimal(2,1) DEFAULT 4.5,
  `reviews_count` int(11) DEFAULT 0,
  `approval_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `verified` tinyint(1) DEFAULT 0,
  `featured` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `photographers`
--

INSERT INTO `photographers` (`id`, `owner_id`, `studio_name`, `description`, `city`, `address`, `phone`, `email`, `image`, `cover_image`, `instagram`, `portfolio_url`, `years_experience`, `rating`, `reviews_count`, `approval_status`, `verified`, `featured`, `created_at`) VALUES
(1, 101, 'استوديو أحمد الفروسي', 'متخصص في تصوير الخيل والفروسية منذ 8 سنوات. نلتقط أجمل اللحظات بعدسة احترافية في أماكن خلابة.', 'رام الله', 'شارع الإرسال، رام الله', '0591000001', 'photographer1@khoyol.test', 'assets/images/horses/h1.jpg', 'assets/images/hero.jpg', NULL, NULL, 8, 4.8, 24, 'approved', 1, 1, '2026-06-04 17:57:33'),
(2, 102, 'لنز سارة للتصوير', 'مصورة متخصصة في جلسات التصوير العائلي مع الخيل. أُقدّم تجربة دافئة ومميزة للعائلات والأطفال.', 'نابلس', 'حي الشمالي، نابلس', '0591000002', 'photographer2@khoyol.test', 'assets/images/horses/h1.jpg', 'assets/images/hero.jpg', NULL, NULL, 5, 4.7, 18, 'approved', 1, 0, '2026-06-04 17:57:33'),
(3, 103, 'استوديو الفارس الذهبي', 'استوديو متكامل لتصوير الفروسية والخيل بأعلى مستوى. خبرة 12 سنة وجلسات VIP لمن يريد الأفضل.', 'الخليل', 'منطقة الحرس، الخليل', '0591000003', 'photographer3@khoyol.test', 'assets/images/horses/h1.jpg', 'assets/images/hero.jpg', NULL, NULL, 12, 4.9, 41, 'approved', 1, 1, '2026-06-04 17:57:33');

-- --------------------------------------------------------

--
-- Table structure for table `photographer_gallery`
--

CREATE TABLE `photographer_gallery` (
  `id` int(11) NOT NULL,
  `photographer_id` int(11) NOT NULL,
  `image` varchar(500) NOT NULL,
  `title` varchar(200) DEFAULT NULL,
  `caption` varchar(500) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `photographer_gallery`
--

INSERT INTO `photographer_gallery` (`id`, `photographer_id`, `image`, `title`, `caption`, `sort_order`, `created_at`) VALUES
(1, 1, 'assets/uploads/gallery/g_1_1780596189_0.jpg', NULL, NULL, 0, '2026-06-04 18:03:09'),
(2, 2, 'assets/uploads/gallery/g_2_1780596524_0.jpg', NULL, NULL, 0, '2026-06-04 18:08:44');

-- --------------------------------------------------------

--
-- Table structure for table `photoshoot_bookings`
--

CREATE TABLE `photoshoot_bookings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `package_id` int(11) NOT NULL,
  `horse_id` int(11) DEFAULT NULL,
  `session_date` date NOT NULL,
  `session_time` time NOT NULL,
  `outfit_choices` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `session_notes` text DEFAULT NULL,
  `delivery_link` varchar(500) DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `status` enum('pending','confirmed','completed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `photoshoot_bookings`
--

INSERT INTO `photoshoot_bookings` (`id`, `user_id`, `package_id`, `horse_id`, `session_date`, `session_time`, `outfit_choices`, `notes`, `session_notes`, `delivery_link`, `delivered_at`, `status`, `created_at`) VALUES
(1, 1, 1, NULL, '2026-06-12', '10:00:00', 'جاكيت', '', NULL, NULL, NULL, 'confirmed', '2026-06-04 17:58:38'),
(2, 1, 1, 1, '2026-06-10', '09:00:00', 'جاكيت,بوت,خوذة', '', NULL, NULL, NULL, 'confirmed', '2026-06-04 18:27:50'),
(3, 1, 1, NULL, '2026-06-10', '10:00:00', NULL, '', NULL, NULL, NULL, 'cancelled', '2026-06-04 18:36:12'),
(4, 1, 1, NULL, '2026-06-24', '08:00:00', NULL, '', NULL, NULL, NULL, 'confirmed', '2026-06-04 18:39:49');

-- --------------------------------------------------------

--
-- Table structure for table `photoshoot_deliveries`
--

CREATE TABLE `photoshoot_deliveries` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `photographer_id` int(11) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `is_video` tinyint(1) DEFAULT 0,
  `caption` varchar(300) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `photoshoot_packages`
--

CREATE TABLE `photoshoot_packages` (
  `id` int(11) NOT NULL,
  `center_id` int(11) DEFAULT NULL,
  `photographer_id` int(11) DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `duration_minutes` int(11) DEFAULT 60,
  `photos_count` int(11) DEFAULT 30,
  `free_outfits` varchar(255) DEFAULT 'Ï¼Ïº┘â┘èÏ¬Ïî Ï¿┘êÏ¬',
  `max_outfit_choices` tinyint(2) NOT NULL DEFAULT 1,
  `image` varchar(500) DEFAULT 'assets/images/photoshoots/default.jpg',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `photoshoot_packages`
--

INSERT INTO `photoshoot_packages` (`id`, `center_id`, `photographer_id`, `title`, `description`, `price`, `duration_minutes`, `photos_count`, `free_outfits`, `max_outfit_choices`, `image`, `is_active`, `created_at`) VALUES
(1, NULL, 1, 'باقة الفارس الأنيق', 'جلسة تصوير فردية أنيقة مع فرسك، مناسبة للذكريات الشخصية والصور الاحترافية. تشمل 30 صورة محررة باحترافية عالية.', 350.00, 60, 30, 'جاكيت، بوت، خوذة', 3, 'assets/images/photoshoots/elegant.jpg', 1, '2026-06-04 17:55:20'),
(2, NULL, 2, 'باقة العائلة', 'جلسة تصوير عائلية ترسم ذكريات جميلة مع الأطفال والخيل. تشمل 50 صورة محررة مع ملابس فروسية مجانية للأطفال.', 500.00, 90, 50, 'جاكيت أطفال، بوت أطفال', 2, 'assets/images/photoshoots/family.jpg', 1, '2026-06-04 17:55:20'),
(3, NULL, 3, 'باقة الفارس الذهبية', 'الباقة الأشمل والأفخم — جلسة ممتدة مع فرسك تشمل مشاهد متعددة وأزياء احترافية. 80 صورة محررة بجودة استوديو، مثالية للمسابقات والنشر.', 750.00, 120, 80, 'جاكيت، بوت، قفازات، سرج زينة', 4, 'assets/images/photoshoots/golden.jpg', 1, '2026-06-04 17:55:20');

-- --------------------------------------------------------

--
-- Table structure for table `prescriptions`
--

CREATE TABLE `prescriptions` (
  `id` int(11) NOT NULL,
  `clinic_id` int(11) NOT NULL,
  `horse_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `medication` varchar(200) NOT NULL,
  `dosage` varchar(150) DEFAULT NULL,
  `frequency` varchar(150) DEFAULT NULL,
  `duration` varchar(100) DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `issue_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `seller_id` int(11) DEFAULT NULL,
  `seller_name` varchar(100) DEFAULT NULL,
  `approval_status` enum('approved','pending','rejected') DEFAULT 'approved',
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `old_price` decimal(10,2) DEFAULT NULL,
  `image` varchar(500) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `stock` int(11) DEFAULT 10,
  `rating` decimal(2,1) DEFAULT 4.5,
  `featured` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `seller_id`, `seller_name`, `approval_status`, `name`, `description`, `price`, `old_price`, `image`, `category_id`, `stock`, `rating`, `featured`, `created_at`) VALUES
(1, 3, NULL, 'approved', 'سرج جلدي فاخر - صناعة إيطالية', 'سرج جلد طبيعي 100% بتصميم كلاسيكي، مريح للفارس والحصان، مناسب للفروسية الاحترافية والدروس اليومية.', 650.00, 850.00, 'assets/images/products/p1.jpg', 1, 1, 4.9, 1, '2026-04-17 18:31:46'),
(2, 3, NULL, 'approved', 'خوذة فروسية احترافية سوداء', 'خوذة معتمدة دولياً بمواصفات السلامة العالمية، خفيفة الوزن ومزودة بتهوية داخلية.', 180.00, 240.00, 'assets/images/products/p2.jpg', 3, 13, 4.8, 1, '2026-04-17 18:31:46'),
(3, 3, NULL, 'approved', 'بوت فروسية جلد طويل', 'حذاء ركوب خيل جلد أصلي أسود أنيق، مقاس قابل للتعديل، مناسب للتدريبات والمسابقات.', 220.00, NULL, 'assets/images/products/p3.jpg', 2, 8, 4.7, 1, '2026-04-17 18:31:46'),
(4, 3, NULL, 'approved', 'لجام جلدي مُطعّم', 'لجام من الجلد الطبيعي مزود بقطع معدنية عالية الجودة لتحكم مثالي.', 120.00, 160.00, 'assets/images/products/p4.jpg', 5, 12, 4.6, 0, '2026-04-17 18:31:46'),
(5, 3, NULL, 'approved', 'بنطال فروسية للسيدات', 'بنطال مرن مخصص للفروسية، قماش تقني يتحمل الاحتكاك ومقاوم للبقع.', 95.00, NULL, 'assets/images/products/p5.jpg', 2, 20, 4.5, 1, '2026-04-17 18:31:46'),
(6, 3, NULL, 'approved', 'فرشاة عناية بالخيل - طقم كامل', 'طقم 6 قطع للعناية اليومية بالخيل يشمل فرش متنوعة ومشط.', 75.00, 95.00, 'assets/images/products/p6.jpg', 4, 25, 4.4, 0, '2026-04-17 18:31:46'),
(7, 3, NULL, 'approved', 'قفازات فروسية جلد', 'قفازات جلدية ناعمة بقبضة محكمة لراحة اليد أثناء الركوب.', 60.00, NULL, 'assets/images/products/p7.jpg', 2, 30, 4.3, 0, '2026-04-17 18:31:46'),
(8, 3, NULL, 'approved', 'بطانية حصان شتوية', 'بطانية عازلة للبرد ومقاومة للماء، مقاسات متعددة.', 140.00, 180.00, 'assets/images/products/p8.jpg', 5, 10, 4.7, 1, '2026-04-17 18:31:46'),
(9, 3, NULL, 'approved', 'علف خيل بروتين عالي 25 كغ', 'علف متوازن غني بالفيتامينات لدعم قوة الخيل ولمعان الفراء.', 85.00, NULL, 'assets/images/products/p9.jpg', 6, 40, 4.6, 0, '2026-04-17 18:31:46'),
(10, 3, NULL, 'approved', 'ركاب معدني مطلي كروم', 'ركاب مقاوم للصدأ بتصميم مريح للقدم ومانع للانزلاق.', 55.00, 75.00, 'assets/images/products/p10.jpg', 1, 18, 4.5, 0, '2026-04-17 18:31:46'),
(11, 3, NULL, 'approved', 'حزام سرج مبطن', 'حزام عالي الجودة بطانة إسفنجية مريحة للحصان.', 80.00, NULL, 'assets/images/products/p11.jpg', 1, 14, 4.4, 0, '2026-04-17 18:31:46'),
(12, 3, NULL, 'approved', 'جاكيت فروسية احترافي', 'جاكيت رسمي للمسابقات بتطريز أنيق ومقاسات مختلفة.', 250.00, 320.00, 'assets/images/products/p12.jpg', 2, 7, 4.8, 1, '2026-04-17 18:31:46'),
(13, 106, NULL, 'approved', 'سرج', '', 200.00, NULL, 'assets/images/products/p_106_1780824763.jpg', 1, 4, 4.5, 0, '2026-06-07 09:32:43'),
(14, 106, NULL, 'approved', 'خوذه', '', 50.00, NULL, 'assets/images/products/p_106_1780825420.jpg', 3, 2, 4.5, 0, '2026-06-07 09:43:40');

-- --------------------------------------------------------

--
-- Table structure for table `public_notifications`
--

CREATE TABLE `public_notifications` (
  `id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `icon` varchar(20) DEFAULT 'Ô£¿',
  `color` varchar(20) DEFAULT 'gold',
  `link` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `public_notifications`
--

INSERT INTO `public_notifications` (`id`, `title`, `icon`, `color`, `link`, `is_active`, `sort_order`, `created_at`) VALUES
(2, 'سروج جديدة وصلت!', '🪑', 'gold', 'shop.php', 1, 2, '2026-04-19 21:25:34'),
(3, 'بطولة القفز القادمة: ١٥ مايو', '🏆', 'gold', 'events.php', 1, 3, '2026-04-19 21:25:34'),
(4, 'مزاد مفتوح: كحيلان الثالث', '🔨', 'red', 'auctions.php', 1, 4, '2026-04-19 21:25:34');

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `id` int(11) NOT NULL,
  `reporter_id` int(11) DEFAULT NULL,
  `target_type` enum('user','center','product','review') NOT NULL,
  `target_id` int(11) NOT NULL,
  `reason` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('pending','reviewed','dismissed','actioned') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reports`
--

INSERT INTO `reports` (`id`, `reporter_id`, `target_type`, `target_id`, `reason`, `description`, `status`, `created_at`) VALUES
(1, 1, 'center', 5, 'معلومات غير صحيحة', 'أرقام الهاتف والعنوان غير صحيحة في صفحة المركز.', 'pending', '2026-04-17 20:07:23'),
(2, 1, 'product', 3, 'صورة لا تطابق المنتج', 'الصورة المعروضة مختلفة عن المنتج الحقيقي.', 'pending', '2026-04-17 20:07:23');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `center_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` between 1 and 5),
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `center_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `duration` varchar(50) DEFAULT '60 دقيقة',
  `icon` varchar(20) DEFAULT '?'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `center_id`, `name`, `description`, `price`, `duration`, `icon`) VALUES
(20, 7, 'درس ركوب مبتدئين', 'درس فردي مع مدرب معتمد لتعلم أساسيات الركوب والتوازن والتحكم بالحصان بأسلوب آمن ومنظم.', 120.00, '60 دقيقة', '🐴'),
(21, 7, 'درس ركوب متوسط', 'تطوير مهارات الركوب وتعلم الخبب والجري والتحكم بالاتجاهات مع مدرب متخصص.', 150.00, '60 دقيقة', '🏇'),
(22, 7, 'درس ركوب احترافي', 'تدريب متقدم على الفروسية الكلاسيكية وتقنيات القفز والتحضير للمنافسات.', 200.00, '90 دقيقة', '🏆'),
(23, 7, 'جولة ترفيهية على الخيل', 'جولة ممتعة داخل مسارات النادي المخصصة للعائلات والزيارات الترفيهية، مناسبة لجميع الأعمار.', 80.00, '30 دقيقة', '🌿'),
(24, 7, 'إيواء الخيول', 'خدمة إيواء متكاملة تشمل إسطبل مجهز، تغذية يومية، رعاية بيطرية دورية ومتابعة صحية كاملة.', 800.00, 'شهري', '🏠'),
(25, 7, 'جلسة تصوير فروسية', 'جلسة تصوير احترافية للفارس وحصانه داخل حلبة النادي مع مصور متخصص وملابس فروسية متوفرة.', 350.00, '2 ساعة', '📸'),
(26, 8, 'درس ركوب للأطفال', 'برنامج تعليمي ممتع ومنظم للأطفال من 5 سنوات فأكثر، يركز على بناء الثقة والألفة مع الخيول.', 90.00, '45 دقيقة', '👦'),
(27, 8, 'درس ركوب مبتدئين', 'تعلم ركوب الخيل من الصفر مع مدرب متخصص، يشمل أساسيات التحكم والأمان والتوازن.', 110.00, '60 دقيقة', '🐴'),
(28, 8, 'درس ركوب احترافي', 'تدريب متقدم للفرسان المحترفين على أسلوب الفروسية العربية والقفز على الحواجز.', 180.00, '90 دقيقة', '🏆'),
(29, 8, 'برنامج صيفي للناشئين', 'برنامج تدريبي مكثف خلال الصيف يشمل ركوب الخيل والرعاية والتاريخ العربي للخيول.', 1200.00, '4 أسابيع', '☀️'),
(30, 8, 'جولة ترفيهية', 'جولة على ظهر الخيل في المسارات الطبيعية المحيطة بالنادي، مناسبة للعائلات والزيارات الترفيهية.', 70.00, '30 دقيقة', '🌿'),
(31, 8, 'إيواء الخيول', 'خدمة إيواء شاملة بمعايير عالية تشمل الإسطبل والتغذية اليومية ومتابعة بيطرية منتظمة.', 750.00, 'شهري', '🏠'),
(32, 9, 'درس ركوب مبتدئين', 'مقدمة شاملة لعالم الفروسية مع مدربين ذوي خبرة دولية، تشمل التعارف بالحصان وأساسيات الركوب.', 130.00, '60 دقيقة', '🐴'),
(33, 9, 'درس الفروسية الكلاسيكية', 'تعلم أسلوب الفروسية الكلاسيكية الأوروبية بإشراف مدرب حاصل على شهادة دولية.', 220.00, '90 دقيقة', '🎩'),
(34, 9, 'تدريب على القفز', 'برنامج متخصص في تدريب الفروسية على القفز فوق الحواجز وفق معايير الفيدرالية الدولية.', 250.00, '90 دقيقة', '🏇'),
(35, 9, 'علاج بالخيل (هيبوثيرابي)', 'جلسات علاجية معتمدة تستخدم الخيل كأداة علاج للأطفال وذوي الاحتياجات الخاصة.', 300.00, '60 دقيقة', '💚'),
(36, 9, 'رحلة ركوب في الطبيعة', 'رحلة جماعية على ظهور الخيل في المناطق الجبلية حول القدس، مع مرشد متخصص.', 180.00, '3 ساعات', '⛰️'),
(37, 9, 'إيواء الخيول VIP', 'إيواء فاخر بمستوى خمس نجوم يشمل إسطبل مكيف، تغذية مخصصة، ورعاية بيطرية يومية.', 1200.00, 'شهري', '⭐'),
(38, 10, 'درس ركوب مبتدئين', 'بداية رحلتك مع الخيول بأيدي خبراء، تعلم الركوب الصحيح والتواصل مع الحصان بثقة.', 100.00, '60 دقيقة', '🐴'),
(39, 10, 'رحلة ركوب طبيعية', 'استكشف جمال الجليل على ظهر الخيل في مسارات طبيعية خلابة مع مرشد سياحي متخصص.', 160.00, '2 ساعة', '🌄'),
(40, 10, 'برنامج علاج بالخيل', 'جلسات علاج نفسي وجسدي معتمدة تستخدم التفاعل مع الخيول لتحسين الصحة النفسية والجسدية.', 280.00, '60 دقيقة', '💚'),
(41, 10, 'إيواء الخيول', 'إيواء شامل في إسطبلات الجليل الطبيعية الهادئة مع رعاية بيطرية متكاملة.', 700.00, 'شهري', '🏠'),
(42, 11, 'درس ركوب مبتدئين', 'ابدأ رحلتك في عالم الفروسية مع فرسان الخليل تحت إشراف مدربين ذوي خبرة واسعة.', 100.00, '60 دقيقة', '🐴'),
(43, 11, 'تدريب خيول السباق', 'برنامج تدريبي احترافي لخيول السباق يشمل التكييف البدني والتغذية والتحضير للبطولات.', 500.00, 'يومي', '🏁'),
(44, 11, 'تدريب خيول الجمال', 'تأهيل وتدريب الخيول للمشاركة في مسابقات الجمال وفق المعايير الدولية للخيول العربية الأصيلة.', 450.00, 'يومي', '🌟'),
(45, 11, 'جلسة تصوير الخيول', 'جلسة تصوير احترافية مع أجمل خيول الخليل وفتوغرافر متخصص في تصوير الفروسية.', 400.00, '2 ساعة', '📸'),
(46, 11, 'إيواء الخيول', 'إيواء احترافي مع متابعة يومية من متخصصي الخيول، يشمل الرعاية الغذائية والبيطرية الكاملة.', 850.00, 'شهري', '🏠'),
(47, 12, 'درس ركوب للأطفال', 'برنامج تعليمي آمن وممتع للأطفال يبني الثقة بالنفس ويعلمهم قيم الفروسية الأصيلة.', 85.00, '45 دقيقة', '👦'),
(48, 12, 'درس ركوب مبتدئين', 'تعلم ركوب الخيل بشكل صحيح وآمن مع مدرب متخصص في بيئة حديثة ومجهزة.', 110.00, '60 دقيقة', '🐴'),
(49, 12, 'تدريب القفز الدولي', 'تدريب على القفز فوق حواجز الحلبة الدولية في النادي استعداداً للبطولات المحلية والإقليمية.', 230.00, '90 دقيقة', '🏇'),
(50, 12, 'اشتراك شهري تدريبي', 'باقة تدريب شهرية تشمل 8 دروس مع مدرب خاص وإمكانية استخدام الحلبات في أوقات محددة.', 700.00, 'شهري', '📅'),
(51, 12, 'إيواء الخيول', 'خدمة إيواء متكاملة في إسطبلات نادي طولكرم مع رعاية بيطرية ومتابعة يومية.', 780.00, 'شهري', '🏠'),
(52, 13, 'درس ركوب مبتدئين', 'انطلق في رحلتك مع الفروسية في قلب غزة مع مدربين متخصصين وخيول مدربة ومعتادة على التعليم.', 90.00, '60 دقيقة', '🐴'),
(53, 13, 'برنامج ناشئين صيفي', 'برنامج صيفي خاص للناشئين يشمل الركوب اليومي والرعاية وتاريخ الخيول العربية الأصيلة.', 800.00, '3 أسابيع', '☀️'),
(54, 13, 'جلسة ركوب ترفيهية', 'تجربة ركوب ممتعة لمن يريد الاستمتاع بالخيول دون التزام بدورات تدريبية مطولة.', 70.00, '30 دقيقة', '🌿'),
(55, 13, 'إيواء الخيول', 'رعاية واحتضان خيلك في اسطبلات مركز غزة مع فريق متخصص يوفر الغذاء والرعاية الكاملة.', 650.00, 'شهري', '🏠');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `role` enum('user','center','clinic','photographer','admin') DEFAULT 'user',
  `avatar` varchar(255) DEFAULT 'assets/images/default-avatar.png',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','banned','pending') DEFAULT 'active',
  `verified` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password`, `phone`, `city`, `role`, `avatar`, `created_at`, `status`, `verified`) VALUES
(1, 'محمد المستخدم', 'user@test.com', '$2y$10$.u249rt73D/AjUxBoCrg1ORVvsGiuiI.LlvVWNX5Mz3rYDiKQY0PS', '0591111111', 'رام الله', 'user', 'assets/images/default-avatar.png', '2026-04-17 19:47:57', 'active', 0),
(3, 'المدير', 'admin@test.com', '$2y$10$T.3KA1oD7i21ZcvAZEDkneKozY8Krw5LUaYt5UxcVsJbTVO6SL9oy', '0593333333', 'القدس', 'admin', 'assets/images/default-avatar.png', '2026-04-17 19:47:57', 'active', 0),
(5, 'الملكي', 'c@test.com', '$2y$10$WuKii9LClH/Gkew2jgGjC.1EJdj.I.zIuw4QMr87MtNi320fYzQYa', '00000000000000', 'رام الله', 'center', 'assets/images/default-avatar.png', '2026-04-23 18:26:01', 'active', 0),
(6, 'طبيبي', 'cl@test.com', '$2y$10$63i6FmMRD8tvdoxibz8IW.0qS9nMOdt0Lqo28.1oSpx3p/9obMxue', '00000000000', 'جنين', 'clinic', 'assets/images/default-avatar.png', '2026-04-23 18:50:23', 'active', 0),
(101, 'أحمد خالد المصور', 'photographer1@khoyol.test', '$2y$10$rJ2ZFDP0zlcm2f.S5oScCu0ZgIddezLXlabj9XLdfhCYamzKK13B6', '0591000001', 'رام الله', 'photographer', 'assets/images/default-avatar.png', '2026-06-04 17:57:33', 'active', 1),
(102, 'سارة يوسف للتصوير', 'photographer2@khoyol.test', '$2y$10$rJ2ZFDP0zlcm2f.S5oScCu0ZgIddezLXlabj9XLdfhCYamzKK13B6', '0591000002', 'نابلس', 'photographer', 'assets/images/default-avatar.png', '2026-06-04 17:57:33', 'active', 1),
(103, 'استوديو الفارس', 'photographer3@khoyol.test', '$2y$10$rJ2ZFDP0zlcm2f.S5oScCu0ZgIddezLXlabj9XLdfhCYamzKK13B6', '0591000003', 'الخليل', 'photographer', 'assets/images/default-avatar.png', '2026-06-04 17:57:33', 'active', 1),
(104, 'نادي الملكي للفروسية', 'center.malki@khyol.ps', '$2y$10$xg7Muxf9a097Y9Q6CGKkyeAEIpf8bsSdQ6AFun6J6ymZ7cEVvvdDe', '0592100007', 'رام الله', 'center', 'assets/images/default-avatar.png', '2026-06-05 14:14:47', 'active', 1),
(105, 'نادي النخيل للفروسية', 'center.nakheel@khyol.ps', '$2y$10$UAetd8n2SU0la32FzRboXe3ZoESHk/UvsEpJ6nER81VgwrR4eZv6i', '0592100008', 'نابلس', 'center', 'assets/images/default-avatar.png', '2026-06-05 14:14:48', 'active', 1),
(106, 'مركز القدس للفروسية', 'center.quds@khyol.ps', '$2y$10$nKox0dXbjCSJa/3XZZ9UT.P50cL4SqiucTKKDF9jiYOGWoQjDc9aC', '0592100009', 'القدس', 'center', 'assets/images/default-avatar.png', '2026-06-05 14:14:48', 'active', 1),
(107, 'اسطبلات الجليل', 'center.galilee@khyol.ps', '$2y$10$oabLn3thXkViR/3c7WmpnOZ1x0VTdllLFtuqGmEX31txb94n1iy0q', '0592100010', 'الناصرة', 'center', 'assets/images/default-avatar.png', '2026-06-05 14:14:48', 'active', 1),
(108, 'فرسان الخليل', 'center.khalil@khyol.ps', '$2y$10$jXrc78kOXAGLup0hC4VCLe6y74bgbr7EHAyc8whCWCfTqC4HVUttW', '0592100011', 'الخليل', 'center', 'assets/images/default-avatar.png', '2026-06-05 14:14:48', 'active', 1),
(109, 'نادي طولكرم الرياضي للفروسية', 'center.tulkarm@khyol.ps', '$2y$10$ui41.wiEDUVSC3aLaFpThuGNaSRurRzWHDHSxvW2NMffWwikaLzAW', '0592100012', 'طولكرم', 'center', 'assets/images/default-avatar.png', '2026-06-05 14:14:48', 'active', 1),
(110, 'مركز غزة للفروسية', 'center.gaza@khyol.ps', '$2y$10$3qJlkyBVBEW2rVy2fdnQvO/XCqZU.8tUACJq1StSTG8u4wIvyVsS2', '0592100013', 'غزة', 'center', 'assets/images/default-avatar.png', '2026-06-05 14:14:48', 'active', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `articles`
--
ALTER TABLE `articles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category` (`category_id`,`created_at`);
ALTER TABLE `articles` ADD FULLTEXT KEY `idx_search` (`title`,`excerpt`,`content`);

--
-- Indexes for table `article_categories`
--
ALTER TABLE `article_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `auctions`
--
ALTER TABLE `auctions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `horse_id` (`horse_id`),
  ADD KEY `idx_status_ends` (`status`,`ends_at`),
  ADD KEY `idx_seller` (`seller_id`);

--
-- Indexes for table `auction_bids`
--
ALTER TABLE `auction_bids`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_auction` (`auction_id`,`id`),
  ADD KEY `idx_bidder` (`bidder_id`);

--
-- Indexes for table `boarding_agreements`
--
ALTER TABLE `boarding_agreements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `horse_id` (`horse_id`),
  ADD KEY `idx_owner` (`owner_id`),
  ADD KEY `idx_center` (`center_id`,`status`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `center_id` (`center_id`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `idx_bookings_horse` (`horse_id`),
  ADD KEY `idx_bookings_date` (`booking_date`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `centers`
--
ALTER TABLE `centers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `clinics`
--
ALTER TABLE `clinics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_clinics_owner` (`owner_id`);

--
-- Indexes for table `clinic_appointments`
--
ALTER TABLE `clinic_appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `horse_id` (`horse_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_clinic` (`clinic_id`,`appointment_date`);

--
-- Indexes for table `clinic_services`
--
ALTER TABLE `clinic_services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_clinic` (`clinic_id`);

--
-- Indexes for table `complaints`
--
ALTER TABLE `complaints`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `conversations`
--
ALTER TABLE `conversations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_center_conv` (`user_id`,`center_id`),
  ADD UNIQUE KEY `unique_clinic_conv` (`user_id`,`clinic_id`),
  ADD UNIQUE KEY `unique_photog_conv` (`user_id`,`photographer_id`),
  ADD KEY `idx_user` (`user_id`,`last_message_at`),
  ADD KEY `idx_center` (`center_id`,`last_message_at`),
  ADD KEY `fk_conv_clinic` (`clinic_id`),
  ADD KEY `fk_conv_photographer` (`photographer_id`),
  ADD KEY `idx_conv_other_user` (`other_user_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `center_id` (`center_id`);

--
-- Indexes for table `event_registrations`
--
ALTER TABLE `event_registrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_event` (`user_id`,`event_id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `horses`
--
ALTER TABLE `horses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_owner` (`owner_id`),
  ADD KEY `idx_for_sale` (`is_for_sale`);

--
-- Indexes for table `horse_care_tips`
--
ALTER TABLE `horse_care_tips`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category` (`category`,`is_published`);

--
-- Indexes for table `horse_certificates`
--
ALTER TABLE `horse_certificates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_horse` (`horse_id`),
  ADD KEY `idx_hc_clinic` (`clinic_id`);

--
-- Indexes for table `horse_diseases`
--
ALTER TABLE `horse_diseases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category` (`category`,`is_published`);

--
-- Indexes for table `horse_health_records`
--
ALTER TABLE `horse_health_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_horse` (`horse_id`,`record_date`),
  ADD KEY `idx_hhr_clinic` (`clinic_id`);

--
-- Indexes for table `horse_images`
--
ALTER TABLE `horse_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_horse` (`horse_id`,`sort_order`);

--
-- Indexes for table `horse_purchase_requests`
--
ALTER TABLE `horse_purchase_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_hpr_horse` (`horse_id`),
  ADD KEY `idx_hpr_buyer` (`buyer_id`),
  ADD KEY `idx_hpr_seller` (`seller_id`);

--
-- Indexes for table `horse_vaccinations`
--
ALTER TABLE `horse_vaccinations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_horse` (`horse_id`),
  ADD KEY `idx_hv_clinic` (`clinic_id`);

--
-- Indexes for table `horse_videos`
--
ALTER TABLE `horse_videos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_horse` (`horse_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `idx_conv` (`conversation_id`,`id`),
  ADD KEY `idx_unread` (`conversation_id`,`sender_type`,`is_read`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_unread` (`user_id`,`is_read`,`id`),
  ADD KEY `idx_user_created` (`user_id`,`created_at`);

--
-- Indexes for table `offers`
--
ALTER TABLE `offers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `center_id` (`center_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `photographers`
--
ALTER TABLE `photographers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_owner` (`owner_id`),
  ADD KEY `idx_status` (`approval_status`,`featured`);

--
-- Indexes for table `photographer_gallery`
--
ALTER TABLE `photographer_gallery`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_photog` (`photographer_id`,`sort_order`);

--
-- Indexes for table `photoshoot_bookings`
--
ALTER TABLE `photoshoot_bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `horse_id` (`horse_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_pkg_date` (`package_id`,`session_date`);

--
-- Indexes for table `photoshoot_deliveries`
--
ALTER TABLE `photoshoot_deliveries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_booking` (`booking_id`),
  ADD KEY `idx_photog` (`photographer_id`);

--
-- Indexes for table `photoshoot_packages`
--
ALTER TABLE `photoshoot_packages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `center_id` (`center_id`),
  ADD KEY `idx_pkg_photographer` (`photographer_id`);

--
-- Indexes for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `idx_clinic` (`clinic_id`,`issue_date`),
  ADD KEY `idx_horse` (`horse_id`,`issue_date`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `public_notifications`
--
ALTER TABLE `public_notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reporter_id` (`reporter_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `center_id` (`center_id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `center_id` (`center_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_logs`
--
ALTER TABLE `admin_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `articles`
--
ALTER TABLE `articles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `article_categories`
--
ALTER TABLE `article_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `auctions`
--
ALTER TABLE `auctions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `auction_bids`
--
ALTER TABLE `auction_bids`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `boarding_agreements`
--
ALTER TABLE `boarding_agreements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `centers`
--
ALTER TABLE `centers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `clinics`
--
ALTER TABLE `clinics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `clinic_appointments`
--
ALTER TABLE `clinic_appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `clinic_services`
--
ALTER TABLE `clinic_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `complaints`
--
ALTER TABLE `complaints`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `conversations`
--
ALTER TABLE `conversations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `event_registrations`
--
ALTER TABLE `event_registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `horses`
--
ALTER TABLE `horses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `horse_care_tips`
--
ALTER TABLE `horse_care_tips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `horse_certificates`
--
ALTER TABLE `horse_certificates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `horse_diseases`
--
ALTER TABLE `horse_diseases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `horse_health_records`
--
ALTER TABLE `horse_health_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `horse_images`
--
ALTER TABLE `horse_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `horse_purchase_requests`
--
ALTER TABLE `horse_purchase_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `horse_vaccinations`
--
ALTER TABLE `horse_vaccinations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `horse_videos`
--
ALTER TABLE `horse_videos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `offers`
--
ALTER TABLE `offers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `photographers`
--
ALTER TABLE `photographers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `photographer_gallery`
--
ALTER TABLE `photographer_gallery`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `photoshoot_bookings`
--
ALTER TABLE `photoshoot_bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `photoshoot_deliveries`
--
ALTER TABLE `photoshoot_deliveries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `photoshoot_packages`
--
ALTER TABLE `photoshoot_packages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `public_notifications`
--
ALTER TABLE `public_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=111;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD CONSTRAINT `admin_logs_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `articles`
--
ALTER TABLE `articles`
  ADD CONSTRAINT `articles_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `article_categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `auctions`
--
ALTER TABLE `auctions`
  ADD CONSTRAINT `auctions_ibfk_1` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `auctions_ibfk_2` FOREIGN KEY (`horse_id`) REFERENCES `horses` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `auction_bids`
--
ALTER TABLE `auction_bids`
  ADD CONSTRAINT `auction_bids_ibfk_1` FOREIGN KEY (`auction_id`) REFERENCES `auctions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `auction_bids_ibfk_2` FOREIGN KEY (`bidder_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `boarding_agreements`
--
ALTER TABLE `boarding_agreements`
  ADD CONSTRAINT `boarding_agreements_ibfk_1` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `boarding_agreements_ibfk_2` FOREIGN KEY (`center_id`) REFERENCES `centers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `boarding_agreements_ibfk_3` FOREIGN KEY (`horse_id`) REFERENCES `horses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`center_id`) REFERENCES `centers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_3` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `clinic_appointments`
--
ALTER TABLE `clinic_appointments`
  ADD CONSTRAINT `clinic_appointments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `clinic_appointments_ibfk_2` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `clinic_appointments_ibfk_3` FOREIGN KEY (`horse_id`) REFERENCES `horses` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `clinic_services`
--
ALTER TABLE `clinic_services`
  ADD CONSTRAINT `clinic_services_ibfk_1` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `complaints`
--
ALTER TABLE `complaints`
  ADD CONSTRAINT `complaints_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `conversations`
--
ALTER TABLE `conversations`
  ADD CONSTRAINT `conversations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `conversations_ibfk_2` FOREIGN KEY (`center_id`) REFERENCES `centers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_conv_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_conv_photographer` FOREIGN KEY (`photographer_id`) REFERENCES `photographers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `events_ibfk_1` FOREIGN KEY (`center_id`) REFERENCES `centers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `event_registrations`
--
ALTER TABLE `event_registrations`
  ADD CONSTRAINT `event_registrations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_registrations_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `horses`
--
ALTER TABLE `horses`
  ADD CONSTRAINT `horses_ibfk_1` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `horse_certificates`
--
ALTER TABLE `horse_certificates`
  ADD CONSTRAINT `horse_certificates_ibfk_1` FOREIGN KEY (`horse_id`) REFERENCES `horses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `horse_health_records`
--
ALTER TABLE `horse_health_records`
  ADD CONSTRAINT `horse_health_records_ibfk_1` FOREIGN KEY (`horse_id`) REFERENCES `horses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `horse_images`
--
ALTER TABLE `horse_images`
  ADD CONSTRAINT `horse_images_ibfk_1` FOREIGN KEY (`horse_id`) REFERENCES `horses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `horse_vaccinations`
--
ALTER TABLE `horse_vaccinations`
  ADD CONSTRAINT `horse_vaccinations_ibfk_1` FOREIGN KEY (`horse_id`) REFERENCES `horses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `horse_videos`
--
ALTER TABLE `horse_videos`
  ADD CONSTRAINT `horse_videos_ibfk_1` FOREIGN KEY (`horse_id`) REFERENCES `horses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `offers`
--
ALTER TABLE `offers`
  ADD CONSTRAINT `offers_ibfk_1` FOREIGN KEY (`center_id`) REFERENCES `centers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `photographers`
--
ALTER TABLE `photographers`
  ADD CONSTRAINT `photographers_ibfk_1` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `photographer_gallery`
--
ALTER TABLE `photographer_gallery`
  ADD CONSTRAINT `photographer_gallery_ibfk_1` FOREIGN KEY (`photographer_id`) REFERENCES `photographers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `photoshoot_bookings`
--
ALTER TABLE `photoshoot_bookings`
  ADD CONSTRAINT `photoshoot_bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `photoshoot_bookings_ibfk_2` FOREIGN KEY (`package_id`) REFERENCES `photoshoot_packages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `photoshoot_bookings_ibfk_3` FOREIGN KEY (`horse_id`) REFERENCES `horses` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `photoshoot_deliveries`
--
ALTER TABLE `photoshoot_deliveries`
  ADD CONSTRAINT `photoshoot_deliveries_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `photoshoot_bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `photoshoot_deliveries_ibfk_2` FOREIGN KEY (`photographer_id`) REFERENCES `photographers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `photoshoot_packages`
--
ALTER TABLE `photoshoot_packages`
  ADD CONSTRAINT `photoshoot_packages_ibfk_1` FOREIGN KEY (`center_id`) REFERENCES `centers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `prescriptions_ibfk_2` FOREIGN KEY (`horse_id`) REFERENCES `horses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `prescriptions_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `prescriptions_ibfk_4` FOREIGN KEY (`appointment_id`) REFERENCES `clinic_appointments` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`center_id`) REFERENCES `centers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `services`
--
ALTER TABLE `services`
  ADD CONSTRAINT `services_ibfk_1` FOREIGN KEY (`center_id`) REFERENCES `centers` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
