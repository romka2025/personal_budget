<?php
header("Content-Type: application/json");
include("../config/db.php");

$raw  = file_get_contents("php://input");
$data = json_decode($raw);

if (!$data || !isset($data->name, $data->email, $data->password)) {
    echo json_encode(["error" => "Missing fields"]);
    exit;
}

$name     = trim($data->name);
$email    = trim($data->email);
$password = $data->password;

if ($name === "" || $email === "" || $password === "") {
    echo json_encode(["error" => "All fields are required"]);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["error" => "Invalid email"]);
    exit;
}
if (strlen($password) < 4) {
    echo json_encode(["error" => "Password too short"]);
    exit;
}

// Check duplicate up front for a clean error message.
$check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
if ($check->get_result()->fetch_assoc()) {
    echo json_encode(["error" => "Email already registered"]);
    exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);

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

    echo json_encode([
        "success" => true,
        "user_id" => $new_user_id,
        "name"    => $name
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["error" => $e->getMessage()]);
}
?>
