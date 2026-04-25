<?php
/**
 * realize_goal.php
 * Mark a savings goal as 'realized' (fulfilled).
 * The goal's allocated_amount is zeroed out so the money returns to free balance.
 *
 * POST body JSON:
 *   user_id  int
 *   goal_id  int
 */
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
    echo json_encode(["error" => "Invalid input"]);
    exit;
}

// Set status = 'realized' and zero out allocated_amount (returns funds to free balance)
$stmt = $conn->prepare(
    "UPDATE goals
     SET status = 'realized', allocated_amount = 0
     WHERE goal_id = ? AND user_id = ? AND status = 'active'"
);
$stmt->bind_param("ii", $goal_id, $user_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows === 0) {
        echo json_encode(["error" => "Goal not found or already realized"]);
    } else {
        echo json_encode(["success" => true]);
    }
} else {
    echo json_encode(["error" => "Update failed: " . $conn->error]);
}
?>
