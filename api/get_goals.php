<?php
header("Content-Type: application/json");
include("../config/db.php");

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($user_id <= 0) {
    echo json_encode(["error" => "Invalid user_id"]);
    exit;
}

// Fetch goals with new columns
$stmt = $conn->prepare(
    "SELECT goal_id, target_amount, allocated_amount, description, deadline, status
     FROM goals
     WHERE user_id = ?
     ORDER BY status ASC, (deadline IS NULL), deadline ASC, goal_id ASC"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$goals = [];
while ($row = $result->fetch_assoc()) {
    $goals[] = [
        "goal_id"          => (int)$row['goal_id'],
        "target_amount"    => (float)$row['target_amount'],
        "allocated_amount" => (float)$row['allocated_amount'],
        "description"      => $row['description'],
        "deadline"         => $row['deadline'],
        "status"           => $row['status']
    ];
}

// Balance: income - expenses
$balStmt = $conn->prepare(
    "SELECT
       COALESCE(SUM(CASE WHEN type='income'  THEN amount ELSE 0 END), 0) AS income,
       COALESCE(SUM(CASE WHEN type='expense' THEN amount ELSE 0 END), 0) AS expense
     FROM transactions
     WHERE user_id = ?"
);
$balStmt->bind_param("i", $user_id);
$balStmt->execute();
$balRow  = $balStmt->get_result()->fetch_assoc();
$balance = (float)$balRow['income'] - (float)$balRow['expense'];

// Total allocated across all active goals
$allocStmt = $conn->prepare(
    "SELECT COALESCE(SUM(allocated_amount), 0) AS total_allocated
     FROM goals
     WHERE user_id = ? AND status = 'active'"
);
$allocStmt->bind_param("i", $user_id);
$allocStmt->execute();
$totalAllocated = (float)$allocStmt->get_result()->fetch_assoc()['total_allocated'];

$freeBalance = $balance - $totalAllocated;

echo json_encode([
    "balance"         => $balance,
    "total_allocated" => $totalAllocated,
    "free_balance"    => $freeBalance,
    "goals"           => $goals
]);
?>
