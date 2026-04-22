<?php
header("Content-Type: application/json");
include("../config/db.php");

$raw  = file_get_contents("php://input");
$data = json_decode($raw);

if (!$data || !isset($data->user_id, $data->target_amount)) {
    echo json_encode(["error" => "Missing fields"]);
    exit;
}

$user_id     = intval($data->user_id);
$target      = floatval($data->target_amount);
$description = isset($data->description) && $data->description !== "" ? trim($data->description) : null;
$deadline    = isset($data->deadline)    && $data->deadline    !== "" ? $data->deadline           : null;

if ($user_id <= 0 || $target <= 0) {
    echo json_encode(["error" => "Invalid input"]);
    exit;
}

// Build the insert with explicit NULLs where needed (bind_param can't pass null
// for non-string types, and we want to avoid empty strings in the description).
if ($description === null && $deadline === null) {
    $stmt = $conn->prepare(
        "INSERT INTO goals (user_id, target_amount, description, deadline) VALUES (?, ?, NULL, NULL)"
    );
    $stmt->bind_param("id", $user_id, $target);
} elseif ($description === null) {
    $stmt = $conn->prepare(
        "INSERT INTO goals (user_id, target_amount, description, deadline) VALUES (?, ?, NULL, ?)"
    );
    $stmt->bind_param("ids", $user_id, $target, $deadline);
} elseif ($deadline === null) {
    $stmt = $conn->prepare(
        "INSERT INTO goals (user_id, target_amount, description, deadline) VALUES (?, ?, ?, NULL)"
    );
    $stmt->bind_param("ids", $user_id, $target, $description);
} else {
    $stmt = $conn->prepare(
        "INSERT INTO goals (user_id, target_amount, description, deadline) VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param("idss", $user_id, $target, $description, $deadline);
}

if ($stmt->execute()) {
    echo json_encode(["success" => true, "goal_id" => $conn->insert_id]);
} else {
    echo json_encode(["error" => "Insert failed: " . $conn->error]);
}
?>
