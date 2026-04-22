<?php
header("Content-Type: application/json");
include("../config/db.php");

$raw  = file_get_contents("php://input");
$data = json_decode($raw);

if (!$data || !isset($data->email) || !isset($data->password)) {
    echo json_encode(["error" => "Invalid input"]);
    exit;
}

$email    = $data->email;
$password = $data->password;

$sql  = "SELECT user_id, name, password FROM users WHERE email = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(["error" => "DB prepare failed"]);
    exit;
}

$stmt->bind_param("s", $email);
$stmt->execute();

$result = $stmt->get_result();
$user   = $result->fetch_assoc();

if (!$user) {
    echo json_encode(["error" => "Invalid email or password"]);
    exit;
}

// Accept either a bcrypt hash (from register.php) or a legacy plain-text
// password (from the original seed data). Both paths succeed silently.
$stored = $user['password'];
$ok     = false;

if (password_verify($password, $stored)) {
    $ok = true;
} elseif (hash_equals($stored, $password)) {
    // Legacy plain-text seed user — log in and silently upgrade to a hash.
    $ok      = true;
    $newHash = password_hash($password, PASSWORD_DEFAULT);
    $upd     = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
    $upd->bind_param("si", $newHash, $user['user_id']);
    $upd->execute();
}

if ($ok) {
    echo json_encode([
        "user_id" => $user['user_id'],
        "name"    => $user['name']
    ]);
} else {
    echo json_encode(["error" => "Invalid email or password"]);
}
?>
