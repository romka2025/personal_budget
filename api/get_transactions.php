<?php
header("Content-Type: application/json");
include("../config/db.php");

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($user_id <= 0) {
    echo json_encode(["error" => "Invalid user_id"]);
    exit;
}

// מציג את כל התנועות "האמיתיות" של המשתמש (לא כולל שורות הסטורנו עצמן,
// שהן רק רשומת הנהלת חשבונות פנימית) — כולל תנועות שבוטלו, אך מסומנות ככאלה.
$sql = "SELECT t.transaction_id, t.date, t.type, t.amount, t.description,
               t.category_id, c.name AS category,
               CASE WHEN s.storno_ref IS NOT NULL THEN 1 ELSE 0 END AS is_cancelled
        FROM transactions t
        LEFT JOIN categories c ON t.category_id = c.category_id
        LEFT JOIN transactions s ON s.storno_ref = t.transaction_id AND s.user_id = t.user_id
        WHERE t.user_id = ? AND t.is_storno = 0
        ORDER BY t.date DESC, t.transaction_id DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$transactions = [];

while ($row = $result->fetch_assoc()) {
    $transactions[] = [
        "transaction_id" => (int)$row['transaction_id'],
        "date"           => $row['date'],
        "type"           => $row['type'],
        "amount"         => (float)$row['amount'],
        "description"    => $row['description'],
        "category_id"    => $row['category_id'] !== null ? (int)$row['category_id'] : null,
        "category"       => $row['category'],
        "is_cancelled"   => (bool)$row['is_cancelled']
    ];
}

echo json_encode($transactions);
?>
