<?php
include("../config/db.php");

$data = json_decode(file_get_contents("php://input"));

$user_id = $data->user_id;
$amount = $data->amount;
$type = $data->type;
$category_id = $data->category_id;
$date = $data->date;
$description = $data->description;

$sql = "INSERT INTO transactions (user_id, amount, type, category_id, date, description)
VALUES (?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("idssss", $user_id, $amount, $type, $category_id, $date, $description);

if ($stmt->execute()) {
    echo json_encode(["message" => "Transaction added"]);
} else {
    echo json_encode(["error" => "Error"]);
}
?>