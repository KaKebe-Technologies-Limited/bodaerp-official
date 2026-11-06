<?php
// Sidebar menu for every portal, one entry per role. partials/sidebar.php renders it.
// Group: label => items, with the group's icon in 'icons'. A group with one item is a plain link; a group with more
// opens as a dropdown of its pages. Item: key => [file, icon, label, badge]; key matches the page's $active value.
// Badge: null, or [count, tone] where tone is 'info' | 'danger' | 'warning'. Counts of 0 are hidden.
require_once __DIR__ . '/anomaly_engine.php';

/** @return array{folder:string, scope:string, icons:array<string,string>, groups:array<string, array<string, array>>} */
function portal_nav(string $role): array {
    static $built = [];
    return $built[$role] ??= portal_nav_build($role);
}

/**
 * The menu for the page being served. A Super Admin reading the national (government) portal gets that portal's
 * menu, plus a link back to their own, so the pages they browse there are reachable and the right one is marked.
 */
function portal_nav_current(): array {
    $role = (string) ($_SESSION['role'] ?? '');
    $folder = basename(dirname(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_FILENAME'] ?? ''))));
    if ($role === 'super_admin' && $folder === 'government') {
        $nav = portal_nav('government');
        $nav['icons']['Admin'] = 'fa-arrow-left';
        $nav['groups']['Admin'] = ['back-to-admin' => ['../superadmin/dashboard.php', 'fa-arrow-left', 'Admin', null]];
        return $nav;
    }
    return portal_nav($role);
}

/**
 * The sidebar label of the page being served, when it is in the current menu (the browser tab title uses it, so the
 * tab and the menu always say the same thing). Pages outside the menu return null and keep their own title.
 */
function portal_page_label(): ?string {
    if (empty($_SESSION['role'])) return null;
    $nav = portal_nav_current();
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
    if ($nav['folder'] === '' || basename(dirname($script)) !== $nav['folder']) return null;
    foreach ($nav['groups'] as $items) {
        foreach ($items as [$file, , $label]) {
            if ($file === basename($script)) return $label;
        }
    }
    return null;
}

function portal_nav_build(string $role): array {
    switch ($role) {
        case 'super_admin':
            $failed = migrator_last_failure() || schema_last_failure();
            return [
                'folder' => 'superadmin',
                'scope' => 'Platform administration',
                'icons' => ['Overview' => 'fa-gauge-high', 'People' => 'fa-city', 'Messages' => 'fa-envelope', 'Website' => 'fa-globe', 'System' => 'fa-gear'],
                'groups' => [
                    'Overview' => [
                        'dashboard'        => ['dashboard.php', 'fa-gauge-high', 'Dashboard', null],
                        'platform-reports' => ['platform-reports.php', 'fa-chart-column', 'Reports', null],
                        'national'         => ['../government/dashboard.php', 'fa-earth-africa', 'National', null],
                    ],
                    'People' => [
                        'cities'         => ['cities.php', 'fa-city', 'Cities', null],
                        'users'          => ['users.php', 'fa-users-gear', 'Users', null],
                        'revenue-splits' => ['revenue-splits.php', 'fa-percent', 'Splits', null],
                        'data-quality'   => ['data-quality.php', 'fa-shield-halved', 'Quality', [anomaly_open_count('high'), 'danger']],
                    ],
                    'Messages' => [
                        'inbox'         => ['inbox.php', 'fa-inbox', 'Inbox', [(int) fetchValue("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'"), 'info']],
                        'data-requests' => ['data-requests.php', 'fa-user-shield', 'Requests', [(int) fetchValue("SELECT COUNT(*) FROM data_requests WHERE status = 'open'"), 'warning']],
                        'sms'           => ['sms.php', 'fa-comment-sms', 'SMS', null],
                    ],
                    'Website' => [
                        'content'         => ['content.php', 'fa-file-lines', 'Pages', null],
                        'site-settings'   => ['site-settings.php', 'fa-sliders', 'Branding', null],
                        'email-templates' => ['email-templates.php', 'fa-envelope-open-text', 'Emails', null],
                        'media'           => ['media.php', 'fa-photo-film', 'Media', null],
                        'languages'       => ['languages.php', 'fa-language', 'Languages', null],
                    ],
                    'System' => [
                        'platform-settings' => ['platform-settings.php', 'fa-gear', 'Settings', null],
                        'audit-logs'        => ['audit-logs.php', 'fa-clock-rotate-left', 'Audit', null],
                        'database'          => ['database.php', 'fa-database', 'Database', $failed ? [1, 'danger'] : null],
                        'system'            => ['system.php', 'fa-server', 'Updates', null],
                    ],
                ],
            ];

        case 'city_admin':
            // City ids are codes such as 'LIR'; cast to int they became 0, which MySQL matches against every city.
            $cityId = (string) ($_SESSION['city_id'] ?? '');
            return [
                'folder' => 'citycouncil',
                'scope' => $_SESSION['city_name'] ?? 'City council',
                'icons' => ['Overview' => 'fa-gauge-high', 'Riders' => 'fa-users', 'Money' => 'fa-wallet', 'Compliance' => 'fa-shield-halved', 'Settings' => 'fa-gear'],
                'groups' => [
                    'Overview' => [
                        'dashboard' => ['dashboard.php', 'fa-gauge-high', 'Dashboard', null],
                    ],
                    'Riders' => [
                        'city-riders'       => ['city-riders.php', 'fa-users', 'Riders', null],
                        'import-riders'     => ['import-riders.php', 'fa-file-import', 'Import', null],
                        'id-cards'          => ['id-cards.php', 'fa-id-card', 'Cards', null],
                        'all-stages'        => ['all-stages.php', 'fa-location-dot', 'Stages', null],
                        'stage-management'  => ['stage-management.php', 'fa-sitemap', 'Setup', null],
                        'stage-performance' => ['stage-performance.php', 'fa-ranking-star', 'Performance', null],
                    ],
                    'Money' => [
                        'payments_management' => ['payments_management.php', 'fa-credit-card', 'Payments', null],
                        'cash-handovers'      => ['cash-handovers.php', 'fa-hand-holding-dollar', 'Handovers',
                            [(int) fetchValue("SELECT COUNT(*) FROM cash_handovers WHERE city_id = ? AND status = 'submitted'", [$cityId]), 'warning']],
                        'revenue-analytics'   => ['revenue-analytics.php', 'fa-money-bill-trend-up', 'Revenue', null],
                    ],
                    'Compliance' => [
                        'compliance-monitoring' => ['compliance-monitoring.php', 'fa-circle-check', 'Monitoring', null],
                        'city-defaulters'       => ['city-defaulters.php', 'fa-triangle-exclamation', 'Defaulters',
                            [(int) fetchValue("SELECT COUNT(*) FROM riders WHERE city_id = ? AND status = 'expired'", [$cityId]), 'danger']],
                        'enforcement-actions'   => ['enforcement-actions.php', 'fa-gavel', 'Enforcement',
                            [(int) fetchValue("SELECT COUNT(*) FROM enforcement_actions WHERE city_id = ? AND appeal_status = 'open'", [$cityId]), 'warning']],
                        'permit-checks'         => ['permit-checks.php', 'fa-qrcode', 'Checks', null],
                        'data-flags'            => ['data-flags.php', 'fa-shield-halved', 'Flags', [anomaly_open_count('high', $cityId), 'danger']],
                    ],
                    'Settings' => [
                        'council-setting' => ['council-setting.php', 'fa-gear', 'Council', null],
                    ],
                ],
            ];

        case 'chairperson':
            $stageId = (int) ($_SESSION['stage_id'] ?? 0);
            $stage = (string) fetchValue("SELECT name FROM stages WHERE id = ?", [$stageId]);
            return [
                'folder' => 'chairperson',
                'scope' => trim($stage . ($stage && !empty($_SESSION['city_name']) ? ', ' : '') . ($_SESSION['city_name'] ?? '')) ?: 'Stage',
                'icons' => ['Overview' => 'fa-gauge-high', 'Riders' => 'fa-users', 'Money' => 'fa-wallet', 'Compliance' => 'fa-shield-halved', 'Stage' => 'fa-location-dot'],
                'groups' => [
                    'Overview' => [
                        'dashboard' => ['dashboard.php', 'fa-gauge-high', 'Dashboard', null],
                    ],
                    'Riders' => [
                        'my-riders'      => ['my-riders.php', 'fa-users', 'Riders', null],
                        'register-rider' => ['register-rider.php', 'fa-user-plus', 'Register', null],
                        'id-cards'       => ['id-cards.php', 'fa-id-card', 'Cards',
                            [(int) fetchValue("SELECT COUNT(*) FROM id_cards WHERE stage_id = ? AND status = 'issued'", [$stageId]), 'warning']],
                    ],
                    'Money' => [
                        'collect-payments' => ['collect-payments.php', 'fa-money-bill-wave', 'Collect', null],
                        'cash-handover'    => ['cash-handover.php', 'fa-hand-holding-dollar', 'Handover',
                            [(int) fetchValue("SELECT COUNT(*) FROM payments p JOIN riders r ON r.id = p.rider_id WHERE r.stage_id = ? AND p.payment_method = 'Cash' AND p.status = 'Confirmed' AND p.handover_id IS NULL AND p.channel = 'stage'", [$stageId]), 'info']],
                    ],
                    'Compliance' => [
                        'expiry-alerts' => ['expiry-alerts.php', 'fa-bell', 'Expiring',
                            [(int) fetchValue("SELECT COUNT(*) FROM riders WHERE stage_id = ? AND status = 'active' AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)", [$stageId]), 'warning']],
                        'defaulters'    => ['defaulters.php', 'fa-triangle-exclamation', 'Defaulters',
                            [(int) fetchValue("SELECT COUNT(*) FROM riders WHERE stage_id = ? AND status = 'expired'", [$stageId]), 'danger']],
                    ],
                    'Stage' => [
                        'stage-report'   => ['stage-report.php', 'fa-file-lines', 'Report', null],
                        'stage-settings' => ['stage-settings.php', 'fa-gear', 'Settings', null],
                    ],
                ],
            ];

        case 'rider':
            return [
                'folder' => 'rider',
                'scope' => $_SESSION['city_name'] ?? 'Rider',
                'icons' => [t('nav.rider.group_overview', 'Overview') => 'fa-gauge-high', t('nav.rider.group_permit', 'Permit') => 'fa-id-card', t('nav.rider.group_me', 'Me') => 'fa-user'],
                'groups' => [
                    t('nav.rider.group_overview', 'Overview') => [
                        'dashboard'     => ['dashboard.php', 'fa-gauge-high', t('nav.rider.dashboard', 'Dashboard'), null],
                        'notifications' => ['notifications.php', 'fa-bell', t('nav.rider.notifications', 'Notifications'),
                            [(int) fetchValue("SELECT COUNT(*) FROM notifications WHERE rider_id = ? AND is_read = 0", [(int) ($_SESSION['rider_id'] ?? 0)]), 'danger']],
                    ],
                    t('nav.rider.group_permit', 'Permit') => [
                        'my-id-card'      => ['my-id-card.php', 'fa-id-card', t('nav.rider.id_card', 'Card'), null],
                        'renewal-status'  => ['renewal-status.php', 'fa-rotate', t('nav.rider.renewal', 'Renewal'), null],
                        'payment-history' => ['payment-history.php', 'fa-receipt', t('nav.rider.payments', 'Receipts'), null],
                        'enforcement'     => ['enforcement.php', 'fa-gavel', t('nav.rider.enforcement', 'Enforcement'),
                            [(int) fetchValue("SELECT COUNT(*) FROM enforcement_actions WHERE rider_id = ? AND status = 'pending'", [(int) ($_SESSION['rider_id'] ?? 0)]), 'danger']],
                    ],
                    t('nav.rider.group_me', 'Me') => [
                        'my-profile' => ['my-profile.php', 'fa-user', t('nav.rider.details', 'Profile'), null],
                    ],
                ],
            ];

        case 'government':
            return [
                'folder' => 'government',
                'scope' => $_SESSION['agency_name'] ?? 'Government (read only)',
                'icons' => ['Overview' => 'fa-earth-africa', 'Compliance' => 'fa-shield-halved', 'Reports' => 'fa-file-lines'],
                'groups' => [
                    'Overview' => [
                        'dashboard'  => ['dashboard.php', 'fa-earth-africa', 'Dashboard', null],
                        'comparison' => ['comparison.php', 'fa-scale-balanced', 'Cities', null],
                    ],
                    'Compliance' => [
                        'registry'    => ['registry.php', 'fa-id-card', 'Registry', null],
                        'enforcement' => ['enforcement.php', 'fa-gavel', 'Enforcement', null],
                        'data-flags'  => ['data-flags.php', 'fa-shield-halved', 'Flags', [anomaly_open_count('high'), 'danger']],
                    ],
                    'Reports' => [
                        'reports' => ['reports.php', 'fa-file-invoice', 'Reports', null],
                    ],
                ],
            ];
    }
    return ['folder' => '', 'scope' => '', 'icons' => [], 'groups' => []];
}
