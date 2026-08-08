<?php
// TEMPORARY DIAGNOSTIC — DELETE THIS FILE AFTER FIXING DB CONNECTION
// Access: https://bodaerp.site/dbcheck.php

$isLocal = (
    $_SERVER['SERVER_NAME'] === 'localhost' ||
    $_SERVER['SERVER_NAME'] === '127.0.0.1' ||
    strpos($_SERVER['SERVER_NAME'], 'localhost') !== false
);

$host   = $isLocal ? '127.0.0.1' : 'localhost';
$dbname = $isLocal ? 'bodaerp'              : 'u850523537_BodaERP27';
$user   = $isLocal ? 'root'                 : 'u850523537_bodAUser';
$pass   = $isLocal ? ''                     : 'i#@Recover2u';

echo '<pre>';
echo "SERVER_NAME : " . ($_SERVER['SERVER_NAME'] ?? 'n/a') . "\n";
echo "Detected env: " . ($isLocal ? 'LOCAL' : 'LIVE') . "\n";
echo "Host        : $host\n";
echo "DB name     : $dbname\n";
echo "User        : $user\n\n";

mysqli_report(MYSQLI_REPORT_OFF); // don't throw, just show the error
$conn = @mysqli_connect($host, $user, $pass, $dbname, 3306);

if ($conn) {
    echo "✅ Connected successfully!\n";
    echo "MySQL server info: " . mysqli_get_server_info($conn) . "\n";
    mysqli_close($conn);
} else {
    echo "❌ Connection FAILED\n";
    echo "Error #" . mysqli_connect_errno() . ": " . mysqli_connect_error() . "\n";
}
echo '</pre>';
