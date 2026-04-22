<?php
header("Content-Type: application/json");
include("../config/db.php");

$raw  = file_get_contents("php://input");
$data = json_decode($raw);

if (!$data || !isset($data->user_id, $data->name, $data->type)) {
    echo json_encode(["error" => "Missing fields"]);
    exit;
}

$user_id = intval($data->user_id);
$name    = trim($data->name);
$type    = $data->type;

if ($user_id <= 0) {
    echo json_encode(["error" => "Invalid user_id"]);
    exit;
}
if ($name === "") {
    echo json_encode(["error" => "Name required"]);
    exit;
}
if (!in_array($type, ['income', 'expense'])) {
    echo json_encode(["error" => "Invalid type"]);
    exit;
}

$stmt = $conn->prepare("INSERT INTO categories (user_id, name, type) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $user_id, $name, $type);

if ($stmt->execute()) {
    echo json_encode([
        "success"     => true,
        "category_id" => $conn->insert_id,
        "name"        => $name,
        "type"        => $type
    ]);
} else {
    if ($conn->errno === 1062) {
        echo json_encode(["error" => "קטגוריה עם אותו שם וסוג כבר קיימת"]);
    } else {
        echo json_encode(["error" => "Insert failed: " . $conn->error]);
    }
}
?>
