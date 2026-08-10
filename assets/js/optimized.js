/**
 * Optimized JavaScript - Essential functionality only
 * Reduces external dependencies and improves loading speed
 */

// Essential Bootstrap-like functionality
class BootstrapComponents {
    constructor() {
        this.init();
    }

    init() {
        this.initModals();
        this.initDropdowns();
        this.initAlerts();
        this.initLoadingStates();
    }

    // Modal functionality
    initModals() {
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-bs-toggle="modal"]')) {
                const target = e.target.getAttribute('data-bs-target');
                const modal = document.querySelector(target);
                if (modal) {
                    this.showModal(modal);
                }
            }
            
            if (e.target.matches('[data-bs-dismiss="modal"]')) {
                const modal = e.target.closest('.modal');
                if (modal) {
                    this.hideModal(modal);
                }
            }
        });
    }

    showModal(modal) {
        modal.style.display = 'block';
        modal.classList.add('show');
        document.body.classList.add('modal-open');
        
        // Create backdrop
        const backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show';
        backdrop.setAttribute('data-bs-dismiss', 'modal');
        document.body.appendChild(backdrop);
    }

    hideModal(modal) {
        modal.style.display = 'none';
        modal.classList.remove('show');
        document.body.classList.remove('modal-open');
        
        // Remove backdrop
        const backdrop = document.querySelector('.modal-backdrop');
        if (backdrop) {
            backdrop.remove();
        }
    }

    // Dropdown functionality
    initDropdowns() {
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-bs-toggle="dropdown"]')) {
                e.preventDefault();
                const dropdown = e.target.nextElementSibling;
                if (dropdown && dropdown.classList.contains('dropdown-menu')) {
                    this.toggleDropdown(dropdown);
                }
            } else {
                // Close all dropdowns
                document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                    menu.classList.remove('show');
                });
            }
        });
    }

    toggleDropdown(dropdown) {
        const isOpen = dropdown.classList.contains('show');
        
        // Close all other dropdowns
        document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
            menu.classList.remove('show');
        });
        
        if (!isOpen) {
            dropdown.classList.add('show');
        }
    }

    // Alert functionality
    initAlerts() {
        document.addEventListener('click', (e) => {
            if (e.target.matches('.alert .btn-close')) {
                const alert = e.target.closest('.alert');
                if (alert) {
                    this.hideAlert(alert);
                }
            }
        });
    }

    hideAlert(alert) {
        alert.style.transition = 'opacity 0.15s linear';
        alert.style.opacity = '0';
        setTimeout(() => {
            alert.remove();
        }, 150);
    }

    // Loading states
    initLoadingStates() {
        document.addEventListener('submit', (e) => {
            if (e.target.tagName === 'FORM') {
                this.showFormLoading(e.target);
            }
        });
    }

    showFormLoading(form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="loading-spinner"></span> Loading...';
            submitBtn.disabled = true;
            
            // Re-enable after 3 seconds as fallback
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 3000);
        }
    }
}

// Auto-timeout functionality (simplified)
class AutoTimeoutManager {
    constructor() {
        this.timeoutDuration = 30 * 60 * 1000; // 30 minutes
        this.warningTime = 5 * 60 * 1000; // 5 minutes before timeout
        this.lastActivity = Date.now();
        this.warningShown = false;
        this.init();
    }

    init() {
        this.bindEvents();
        this.startTimeout();
    }

    bindEvents() {
        const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
        events.forEach(event => {
            document.addEventListener(event, () => {
                this.resetTimeout();
            }, true);
        });
    }

    resetTimeout() {
        this.lastActivity = Date.now();
        this.warningShown = false;
        this.startTimeout();
    }

    startTimeout() {
        clearTimeout(this.timeoutId);
        clearTimeout(this.warningId);
        
        // Set warning
        this.warningId = setTimeout(() => {
            this.showWarning();
        }, this.timeoutDuration - this.warningTime);
        
        // Set actual timeout
        this.timeoutId = setTimeout(() => {
            this.handleTimeout();
        }, this.timeoutDuration);
    }

    showWarning() {
        if (this.warningShown) return;
        this.warningShown = true;
        
        const warning = document.createElement('div');
        warning.className = 'alert alert-warning alert-dismissible fade show position-fixed';
        warning.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 400px;';
        warning.innerHTML = `
            <strong>Session Warning</strong>
            <p>Your session will expire in 5 minutes due to inactivity.</p>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(warning);
        
        // Auto-remove after 10 seconds
        setTimeout(() => {
            if (warning.parentNode) {
                warning.remove();
            }
        }, 10000);
    }

    handleTimeout() {
        // Show timeout message
        const timeoutAlert = document.createElement('div');
        timeoutAlert.className = 'alert alert-danger position-fixed';
        timeoutAlert.style.cssText = 'top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 10000; max-width: 500px;';
        timeoutAlert.innerHTML = `
            <h4>Session Expired</h4>
            <p>Your session has expired due to inactivity. You will be redirected to the login page.</p>
        `;
        
        document.body.appendChild(timeoutAlert);
        
        // Redirect after 3 seconds
        setTimeout(() => {
            window.location.href = window.location.pathname.includes('admin') ? 
                '../admin/login.php' : 
                window.location.pathname.includes('staff') ? 
                '../staff/login.php' : 
                '../member/login.php';
        }, 3000);
    }
}

// Chart.js replacement (simplified)
class SimpleChart {
    constructor(canvas, options = {}) {
        this.canvas = canvas;
        this.ctx = canvas.getContext('2d');
        this.options = {
            type: 'line',
            data: { labels: [], datasets: [] },
            ...options
        };
        this.render();
    }

    render() {
        const { width, height } = this.canvas;
        this.ctx.clearRect(0, 0, width, height);
        
        if (this.options.type === 'line') {
            this.renderLineChart();
        } else if (this.options.type === 'bar') {
            this.renderBarChart();
        }
    }

    renderLineChart() {
        const { data } = this.options;
        if (!data.datasets.length) return;
        
        const dataset = data.datasets[0];
        const points = dataset.data;
        if (points.length < 2) return;
        
        this.ctx.strokeStyle = dataset.borderColor || '#007bff';
        this.ctx.lineWidth = 2;
        this.ctx.beginPath();
        
        const stepX = this.canvas.width / (points.length - 1);
        const maxY = Math.max(...points);
        const minY = Math.min(...points);
        const rangeY = maxY - minY || 1;
        
        points.forEach((point, index) => {
            const x = index * stepX;
            const y = this.canvas.height - ((point - minY) / rangeY) * this.canvas.height;
            
            if (index === 0) {
                this.ctx.moveTo(x, y);
            } else {
                this.ctx.lineTo(x, y);
            }
        });
        
        this.ctx.stroke();
    }

    renderBarChart() {
        const { data } = this.options;
        if (!data.datasets.length) return;
        
        const dataset = data.datasets[0];
        const points = dataset.data;
        
        this.ctx.fillStyle = dataset.backgroundColor || '#007bff';
        
        const barWidth = this.canvas.width / points.length * 0.8;
        const maxY = Math.max(...points);
        
        points.forEach((point, index) => {
            const x = (index * this.canvas.width / points.length) + (this.canvas.width / points.length - barWidth) / 2;
            const height = (point / maxY) * this.canvas.height;
            const y = this.canvas.height - height;
            
            this.ctx.fillRect(x, y, barWidth, height);
        });
    }
}

// Utility functions
const Utils = {
    // Debounce function
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    // Format date
    formatDate(date) {
        return new Date(date).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    },

    // Format time
    formatTime(date) {
        return new Date(date).toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit'
        });
    },

    // Show loading
    showLoading(element) {
        if (typeof element === 'string') {
            element = document.querySelector(element);
        }
        if (element) {
            element.innerHTML = '<div class="loading-spinner"></div> Loading...';
        }
    },

    // Hide loading
    hideLoading(element) {
        if (typeof element === 'string') {
            element = document.querySelector(element);
        }
        if (element) {
            element.innerHTML = '';
        }
    },

    // AJAX helper
    async fetch(url, options = {}) {
        try {
            const response = await fetch(url, {
                headers: {
                    'Content-Type': 'application/json',
                    ...options.headers
                },
                ...options
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error('Fetch error:', error);
            throw error;
        }
    }
};

// Initialize everything when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap components
    window.bootstrap = new BootstrapComponents();
    
    // Initialize auto-timeout if on a dashboard page
    if (window.location.pathname.includes('dashboard.php')) {
        window.autoTimeoutManager = new AutoTimeoutManager();
    }
    
    // Initialize charts if canvas elements exist
    document.querySelectorAll('canvas[data-chart]').forEach(canvas => {
        const type = canvas.getAttribute('data-chart-type') || 'line';
        const data = JSON.parse(canvas.getAttribute('data-chart-data') || '{}');
        new SimpleChart(canvas, { type, data });
    });
    
    console.log('Optimized system initialized');
});

// Export for global use
window.BootstrapComponents = BootstrapComponents;
window.AutoTimeoutManager = AutoTimeoutManager;
window.SimpleChart = SimpleChart;
window.Utils = Utils;






