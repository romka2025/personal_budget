<?php
header("Content-Type: application/json");
include("../config/db.php");

$raw  = file_get_contents("php://input");
$data = json_decode($raw);

if (!$data || !isset($data->user_id, $data->category_id, $data->name)) {
    echo json_encode(["error" => "Missing fields"]);
    exit;
}

$user_id     = intval($data->user_id);
$category_id = intval($data->category_id);
$name        = trim($data->name);
$type        = isset($data->type) ? $data->type : null;

if ($user_id <= 0 || $category_id <= 0 || $name === "") {
    echo json_encode(["error" => "Invalid input"]);
    exit;
}

if ($type !== null && !in_array($type, ['income', 'expense'])) {
    echo json_encode(["error" => "Invalid type"]);
    exit;
}

if ($type === null) {
    $stmt = $conn->prepare(
        "UPDATE categories SET name = ?
         WHERE category_id = ? AND user_id = ?"
    );
    $stmt->bind_param("sii", $name, $category_id, $user_id);
} else {
    $stmt = $conn->prepare(
        "UPDATE categories SET name = ?, type = ?
         WHERE category_id = ? AND user_id = ?"
    );
    $stmt->bind_param("ssii", $name, $type, $category_id, $user_id);
}

if ($stmt->execute()) {
    echo json_encode(["success" => true, "rows_changed" => $stmt->affected_rows]);
} else {
    if ($conn->errno === 1062) {
        echo json_encode(["error" => "קטגוריה עם אותו שם וסוג כבר קיימת"]);
    } else {
        echo json_encode(["error" => "Update failed: " . $conn->error]);
    }
}
?>
