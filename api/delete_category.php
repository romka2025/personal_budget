<?php
header("Content-Type: application/json");
include("../config/db.php");

$raw  = file_get_contents("php://input");
$data = json_decode($raw);

if (!$data || !isset($data->user_id, $data->category_id)) {
    echo json_encode(["error" => "Missing fields"]);
    exit;
}

$user_id     = intval($data->user_id);
$category_id = intval($data->category_id);

if ($user_id <= 0 || $category_id <= 0) {
    echo json_encode(["error" => "Invalid id"]);
    exit;
}

// Scoped by user. FKs: transactions.category_id ON DELETE SET NULL, budgets ON DELETE CASCADE.
$stmt = $conn->prepare(
    "DELETE FROM categories WHERE category_id = ? AND user_id = ?"
);
$stmt->bind_param("ii", $category_id, $user_id);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "deleted" => $stmt->affected_rows
    ]);
} else {
    echo json_encode(["error" => "Delete failed: " . $conn->error]);
}
?>
