<?php
header("Content-Type: application/json");
include("../config/db.php");

$raw  = file_get_contents("php://input");
$data = json_decode($raw);

if (!$data || !isset($data->user_id, $data->goal_id, $data->target_amount)) {
    echo json_encode(["error" => "Missing fields"]);
    exit;
}

$user_id     = intval($data->user_id);
$goal_id     = intval($data->goal_id);
$target      = floatval($data->target_amount);
$description = isset($data->description) && $data->description !== "" ? trim($data->description) : null;
$deadline    = isset($data->deadline)    && $data->deadline    !== "" ? $data->deadline           : null;

if ($user_id <= 0 || $goal_id <= 0 || $target <= 0) {
    echo json_encode(["error" => "Invalid input"]);
    exit;
}

// Pick the variant that matches the null-ness of description/deadline so we
// store real SQL NULLs rather than empty strings.
if ($description === null && $deadline === null) {
    $stmt = $conn->prepare(
        "UPDATE goals
         SET target_amount = ?, description = NULL, deadline = NULL
         WHERE goal_id = ? AND user_id = ?"
    );
    $stmt->bind_param("dii", $target, $goal_id, $user_id);
} elseif ($description === null) {
    $stmt = $conn->prepare(
        "UPDATE goals
         SET target_amount = ?, description = NULL, deadline = ?
         WHERE goal_id = ? AND user_id = ?"
    );
    $stmt->bind_param("dsii", $target, $deadline, $goal_id, $user_id);
} elseif ($deadline === null) {
    $stmt = $conn->prepare(
        "UPDATE goals
         SET target_amount = ?, description = ?, deadline = NULL
         WHERE goal_id = ? AND user_id = ?"
    );
    $stmt->bind_param("dsii", $target, $description, $goal_id, $user_id);
} else {
    $stmt = $conn->prepare(
        "UPDATE goals
         SET target_amount = ?, description = ?, deadline = ?
         WHERE goal_id = ? AND user_id = ?"
    );
    $stmt->bind_param("dssii", $target, $description, $deadline, $goal_id, $user_id);
}

if ($stmt->execute()) {
    echo json_encode(["success" => true, "rows_changed" => $stmt->affected_rows]);
} else {
    echo json_encode(["error" => "Update failed: " . $conn->error]);
}
?>
