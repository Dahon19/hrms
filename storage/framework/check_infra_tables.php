<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=hrms_db', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
foreach (['cache','cache_locks','jobs','failed_jobs','sessions','migrations'] as $t) {
  try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM `$t`");
    echo "$t=OK:".$stmt->fetchColumn()."\n";
  } catch (Throwable $e) {
    echo "$t=ERR: ".$e->getMessage()."\n";
  }
}
