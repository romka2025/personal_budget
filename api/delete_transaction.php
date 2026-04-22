<?php
header("Content-Type: application/json");
include("../config/db.php");

$raw  = file_get_contents("php://input");
$data = json_decode($raw);

if (!$data || !isset($data->user_id, $data->transaction_id)) {
    echo json_encode(["error" => "Missing fields"]);
    exit;
}

$user_id        = intval($data->user_id);
$transaction_id = intval($data->transaction_id);

if ($user_id <= 0 || $transaction_id <= 0) {
    echo json_encode(["error" => "Invalid ids"]);
    exit;
}

$stmt = $conn->prepare(
    "DELETE FROM transactions WHERE transaction_id = ? AND user_id = ?"
);
$stmt->bind_param("ii", $transaction_id, $user_id);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "deleted" => $stmt->affected_rows
    ]);
} else {
    echo json_encode(["error" => "Delete failed"]);
}
?>
