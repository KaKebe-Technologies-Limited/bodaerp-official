            <li>
                <a href="#" class="text-danger" onclick="document.getElementById('logoutForm').submit(); return false;">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
        <?php
        $sfcRoleLabels = [
            'super_admin' => 'Platform Admin',
            'city_admin'  => 'City Council',
            'chairperson' => 'Chairperson',
            'rider'       => 'Rider',
        ];
        $sfcRole = $sfcRoleLabels[$_SESSION['role'] ?? ''] ?? ($_SESSION['role'] ?? '');
        ?>
        <div class="sidebar-footer-card">
            <img src="<?= BASE_URL ?>/assets/images/avatar-placeholder.png" alt="<?= h($_SESSION['name'] ?? '') ?>">
            <div class="flex-1" style="min-width:0;">
                <div class="sfc-name"><?= h($_SESSION['name'] ?? '') ?></div>
                <div class="sfc-role"><?= h($sfcRole) ?></div>
            </div>
            <span class="sfc-dot" title="Online"></span>
        </div>
    </nav>
    <form id="logoutForm" method="post" action="<?= BASE_URL ?>/logout.php" class="d-none">
        <?= csrf_field() ?>
    </form>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
