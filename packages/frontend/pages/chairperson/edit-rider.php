<?php
require_once __DIR__ . '/../../../backend/rider_edit_page.php';
require_role(['chairperson']);
rider_edit_page('chairperson', 'my-riders', 'r.stage_id = ?', (int) $_SESSION['stage_id'], false,
    '/pages/chairperson/rider-profile.php?id=' . (int) ($_GET['id'] ?? 0), '/pages/chairperson/id-preview.php?id=');
