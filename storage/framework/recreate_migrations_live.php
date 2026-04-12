<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=hrms_db', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
try {
  $stmt = $pdo->query("SHOW CREATE TABLE `migrations`");
  echo "SHOW_OK\n";
} catch (Throwable $e) { echo "SHOW_ERR: ".$e->getMessage()."\n"; }
try {
  $pdo->exec('DROP TABLE `migrations`');
  echo "DROP_OK\n";
} catch (Throwable $e) { echo "DROP_ERR: ".$e->getMessage()."\n"; }
try {
  $pdo->exec('CREATE TABLE `migrations` (`id` bigint unsigned NOT NULL AUTO_INCREMENT, `migration` varchar(255) NOT NULL, `batch` int NOT NULL, PRIMARY KEY (`id`), UNIQUE KEY `migrations_migration_unique` (`migration`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
  echo "CREATE_OK\n";
} catch (Throwable $e) { echo "CREATE_ERR: ".$e->getMessage()."\n"; }
