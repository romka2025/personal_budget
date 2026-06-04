<?php
/**
 * security.php
 * ─────────────────────────────────────────────────────────────────────────────
 * מרכז האבטחה של הפרויקט. כלול בכל קובץ API לפני כל פלט.
 *
 * מה כלול:
 *  1. הגדרות Session מאובטח  → מניעת Session Hijacking
 *  2. Security Headers        → הגנה מ-XSS, Clickjacking, MIME sniffing
 *  3. לוג כישלונות התחברות   → זיהוי ניסיונות פריצה
 *  4. Rate Limiting           → חסימה לאחר X כישלונות
 *  5. פונקציות Sanitization   → הגנה מ-XSS בפלט
 */

// ─────────────────────────────────────────────────────────────────────────────
// 1. SESSION HARDENING
// ─────────────────────────────────────────────────────────────────────────────
// HttpOnly: הדפדפן מונע מ-JavaScript לגשת לעוגיית ה-session.
//           גם אם תוקף הצליח להזריק JS (XSS), הוא לא יוכל לגנוב את ה-session.
ini_set('session.cookie_httponly', 1);

// SameSite=Strict: הדפדפן לא שולח את העוגייה בבקשות cross-site.
//                  מונע CSRF — אתר צד שלישי לא יכול לבצע פעולות בשם המשתמש.
ini_set('session.cookie_samesite', 'Strict');

// use_strict_mode: שרת PHP יסרב ל-session ID שלא הוא עצמו יצר.
//                  מונע Session Fixation — תוקף לא יכול לכפות session ID מוכר.
ini_set('session.use_strict_mode', 1);

// use_only_cookies: session ID מועבר רק דרך cookie, לעולם לא ב-URL.
//                   מונע חשיפת session ID ב-logs, referrers, וכתובות.
ini_set('session.use_only_cookies', 1);

// Secure cookie — רק בחיבור HTTPS (production).
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. SECURITY HEADERS (XSS / Clickjacking / MIME sniffing)
// ─────────────────────────────────────────────────────────────────────────────
// CSP: מגביל מאיפה הדפדפן רשאי לטעון scripts/styles/images.
//      מונע הרצת קוד זדוני שהוזרק לדף (XSS).
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:;");

// X-Content-Type-Options: מונע מהדפדפן "לנחש" סוג תוכן שונה ממה שנשלח.
//                         מונע התקפות MIME-type confusion.
header("X-Content-Type-Options: nosniff");

// X-Frame-Options: מונע הטמעת הדף ב-iframe של אתר אחר (Clickjacking).
header("X-Frame-Options: SAMEORIGIN");

// Referrer-Policy: מגביל כמה מידע נשלח ב-Referer header לאתרים אחרים.
header("Referrer-Policy: strict-origin-when-cross-origin");

// ─────────────────────────────────────────────────────────────────────────────
// 3. CONSTANTS
// ─────────────────────────────────────────────────────────────────────────────
define('LOG_DIR',          dirname(__DIR__) . '/logs');
define('FAILED_LOGIN_LOG', LOG_DIR . '/failed_logins.log');
define('MAX_ATTEMPTS',     5);
define('LOCKOUT_SECONDS',  900); // 15 דקות

// ─────────────────────────────────────────────────────────────────────────────
// 4. FAILED LOGIN LOGGING & RATE LIMITING
// ─────────────────────────────────────────────────────────────────────────────

/**
 * רושם ניסיון כניסה כושל עם timestamp, IP, email, ו-User-Agent.
 */
function log_failed_login(string $email): void {
    if (!is_dir(LOG_DIR)) {
        mkdir(LOG_DIR, 0750, true);
    }
    $ip        = $_SERVER['REMOTE_ADDR']     ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $timestamp = date('Y-m-d H:i:s');
    $line      = "[$timestamp] FAILED_LOGIN | IP: $ip | Email: " . addslashes($email) . " | UA: $userAgent\n";
    file_put_contents(FAILED_LOGIN_LOG, $line, FILE_APPEND | LOCK_EX);
}

/**
 * בודק אם ה-IP הנוכחי ביצע יותר מ-MAX_ATTEMPTS כישלונות בחלון הזמן האחרון.
 * אם כן — מחזיר true וניתן לחסום את הבקשה.
 */
function is_ip_locked_out(): bool {
    if (!file_exists(FAILED_LOGIN_LOG)) return false;

    $ip     = $_SERVER['REMOTE_ADDR'] ?? '';
    $cutoff = time() - LOCKOUT_SECONDS;
    $count  = 0;

    $lines = file(FAILED_LOGIN_LOG, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach (array_reverse($lines) as $line) {
        // רק שורות מה-IP הזה
        if (strpos($line, "IP: $ip") === false) continue;

        // חילוץ timestamp
        if (!preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $m)) continue;

        // אם הרשומה ישנה מחלון הזמן — עצור
        if (strtotime($m[1]) < $cutoff) break;

        $count++;
        if ($count >= MAX_ATTEMPTS) return true;
    }

    return false;
}

// ─────────────────────────────────────────────────────────────────────────────
// 5. SESSION MANAGEMENT
// ─────────────────────────────────────────────────────────────────────────────

/**
 * פותח session מאובטח.
 * יש לקרוא לפני כל שימוש ב-$_SESSION.
 */
function start_secure_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // מחדש session ID אחרי 30 דקות — מקשה על Session Hijacking לאורך זמן.
    if (!isset($_SESSION['_created'])) {
        $_SESSION['_created'] = time();
    } elseif (time() - $_SESSION['_created'] > 1800) {
        session_regenerate_id(true); // true = מוחק את הקובץ הישן
        $_SESSION['_created'] = time();
    }
}

/**
 * מאמת שה-session שייך למשתמש שנכנס ולא נחטף.
 *
 * Fingerprinting: שומר hash של User-Agent בעת הכניסה.
 * בכל בקשה מאוחרת — משווה. אם ה-UA השתנה, ייתכן שה-session נגנב
 * ועבר לדפדפן/מכשיר אחר.
 *
 * הערה: UA אינו הוכחה מוחלטת (ניתן לזייף), אך מוסיף שכבת קושי.
 */
function validate_session(): void {
    start_secure_session();

    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(["error" => "Unauthorized — please log in"]);
        exit;
    }

    $currentFingerprint = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));

    if (!isset($_SESSION['fingerprint'])) {
        $_SESSION['fingerprint'] = $currentFingerprint;
    } elseif (!hash_equals($_SESSION['fingerprint'], $currentFingerprint)) {
        // Fingerprint לא תואם — Session ייתכן שנחטף
        session_destroy();
        http_response_code(401);
        echo json_encode(["error" => "Session invalid — please log in again"]);
        exit;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 6. XSS OUTPUT SANITIZATION
// ─────────────────────────────────────────────────────────────────────────────

/**
 * מנקה ערך לפני הכנסה ל-HTML.
 * ממיר תווים מיוחדים (< > " ' &) לישויות HTML כדי שהדפדפן לא יפרש
 * אותם כקוד — מניעת Stored/Reflected XSS.
 */
function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * מנקה קלט נכנס: מסיר תגיות HTML/JS ורווחים מיותרים.
 * שכבת הגנה ראשונה לפני שמירה ל-DB.
 */
function sanitize_input(string $value): string {
    return trim(strip_tags($value));
}

/**
 * json_encode עם דגלים שמונעים הזרקת HTML דרך JSON.
 * JSON_HEX_TAG   → < > הופכים ל-< >
 * JSON_HEX_AMP   → & הופך ל-&
 * JSON_HEX_QUOT  → " הופך ל-"
 * כך אפילו אם ה-JSON מוכנס ב-innerHTML, התווים לא יתפרשו כתגיות.
 */
function safe_json(mixed $data): string {
    return json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
}
