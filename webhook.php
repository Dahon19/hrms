<?php

$secret = "9kP!xR72mQzL#91";

if (!isset($_GET['token']) || $_GET['token'] !== $secret) {
    http_response_code(403);
    exit("Unauthorized");
}

chdir(dirname(__DIR__));

echo "<pre>";
echo shell_exec("git pull origin main 2>&1");
echo shell_exec("php artisan optimize:clear 2>&1");
echo "</pre>";