<?php
require_once "../config/security.php";
header("Content-Type: application/json");
include("../config/db.php");

$raw  = file_get_contents("php://input");
$data = json_decode($raw);

if (!$data || !isset($data->name, $data->email, $data->password)) {
    echo safe_json(["error" => "Missing fields"]);
    exit;
}

$name     = sanitize_input($data->name);
$email    = sanitize_input($data->email);
$password = $data->password; // לא מנוקה — נשמר כ-hash בלבד

if ($name === "" || $email === "" || $password === "") {
    echo safe_json(["error" => "All fields are required"]);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo safe_json(["error" => "Invalid email"]);
    exit;
}
if (strlen($password) < 6) {
    echo safe_json(["error" => "Password too short (minimum 6 characters)"]);
    exit;
}

// Check duplicate up front for a clean error message.
$check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
if ($check->get_result()->fetch_assoc()) {
    echo safe_json(["error" => "Email already registered"]);
    exit;
}

// bcrypt עם cost=12 — מאזן בין אבטחה לביצועים.
// cost גבוה יותר = hashing איטי יותר = קשה יותר לתוקף ל-brute force.
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $email, $hash);

    if (!$stmt->execute()) {
        if ($conn->errno === 1062) {
            throw new Exception("Email already registered");
        }
        throw new Exception("Insert failed");
    }

    $new_user_id = $conn->insert_id;

    // Seed the 5 default Hebrew categories for this user.
    $defaults = [
        ['משכורת',  'income'],
        ['פרילנס',  'income'],
        ['מזון',    'expense'],
        ['תחבורה',  'expense'],
        ['בידור',   'expense'],
    ];

    $catInsert = $conn->prepare(
        "INSERT INTO categories (user_id, name, type) VALUES (?, ?, ?)"
    );
    foreach ($defaults as [$cname, $ctype]) {
        $catInsert->bind_param("iss", $new_user_id, $cname, $ctype);
        $catInsert->execute();
    }

    $conn->commit();

    echo safe_json([
        "success" => true,
        "user_id" => $new_user_id,
        "name"    => $name
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo safe_json(["error" => $e->getMessage()]);
}
?>
