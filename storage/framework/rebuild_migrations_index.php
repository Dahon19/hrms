<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=hrms_db;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$projectRoot = dirname(__DIR__, 2);
$files = glob($projectRoot . '/database/migrations/*.php');
sort($files);
$pdo->exec('TRUNCATE TABLE migrations');
$stmt = $pdo->prepare('INSERT INTO migrations (migration, batch) VALUES (?, 1)');
foreach ($files as $file) {
    $stmt->execute([basename($file, '.php')]);
}
echo 'inserted=' . count($files) . PHP_EOL;
