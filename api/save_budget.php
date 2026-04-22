<?php
header("Content-Type: application/json");
include("../config/db.php");

$raw  = file_get_contents("php://input");
$data = json_decode($raw);

if (!$data || !isset($data->user_id, $data->category_id, $data->monthly_limit)) {
    echo json_encode(["error" => "Missing fields"]);
    exit;
}

$user_id     = intval($data->user_id);
$category_id = intval($data->category_id);
$limit       = floatval($data->monthly_limit);

if ($user_id <= 0 || $category_id <= 0 || $limit < 0) {
    echo json_encode(["error" => "Invalid input"]);
    exit;
}

// UPSERT: relies on UNIQUE KEY uk_user_category (user_id, category_id).
$sql = "
    INSERT INTO budgets (user_id, category_id, monthly_limit)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE monthly_limit = VALUES(monthly_limit)
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iid", $user_id, $category_id, $limit);

if ($stmt->execute()) {
    echo json_encode([
        "success"      => true,
        "budget_id"    => $conn->insert_id ?: null,
        "rows_changed" => $stmt->affected_rows
    ]);
} else {
    echo json_encode(["error" => "Save failed: " . $conn->error]);
}
?>
