<?php
require_once __DIR__ . '/../../../backend/admin_ui.php';
require_once __DIR__ . '/../../../backend/accounts.php';
require_role(['super_admin']);

$errors = [];
$me = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? 'save';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete') {
        if ($id === $me) {
            flash('error', 'You cannot delete your own account while signed in.');
        } else {
            runQuery("UPDATE users SET deleted_at = NOW(), status = 'suspended' WHERE id = ?", [$id]);
            runQuery("UPDATE login_sessions SET is_active = 0 WHERE user_id = ?", [$id]);
            runQuery("DELETE FROM user_tokens WHERE user_id = ?", [$id]);
            audit_log('DELETE', 'user', (string) $id, 'User deleted');
            flash('success', 'User deleted and signed out everywhere.');
        }
        redirect('/pages/superadmin/users.php' . query_with([]));
    }
    if ($action === 'send_link') {
        $u = fetchOne("SELECT id, email, password_changed_at FROM users WHERE id = ? AND deleted_at IS NULL", [$id]);
        $ok = $u && account_send_invite($id);
        audit_log('INVITE', 'user', (string) $id, 'Password link emailed by administrator');
        flash($ok ? 'success' : 'error', $ok ? 'A link to set a new password was emailed to ' . $u['email'] . '.' : 'The email could not be sent. Check Email Templates → Sent log.');
        redirect('/pages/superadmin/users.php' . query_with([]));
    }
    if ($action === 'sign_out') {
        runQuery("UPDATE login_sessions SET is_active = 0 WHERE user_id = ?", [$id]);
        runQuery("DELETE FROM user_tokens WHERE user_id = ? AND purpose = 'remember'", [$id]);
        audit_log('LOGOUT', 'user', (string) $id, 'Signed out of every device by administrator');
        flash('success', 'The user has been signed out of every device.');
        redirect($id === $me ? '/login.php' : '/pages/superadmin/users.php' . query_with([]));
    }

    $editId   = (int) ($_POST['edit_user_id'] ?? 0);
    $name     = trim($_POST['name'] ?? '');
    $email    = mb_strtolower(trim($_POST['email'] ?? ''));
    $phone    = trim($_POST['phone'] ?? '') ?: null;
    $role     = $_POST['role'] ?? 'rider';
    $cityId   = in_array($role, ['super_admin', 'government'], true) ? null : (($_POST['city_id'] ?? '') ?: null);
    $stageId  = in_array($role, ['chairperson', 'rider'], true) ? ((int) ($_POST['stage_id'] ?? 0) ?: null) : null;
    $agency   = $role === 'government' ? trim($_POST['agency_name'] ?? '') : null;
    $status   = ($_POST['status'] ?? 'active') === 'suspended' ? 'suspended' : 'active';
    $password = (string) ($_POST['password'] ?? '');
    $before   = $editId ? fetchOne("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL", [$editId]) : null;

    if ($editId && !$before) $errors[] = 'That user no longer exists.';
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a name and a valid email address.';
    if (!isset(ROLE_LABELS[$role])) $errors[] = 'Choose a valid role.';
    if (!in_array($role, ['super_admin', 'government'], true) && !$cityId) $errors[] = 'Choose the city this account works in.';
    if ($cityId && !fetchValue("SELECT COUNT(*) FROM cities WHERE id = ?", [$cityId])) $errors[] = 'Choose a valid city.';
    if ($role === 'chairperson' && !$stageId) $errors[] = 'Choose the stage this chairperson runs.';
    if ($stageId && !fetchValue("SELECT COUNT(*) FROM stages WHERE id = ? AND city_id = ?", [$stageId, $cityId])) $errors[] = 'The stage must be in the chosen city.';
    if ($role === 'government' && $agency === '') $errors[] = 'Enter the agency or ministry for government accounts.';
    if ($editId === $me && ($role !== 'super_admin' || $status !== 'active')) $errors[] = 'You cannot remove your own Super Admin role or suspend yourself.';
    if ($password !== '') foreach (password_problems($password, $email, $name) as $p) $errors[] = $p;
    if (!$errors && fetchOne("SELECT id FROM users WHERE email = ? AND id != ? AND deleted_at IS NULL", [$email, $editId])) $errors[] = 'Another account already uses that email.';

    if (!$errors && $editId) {
        runQuery("UPDATE users SET name=?,email=?,phone=?,role=?,city_id=?,stage_id=?,agency_name=?,status=? WHERE id=?",
            [$name, $email, $phone, $role, $cityId, $stageId, $agency, $status, $editId]);
        if ($email !== $before['email']) runQuery("UPDATE users SET email_verified_at = NULL WHERE id = ?", [$editId]);
        if ($password !== '') set_user_password($editId, $password);
        if ($status === 'suspended') runQuery("UPDATE login_sessions SET is_active = 0 WHERE user_id = ?", [$editId]);
        if ($status !== $before['status']) send_template_email('account_status_changed', $email, ['name' => $name, 'status' => $status]);
        $after = ['name' => $name, 'email' => $email, 'role' => $role, 'city_id' => $cityId, 'stage_id' => $stageId, 'status' => $status];
        $changes = array_keys(array_filter($after, fn($v, $f) => (string) $before[$f] !== (string) $v, ARRAY_FILTER_USE_BOTH));
        audit_log('UPDATE', 'user', (string) $editId, 'Changed: ' . ($changes ? implode(', ', $changes) : 'nothing') . ($password !== '' ? ', password set by admin' : ''));
        flash('success', 'User saved.');
        redirect('/pages/superadmin/users.php' . query_with([]));
    } elseif (!$errors) {
        $newId = account_create(['name' => $name, 'email' => $email, 'phone' => $phone, 'role' => $role, 'city_id' => $cityId, 'stage_id' => $stageId, 'agency_name' => $agency]);
        if (is_string($newId)) { $errors[] = $newId; }
        else {
            if ($status === 'suspended') runQuery("UPDATE users SET status = 'suspended' WHERE id = ?", [$newId]);
            if ($password !== '') { set_user_password($newId, $password); $msg = 'User created with the password you set.'; }
            else $msg = account_send_invite($newId) ? 'User created. A link to set their password was emailed to ' . $email . '.' : 'User created, but the invite email could not be sent. Use "Email a password link" once email works.';
            audit_log('CREATE', 'user', (string) $newId, 'User created as ' . $role);
            flash('success', $msg);
            redirect('/pages/superadmin/users.php');
        }
    }
}

$active = 'users';
$search = trim($_GET['q'] ?? '');
$filterCity = $_GET['city'] ?? '';
$filterRole = $_GET['role'] ?? '';

$sql = "FROM users u LEFT JOIN cities c ON c.id = u.city_id LEFT JOIN stages s ON s.id = u.stage_id WHERE u.deleted_at IS NULL";
$params = [];
if ($search !== '') { $sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($filterCity !== '') { $sql .= " AND u.city_id = ?"; $params[] = $filterCity; }
if ($filterRole !== '') { $sql .= " AND u.role = ?"; $params[] = $filterRole; }
$filterStatus = in_array($_GET['status'] ?? '', ['active', 'suspended'], true) ? $_GET['status'] : '';
if ($filterStatus !== '') { $sql .= " AND u.status = ?"; $params[] = $filterStatus; }
$pg = paginate((int) fetchValue("SELECT COUNT(*) $sql", $params), 25);
$users = fetchAll("SELECT u.id, u.name, u.email, u.phone, u.avatar_path, u.role, u.city_id, u.stage_id, u.agency_name, u.status, u.created_at, u.last_login_at,
    u.email_verified_at, u.two_factor_enabled, u.password_changed_at, c.name AS city_name, s.name AS stage_name $sql ORDER BY "
    . sort_sql(['name' => 'u.name', 'role' => 'u.role', 'created' => 'u.id', 'login' => 'u.last_login_at'], 'created') . " LIMIT {$pg['per_page']} OFFSET {$pg['offset']}", $params);
$stages = fetchAll("SELECT id, city_id, name FROM stages ORDER BY name");
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
    <?php $pageTitle = 'All Users | ' . get_setting('site_name', 'BodaERP'); require __DIR__ . '/../../partials/head-assets.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../partials/sidebar-superadmin.php'; ?>

<div class="main-content" id="mainContent">
    <?php ob_start(); ?>
<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#userModal" data-add-user><i class="fas fa-user-plus me-1" aria-hidden="true"></i>Add a user</button>
    <?php $headerActions = ob_get_clean();
    $pageTitle = 'Users';
    $pageSubtitle = 'Accounts for every role and city';
    require __DIR__ . '/../../partials/topheader.php'; ?>

    <div class="content-area">
        <form class="filter-bar mb-3" method="get" role="search" aria-label="Filter users">
                <input type="text" class="form-control form-control-sm" name="q" value="<?= h($search) ?>" placeholder="Name, email or phone" aria-label="Search users">
                <select class="form-select form-select-sm" name="city" aria-label="City" data-autosubmit>
                    <option value="">All Cities</option>
                    <?php foreach ($cities as $c): ?>
                        <option value="<?= h($c['id']) ?>" <?= $filterCity === $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="form-select form-select-sm" name="role" aria-label="Role" data-autosubmit>
                    <option value="">All Roles</option>
                    <option value="super_admin" <?= $filterRole === 'super_admin' ? 'selected' : '' ?>>Super admin</option>
                    <option value="city_admin" <?= $filterRole === 'city_admin' ? 'selected' : '' ?>>City admin</option>
                    <option value="chairperson" <?= $filterRole === 'chairperson' ? 'selected' : '' ?>>Chairperson</option>
                    <option value="rider" <?= $filterRole === 'rider' ? 'selected' : '' ?>>Rider</option>
                    <option value="government" <?= $filterRole === 'government' ? 'selected' : '' ?>>Government</option>
                </select>
                <select class="form-select form-select-sm" name="status" data-autosubmit aria-label="Status">
                    <option value="">Any status</option>
                    <option value="active"<?= $filterStatus === 'active' ? ' selected' : '' ?>>Active</option>
                    <option value="suspended"<?= $filterStatus === 'suspended' ? ' selected' : '' ?>>Suspended</option>
                </select>
                <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="fas fa-magnifying-glass me-1" aria-hidden="true"></i>Search</button>
            </form>
        <?php foreach (flash_take() as [$ft, $fm]): ?><div class="alert alert-<?= $ft === 'error' ? 'danger' : 'success' ?>" role="status"><?= h($fm) ?></div><?php endforeach; ?>
        <?php if ($errors): ?>
            <div class="alert alert-danger" data-keep><?php foreach ($errors as $e) echo h($e) . '<br>'; ?></div>
        <?php endif; ?>

        <div class="row g-3 row-cols-2 row-cols-xl-4 mb-4">
            <div class="col"><div class="stat-hero-card tone-blue"><div class="stat-hero-icon"><i class="fas fa-users"></i></div><div><div class="stat-hero-value"><?= number_format($totalUsersAll) ?></div><div class="stat-hero-label">Total users</div></div></div></div>
            <div class="col"><div class="stat-hero-card tone-green"><div class="stat-hero-icon"><i class="fas fa-user-check"></i></div><div><div class="stat-hero-value"><?= number_format($activeUsersAll) ?></div><div class="stat-hero-label">Active</div></div></div></div>
            <div class="col"><div class="stat-hero-card tone-red"><div class="stat-hero-icon"><i class="fas fa-user-slash"></i></div><div><div class="stat-hero-value"><?= number_format($suspendedUsersAll) ?></div><div class="stat-hero-label">Suspended</div></div></div></div>
            <div class="col"><div class="stat-hero-card tone-dark"><div class="stat-hero-icon"><i class="fas fa-user-shield"></i></div><div><div class="stat-hero-value"><?= number_format($adminUsersAll) ?></div><div class="stat-hero-label">Admins</div></div></div></div>
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
                        <thead><tr><th><?= sort_link('name', 'User', 'created') ?></th><th><?= sort_link('role', 'Role', 'created') ?></th><th>City / stage</th><th>Phone</th><th>Status</th><th><?= sort_link('login', 'Last sign-in', 'created') ?></th><th class="no-export">Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($users as $u): $sc = ['active'=>'success','suspended'=>'secondary']; ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <?= person_avatar($u['avatar_path'] ?? null, $u['name']) ?>
                                        <div><strong style="font-size:0.85rem;"><?= h($u['name']) ?></strong><br><small class="text-muted"><?= h($u['email']) ?></small>
                                            <?= $u['email_verified_at'] ? '' : ' <span class="badge bg-light text-dark border" title="Email not confirmed">unconfirmed</span>' ?><?= (int) $u['two_factor_enabled'] ? ' <span class="badge bg-light text-success border">2-step</span>' : '' ?><?= $u['password_changed_at'] || $u['last_login_at'] ? '' : ' <span class="badge bg-light text-warning border" title="The person has not set a password yet">invite not accepted</span>' ?></div>
                                    </div>
                                </td>
                                <td><?php if ($u['role'] === 'government'): ?><span class="badge" style="font-size:0.65rem;background:#b45309;">Government</span><?php else: ?><span class="badge bg-<?= $roleColors[$u['role']] ?? 'secondary' ?>" style="font-size:0.65rem;"><?= role_label($u['role']) ?></span><?php endif; ?></td>
                                <td><?= $u['role'] === 'government' ? h($u['agency_name'] ?: 'National') : ($u['city_name'] ? h($u['city_name']) : '<span class="text-muted">Platform</span>') ?><?= $u['stage_name'] ? '<div class="small text-muted">' . h($u['stage_name']) . '</div>' : '' ?></td>
                                <td><?= h($u['phone'] ?: '–') ?></td>
                                <td><span class="badge bg-<?= $sc[$u['status']] ?? 'secondary' ?>"><?= h(ucfirst(str_replace('_', ' ', $u['status']))) ?></span></td>
                                <td style="font-size:0.8rem;"><?= $u['last_login_at'] ? h(timeAgo($u['last_login_at'])) : '<span class="text-muted">Never</span>' ?></td>
                                <td class="no-export">
                                    <button class="btn btn-sm btn-outline-primary" data-edit-user="<?= (int) $u['id'] ?>" aria-label="Edit <?= h($u['name']) ?>"><i class="fas fa-edit"></i></button>
                                    <form method="post" class="d-inline" data-confirm="Email <?= h($u['email']) ?> a link to set a new password?" data-confirm-ok="Send link">
                                        <?= csrf_field() ?><input type="hidden" name="action" value="send_link"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary" title="Email a password link" aria-label="Email <?= h($u['name']) ?> a password link"><i class="fas fa-key"></i></button>
                                    </form>
                                    <form method="post" class="d-inline" data-confirm="Sign <?= h($u['name']) ?> out of every device?" data-confirm-ok="Sign out">
                                        <?= csrf_field() ?><input type="hidden" name="action" value="sign_out"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary" title="Sign out everywhere" aria-label="Sign <?= h($u['name']) ?> out everywhere"><i class="fas fa-right-from-bracket"></i></button>
                                    </form>
                                    <form method="post" class="d-inline" data-confirm="Delete <?= h($u['name']) ?>? They are signed out and can no longer sign in.">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Delete <?= h($u['name']) ?>" title="Delete"><i class="fas fa-trash" aria-hidden="true"></i></button>
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
                        <p>No account matches. Clear the filters, or add a user.</p>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="mt-3"><?= render_pagination($pg) ?></div>
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
                <div class="modal-header" style="background:var(--primary-blue);color:white;border-bottom:none;">
                    <h5 class="modal-title fw-bold" id="userModalTitle">Add user</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <?php if ($errors): ?><div class="alert alert-danger" role="alert"><?= implode('<br>', array_map('h', $errors)) ?></div><?php endif; ?>
                    <div class="row g-3">
                        <div class="col-12"><label for="userName" class="form-label fw-semibold">Full name <span class="text-danger">*</span></label><input type="text" class="form-control" name="name" id="userName" placeholder="Full name" required></div>
                        <div class="col-md-6"><label for="userEmail" class="form-label fw-semibold">Email <span class="text-danger">*</span></label><input type="email" class="form-control" name="email" id="userEmail" placeholder="email@example.com" required></div>
                        <div class="col-md-6"><label for="userPhone" class="form-label fw-semibold">Phone</label><input type="text" class="form-control" name="phone" id="userPhone" placeholder="+256 7xx xxx xxx"></div>
                        <div class="col-md-6">
                            <label for="userRole" class="form-label fw-semibold">Role <span class="text-danger">*</span></label>
                            <select class="form-select" name="role" id="userRole">
                                <option value="super_admin">Super admin</option><option value="city_admin">City admin</option><option value="chairperson">Chairperson</option><option value="rider">Rider</option><option value="government">Government (national portal)</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="cityFieldWrap">
                            <label for="userCity" class="form-label fw-semibold">City <span class="text-danger">*</span></label>
                            <select class="form-select" name="city_id" id="userCity">
                                <?php foreach ($cities as $c): ?><option value="<?= h($c['id']) ?>"><?= h($c['name']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 d-none" id="stageFieldWrap">
                            <label class="form-label fw-semibold" for="userStage">Stage <span class="text-danger" id="stageRequired">*</span></label>
                            <select class="form-select" name="stage_id" id="userStage"><option value="">No stage</option>
                                <?php foreach ($stages as $st): ?><option value="<?= (int) $st['id'] ?>" data-city="<?= h($st['city_id']) ?>"><?= h($st['name']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 d-none" id="agencyFieldWrap">
                            <label for="userAgency" class="form-label fw-semibold">Agency / ministry <span class="text-danger">*</span></label>
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
                        <div class="col-md-6"><label class="form-label fw-semibold" for="userPassword">Password <span class="text-muted fw-normal">(optional)</span></label><input type="password" class="form-control" name="password" id="userPassword" autocomplete="new-password" aria-describedby="pwHelp"><div class="form-text" id="pwHelp">Leave empty to email the person a link to set their own password (recommended).</div></div>
                        <div class="col-md-6"><label for="userStatus" class="form-label fw-semibold">Status</label><select class="form-select" name="status" id="userStatus"><option value="active">Active</option><option value="suspended">Suspended</option></select></div>
                    </div>
                </div>
                <div class="modal-footer border-0 pb-3">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save user</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../partials/scripts-footer.php'; ?>
<script nonce="<?= csp_nonce() ?>">
    const usersData = <?= js_json(array_column($users, null, 'id'), JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    const posted = <?= js_json($errors ? array_intersect_key($_POST, array_flip(['edit_user_id', 'name', 'email', 'phone', 'role', 'city_id', 'stage_id', 'agency_name', 'status'])) : null, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    const $ = id => document.getElementById(id);

    function toggleFields() {
        const role = $('userRole').value;
        $('cityFieldWrap').classList.toggle('d-none', role === 'super_admin' || role === 'government');
        $('agencyFieldWrap').classList.toggle('d-none', role !== 'government');
        $('stageFieldWrap').classList.toggle('d-none', role !== 'chairperson' && role !== 'rider');
        $('stageRequired').classList.toggle('d-none', role !== 'chairperson');
        const city = $('userCity').value;
        $('userStage').querySelectorAll('option[data-city]').forEach(o => { o.hidden = o.dataset.city !== city; if (o.hidden && o.selected) $('userStage').value = ''; });
    }

    function fill(u, title) {
        $('userModalTitle').innerHTML = title;
        $('editUserId').value = u.edit_user_id || u.id || '';
        $('userName').value = u.name || '';
        $('userEmail').value = u.email || '';
        $('userPhone').value = u.phone || '';
        $('userRole').value = u.role || 'city_admin';
        $('userCity').value = u.city_id || $('userCity').value;
        $('userAgency').value = u.agency_name || '';
        $('userStatus').value = u.status || 'active';
        $('userPassword').value = '';
        toggleFields();
        $('userStage').value = u.stage_id || '';
    }

    $('userRole').addEventListener('change', toggleFields);
    $('userCity').addEventListener('change', toggleFields);
    document.querySelector('[data-add-user]').addEventListener('click', () => fill({}, '<i class="fas fa-user-plus me-2"></i>Add User'));
    document.querySelectorAll('[data-edit-user]').forEach(btn => btn.addEventListener('click', () => {
        const u = usersData[btn.dataset.editUser];
        if (!u) return;
        fill(u, '<i class="fas fa-user-edit me-2"></i>Edit User');
        bootstrap.Modal.getOrCreateInstance($('userModal')).show();
    }));
    if (posted) { fill(posted, posted.edit_user_id ? '<i class="fas fa-user-edit me-2"></i>Edit User' : '<i class="fas fa-user-plus me-2"></i>Add User'); bootstrap.Modal.getOrCreateInstance($('userModal')).show(); }
</script>
</body>
</html>
