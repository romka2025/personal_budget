<?php
require_once "../config/security.php";
header("Content-Type: application/json");
include("../config/db.php");

// ── Rate limiting: חסום אם ה-IP ביצע יותר מדי כישלונות ──────────────────────
if (is_ip_locked_out()) {
    http_response_code(429);
    echo safe_json(["error" => "יותר מדי ניסיונות כושלים. נסה שוב בעוד 15 דקות."]);
    exit;
}

// ── קלט ───────────────────────────────────────────────────────────────────────
$raw  = file_get_contents("php://input");
$data = json_decode($raw);

if (!$data || !isset($data->email) || !isset($data->password)) {
    echo safe_json(["error" => "Invalid input"]);
    exit;
}

$email    = sanitize_input($data->email);
$password = $data->password; // לא מנוקה — password_verify צריך את הערך המקורי

// ── שליפת משתמש (Prepared Statement → מניעת SQL Injection) ───────────────────
$stmt = $conn->prepare("SELECT user_id, name, password FROM users WHERE email = ?");
if (!$stmt) {
    echo safe_json(["error" => "DB prepare failed"]);
    exit;
}
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// ── אימות סיסמה ───────────────────────────────────────────────────────────────
$ok = false;

if ($user) {
    $stored = $user['password'];

    if (password_verify($password, $stored)) {
        $ok = true;

        // אם ה-hash ישן (cost נמוך מ-12) — שדרג אוטומטית
        if (password_needs_rehash($stored, PASSWORD_BCRYPT, ['cost' => 12])) {
            $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $upd = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $upd->bind_param("si", $newHash, $user['user_id']);
            $upd->execute();
        }

    } elseif (hash_equals($stored, $password)) {
        // משתמש seed עם סיסמה plain-text — כנס ושדרג ל-bcrypt
        $ok      = true;
        $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $upd     = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $upd->bind_param("si", $newHash, $user['user_id']);
        $upd->execute();
    }
}

// ── תגובה ────────────────────────────────────────────────────────────────────
if ($ok) {
    // Session מאובטח — regenerate ID למניעת Session Fixation
    start_secure_session();
    session_regenerate_id(true);

    $_SESSION['user_id']     = $user['user_id'];
    $_SESSION['_created']    = time();
    $_SESSION['fingerprint'] = hash('sha256',
        ($_SERVER['HTTP_USER_AGENT']      ?? '') .
        ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')
    );

    echo safe_json([
        "user_id" => $user['user_id'],
        "name"    => $user['name']
    ]);

} else {
    log_failed_login($email ?? 'unknown');
    http_response_code(401);
    echo safe_json(["error" => "אימייל או סיסמה שגויים"]);
}
?>
