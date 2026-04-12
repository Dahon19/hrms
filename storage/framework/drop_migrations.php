<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=hrms_db', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
try {
  $pdo->exec('DROP TABLE IF EXISTS `migrations`');
  echo "DROP_OK\n";
} catch (Throwable $e) {
  echo "DROP_ERR: ".$e->getMessage()."\n";
}
