<?php
/**
 * allocate_goal.php
 * Set (or change) the allocated_amount for a savings goal.
 * Validates that the new allocation doesn't exceed the free balance.
 *
 * POST body JSON:
 *   user_id       int
 *   goal_id       int
 *   new_amount    float  (the new allocated amount; 0 = remove allocation)
 */
header("Content-Type: application/json");
include("../config/db.php");

$raw  = file_get_contents("php://input");
$data = json_decode($raw);

if (!$data || !isset($data->user_id, $data->goal_id, $data->new_amount)) {
    echo json_encode(["error" => "Missing fields"]);
    exit;
}

$user_id    = intval($data->user_id);
$goal_id    = intval($data->goal_id);
$new_amount = floatval($data->new_amount);

if ($user_id <= 0 || $goal_id <= 0 || $new_amount < 0) {
    echo json_encode(["error" => "Invalid input"]);
    exit;
}

// Fetch current allocated_amount for this goal (must belong to user and be active)
$goalStmt = $conn->prepare(
    "SELECT allocated_amount FROM goals
     WHERE goal_id = ? AND user_id = ? AND status = 'active'"
);
$goalStmt->bind_param("ii", $goal_id, $user_id);
$goalStmt->execute();
$goalRow = $goalStmt->get_result()->fetch_assoc();

if (!$goalRow) {
    echo json_encode(["error" => "Goal not found or already realized"]);
    exit;
}

$current_allocated = (float)$goalRow['allocated_amount'];

// Calculate balance (income - expenses)
$balStmt = $conn->prepare(
    "SELECT
       COALESCE(SUM(CASE WHEN type='income'  THEN amount ELSE 0 END), 0) -
       COALESCE(SUM(CASE WHEN type='expense' THEN amount ELSE 0 END), 0) AS balance
     FROM transactions WHERE user_id = ?"
);
$balStmt->bind_param("i", $user_id);
$balStmt->execute();
$balance = (float)$balStmt->get_result()->fetch_assoc()['balance'];

// Total allocated across ALL active goals
$totalAllocStmt = $conn->prepare(
    "SELECT COALESCE(SUM(allocated_amount), 0) AS total_allocated
     FROM goals WHERE user_id = ? AND status = 'active'"
);
$totalAllocStmt->bind_param("i", $user_id);
$totalAllocStmt->execute();
$total_allocated = (float)$totalAllocStmt->get_result()->fetch_assoc()['total_allocated'];

// Free balance = total balance - total allocated + current goal's allocation
// (we "return" the current goal's allocation before re-checking)
$free_balance = $balance - $total_allocated + $current_allocated;

if ($new_amount > $free_balance + 0.001) {
    echo json_encode([
        "error"        => "הסכום שהוקצה חורג מהיתרה החופשית",
        "free_balance" => round($free_balance, 2)
    ]);
    exit;
}

// Update the allocation
$updateStmt = $conn->prepare(
    "UPDATE goals SET allocated_amount = ? WHERE goal_id = ? AND user_id = ?"
);
$updateStmt->bind_param("dii", $new_amount, $goal_id, $user_id);

if ($updateStmt->execute()) {
    // Return updated balance info
    $newTotalAllocated = $total_allocated - $current_allocated + $new_amount;
    echo json_encode([
        "success"         => true,
        "allocated_amount"=> $new_amount,
        "free_balance"    => round($balance - $newTotalAllocated, 2),
        "total_allocated" => round($newTotalAllocated, 2)
    ]);
} else {
    echo json_encode(["error" => "Update failed: " . $conn->error]);
}
?>
