<?php
$dsn = 'mysql:host=127.0.0.1;port=3306;dbname=myapptimatic;charset=utf8mb4';
$user = 'root';
$pass = 'root';
$tables = ['orders','subscriptions'];
$pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
foreach ($tables as $table) {
    $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "-- {$table}\n";
    echo ($row['Create Table'] ?? '') . "\n\n";
}
?>
