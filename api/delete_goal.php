<?php
header("Content-Type: application/json");
include("../config/db.php");

$raw  = file_get_contents("php://input");
$data = json_decode($raw);

if (!$data || !isset($data->user_id, $data->goal_id)) {
    echo json_encode(["error" => "Missing fields"]);
    exit;
}

$user_id = intval($data->user_id);
$goal_id = intval($data->goal_id);

if ($user_id <= 0 || $goal_id <= 0) {
    echo json_encode(["error" => "Invalid ids"]);
    exit;
}

$stmt = $conn->prepare("DELETE FROM goals WHERE goal_id = ? AND user_id = ?");
$stmt->bind_param("ii", $goal_id, $user_id);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "deleted" => $stmt->affected_rows
    ]);
} else {
    echo json_encode(["error" => "Delete failed"]);
}
?>
