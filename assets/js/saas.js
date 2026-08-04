// ============================================================
// BodaERP SaaS - Multi-Tenant Data Layer
// All tenant data lives here until a real backend is connected.
// Replace localStorage calls with API calls when backend is ready.
// ============================================================

const BodaERP = {

    // ── CONSTANTS ────────────────────────────────────────────
    STORAGE_KEYS: {
        CURRENT_USER: 'bodaerp_user',
        TENANTS:      'bodaerp_tenants',
        USERS:        'bodaerp_users',
        STAGES:       'bodaerp_stages',
        RIDERS:       'bodaerp_riders',
        PAYMENTS:     'bodaerp_payments',
    },

    ROLES: {
        SUPER_ADMIN:  'super_admin',   // Kakebe Tech — manages all cities
        CITY_ADMIN:   'city_admin',    // City Council admin — manages own city
        CHAIRPERSON:  'chairperson',   // Stage chairperson — manages own stage
        RIDER:        'rider',         // Individual boda rider
    },

    // ── SEED DATA ─────────────────────────────────────────────
    /**
     * Called once on first load to populate demo data.
     */
    seed() {
        // Force re-seed if version changed
        if (localStorage.getItem('bodaerp_seeded') === 'v3') return;

        // ── Tenants (Cities) ──────────────────────────────────
        const tenants = [
            {
                id: 'LIR',
                name: 'Lira City',
                country: 'Uganda',
                currency: 'UGX',
                logo: '../../assets/images/lcc.png',
                annualFee: 50000,
                fiscalYear: '2026/2027',
                idPrefix: 'BODA-LIR',
                status: 'active',
                contactEmail: 'council@liracityuganda.go.ug',
                contactPhone: '+256 473 420 123',
                address: 'Lira City Council, Parliament Avenue, Lira, Uganda',
                paymentGateway: 'MTN MoMo',
                smsGateway: "Africa's Talking",
                revenueSplit: {
                    city:        60,
                    association: 26,
                    platform:    14,   // Kakebe Tech
                },
                complianceTarget: 70,
                createdAt: '2025-01-01',
                createdBy: 'super_admin_001',
            },
            {
                id: 'GUL',
                name: 'Gulu City',
                country: 'Uganda',
                currency: 'UGX',
                logo: '../../assets/images/logo.png',
                annualFee: 45000,
                fiscalYear: '2026/2027',
                idPrefix: 'BODA-GUL',
                status: 'active',
                contactEmail: 'council@gulucity.go.ug',
                contactPhone: '+256 471 432 456',
                address: 'Gulu City Council, Gulu, Uganda',
                paymentGateway: 'Airtel Money',
                smsGateway: "Africa's Talking",
                revenueSplit: {
                    city:        55,
                    association: 30,
                    platform:    15,
                },
                complianceTarget: 70,
                createdAt: '2025-03-01',
                createdBy: 'super_admin_001',
            },
            {
                id: 'KLA',
                name: 'Kampala City',
                country: 'Uganda',
                currency: 'UGX',
                logo: '../../assets/images/logo.png',
                annualFee: 60000,
                fiscalYear: '2026/2027',
                idPrefix: 'BODA-KLA',
                status: 'active',
                contactEmail: 'council@kcca.go.ug',
                contactPhone: '+256 417 123 456',
                address: 'KCCA, City Square, Kampala, Uganda',
                paymentGateway: 'MTN MoMo',
                smsGateway: "Africa's Talking",
                revenueSplit: {
                    city:        65,
                    association: 22,
                    platform:    13,
                },
                complianceTarget: 75,
                createdAt: '2025-06-01',
                createdBy: 'super_admin_001',
            },
            {
                id: 'MBA',
                name: 'Mbarara City',
                country: 'Uganda',
                currency: 'UGX',
                logo: '../../assets/images/logo.png',
                annualFee: 48000,
                fiscalYear: '2026/2027',
                idPrefix: 'BODA-MBA',
                status: 'pending',
                contactEmail: 'council@mbararacity.go.ug',
                contactPhone: '+256 485 456 789',
                address: 'Mbarara City Council, Mbarara, Uganda',
                paymentGateway: 'Both',
                smsGateway: "Africa's Talking",
                revenueSplit: {
                    city:        58,
                    association: 28,
                    platform:    14,
                },
                complianceTarget: 70,
                createdAt: '2026-01-15',
                createdBy: 'super_admin_001',
            },
        ];

        // ── Users ─────────────────────────────────────────────
        const users = [
            // Super Admin (platform-wide)
            {
                id: 'super_admin_001',
                name: 'Kakebe Admin',
                email: 'admin@bodaerp.com',
                phone: '+256 700 000 001',
                password: 'admin123',
                role: BodaERP.ROLES.SUPER_ADMIN,
                tenantId: null,
                stageId: null,
                avatar: '',
                status: 'active',
                createdAt: '2024-01-01',
            },
            // City Admins
            {
                id: 'city_lira_001',
                name: 'Lira City Council',
                email: 'council@liracityuganda.go.ug',
                phone: '+256 473 420 123',
                password: 'lira2025',
                role: BodaERP.ROLES.CITY_ADMIN,
                tenantId: 'LIR',
                stageId: null,
                avatar: '',
                status: 'active',
                createdAt: '2025-01-01',
            },
            {
                id: 'city_gulu_001',
                name: 'Gulu City Council',
                email: 'council@gulucity.go.ug',
                phone: '+256 471 432 456',
                password: 'gulu2025',
                role: BodaERP.ROLES.CITY_ADMIN,
                tenantId: 'GUL',
                stageId: null,
                avatar: '',
                status: 'active',
                createdAt: '2025-03-01',
            },
            {
                id: 'city_kla_001',
                name: 'Kampala City Council',
                email: 'council@kcca.go.ug',
                phone: '+256 417 123 456',
                password: 'kampala2025',
                role: BodaERP.ROLES.CITY_ADMIN,
                tenantId: 'KLA',
                stageId: null,
                avatar: '',
                status: 'active',
                createdAt: '2025-06-01',
            },
            {
                id: 'city_mba_001',
                name: 'Mbarara City Council',
                email: 'council@mbararacity.go.ug',
                phone: '+256 485 456 789',
                password: 'mbarara2025',
                role: BodaERP.ROLES.CITY_ADMIN,
                tenantId: 'MBA',
                stageId: null,
                avatar: '',
                status: 'active',
                createdAt: '2026-01-15',
            },
            // Chairpersons (Lira)
            {
                id: 'chair_lir_001',
                name: 'Ocen Patrick',
                email: 'ocen@railway.lira.ug',
                phone: '+256 772 111 001',
                password: 'chair123',
                role: BodaERP.ROLES.CHAIRPERSON,
                tenantId: 'LIR',
                stageId: 'LIR-STG-001',
                avatar: '',
                status: 'active',
                createdAt: '2025-01-15',
            },
            {
                id: 'chair_lir_002',
                name: 'Opio John',
                email: 'opio@market.lira.ug',
                phone: '+256 772 111 002',
                password: 'chair123',
                role: BodaERP.ROLES.CHAIRPERSON,
                tenantId: 'LIR',
                stageId: 'LIR-STG-002',
                avatar: '',
                status: 'active',
                createdAt: '2025-01-15',
            },
            // Chairperson (Gulu)
            {
                id: 'chair_gul_001',
                name: 'Komakech Denis',
                email: 'komakech@gulumain.gulu.ug',
                phone: '+256 772 222 001',
                password: 'chair123',
                role: BodaERP.ROLES.CHAIRPERSON,
                tenantId: 'GUL',
                stageId: 'GUL-STG-001',
                avatar: '',
                status: 'active',
                createdAt: '2025-03-10',
            },
            // Chairperson (Kampala)
            {
                id: 'chair_kla_001',
                name: 'Nakato Sarah',
                email: 'nakato@nakasero.kla.ug',
                phone: '+256 772 333 001',
                password: 'chair123',
                role: BodaERP.ROLES.CHAIRPERSON,
                tenantId: 'KLA',
                stageId: 'KLA-STG-001',
                avatar: '',
                status: 'active',
                createdAt: '2025-06-10',
            },
            // Chairperson (Mbarara)
            {
                id: 'chair_mba_001',
                name: 'Tumwine Edward',
                email: 'tumwine@mbararamain.mba.ug',
                phone: '+256 772 444 001',
                password: 'chair123',
                role: BodaERP.ROLES.CHAIRPERSON,
                tenantId: 'MBA',
                stageId: 'MBA-STG-001',
                avatar: '',
                status: 'active',
                createdAt: '2026-01-20',
            },
            // Rider (Lira)
            {
                id: 'rider_lir_001',
                name: 'Akello James',
                email: 'akello@bodaerp.com',
                phone: '+256 772 123 456',
                password: 'rider123',
                role: BodaERP.ROLES.RIDER,
                tenantId: 'LIR',
                stageId: 'LIR-STG-001',
                riderId: 'BODA-LIR-004521',
                avatar: '',
                status: 'active',
                createdAt: '2025-02-01',
            },
            // Rider (Gulu)
            {
                id: 'rider_gul_001',
                name: 'Aciro Grace',
                email: 'aciro@bodaerp.com',
                phone: '+256 772 222 456',
                password: 'rider123',
                role: BodaERP.ROLES.RIDER,
                tenantId: 'GUL',
                stageId: 'GUL-STG-001',
                riderId: 'BODA-GUL-001042',
                avatar: '',
                status: 'active',
                createdAt: '2025-03-20',
            },
            // Rider (Kampala)
            {
                id: 'rider_kla_001',
                name: 'Mukasa Ronald',
                email: 'mukasa@bodaerp.com',
                phone: '+256 772 333 456',
                password: 'rider123',
                role: BodaERP.ROLES.RIDER,
                tenantId: 'KLA',
                stageId: 'KLA-STG-001',
                riderId: 'BODA-KLA-002310',
                avatar: '',
                status: 'active',
                createdAt: '2025-06-20',
            },
            // Rider (Mbarara)
            {
                id: 'rider_mba_001',
                name: 'Kyomuhendo Betty',
                email: 'kyomuhendo@bodaerp.com',
                phone: '+256 772 444 456',
                password: 'rider123',
                role: BodaERP.ROLES.RIDER,
                tenantId: 'MBA',
                stageId: 'MBA-STG-001',
                riderId: 'BODA-MBA-000112',
                avatar: '',
                status: 'active',
                createdAt: '2026-01-25',
            },
        ];

        // ── Stages ────────────────────────────────────────────
        const stages = [
            { id: 'LIR-STG-001', tenantId: 'LIR', name: 'Railway Stage',     chairpersonId: 'chair_lir_001', riders: 87,  compliance: 85 },
            { id: 'LIR-STG-002', tenantId: 'LIR', name: 'Market Stage',      chairpersonId: 'chair_lir_002', riders: 81,  compliance: 72 },
            { id: 'LIR-STG-003', tenantId: 'LIR', name: 'Hospital Stage',    chairpersonId: null,             riders: 65,  compliance: 65 },
            { id: 'LIR-STG-004', tenantId: 'LIR', name: 'Town Stage',        chairpersonId: null,             riders: 60,  compliance: 58 },
            { id: 'LIR-STG-005', tenantId: 'LIR', name: 'Bazaar Stage',      chairpersonId: null,             riders: 62,  compliance: 45 },
            { id: 'GUL-STG-001', tenantId: 'GUL', name: 'Gulu Main Stage',   chairpersonId: 'chair_gul_001', riders: 120, compliance: 78 },
            { id: 'GUL-STG-002', tenantId: 'GUL', name: 'Layibi Stage',      chairpersonId: null,             riders: 95,  compliance: 68 },
            { id: 'KLA-STG-001', tenantId: 'KLA', name: 'Nakasero Stage',    chairpersonId: 'chair_kla_001', riders: 340, compliance: 82 },
            { id: 'KLA-STG-002', tenantId: 'KLA', name: 'Kalerwe Stage',     chairpersonId: null,             riders: 280, compliance: 74 },
            { id: 'MBA-STG-001', tenantId: 'MBA', name: 'Mbarara Main Stage', chairpersonId: 'chair_mba_001', riders: 0,   compliance: 0 },
        ];

        BodaERP.storage.set(BodaERP.STORAGE_KEYS.TENANTS, tenants);
        BodaERP.storage.set(BodaERP.STORAGE_KEYS.USERS,   users);
        BodaERP.storage.set(BodaERP.STORAGE_KEYS.STAGES,  stages);

        localStorage.setItem('bodaerp_seeded', 'v3');
    },

    // ── STORAGE HELPERS ────────────────────────────────────────
    storage: {
        get(key) {
            try { return JSON.parse(localStorage.getItem(key)) || []; }
            catch { return []; }
        },
        set(key, val) {
            localStorage.setItem(key, JSON.stringify(val));
        },
        getObj(key) {
            try { return JSON.parse(localStorage.getItem(key)) || {}; }
            catch { return {}; }
        },
    },

    // ── AUTH ──────────────────────────────────────────────────
    auth: {
        login(email, password) {
            const users = BodaERP.storage.get(BodaERP.STORAGE_KEYS.USERS);
            const user  = users.find(u => u.email === email && u.password === password && u.status === 'active');
            if (!user) return { success: false, message: 'Invalid email or password.' };

            const tenant = user.tenantId ? BodaERP.tenants.getById(user.tenantId) : null;
            const session = {
                id:       user.id,
                name:     user.name,
                email:    user.email,
                role:     user.role,
                tenantId: user.tenantId,
                stageId:  user.stageId,
                riderId:  user.riderId || null,
                avatar:   user.avatar || '',
                tenant:   tenant ? { id: tenant.id, name: tenant.name, logo: tenant.logo, idPrefix: tenant.idPrefix } : null,
                loginTime: new Date().toISOString(),
            };
            localStorage.setItem(BodaERP.STORAGE_KEYS.CURRENT_USER, JSON.stringify(session));
            return { success: true, session };
        },

        logout() {
            localStorage.removeItem(BodaERP.STORAGE_KEYS.CURRENT_USER);
            sessionStorage.clear();
            window.location.href = BodaERP.auth._loginUrl();
        },

        getSession() {
            try { return JSON.parse(localStorage.getItem(BodaERP.STORAGE_KEYS.CURRENT_USER)); }
            catch { return null; }
        },

        require(allowedRoles) {
            const s = BodaERP.auth.getSession();
            if (!s) { window.location.href = BodaERP.auth._loginUrl(); return null; }
            if (allowedRoles && !allowedRoles.includes(s.role)) {
                BodaERP.auth._redirectToDashboard(s.role);
                return null;
            }
            return s;
        },

        _pathPrefix() {
            const dirs = Math.max((window.location.pathname.match(/\//g) || []).length - 1, 0);
            return '../'.repeat(dirs);
        },

        _loginUrl() {
            return BodaERP.auth._pathPrefix() + 'login.html';
        },

        _redirectToDashboard(role) {
            const base = BodaERP.auth._pathPrefix();
            const map   = {
                [BodaERP.ROLES.SUPER_ADMIN]:  base + 'pages/superadmin/dashboard.html',
                [BodaERP.ROLES.CITY_ADMIN]:   base + 'pages/citycouncil/dashboard.html',
                [BodaERP.ROLES.CHAIRPERSON]:  base + 'pages/chairperson/dashboard.html',
                [BodaERP.ROLES.RIDER]:        base + 'pages/rider/dashboard.html',
            };
            window.location.href = map[role] || BodaERP.auth._loginUrl();
        },

        dashboardUrl(role) {
            const map = {
                [BodaERP.ROLES.SUPER_ADMIN]:  'pages/superadmin/dashboard.html',
                [BodaERP.ROLES.CITY_ADMIN]:   'pages/citycouncil/dashboard.html',
                [BodaERP.ROLES.CHAIRPERSON]:  'pages/chairperson/dashboard.html',
                [BodaERP.ROLES.RIDER]:        'pages/rider/dashboard.html',
            };
            return map[role] || 'login.html';
        },
    },

    // ── TENANT CRUD ───────────────────────────────────────────
    tenants: {
        getAll()         { return BodaERP.storage.get(BodaERP.STORAGE_KEYS.TENANTS); },
        getById(id)      { return this.getAll().find(t => t.id === id) || null; },
        getActive()      { return this.getAll().filter(t => t.status === 'active'); },

        create(data) {
            const tenants = this.getAll();
            if (tenants.find(t => t.id === data.id))
                return { success: false, message: 'City ID already exists.' };
            const tenant = { ...data, createdAt: new Date().toISOString().split('T')[0] };
            tenants.push(tenant);
            BodaERP.storage.set(BodaERP.STORAGE_KEYS.TENANTS, tenants);
            return { success: true, tenant };
        },

        update(id, data) {
            const tenants = this.getAll();
            const idx = tenants.findIndex(t => t.id === id);
            if (idx === -1) return { success: false, message: 'City not found.' };
            tenants[idx] = { ...tenants[idx], ...data };
            BodaERP.storage.set(BodaERP.STORAGE_KEYS.TENANTS, tenants);
            return { success: true, tenant: tenants[idx] };
        },

        delete(id) {
            let tenants = this.getAll();
            tenants = tenants.filter(t => t.id !== id);
            BodaERP.storage.set(BodaERP.STORAGE_KEYS.TENANTS, tenants);
            return { success: true };
        },

        // Stats helpers
        getRiderCount(tenantId) {
            return BodaERP.storage.get(BodaERP.STORAGE_KEYS.STAGES)
                .filter(s => s.tenantId === tenantId)
                .reduce((sum, s) => sum + (s.riders || 0), 0);
        },
        getStageCount(tenantId) {
            return BodaERP.storage.get(BodaERP.STORAGE_KEYS.STAGES)
                .filter(s => s.tenantId === tenantId).length;
        },
    },

    // ── USER CRUD ─────────────────────────────────────────────
    users: {
        getAll()            { return BodaERP.storage.get(BodaERP.STORAGE_KEYS.USERS); },
        getById(id)         { return this.getAll().find(u => u.id === id) || null; },
        getByTenant(tid)    { return this.getAll().filter(u => u.tenantId === tid); },
        getByRole(role)     { return this.getAll().filter(u => u.role === role); },
        getByTenantRole(tid, role) {
            return this.getAll().filter(u => u.tenantId === tid && u.role === role);
        },

        create(data) {
            const users = this.getAll();
            if (users.find(u => u.email === data.email))
                return { success: false, message: 'Email already in use.' };
            const user = {
                ...data,
                id: data.role + '_' + Date.now(),
                createdAt: new Date().toISOString().split('T')[0],
            };
            users.push(user);
            BodaERP.storage.set(BodaERP.STORAGE_KEYS.USERS, users);
            return { success: true, user };
        },

        update(id, data) {
            const users = this.getAll();
            const idx = users.findIndex(u => u.id === id);
            if (idx === -1) return { success: false, message: 'User not found.' };
            users[idx] = { ...users[idx], ...data };
            BodaERP.storage.set(BodaERP.STORAGE_KEYS.USERS, users);
            return { success: true, user: users[idx] };
        },

        delete(id) {
            let users = this.getAll().filter(u => u.id !== id);
            BodaERP.storage.set(BodaERP.STORAGE_KEYS.USERS, users);
            return { success: true };
        },
    },

    // ── STAGE CRUD ────────────────────────────────────────────
    stages: {
        getAll()         { return BodaERP.storage.get(BodaERP.STORAGE_KEYS.STAGES); },
        getByTenant(tid) { return this.getAll().filter(s => s.tenantId === tid); },
        getById(id)      { return this.getAll().find(s => s.id === id) || null; },

        create(tenantId, data) {
            const stages = this.getAll();
            const id = tenantId + '-STG-' + String(Date.now()).slice(-4);
            const stage = { id, tenantId, ...data, createdAt: new Date().toISOString().split('T')[0] };
            stages.push(stage);
            BodaERP.storage.set(BodaERP.STORAGE_KEYS.STAGES, stages);
            return { success: true, stage };
        },

        update(id, data) {
            const stages = this.getAll();
            const idx = stages.findIndex(s => s.id === id);
            if (idx === -1) return { success: false };
            stages[idx] = { ...stages[idx], ...data };
            BodaERP.storage.set(BodaERP.STORAGE_KEYS.STAGES, stages);
            return { success: true, stage: stages[idx] };
        },
    },

    // ── UI HELPERS ────────────────────────────────────────────
    ui: {
        /**
         * Inject tenant branding into sidebar header.
         * Call on every protected page after auth check.
         */
        applyTenantBranding(session) {
            if (!session) return;

            // Sidebar header logo
            const sidebarLogo = document.querySelector('.sidebar .sidebar-header img');
            if (sidebarLogo && session.tenant?.logo) {
                sidebarLogo.src   = session.tenant.logo;
                sidebarLogo.alt   = session.tenant.name;
                sidebarLogo.onerror = () => { sidebarLogo.style.display = 'none'; };
            }

            // Sidebar header label
            const sidebarLabel = document.querySelector('.sidebar .sidebar-header small');
            if (sidebarLabel && session.tenant?.name) {
                sidebarLabel.textContent = session.tenant.name;
            }

            // Top-right user name
            const nameEl = document.querySelector('.top-header .user-info .fw-bold');
            if (nameEl) nameEl.textContent = session.name;

            // Top-right role/city label
            const roleEl = document.querySelector('.top-header .user-info .text-muted');
            if (roleEl) {
                const roleLabel = {
                    [BodaERP.ROLES.SUPER_ADMIN]:  'Kakebe Tech · Platform',
                    [BodaERP.ROLES.CITY_ADMIN]:   session.tenant?.name || 'City Council',
                    [BodaERP.ROLES.CHAIRPERSON]:  'Stage Chairperson',
                    [BodaERP.ROLES.RIDER]:        'Rider',
                };
                roleEl.textContent = roleLabel[session.role] || session.role;
            }

            // Page <title>
            if (session.tenant?.name) {
                document.title = document.title.replace('BodaERP', 'BodaERP · ' + session.tenant.name);
            }
        },

        /**
         * Populate a <select> with tenants.
         */
        populateTenantSelect(selectId, includeAll = true) {
            const sel = document.getElementById(selectId);
            if (!sel) return;
            const tenants = BodaERP.tenants.getActive();
            sel.innerHTML = includeAll ? '<option value="">All Cities</option>' : '<option value="">Select City</option>';
            tenants.forEach(t => {
                sel.innerHTML += `<option value="${t.id}">${t.name}</option>`;
            });
        },

        showToast(message, type = 'success') {
            const icons = { success: 'check-circle', error: 'times-circle', warning: 'exclamation-triangle', info: 'info-circle' };
            const colors = { success: '#198754', error: '#dc3545', warning: '#f59e0b', info: '#0d6efd' };
            const container = document.getElementById('toastContainer') || (() => {
                const c = document.createElement('div');
                c.id = 'toastContainer';
                c.style.cssText = 'position:fixed;top:20px;right:20px;z-index:99999;display:flex;flex-direction:column;gap:10px;';
                document.body.appendChild(c);
                return c;
            })();

            const toast = document.createElement('div');
            toast.style.cssText = `background:${colors[type]||colors.success};color:#fff;padding:12px 20px;border-radius:10px;box-shadow:0 6px 24px rgba(0,0,0,0.15);display:flex;align-items:center;gap:10px;font-size:0.88rem;font-weight:600;min-width:260px;max-width:360px;animation:slideInRight 0.3s ease;`;
            toast.innerHTML = `<i class="fas fa-${icons[type]||'info-circle'}"></i>${message}<button onclick="this.parentElement.remove()" style="background:none;border:none;color:rgba(255,255,255,0.7);cursor:pointer;margin-left:auto;font-size:1rem;">&times;</button>`;
            container.appendChild(toast);
            setTimeout(() => { toast.style.opacity='0'; toast.style.transition='opacity 0.3s'; setTimeout(()=>toast.remove(),300); }, 4000);
        },

        formatCurrency(amount, currency = 'UGX') {
            return currency + ' ' + Number(amount).toLocaleString('en-US');
        },

        /**
         * Render an ID card with tenant branding.
         */
        renderIDCard(rider, tenant) {
            return `
            <div class="id-card" id="idCardFront">
                <div class="id-header">
                    <div class="logo-wrapper">
                        <img src="${tenant.logo}" alt="${tenant.name}" onerror="this.style.display='none'">
                    </div>
                    <div class="header-text">
                        <p class="header-title">${tenant.name.toUpperCase()}</p>
                        <p class="header-sub">Boda Boda Operator ID Card</p>
                    </div>
                    <span class="header-year">${tenant.fiscalYear}</span>
                </div>
                <div class="id-body">
                    <div class="id-photo">
                        <img src="${rider.photo || tenant.logo.replace('lcc.png','default.jpg')}" alt="Photo"
                             onerror="this.src='../../assets/images/avatar-placeholder.png'">
                        <span class="photo-badge">PASS</span>
                    </div>
                    <div class="id-details">
                        <div class="detail-row"><strong>Name</strong><span class="value">${rider.fullName}</span></div>
                        <div class="detail-row"><strong>NIN</strong><span class="value">${rider.nin}</span></div>
                        <div class="detail-row"><strong>Stage</strong><span class="value">${rider.stage}</span></div>
                        <div class="detail-row"><strong>Bike</strong><span class="value">${rider.bikePlate}</span></div>
                        <div class="detail-row"><strong>Status</strong><span class="status-badge">● ACTIVE</span></div>
                    </div>
                </div>
                <div class="id-footer">
                    <div class="qr-section">
                        <div class="qr-code"><i class="fas fa-qrcode"></i></div>
                        <div class="expiry-info">
                            <small>Expiry Date</small>
                            <strong>${rider.expiryDate}</strong>
                        </div>
                    </div>
                    <div class="id-meta">
                        <span>ID: ${rider.idNumber}</span>
                        <span>Since: ${rider.memberSince}</span>
                    </div>
                </div>
            </div>`;
        },
    },
};

// ── AUTO-SEED ON LOAD ─────────────────────────────────────────
BodaERP.seed();

// ── BACKWARD COMPATIBILITY ────────────────────────────────────
// Pages that check `localStorage.getItem('bodaerp_user')` inline
// will still work because we use the same key. But if the session
// is in old format (no .id), we clear it to force re-login.
(function patchOldSessions() {
    const raw = localStorage.getItem('bodaerp_user');
    if (!raw) return;
    try {
        const s = JSON.parse(raw);
        if (!s.id || !s.email) {
            // old format — remove it so pages redirect to login
            localStorage.removeItem('bodaerp_user');
        }
    } catch(e) {
        localStorage.removeItem('bodaerp_user');
    }
})();
