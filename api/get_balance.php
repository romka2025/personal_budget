<?php
header("Content-Type: application/json");
include("../config/db.php");

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($user_id <= 0) {
    echo json_encode(["error" => "Invalid user_id"]);
    exit;
}

// Income / expense totals.
// חייב להתעלם משורות סטורנו (is_storno = 1) וגם מהתנועות המקוריות שבוטלו
// (יש להן שורת סטורנו שמצביעה עליהן) — אחרת מחיקת תנועה נספרת פעמיים:
// פעם כהוצאה המקורית ופעם כהכנסה ההפוכה (הסטורנו).
$sql = "SELECT
          COALESCE(SUM(CASE WHEN t.type='income'  THEN t.amount END), 0) AS income,
          COALESCE(SUM(CASE WHEN t.type='expense' THEN t.amount END), 0) AS expense,
          COALESCE(SUM(CASE WHEN t.type='income'  THEN t.amount ELSE 0 END)
                 - SUM(CASE WHEN t.type='expense' THEN t.amount ELSE 0 END), 0) AS balance
        FROM transactions t
        LEFT JOIN transactions s ON s.storno_ref = t.transaction_id AND s.user_id = t.user_id
        WHERE t.user_id = ? AND t.is_storno = 0 AND s.storno_ref IS NULL";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$row = $stmt->get_result()->fetch_assoc();

// Total allocated to active savings goals
$allocStmt = $conn->prepare(
    "SELECT COALESCE(SUM(allocated_amount), 0) AS total_allocated
     FROM goals
     WHERE user_id = ? AND status = 'active'"
);
$allocStmt->bind_param("i", $user_id);
$allocStmt->execute();
$totalAllocated = (float)$allocStmt->get_result()->fetch_assoc()['total_allocated'];

$balance     = (float)$row['balance'];
$freeBalance = $balance - $totalAllocated;

echo json_encode([
    "income"          => (float)$row['income'],
    "expense"         => (float)$row['expense'],
    "balance"         => $balance,
    "total_allocated" => $totalAllocated,
    "free_balance"    => $freeBalance
]);
?>
