<?php
// ============================================================
// BodaERP — Database connection (mysqli)
// Edit these constants for your environment. Defaults match a
// stock XAMPP install (Apache + MariaDB, no root password).
// ============================================================

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'bodaerp');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

function db(): mysqli {
    static $link = null;
    if ($link === null) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $link = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int) DB_PORT);
        $link->set_charset(DB_CHARSET);
    }
    return $link;
}


//password: bodaerp=1A
//user: u850523537_u850523537_bod
//db u850523537_bodaDB