<?php
// Super Admin correction of any rider record, opened from a correction request on Rider data requests.
require_once __DIR__ . '/../../../backend/rider_edit_page.php';
require_role(['super_admin']);
rider_edit_page('superadmin', 'data-requests', '1 = ?', 1, true, '/pages/superadmin/data-requests.php', '/pages/superadmin/edit-rider.php?id=');
