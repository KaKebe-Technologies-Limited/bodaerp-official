<?php
require_once '/var/www/html/packages/backend/auth.php';
redirect(is_logged_in() ? dashboard_url_for($_SESSION['role']) : '/login.php');
