<?php
require_once __DIR__ . '/../../packages/backend/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    logout_user();
}
redirect('/login.php');
