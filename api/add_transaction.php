<?php
header("Content-Type: application/json");
include("../config/db.php");

$raw  = file_get_contents("php://input");
$data = json_decode($raw);

if (!$data || !isset($data->user_id, $data->amount, $data->type, $data->date)) {
    echo json_encode(["error" => "Missing fields"]);
    exit;
}

$user_id     = intval($data->user_id);
$amount      = floatval($data->amount);
$type        = $data->type;
$date        = $data->date;
$description = $data->description ?? "";

// Treat 0 / "" / null as "no category" so the FK isn't violated.
$category_id = null;
if (isset($data->category_id) && $data->category_id !== "" && $data->category_id !== null) {
    $cid = intval($data->category_id);
    if ($cid > 0) {
        $category_id = $cid;
    }
}

if (!in_array($type, ['income', 'expense'])) {
    echo json_encode(["error" => "Invalid type"]);
    exit;
}

if ($category_id === null) {
    // Insert with explicit NULL — bind_param can't pass PHP null with type "i".
    $stmt = $conn->prepare(
        "INSERT INTO transactions (user_id, amount, type, category_id, date, description)
         VALUES (?, ?, ?, NULL, ?, ?)"
    );
    $stmt->bind_param("idsss", $user_id, $amount, $type, $date, $description);
} else {
    $stmt = $conn->prepare(
        "INSERT INTO transactions (user_id, amount, type, category_id, date, description)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("idsiss", $user_id, $amount, $type, $category_id, $date, $description);
}

if ($stmt->execute()) {
    echo json_encode(["success" => true, "transaction_id" => $conn->insert_id]);
} else {
    echo json_encode(["error" => "Insert failed: " . $conn->error]);
}
?>
