<?php
/**
 * auth_check.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Middleware לאימות session — יש לכלול בתחילת כל endpoint מוגן.
 *
 * שימוש:
 *   require_once "../config/security.php";
 *   require_once "auth_check.php";
 *
 * מה זה עושה:
 *  - פותח session מאובטח
 *  - מוודא שה-session מכיל user_id תקין
 *  - בודק fingerprint (User-Agent hash) — מזהה גניבת session
 *  - מוודא שה-user_id בבקשה תואם את ה-session (מניעת IDOR בסיסי)
 */

require_once "../config/security.php";

validate_session(); // יסיים עם 401 אם session לא תקין

/**
 * מוודא שה-user_id שנשלח בבקשה שייך למשתמש המחובר.
 * קורא לזה אחרי שאתה קורא את user_id מהבקשה.
 *
 * @param int $requestedUserId  ה-user_id שהגיע מהבקשה (GET/POST/JSON)
 */
function assert_own_user(int $requestedUserId): void {
    if ($requestedUserId !== (int)$_SESSION['user_id']) {
        http_response_code(403);
        echo safe_json(["error" => "Forbidden"]);
        exit;
    }
}
