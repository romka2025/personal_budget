<?php
header("Content-Type: application/json");
include("../config/db.php");

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($user_id <= 0) {
    echo json_encode(["error" => "Invalid user_id"]);
    exit;
}

$stmt = $conn->prepare(
    "SELECT goal_id, target_amount, description, deadline
     FROM goals
     WHERE user_id = ?
     ORDER BY (deadline IS NULL), deadline ASC, goal_id ASC"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$goals = [];
while ($row = $result->fetch_assoc()) {
    $goals[] = [
        "goal_id"       => (int)$row['goal_id'],
        "target_amount" => (float)$row['target_amount'],
        "description"   => $row['description'],
        "deadline"      => $row['deadline']
    ];
}

$incomeStmt = $conn->prepare(
    "SELECT COALESCE(SUM(amount), 0) AS total_income
     FROM transactions
     WHERE user_id = ? AND type = 'income'"
);
$incomeStmt->bind_param("i", $user_id);
$incomeStmt->execute();
$totalIncome = (float)$incomeStmt->get_result()->fetch_assoc()['total_income'];

echo json_encode([
    "total_income" => $totalIncome,
    "goals"        => $goals
]);
?>
