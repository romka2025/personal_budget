<?php
header("Content-Type: application/json");
include("../config/db.php");

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($user_id <= 0) {
    echo json_encode(["error" => "Invalid user_id"]);
    exit;
}

// Each budget row: limit + how much was spent THIS MONTH in that category.
$sql = "
    SELECT
        b.budget_id,
        b.category_id,
        c.name AS category,
        b.monthly_limit,
        COALESCE((
            SELECT SUM(t.amount)
            FROM transactions t
            WHERE t.user_id     = b.user_id
              AND t.category_id = b.category_id
              AND t.type        = 'expense'
              AND YEAR(t.date)  = YEAR(CURDATE())
              AND MONTH(t.date) = MONTH(CURDATE())
        ), 0) AS spent
    FROM budgets b
    LEFT JOIN categories c ON c.category_id = b.category_id
    WHERE b.user_id = ?
    ORDER BY c.name
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$budgets = [];
while ($row = $result->fetch_assoc()) {
    $budgets[] = [
        "budget_id"     => (int)$row['budget_id'],
        "category_id"   => (int)$row['category_id'],
        "category"      => $row['category'],
        "monthly_limit" => (float)$row['monthly_limit'],
        "spent"         => (float)$row['spent']
    ];
}

echo json_encode($budgets);
?>
