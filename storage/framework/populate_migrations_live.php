<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=hrms_db', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$projectRoot = dirname(__DIR__, 2);
$files = glob($projectRoot . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . '*.php');
sort($files, SORT_STRING);
$stmt = $pdo->prepare('INSERT INTO `migrations` (`migration`, `batch`) VALUES (?, 1)');
$count = 0;
foreach ($files as $file) { $stmt->execute([pathinfo($file, PATHINFO_FILENAME)]); $count++; }
echo "INSERTED=$count\n";
