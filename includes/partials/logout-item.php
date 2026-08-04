            <li>
                <a href="#" class="text-danger" onclick="document.getElementById('logoutForm').submit(); return false;">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </nav>
    <form id="logoutForm" method="post" action="<?= BASE_URL ?>/logout.php" class="d-none">
        <?= csrf_field() ?>
    </form>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
