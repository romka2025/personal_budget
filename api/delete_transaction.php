<?php
header("Content-Type: application/json");
include("../config/db.php");

$raw  = file_get_contents("php://input");
$data = json_decode($raw);

if (!$data || !isset($data->user_id, $data->transaction_id)) {
    echo json_encode(["error" => "Missing fields"]);
    exit;
}

$user_id        = intval($data->user_id);
$transaction_id = intval($data->transaction_id);

if ($user_id <= 0 || $transaction_id <= 0) {
    echo json_encode(["error" => "Invalid ids"]);
    exit;
}

// שלוף את הרשומה המקורית (רק רשומות שאינן סטורנו עצמן, ושעדיין לא בוטלו)
$stmt = $conn->prepare(
    "SELECT t.amount, t.type, t.category_id, t.description
     FROM transactions t
     WHERE t.transaction_id = ? AND t.user_id = ? AND t.is_storno = 0
       AND NOT EXISTS (
           SELECT 1 FROM transactions s
           WHERE s.storno_ref = t.transaction_id AND s.user_id = t.user_id
       )"
);
$stmt->bind_param("ii", $transaction_id, $user_id);
$stmt->execute();
$orig = $stmt->get_result()->fetch_assoc();

if (!$orig) {
    echo json_encode(["error" => "Transaction not found or already cancelled"]);
    exit;
}

// סטורנו = סוג הפוך, סכום זהה
$reverse_type = ($orig['type'] === 'income') ? 'expense' : 'income';
$desc         = "[STORNO] " . ($orig['description'] ?? '');

$ins = $conn->prepare(
    "INSERT INTO transactions (user_id, amount, type, category_id, date, description, is_storno, storno_ref)
     VALUES (?, ?, ?, ?, CURDATE(), ?, 1, ?)"
);
$ins->bind_param(
    "idsisi",
    $user_id,
    $orig['amount'],
    $reverse_type,
    $orig['category_id'],
    $desc,
    $transaction_id
);

if ($ins->execute()) {
    echo json_encode(["success" => true, "storno_id" => $conn->insert_id]);
} else {
    echo json_encode(["error" => "Storno failed", "details" => $ins->error]);
}
?>
