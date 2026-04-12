<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=hrms_db', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$stmt = $pdo->query("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA='hrms_db' AND TABLE_NAME IN ('migrations','sessions') ORDER BY TABLE_NAME");
foreach ($stmt as $row) { echo $row['TABLE_NAME'].':'.($row['ENGINE'] ?? 'NULL')."\n"; }
