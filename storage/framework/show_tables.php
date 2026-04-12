<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=hrms_db', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
echo "CONNECTED\n";
foreach (['migrations','sessions'] as $t) {
  try {
    $stmt = $pdo->query("SHOW CREATE TABLE `$t`");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "SHOW:$t:OK\n";
  } catch (Throwable $e) {
    echo "SHOW:$t:ERR:".$e->getMessage()."\n";
  }
}
