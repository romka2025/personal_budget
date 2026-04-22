<?php
header("Content-Type: application/json");
include("../config/db.php");

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($user_id <= 0) {
    echo json_encode(["error" => "Invalid user_id"]);
    exit;
}

$sql = "SELECT
          COALESCE(SUM(CASE WHEN type='income'  THEN amount END), 0) AS income,
          COALESCE(SUM(CASE WHEN type='expense' THEN amount END), 0) AS expense,
          COALESCE(SUM(CASE WHEN type='income'  THEN amount ELSE 0 END)
                 - SUM(CASE WHEN type='expense' THEN amount ELSE 0 END), 0) AS balance
        FROM transactions
        WHERE user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$row    = $result->fetch_assoc();

echo json_encode([
    "income"  => (float)$row['income'],
    "expense" => (float)$row['expense'],
    "balance" => (float)$row['balance']
]);
?>
