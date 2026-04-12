<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=hrms_db', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
try {
  $stmt = $pdo->query("SHOW CREATE TABLE `migrations`");
  echo "MIGRATIONS_VISIBLE\n";
} catch (Throwable $e) { echo "MIGRATIONS_ERR: ".$e->getMessage()."\n"; }
