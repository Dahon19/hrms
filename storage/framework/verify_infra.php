<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=hrms_db;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
echo 'sessions_ok=' . $pdo->query('SELECT COUNT(*) FROM sessions')->fetchColumn() . PHP_EOL;
echo 'migrations_ok=' . $pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn() . PHP_EOL;
