<?php
header("Content-Type: application/json");
include("../config/db.php");

$raw = file_get_contents("php://input");
$data = json_decode($raw);

if (!$data || !isset($data->user_id, $data->amount, $data->type, $data->date)) {
    echo json_encode(["error" => "Missing fields"]);
    exit;
}

$user_id     = intval($data->user_id);
$amount      = floatval($data->amount);
$type        = $data->type;
$category_id = isset($data->category_id) ? intval($data->category_id) : null;
$date        = $data->date;
$description = $data->description ?? "";

if (!in_array($type, ['income', 'expense'])) {
    echo json_encode(["error" => "Invalid type"]);
    exit;
}

$stmt = $conn->prepare(
    "INSERT INTO transactions (user_id, amount, type, category_id, date, description)
     VALUES (?, ?, ?, ?, ?, ?)"
);
$stmt->bind_param("idsiss", $user_id, $amount, $type, $category_id, $date, $description);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "transaction_id" => $conn->insert_id]);
} else {
    echo json_encode(["error" => "Insert failed"]);
}
?>