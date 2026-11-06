<?php
require_once __DIR__ . '/../../../backend/rider_edit_page.php';
require_role(['city_admin']);
rider_edit_page('citycouncil', 'city-riders', 'r.city_id = ?', (string) $_SESSION['city_id'], true, '/pages/citycouncil/city-riders.php', '/pages/citycouncil/id-card.php?id=');
