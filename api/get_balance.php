<?php
include("../config/db.php");

$user_id = $_GET['user_id'];

$sql = "SELECT 
SUM(CASE WHEN type='income' THEN amount ELSE 0 END) -
SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) AS balance
FROM transactions
WHERE user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$row = $result->fetch_assoc();

echo json_encode($row);
?>