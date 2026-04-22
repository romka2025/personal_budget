<?php
header("Content-Type: application/json");
include("../config/db.php");

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($user_id <= 0) {
    echo json_encode(["error" => "Invalid user_id"]);
    exit;
}

// Optional ?type=income or ?type=expense filter.
$type = isset($_GET['type']) ? $_GET['type'] : null;

if ($type && in_array($type, ['income', 'expense'])) {
    $stmt = $conn->prepare(
        "SELECT category_id, name, type
         FROM categories
         WHERE user_id = ? AND type = ?
         ORDER BY name"
    );
    $stmt->bind_param("is", $user_id, $type);
} else {
    $stmt = $conn->prepare(
        "SELECT category_id, name, type
         FROM categories
         WHERE user_id = ?
         ORDER BY type, name"
    );
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();
$result = $stmt->get_result();

$cats = [];
while ($row = $result->fetch_assoc()) {
    $cats[] = [
        "category_id" => (int)$row['category_id'],
        "name"        => $row['name'],
        "type"        => $row['type']
    ];
}

echo json_encode($cats);
?>
