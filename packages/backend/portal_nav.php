<?php
// Sidebar menu for every portal, one entry per role. partials/sidebar.php renders it.
// Item: key => [file, icon, label, badge]; key matches the page's $active value.
// Badge: null, or [count, tone] where tone is 'info' | 'danger' | 'warning'. Counts of 0 are hidden.
require_once __DIR__ . '/anomaly_engine.php';

/** @return array{folder:string, scope:string, groups:array<string, array<string, array>>} */
function portal_nav(string $role): array {
    switch ($role) {
        case 'super_admin':
            $failed = migrator_last_failure() || schema_last_failure();
            return [
                'folder' => 'superadmin',
                'scope' => 'Platform administration',
                'groups' => [
                    'Overview' => [
                        'dashboard'        => ['dashboard.php', 'fa-gauge-high', 'Platform overview', null],
                        'platform-reports' => ['platform-reports.php', 'fa-chart-column', 'Reports', null],
                    ],
                    'Cities & people' => [
                        'cities'         => ['cities.php', 'fa-city', 'Cities', null],
                        'users'          => ['users.php', 'fa-users-gear', 'Users', null],
                        'revenue-splits' => ['revenue-splits.php', 'fa-percent', 'Revenue splits', null],
                        'data-quality'   => ['data-quality.php', 'fa-shield-halved', 'Data quality', [anomaly_open_count('high'), 'danger']],
                    ],
                    'Messages' => [
                        'inbox'         => ['inbox.php', 'fa-inbox', 'Contact inbox', [(int) fetchValue("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'"), 'info']],
                        'data-requests' => ['data-requests.php', 'fa-user-shield', 'Data requests', [(int) fetchValue("SELECT COUNT(*) FROM data_requests WHERE status = 'open'"), 'warning']],
                        'sms'           => ['sms.php', 'fa-comment-sms', 'SMS', null],
                    ],
                    'Website' => [
                        'content'         => ['content.php', 'fa-file-lines', 'Pages & content', null],
                        'site-settings'   => ['site-settings.php', 'fa-sliders', 'Text & branding', null],
                        'email-templates' => ['email-templates.php', 'fa-envelope-open-text', 'Email templates', null],
                        'media'           => ['media.php', 'fa-photo-film', 'Media library', null],
                        'languages'       => ['languages.php', 'fa-language', 'Languages', null],
                    ],
                    'System' => [
                        'platform-settings' => ['platform-settings.php', 'fa-gear', 'Platform settings', null],
                        'audit-logs'        => ['audit-logs.php', 'fa-clock-rotate-left', 'Audit log', null],
                        'database'          => ['database.php', 'fa-database', 'Database', $failed ? [1, 'danger'] : null],
                        'system'            => ['system.php', 'fa-server', 'System & updates', null],
                    ],
                ],
            ];

        case 'city_admin':
            // City ids are codes such as 'LIR'; cast to int they became 0, which MySQL matches against every city.
            $cityId = (string) ($_SESSION['city_id'] ?? '');
            return [
                'folder' => 'citycouncil',
                'scope' => $_SESSION['city_name'] ?? 'City council',
                'groups' => [
                    'Overview' => [
                        'dashboard' => ['dashboard.php', 'fa-gauge-high', 'Dashboard', null],
                    ],
                    'Riders & stages' => [
                        'city-riders'       => ['city-riders.php', 'fa-users', 'Riders', null],
                        'import-riders'     => ['import-riders.php', 'fa-file-import', 'Import riders', null],
                        'all-stages'        => ['all-stages.php', 'fa-location-dot', 'Stages', null],
                        'stage-management'  => ['stage-management.php', 'fa-sitemap', 'Manage stages', null],
                        'stage-performance' => ['stage-performance.php', 'fa-ranking-star', 'Stage performance', null],
                    ],
                    'Money' => [
                        'payments_management' => ['payments_management.php', 'fa-credit-card', 'Payments', null],
                        'cash-handovers'      => ['cash-handovers.php', 'fa-hand-holding-dollar', 'Cash handovers',
                            [(int) fetchValue("SELECT COUNT(*) FROM cash_handovers WHERE city_id = ? AND status = 'submitted'", [$cityId]), 'warning']],
                        'revenue-analytics'   => ['revenue-analytics.php', 'fa-money-bill-trend-up', 'Revenue', null],
                    ],
                    'Compliance' => [
                        'compliance-monitoring' => ['compliance-monitoring.php', 'fa-circle-check', 'Compliance', null],
                        'city-defaulters'       => ['city-defaulters.php', 'fa-triangle-exclamation', 'Defaulters',
                            [(int) fetchValue("SELECT COUNT(*) FROM riders WHERE city_id = ? AND status = 'expired'", [$cityId]), 'danger']],
                        'enforcement-actions'   => ['enforcement-actions.php', 'fa-gavel', 'Enforcement', null],
                        'permit-checks'         => ['permit-checks.php', 'fa-qrcode', 'Roadside checks', null],
                        'data-flags'            => ['data-flags.php', 'fa-shield-halved', 'Data checks', [anomaly_open_count('high', $cityId), 'danger']],
                    ],
                    'Settings' => [
                        'council-setting' => ['council-setting.php', 'fa-gear', 'Council settings', null],
                    ],
                ],
            ];

        case 'chairperson':
            $stageId = (int) ($_SESSION['stage_id'] ?? 0);
            $stage = (string) fetchValue("SELECT name FROM stages WHERE id = ?", [$stageId]);
            return [
                'folder' => 'chairperson',
                'scope' => trim($stage . ($stage && !empty($_SESSION['city_name']) ? ', ' : '') . ($_SESSION['city_name'] ?? '')) ?: 'Stage',
                'groups' => [
                    'Overview' => [
                        'dashboard' => ['dashboard.php', 'fa-gauge-high', 'Dashboard', null],
                    ],
                    'Riders' => [
                        'my-riders'      => ['my-riders.php', 'fa-users', 'My riders', null],
                        'register-rider' => ['register-rider.php', 'fa-user-plus', 'Register a rider', null],
                    ],
                    'Money' => [
                        'collect-payments' => ['collect-payments.php', 'fa-money-bill-wave', 'Collect payments', null],
                        'cash-handover'    => ['cash-handover.php', 'fa-hand-holding-dollar', 'Cash handover',
                            [(int) fetchValue("SELECT COUNT(*) FROM payments p JOIN riders r ON r.id = p.rider_id WHERE r.stage_id = ? AND p.payment_method = 'Cash' AND p.status = 'Confirmed' AND p.handover_id IS NULL AND p.channel = 'stage'", [$stageId]), 'info']],
                    ],
                    'Compliance' => [
                        'expiry-alerts' => ['expiry-alerts.php', 'fa-bell', 'Expiring soon',
                            [(int) fetchValue("SELECT COUNT(*) FROM riders WHERE stage_id = ? AND status = 'active' AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)", [$stageId]), 'warning']],
                        'defaulters'    => ['defaulters.php', 'fa-triangle-exclamation', 'Defaulters',
                            [(int) fetchValue("SELECT COUNT(*) FROM riders WHERE stage_id = ? AND status = 'expired'", [$stageId]), 'danger']],
                    ],
                    'Stage' => [
                        'stage-report'   => ['stage-report.php', 'fa-file-lines', 'Stage report', null],
                        'stage-settings' => ['stage-settings.php', 'fa-gear', 'Stage settings', null],
                    ],
                ],
            ];

        case 'rider':
            return [
                'folder' => 'rider',
                'scope' => $_SESSION['city_name'] ?? 'Rider',
                'groups' => [
                    t('nav.rider.group_overview', 'Overview') => [
                        'dashboard'     => ['dashboard.php', 'fa-gauge-high', t('nav.rider.dashboard', 'Dashboard'), null],
                        'notifications' => ['notifications.php', 'fa-bell', t('nav.rider.notifications', 'Notifications'),
                            [(int) fetchValue("SELECT COUNT(*) FROM notifications WHERE rider_id = ? AND is_read = 0", [(int) ($_SESSION['rider_id'] ?? 0)]), 'danger']],
                    ],
                    t('nav.rider.group_permit', 'My permit') => [
                        'my-id-card'      => ['my-id-card.php', 'fa-id-card', t('nav.rider.id_card', 'ID card'), null],
                        'renewal-status'  => ['renewal-status.php', 'fa-rotate', t('nav.rider.renewal', 'Renewal'), null],
                        'payment-history' => ['payment-history.php', 'fa-receipt', t('nav.rider.payments', 'Payments & receipts'), null],
                    ],
                    t('nav.rider.group_me', 'Me') => [
                        'my-profile' => ['my-profile.php', 'fa-user', t('nav.rider.details', 'My details'), null],
                    ],
                ],
            ];

        case 'government':
            return [
                'folder' => 'government',
                'scope' => $_SESSION['agency_name'] ?? 'Government (read only)',
                'groups' => [
                    'Overview' => [
                        'dashboard'  => ['dashboard.php', 'fa-earth-africa', 'National overview', null],
                        'comparison' => ['comparison.php', 'fa-scale-balanced', 'City comparison', null],
                    ],
                    'Compliance' => [
                        'registry'    => ['registry.php', 'fa-id-card', 'Rider registry', null],
                        'enforcement' => ['enforcement.php', 'fa-gavel', 'Enforcement', null],
                        'data-flags'  => ['data-flags.php', 'fa-shield-halved', 'Data checks', [anomaly_open_count('high'), 'danger']],
                    ],
                    'Reports' => [
                        'reports' => ['reports.php', 'fa-file-invoice', 'National reports', null],
                    ],
                ],
            ];
    }
    return ['folder' => '', 'scope' => '', 'groups' => []];
}
