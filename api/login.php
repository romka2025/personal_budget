<?php
header("Content-Type: application/json");
include("../config/db.php");

$raw = file_get_contents("php://input");
$data = json_decode($raw);

if (!$data || !isset($data->email) || !isset($data->password)) {
    echo json_encode(["error" => "Invalid input"]);
    exit;
}

$email = $data->email;
$password = $data->password;

$sql = "SELECT * FROM users WHERE email = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(["error" => "DB prepare failed"]);
    exit;
}

$stmt->bind_param("s", $email);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user && $user['password'] === $password) {
    echo json_encode([
        "user_id" => $user['user_id'],
        "name"    => $user['name']
    ]);
} else {
    echo json_encode(["error" => "Invalid email or password"]);
}
?>