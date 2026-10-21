// ============================================================
// BodaERP - Core JavaScript
// ============================================================

document.addEventListener('DOMContentLoaded', function() {
    
    // ----- Toggle Sidebar (Mobile) -----
    const toggleBtn = document.getElementById('toggleSidebar');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar.classList.toggle('open');
            if (overlay) overlay.classList.toggle('active');
        });
    }
    
    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        });
    }
    
    // Close sidebar on link click (mobile)
    document.querySelectorAll('.sidebar-menu a').forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 992) {
                sidebar.classList.remove('open');
                if (overlay) overlay.classList.remove('active');
            }
        });
    });

    // ----- Auto-hide alerts after 5 seconds -----
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });

    // ----- Tooltip Initialization -----
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(el => new bootstrap.Tooltip(el));

    // ----- Smooth Scroll -----
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href.length <= 1) return;
            const target = document.querySelector(href);
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    // ----- Dynamic Year in Footer -----
    document.querySelectorAll('.current-year').forEach(el => {
        el.textContent = new Date().getFullYear();
    });

    // ----- Character Counter for Textareas -----
    document.querySelectorAll('textarea[data-maxlength]').forEach(textarea => {
        const max = parseInt(textarea.dataset.maxlength);
        const counter = document.createElement('small');
        counter.className = 'text-muted d-block text-end mt-1';
        counter.textContent = `0 / ${max}`;
        textarea.parentNode.appendChild(counter);
        
        textarea.addEventListener('input', function() {
            const len = this.value.length;
            counter.textContent = `${len} / ${max}`;
            if (len > max) {
                counter.style.color = '#dc3545';
            } else {
                counter.style.color = '';
            }
        });
    });

    // ----- Password Toggle -----
    document.querySelectorAll('.toggle-password').forEach(btn => {
        btn.addEventListener('click', function() {
            const input = this.closest('.input-group').querySelector('input');
            if (input.type === 'password') {
                input.type = 'text';
                this.innerHTML = '<i class="fas fa-eye-slash"></i>';
            } else {
                input.type = 'password';
                this.innerHTML = '<i class="fas fa-eye"></i>';
            }
        });
    });

});

// ============================================================
// Utility Functions
// ============================================================

// Format Currency
function formatCurrency(amount) {
    return 'UGX ' + Number(amount).toLocaleString('en-US');
}

// Format Date
function formatDate(date) {
    const d = new Date(date);
    return d.toLocaleDateString('en-UG', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });
}

// Generate Random ID
function generateId(prefix = 'BODA') {
    const num = Math.floor(100000 + Math.random() * 900000);
    return `${prefix}-${num}`;
}

// Copy to Clipboard
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast('Copied to clipboard!', 'success');
    }).catch(() => {
        // Fallback
        const input = document.createElement('input');
        input.value = text;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
        showToast('Copied to clipboard!', 'success');
    });
}

// Show Toast Notification
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer') || createToastContainer();
    const toast = document.createElement('div');
    toast.className = `toast toast-${type} fade show`;
    toast.role = 'alert';
    toast.style.animation = 'slideInRight 0.4s ease forwards';
    toast.innerHTML = `
        <div class="toast-body d-flex align-items-center">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'times-circle' : 'info-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container';
    document.body.appendChild(container);
    return container;
}

// Confirm Action Modal
function confirmAction(message, callback) {
    // Simple confirmation
    if (confirm(message)) {
        callback();
    }
}

// ============================================================
// API Simulation (For Demo)
// ============================================================

const API = {
    // Simulated fetch
    get: function(endpoint) {
        return new Promise((resolve) => {
            setTimeout(() => {
                resolve({ data: { message: 'Data fetched successfully' } });
            }, 500);
        });
    },
    
    post: function(endpoint, data) {
        return new Promise((resolve) => {
            setTimeout(() => {
                resolve({ data: { message: 'Data saved successfully', ...data } });
            }, 600);
        });
    },
    
    put: function(endpoint, data) {
        return new Promise((resolve) => {
            setTimeout(() => {
                resolve({ data: { message: 'Data updated successfully', ...data } });
            }, 600);
        });
    },
    
    delete: function(endpoint) {
        return new Promise((resolve) => {
            setTimeout(() => {
                resolve({ data: { message: 'Data deleted successfully' } });
            }, 500);
        });
    }
};