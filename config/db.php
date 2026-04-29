<?php
$host     = "localhost";
$user     = "root";
$password = "";
$dbname   = "personal_budget";

require_once __DIR__ . "/init_db.php";

// מתחבר ל-MySQL, יוצר DB / טבלאות / נתוני זרע אם חסרים
$conn = init_database($host, $user, $password, $dbname);
?>
