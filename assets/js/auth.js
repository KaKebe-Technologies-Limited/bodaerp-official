// ============================================================
// BodaERP - auth.js
// Legacy compatibility shim. All real auth now lives in saas.js.
// This file is kept to avoid 404s on pages that still include it.
// ============================================================

// These wrappers call through to BodaERP (saas.js) if available,
// or fall back gracefully otherwise.

function checkAuth() {
    if (typeof BodaERP !== 'undefined') {
        return BodaERP.auth.getSession();
    }
    const raw = localStorage.getItem('bodaerp_user');
    if (!raw) { window.location.href = '../../login.html'; return null; }
    try { return JSON.parse(raw); } catch(e) { return null; }
}

function getCurrentUser() {
    if (typeof BodaERP !== 'undefined') return BodaERP.auth.getSession();
    try { return JSON.parse(localStorage.getItem('bodaerp_user')); } catch(e) { return null; }
}

function performLogout() {
    if (typeof BodaERP !== 'undefined') { BodaERP.auth.logout(); return; }
    localStorage.removeItem('bodaerp_user');
    sessionStorage.clear();
    window.location.href = '../../login.html';
}

function logoutUser() {
    const modal = document.getElementById('logoutConfirmModal');
    if (modal && typeof bootstrap !== 'undefined') {
        new bootstrap.Modal(modal).show();
    } else {
        if (confirm('Are you sure you want to logout?')) performLogout();
    }
}

function displayUserInfo() {
    const session = getCurrentUser();
    if (!session) return;
    const nameEl = document.querySelector('.top-header .user-info .fw-bold');
    const roleEl = document.querySelector('.top-header .user-info .text-muted');
    if (nameEl) nameEl.textContent = session.name || session.username || 'User';
    if (roleEl) {
        const labels = {
            super_admin: 'Platform Admin', city_admin: 'City Council',
            chairperson: 'Stage Chairperson', rider: 'Rider',
            super: 'Super Admin', stage: 'Chairperson', city: 'City Council'
        };
        roleEl.textContent = labels[session.role] || session.role || '';
    }
}

function setActiveNav() {
    const file = window.location.pathname.split('/').pop();
    document.querySelectorAll('.sidebar-menu li').forEach(li => {
        const a = li.querySelector('a');
        if (a && a.getAttribute('href') === file) li.classList.add('active');
        else li.classList.remove('active');
    });
}

// No auto-init that interferes with page logic
