<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=hrms_db', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
try {
  $rows = $pdo->query("SHOW FULL TABLES IN `hrms_db`")->fetchAll(PDO::FETCH_NUM);
  foreach ($rows as $row) { if (stripos($row[0], 'migrations') !== false) echo $row[0]."\n"; }
} catch (Throwable $e) { echo $e->getMessage()."\n"; }
