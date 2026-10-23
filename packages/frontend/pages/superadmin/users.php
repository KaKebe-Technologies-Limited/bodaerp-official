<?php
require_once "/var/www/html/packages/backend/auth.php";
require_role(['super_admin']);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id === (int) $_SESSION['user_id']) {
            $errors[] = 'You cannot delete your own account while logged in.';
        } else {
            runQuery("UPDATE users SET deleted_at = NOW(), status = 'suspended' WHERE id = ?", [$id]);
            audit_log('DELETE', 'user', (string) $id, 'User deleted');
            redirect('/pages/superadmin/users.php');
        }
    } else {
        $editId   = (int) ($_POST['edit_user_id'] ?? 0);
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $role     = $_POST['role'] ?? 'rider';
        $cityId   = in_array($role, ['super_admin', 'government'], true) ? null : ($_POST['city_id'] ?: null);
        $agency   = $role === 'government' ? trim($_POST['agency_name'] ?? '') : null;
        $status   = $_POST['status'] ?? 'active';
        $password = $_POST['password'] ?? '';

        if ($name === '' || $email === '') $errors[] = 'Name and email are required.';
        if (!in_array($role, ['super_admin','city_admin','chairperson','rider','government'], true)) $errors[] = 'Invalid role.';
        if (!in_array($role, ['super_admin', 'government'], true) && !$cityId) $errors[] = 'City is required for this role.';
        if ($role === 'government' && $agency === '') $errors[] = 'Agency / ministry name is required for government accounts.';
        if (!$editId && $password === '') $errors[] = 'Password is required for a new user.';

        if (!$errors) {
            $dupe = fetchOne("SELECT id FROM users WHERE email = ? AND id != ? AND deleted_at IS NULL", [$email, $editId]);
            if ($dupe) $errors[] = 'That email is already in use.';
        }

        if (!$errors && $editId) {
            if ($password !== '') {
                runQuery("UPDATE users SET name=?,email=?,phone=?,role=?,city_id=?,agency_name=?,status=?,password_hash=? WHERE id=?",
                    [$name,$email,$phone,$role,$cityId,$agency,$status,password_hash($password, PASSWORD_DEFAULT),$editId]);
            } else {
                runQuery("UPDATE users SET name=?,email=?,phone=?,role=?,city_id=?,agency_name=?,status=? WHERE id=?",
                    [$name,$email,$phone,$role,$cityId,$agency,$status,$editId]);
            }
            audit_log('UPDATE', 'user', (string) $editId, 'User updated');
            redirect('/pages/superadmin/users.php');
        } elseif (!$errors) {
            runQuery("INSERT INTO users (name,email,phone,password_hash,role,city_id,agency_name,status) VALUES (?,?,?,?,?,?,?,?)",
                [$name,$email,$phone,password_hash($password, PASSWORD_DEFAULT),$role,$cityId,$agency,$status]);
            $newId = db()->insert_id;
            audit_log('CREATE', 'user', (string) $newId, 'User created');
            redirect('/pages/superadmin/users.php');
        }
    }
}

$active = 'users';
$search = trim($_GET['q'] ?? '');
$filterCity = $_GET['city'] ?? '';
$filterRole = $_GET['role'] ?? '';

$sql = "SELECT u.*, c.name AS city_name, c.logo_path AS city_logo FROM users u LEFT JOIN cities c ON c.id = u.city_id WHERE u.deleted_at IS NULL";
$params = [];
if ($search !== '') { $sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($filterCity !== '') { $sql .= " AND u.city_id = ?"; $params[] = $filterCity; }
if ($filterRole !== '') { $sql .= " AND u.role = ?"; $params[] = $filterRole; }
$sql .= " ORDER BY u.id DESC";
$users = fetchAll($sql, $params);
$cities = fetchAll("SELECT id, name FROM cities ORDER BY name");
$roleColors = ['super_admin' => 'primary', 'city_admin' => 'success', 'chairperson' => 'warning', 'rider' => 'info'];

$totalUsersAll    = (int) fetchValue("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL");
$activeUsersAll   = (int) fetchValue("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND status = 'active'");
$suspendedUsersAll = (int) fetchValue("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND status = 'suspended'");
$adminUsersAll    = (int) fetchValue("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND role IN ('super_admin','city_admin')");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'All Users - BodaERP Platform'; require __DIR__ . '/../../partials/head-assets.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../partials/sidebar-superadmin.php'; ?>

<div class="main-content" id="mainContent">
    <header class="top-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-light d-lg-none" id="toggleSidebar"><i class="fas fa-bars"></i></button>
                <div>
                    <h5 class="mb-0 fw-bold"><span class="text-gradient-blue">All Platform Users</span></h5>
                    <small class="text-muted d-none d-sm-block">Manage accounts across every role and city</small>
                </div>
            </div>
            <form class="d-flex gap-2 flex-wrap" method="get">
                <input type="text" class="form-control form-control-sm" name="q" value="<?= h($search) ?>" placeholder="Search name, email, phone..." style="width:200px;">
                <select class="form-select form-select-sm" name="city" style="width:auto;" onchange="this.form.submit()">
                    <option value="">All Cities</option>
                    <?php foreach ($cities as $c): ?>
                        <option value="<?= h($c['id']) ?>" <?= $filterCity === $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="form-select form-select-sm" name="role" style="width:auto;" onchange="this.form.submit()">
                    <option value="">All Roles</option>
                    <option value="super_admin" <?= $filterRole === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                    <option value="city_admin" <?= $filterRole === 'city_admin' ? 'selected' : '' ?>>City Admin</option>
                    <option value="chairperson" <?= $filterRole === 'chairperson' ? 'selected' : '' ?>>Chairperson</option>
                    <option value="rider" <?= $filterRole === 'rider' ? 'selected' : '' ?>>Rider</option>
                    <option value="government" <?= $filterRole === 'government' ? 'selected' : '' ?>>Government</option>
                </select>
                <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="fas fa-search"></i></button>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#userModal" onclick="openAddUser()"><i class="fas fa-user-plus me-1"></i>Add User</button>
            </form>
        </div>
    </header>

    <div class="content-area">
        <?php if ($errors): ?>
            <div class="alert alert-danger"><?php foreach ($errors as $e) echo h($e) . '<br>'; ?></div>
        <?php endif; ?>

        <div class="row g-3 row-cols-2 row-cols-xl-4 mb-4">
            <div class="col"><div class="stat-hero-card grad-blue"><div class="stat-hero-icon"><i class="fas fa-users"></i></div><div><div class="stat-hero-value"><?= number_format($totalUsersAll) ?></div><div class="stat-hero-label">Total Users</div></div></div></div>
            <div class="col"><div class="stat-hero-card grad-green"><div class="stat-hero-icon"><i class="fas fa-user-check"></i></div><div><div class="stat-hero-value"><?= number_format($activeUsersAll) ?></div><div class="stat-hero-label">Active</div></div></div></div>
            <div class="col"><div class="stat-hero-card grad-red"><div class="stat-hero-icon"><i class="fas fa-user-slash"></i></div><div><div class="stat-hero-value"><?= number_format($suspendedUsersAll) ?></div><div class="stat-hero-label">Suspended</div></div></div></div>
            <div class="col"><div class="stat-hero-card grad-dark"><div class="stat-hero-icon"><i class="fas fa-user-shield"></i></div><div><div class="stat-hero-value"><?= number_format($adminUsersAll) ?></div><div class="stat-hero-label">Admins</div></div></div></div>
        </div>

        <div class="card mb-3 no-print">
            <div class="card-body d-flex justify-content-end">
                <?php $exportTableId='usersTable'; $exportFilename='platform_users'; $exportTitle='Platform Users'; require __DIR__ . '/../../partials/export-toolbar.php'; ?>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="usersTable">
                        <thead><tr><th>User</th><th>Role</th><th>City</th><th>Phone</th><th>Status</th><th>Created</th><th class="no-export">Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($users as $u): $sc = ['active'=>'success','suspended'=>'secondary']; ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="<?= BASE_URL ?>/assets/images/avatar-placeholder.png" width="34" height="34" class="rounded-circle border">
                                        <div><strong style="font-size:0.85rem;"><?= h($u['name']) ?></strong><br><small class="text-muted"><?= h($u['email']) ?></small></div>
                                    </div>
                                </td>
                                <td><?php if ($u['role'] === 'government'): ?><span class="badge" style="font-size:0.65rem;background:#e0529c;">GOVERNMENT</span><?php else: ?><span class="badge bg-<?= $roleColors[$u['role']] ?? 'secondary' ?>" style="font-size:0.65rem;"><?= strtoupper(str_replace('_',' ',$u['role'])) ?></span><?php endif; ?></td>
                                <td><?= $u['role'] === 'government' ? h($u['agency_name'] ?: 'National') : ($u['city_name'] ? h($u['city_name']) : '<span class="text-muted">Platform</span>') ?></td>
                                <td><?= h($u['phone'] ?: '—') ?></td>
                                <td><span class="badge bg-<?= $sc[$u['status']] ?? 'secondary' ?>"><?= strtoupper($u['status']) ?></span></td>
                                <td style="font-size:0.8rem;"><?= formatDate($u['created_at']) ?></td>
                                <td class="no-export">
                                    <button class="btn btn-sm btn-outline-primary" onclick="openEditUser(<?= (int) $u['id'] ?>)"><i class="fas fa-edit"></i></button>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Delete this user?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (!$users): ?>
                    <div class="empty-state">
                        <i class="fas fa-user-slash"></i>
                        <h5>No users found</h5>
                        <p>Try adjusting your search or filters, or add a new user to get started.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px;overflow:hidden;">
            <form method="post" id="userForm">
                <?= csrf_field() ?>
                <input type="hidden" name="edit_user_id" id="editUserId">
                <div class="modal-header" style="background:var(--primary-blue-gradient);color:white;border-bottom:none;">
                    <h5 class="modal-title fw-bold" id="userModalTitle"><i class="fas fa-user-plus me-2"></i>Add User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12"><label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label><input type="text" class="form-control" name="name" id="userName" placeholder="Full name" required></div>
                        <div class="col-md-6"><label class="form-label fw-semibold">Email <span class="text-danger">*</span></label><input type="email" class="form-control" name="email" id="userEmail" placeholder="email@example.com" required></div>
                        <div class="col-md-6"><label class="form-label fw-semibold">Phone</label><input type="text" class="form-control" name="phone" id="userPhone" placeholder="+256 7xx xxx xxx"></div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Role <span class="text-danger">*</span></label>
                            <select class="form-select" name="role" id="userRole" onchange="toggleCityField()">
                                <option value="super_admin">Super Admin</option><option value="city_admin">City Admin</option><option value="chairperson">Chairperson</option><option value="rider">Rider</option><option value="government">Government (National Portal)</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="cityFieldWrap">
                            <label class="form-label fw-semibold">City <span class="text-danger">*</span></label>
                            <select class="form-select" name="city_id" id="userCity">
                                <?php foreach ($cities as $c): ?><option value="<?= h($c['id']) ?>"><?= h($c['name']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 d-none" id="agencyFieldWrap">
                            <label class="form-label fw-semibold">Agency / Ministry <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="agency_name" id="userAgency" placeholder="e.g. Uganda Revenue Authority (URA)" list="agencyOptions">
                            <datalist id="agencyOptions">
                                <option value="Ministry of Works & Transport">
                                <option value="Ministry of Finance">
                                <option value="Uganda Revenue Authority (URA)">
                                <option value="Ministry of Internal Affairs">
                                <option value="Ministry of Local Government">
                                <option value="Ministry of ICT & National Guidance">
                                <option value="National Identification & Registration Authority (NIRA)">
                                <option value="Uganda Police Force / Traffic Enforcement">
                            </datalist>
                        </div>
                        <div class="col-md-6"><label class="form-label fw-semibold">Password <span id="pwRequired" class="text-danger">*</span></label><input type="password" class="form-control" name="password" id="userPassword" placeholder="Set password"></div>
                        <div class="col-md-6"><label class="form-label fw-semibold">Status</label><select class="form-select" name="status" id="userStatus"><option value="active">Active</option><option value="suspended">Suspended</option></select></div>
                    </div>
                </div>
                <div class="modal-footer border-0 pb-3">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../partials/scripts-footer.php'; ?>
<script>
    const usersData = <?= json_encode(array_column($users, null, 'id')) ?>;

    function toggleCityField() {
        const role = document.getElementById('userRole').value;
        document.getElementById('cityFieldWrap').classList.toggle('d-none', role === 'super_admin' || role === 'government');
        document.getElementById('agencyFieldWrap').classList.toggle('d-none', role !== 'government');
    }

    function openAddUser() {
        document.getElementById('userModalTitle').innerHTML = '<i class="fas fa-user-plus me-2"></i>Add User';
        document.getElementById('editUserId').value = '';
        ['userName','userEmail','userPhone','userPassword','userAgency'].forEach(id => document.getElementById(id).value = '');
        document.getElementById('userRole').value = 'city_admin';
        document.getElementById('userStatus').value = 'active';
        document.getElementById('userPassword').required = true;
        document.getElementById('pwRequired').style.display = '';
        toggleCityField();
    }

    function openEditUser(id) {
        const u = usersData[id];
        if (!u) return;
        document.getElementById('userModalTitle').innerHTML = '<i class="fas fa-user-edit me-2"></i>Edit User';
        document.getElementById('editUserId').value = u.id;
        document.getElementById('userName').value = u.name;
        document.getElementById('userEmail').value = u.email;
        document.getElementById('userPhone').value = u.phone || '';
        document.getElementById('userRole').value = u.role;
        document.getElementById('userCity').value = u.city_id || '';
        document.getElementById('userAgency').value = u.agency_name || '';
        document.getElementById('userStatus').value = u.status || 'active';
        document.getElementById('userPassword').value = '';
        document.getElementById('userPassword').required = false;
        document.getElementById('pwRequired').style.display = 'none';
        toggleCityField();
        new bootstrap.Modal(document.getElementById('userModal')).show();
    }

    <?php if ($errors): ?>new bootstrap.Modal(document.getElementById('userModal')).show();<?php endif; ?>

    document.getElementById('toggleSidebar')?.addEventListener('click', () => {
        document.getElementById('sidebar').classList.toggle('open');
        document.getElementById('sidebarOverlay').classList.toggle('active');
    });
    document.getElementById('sidebarOverlay')?.addEventListener('click', () => {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('sidebarOverlay').classList.remove('active');
    });
</script>
</body>
</html>
