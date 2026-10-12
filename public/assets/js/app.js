document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });

    const deleteButtons = document.querySelectorAll('.btn-delete');
    deleteButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            if (!confirm('Apakah Anda yakin ingin menghapus data ini?')) {
                e.preventDefault();
            }
        });
    });

    const sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });
    }

    // ============================================
    // SIDEBAR DROPDOWN / SUBMENU TOGGLE
    // ============================================
    const submenuToggles = document.querySelectorAll('.submenu-toggle');
    submenuToggles.forEach(function(toggle) {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const parent = this.closest('li.has-submenu');
            const isOpen = parent.classList.contains('open');

            // Tutup submenu lain (accordion)
            document.querySelectorAll('li.has-submenu.open').forEach(function(item) {
                if (item !== parent) item.classList.remove('open');
            });

            parent.classList.toggle('open', !isOpen);
        });
    });

    const searchInput = document.getElementById('tableSearch');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const table = this.closest('.table-container').querySelector('table');
            if (!table) return;
            const rows = table.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    }

    const statusBadges = document.querySelectorAll('.status-badge');
    statusBadges.forEach(badge => {
        const status = badge.textContent.trim().toLowerCase();
        const colors = {
            'pending': '#f39c12',
            'approved': '#2ecc71',
            'rejected': '#e74c3c',
            'draft': '#95a5a6',
            'verified': '#2ecc71',
            'hadir': '#2ecc71',
            'izin': '#f39c12',
            'sakit': '#3498db',
            'alpa': '#e74c3c',
            'active': '#2ecc71',
            'urgent': '#e74c3c',
            'submitted': '#3498db',
            'expired': '#95a5a6',
            'low': '#2ecc71',
            'medium': '#f39c12',
            'high': '#e74c3c'
        };
        badge.style.background = colors[status] || '#95a5a6';
        badge.style.color = '#fff';
        badge.style.padding = '4px 12px';
        badge.style.borderRadius = '20px';
        badge.style.fontSize = '12px';
        badge.style.fontWeight = '600';
        badge.style.display = 'inline-block';
    });

    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        const closeBtn = modal.querySelector('.modal-close');
        const overlay = modal.querySelector('.modal-overlay');
        const closeBtnAlt = modal.querySelector('.btn-close-modal');
        
        function closeModal() {
            modal.style.display = 'none';
        }
        
        if (closeBtn) closeBtn.addEventListener('click', closeModal);
        if (closeBtnAlt) closeBtnAlt.addEventListener('click', closeModal);
        if (overlay) overlay.addEventListener('click', closeModal);
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.style.display !== 'none') {
                closeModal();
            }
        });
    });

    const notifBadge = document.getElementById('notifBadge');
    if (notifBadge) {
        const count = parseInt(notifBadge.textContent) || 0;
        if (count === 0) {
            notifBadge.style.display = 'none';
        }
    }

    const presensiPage = document.querySelector('.presensi-page');
    if (presensiPage) {
        setInterval(() => {
            fetch('/simak_app/public/api/check_presensi.php')
                .then(response => response.json())
                .then(data => {
                    if (!data.active) {
                        location.reload();
                    }
                })
                .catch(() => {});
        }, 30000);
    }

    const deadlines = document.querySelectorAll('.deadline-countdown');
    deadlines.forEach(el => {
        const deadline = new Date(el.dataset.deadline);
        updateCountdown(el, deadline);
        setInterval(() => updateCountdown(el, deadline), 60000);
    });

    document.querySelectorAll('[data-tooltip]').forEach(el => {
        el.addEventListener('mouseenter', function(e) {
            const tooltip = document.createElement('div');
            tooltip.textContent = this.dataset.tooltip;
            tooltip.style.cssText = `
                position: fixed;
                background: #2c3e50;
                color: #fff;
                padding: 5px 12px;
                border-radius: 4px;
                font-size: 12px;
                z-index: 1000;
                pointer-events: none;
                white-space: nowrap;
                font-family: Arial, sans-serif;
            `;
            const rect = this.getBoundingClientRect();
            tooltip.style.left = (rect.left + rect.width / 2 - tooltip.offsetWidth / 2) + 'px';
            tooltip.style.top = (rect.top - 30) + 'px';
            document.body.appendChild(tooltip);
            this._tooltip = tooltip;
        });
        
        el.addEventListener('mouseleave', function() {
            if (this._tooltip) {
                this._tooltip.remove();
                delete this._tooltip;
            }
        });
    });

    document.querySelectorAll('.btn-print').forEach(btn => {
        btn.addEventListener('click', function() {
            window.print();
        });
    });

    document.querySelectorAll('.auto-submit').forEach(el => {
        el.addEventListener('change', function() {
            this.closest('form').submit();
        });
    });

    document.querySelectorAll('.confirm-action').forEach(el => {
        el.addEventListener('click', function(e) {
            const message = this.dataset.confirmMessage || 'Apakah Anda yakin?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });

    document.querySelectorAll('.toggle-password').forEach(el => {
        el.addEventListener('click', function() {
            const input = document.getElementById(this.dataset.target);
            if (input) {
                const type = input.type === 'password' ? 'text' : 'password';
                input.type = type;
                this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
            }
        });
    });

    document.querySelectorAll('.select-all').forEach(el => {
        el.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll(this.dataset.target || 'input[type="checkbox"]');
            checkboxes.forEach(cb => {
                cb.checked = this.checked;
            });
        });
    });

    document.querySelectorAll('.number-only').forEach(el => {
        el.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9.]/g, '');
        });
    });

    document.querySelectorAll('.capitalize').forEach(el => {
        el.addEventListener('input', function() {
            const words = this.value.split(' ');
            this.value = words.map(word => {
                return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
            }).join(' ');
        });
    });

    const flashMessages = document.querySelectorAll('.flash-message');
    flashMessages.forEach(msg => {
        setTimeout(() => {
            msg.style.transition = 'opacity 0.5s';
            msg.style.opacity = '0';
            setTimeout(() => msg.remove(), 500);
        }, 4000);
    });

    const numericInputs = document.querySelectorAll('input[type="number"]');
    numericInputs.forEach(input => {
        input.addEventListener('blur', function() {
            const min = parseFloat(this.min);
            const max = parseFloat(this.max);
            let val = parseFloat(this.value);
            if (!isNaN(min) && val < min) this.value = min;
            if (!isNaN(max) && val > max) this.value = max;
        });
    });

    const dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(input => {
        if (!input.value) {
            const today = new Date().toISOString().split('T')[0];
            input.setAttribute('max', today);
        }
    });

    const passwordStrength = document.querySelectorAll('.password-strength');
    passwordStrength.forEach(el => {
        const input = document.getElementById(el.dataset.target);
        if (input) {
            input.addEventListener('input', function() {
                const val = this.value;
                let strength = 0;
                if (val.length >= 8) strength++;
                if (/[a-z]/.test(val) && /[A-Z]/.test(val)) strength++;
                if (/\d/.test(val)) strength++;
                if (/[^a-zA-Z0-9]/.test(val)) strength++;
                
                const labels = ['Sangat Lemah', 'Lemah', 'Sedang', 'Kuat', 'Sangat Kuat'];
                const colors = ['#e74c3c', '#e67e22', '#f39c12', '#3498db', '#2ecc71'];
                el.textContent = labels[strength] || '';
                el.style.color = colors[strength] || '#95a5a6';
            });
        }
    });

    document.querySelectorAll('.tab-trigger').forEach(tab => {
        tab.addEventListener('click', function() {
            const target = this.dataset.target;
            const parent = this.closest('.tab-container');
            if (parent) {
                parent.querySelectorAll('.tab-content').forEach(content => {
                    content.style.display = 'none';
                });
                parent.querySelectorAll('.tab-trigger').forEach(t => {
                    t.classList.remove('active');
                });
                const content = document.getElementById(target);
                if (content) content.style.display = 'block';
                this.classList.add('active');
            }
        });
    });

    document.querySelectorAll('.sortable').forEach(th => {
        th.addEventListener('click', function() {
            const table = this.closest('table');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            const index = Array.from(this.parentElement.children).indexOf(this);
            const isAsc = this.dataset.order !== 'desc';
            
            rows.sort((a, b) => {
                const aVal = a.children[index].textContent.trim();
                const bVal = b.children[index].textContent.trim();
                const aNum = parseFloat(aVal);
                const bNum = parseFloat(bVal);
                
                if (!isNaN(aNum) && !isNaN(bNum)) {
                    return isAsc ? aNum - bNum : bNum - aNum;
                }
                return isAsc ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
            });
            
            rows.forEach(row => tbody.appendChild(row));
            this.dataset.order = isAsc ? 'desc' : 'asc';
            
            table.querySelectorAll('.sortable').forEach(th => {
                th.style.cursor = 'pointer';
                th.innerHTML = th.innerHTML.replace(/ [▲▼]/, '');
            });
            this.innerHTML += isAsc ? ' <i class="fas fa-sort-up"></i>' : ' <i class="fas fa-sort-down"></i>';
        });
    });
});

function updateCountdown(el, deadline) {
    const now = new Date();
    const diff = deadline - now;
    
    if (diff <= 0) {
        el.innerHTML = '<i class="fas fa-clock"></i> Expired';
        el.style.color = '#e74c3c';
        return;
    }
    
    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    
    let text = '';
    if (days > 0) text += days + 'd ';
    text += hours + 'h ' + minutes + 'm';
    
    el.innerHTML = '<i class="fas fa-hourglass-half"></i> ' + text;
    el.style.color = days < 1 ? '#e74c3c' : (days < 2 ? '#f39c12' : '#2ecc71');
}

function formatRupiah(amount) {
    return 'Rp ' + amount.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function formatDate(dateString) {
    const options = { day: 'numeric', month: 'long', year: 'numeric' };
    return new Date(dateString).toLocaleDateString('id-ID', options);
}

function formatDateTime(dateString) {
    const options = { 
        day: 'numeric', 
        month: 'long', 
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    return new Date(dateString).toLocaleDateString('id-ID', options);
}

function getStatusColor(status) {
    const colors = {
        'pending': '#f39c12',
        'approved': '#2ecc71',
        'rejected': '#e74c3c',
        'draft': '#95a5a6',
        'verified': '#2ecc71',
        'hadir': '#2ecc71',
        'izin': '#f39c12',
        'sakit': '#3498db',
        'alpa': '#e74c3c'
    };
    return colors[status] || '#95a5a6';
}

function showToast(message, type = 'success') {
    const existingContainer = document.getElementById('toastContainer');
    if (existingContainer && existingContainer.children.length === 0) {
        existingContainer.remove();
    }
    
    let toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 400px;
        `;
        document.body.appendChild(toastContainer);
    }
    
    const toast = document.createElement('div');
    const colors = {
        success: '#2ecc71',
        error: '#e74c3c',
        warning: '#f39c12',
        info: '#3498db'
    };
    const icons = {
        success: '<i class="fas fa-check-circle"></i>',
        error: '<i class="fas fa-times-circle"></i>',
        warning: '<i class="fas fa-exclamation-triangle"></i>',
        info: '<i class="fas fa-info-circle"></i>'
    };
    
    toast.style.cssText = `
        background: #fff;
        padding: 15px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        border-left: 4px solid ${colors[type] || '#3498db'};
        color: #333;
        font-size: 14px;
        animation: slideIn 0.3s ease;
        min-width: 250px;
        max-width: 400px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-family: Arial, sans-serif;
    `;
    toast.innerHTML = `<span>${icons[type] || '<i class="fas fa-bullhorn"></i>'}</span> ${message}`;
    
    toastContainer.appendChild(toast);
    
    setTimeout(() => {
        toast.style.transition = 'opacity 0.3s, transform 0.3s';
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100px)';
        setTimeout(() => {
            toast.remove();
            if (toastContainer.children.length === 0) {
                toastContainer.remove();
            }
        }, 300);
    }, 4000);
}

function printElement(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        const originalContents = document.body.innerHTML;
        const printContents = element.innerHTML;
        document.body.innerHTML = printContents;
        window.print();
        document.body.innerHTML = originalContents;
        location.reload();
    }
}

const styleSheet = document.createElement('style');
styleSheet.textContent = `
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateX(50px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }
    
    .pulse-animation {
        animation: pulse 2s infinite;
    }
    
    @media (max-width: 768px) {
        .table-container {
            overflow-x: auto;
        }
        .table-container table {
            font-size: 13px;
        }
        .table-container table th,
        .table-container table td {
            padding: 8px 10px;
            white-space: nowrap;
        }
    }
    
    .tab-trigger {
        cursor: pointer;
        padding: 10px 20px;
        border: none;
        background: #f8f9fa;
        border-radius: 6px 6px 0 0;
        transition: background 0.3s;
    }
    
    .tab-trigger:hover {
        background: #e9ecef;
    }
    
    .tab-trigger.active {
        background: #3498db;
        color: #fff;
    }
    
    .tab-content {
        display: none;
    }
    
    .tab-content.active {
        display: block;
    }
    
    .sortable {
        cursor: pointer;
        user-select: none;
    }
    
    .sortable:hover {
        background: #e9ecef;
    }
`;
document.head.appendChild(styleSheet);