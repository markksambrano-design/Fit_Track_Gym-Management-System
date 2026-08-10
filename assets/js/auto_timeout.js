/**
 * Auto Timeout System - Client Side
 * Automatically logs out users at 8 PM
 */

class AutoTimeoutManager {
    constructor() {
        this.timeoutTime = '20:00:00'; // 8:00 PM
        this.checkInterval = 60000; // Check every minute
        this.warningTime = 5; // Show warning 5 minutes before timeout
        this.isLoggedOut = false;
        this.warningShown = false;
        
        this.init();
    }
    
    init() {
        // Only run for members and staff (not admins)
        if (this.shouldRunTimeout()) {
            console.log('Auto-timeout manager initialized');
            this.startTimeoutCheck();
            this.setupPageVisibility();
        }
    }
    
    shouldRunTimeout() {
        // Check if user is logged in as member or staff
        const currentPath = window.location.pathname;
        return currentPath.includes('/member/') || currentPath.includes('/staff/');
    }
    
    startTimeoutCheck() {
        // Check immediately
        this.checkTimeout();
        
        // Then check every minute
        setInterval(() => {
            this.checkTimeout();
        }, this.checkInterval);
    }
    
    checkTimeout() {
        if (this.isLoggedOut) return;
        
        const now = new Date();
        const currentTime = now.toTimeString().split(' ')[0]; // HH:MM:SS format
        const currentDate = now.toISOString().split('T')[0]; // YYYY-MM-DD format
        
        // Check if it's past 8 PM
        if (currentTime >= this.timeoutTime) {
            this.performAutoLogout();
            return;
        }
        
        // Check if we're within warning time (5 minutes before 8 PM)
        const timeUntilTimeout = this.getTimeUntilTimeout(currentTime);
        if (timeUntilTimeout <= this.warningTime && !this.warningShown) {
            this.showTimeoutWarning(timeUntilTimeout);
        }
    }
    
    getTimeUntilTimeout(currentTime) {
        const current = new Date(`2000-01-01 ${currentTime}`);
        const timeout = new Date(`2000-01-01 ${this.timeoutTime}`);
        const diff = timeout - current;
        return Math.max(0, Math.ceil(diff / (1000 * 60))); // minutes
    }
    
    showTimeoutWarning(minutesLeft) {
        this.warningShown = true;
        
        const warningMessage = `
            <div class="alert alert-warning alert-dismissible fade show position-fixed" 
                 style="top: 20px; right: 20px; z-index: 9999; min-width: 350px;" 
                 id="timeout-warning">
                <div class="d-flex align-items-center">
                    <i class="fas fa-clock fa-2x me-3 text-warning"></i>
                    <div>
                        <h6 class="mb-1">Auto-Logout Warning</h6>
                        <p class="mb-0">You will be automatically logged out in <strong>${minutesLeft} minute(s)</strong> at 8:00 PM.</p>
                        <small class="text-muted">Please save your work and prepare to log out.</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        // Remove any existing warning
        const existingWarning = document.getElementById('timeout-warning');
        if (existingWarning) {
            existingWarning.remove();
        }
        
        // Add new warning
        document.body.insertAdjacentHTML('beforeend', warningMessage);
        
        // Auto-dismiss after 30 seconds
        setTimeout(() => {
            const warning = document.getElementById('timeout-warning');
            if (warning) {
                warning.remove();
            }
        }, 30000);
    }
    
    async performAutoLogout() {
        if (this.isLoggedOut) return;
        
        this.isLoggedOut = true;
        
        console.log('Performing auto-logout at 8 PM');
        
        // Show logout notification
        this.showLogoutNotification();
        
        // Notify server about auto-logout
        try {
            await this.notifyServerLogout();
        } catch (error) {
            console.error('Error notifying server of auto-logout:', error);
        }
        
        // Wait a moment for user to see the notification
        setTimeout(() => {
            this.redirectToLogin();
        }, 2000);
    }
    
    showLogoutNotification() {
        const logoutMessage = `
            <div class="alert alert-info alert-dismissible fade show position-fixed" 
                 style="top: 20px; right: 20px; z-index: 10000; min-width: 350px;" 
                 id="auto-logout-notification">
                <div class="d-flex align-items-center">
                    <i class="fas fa-sign-out-alt fa-2x me-3 text-info"></i>
                    <div>
                        <h6 class="mb-1">Auto-Logout</h6>
                        <p class="mb-0">You have been automatically logged out at 8:00 PM.</p>
                        <small class="text-muted">Redirecting to login page...</small>
                    </div>
                </div>
            </div>
        `;
        
        // Remove any existing notifications
        const existingNotification = document.getElementById('auto-logout-notification');
        if (existingNotification) {
            existingNotification.remove();
        }
        
        // Add logout notification
        document.body.insertAdjacentHTML('beforeend', logoutMessage);
    }
    
    async notifyServerLogout() {
        try {
            const response = await fetch('auto_logout.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'auto_logout',
                    timestamp: new Date().toISOString(),
                    reason: '8pm_timeout'
                })
            });
            
            if (!response.ok) {
                throw new Error(`Server responded with ${response.status}`);
            }
            
            const result = await response.json();
            console.log('Server notified of auto-logout:', result);
            
        } catch (error) {
            console.error('Failed to notify server:', error);
        }
    }
    
    redirectToLogin() {
        // Clear any session data
        if (typeof(Storage) !== "undefined") {
            localStorage.removeItem('user_session');
            sessionStorage.clear();
        }
        
        // Redirect to appropriate login page
        const currentPath = window.location.pathname;
        let loginUrl = '/login.php';
        
        if (currentPath.includes('/member/')) {
            loginUrl = '/member/login.php';
        } else if (currentPath.includes('/staff/')) {
            loginUrl = '/staff/login.php';
        }
        
        window.location.href = loginUrl;
    }
    
    setupPageVisibility() {
        // Check timeout when page becomes visible again
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && !this.isLoggedOut) {
                this.checkTimeout();
            }
        });
    }
    
    // Public method to manually check timeout (can be called from other scripts)
    manualCheck() {
        this.checkTimeout();
    }
    
    // Public method to get time until timeout
    getTimeUntilTimeoutDisplay() {
        const now = new Date();
        const currentTime = now.toTimeString().split(' ')[0];
        return this.getTimeUntilTimeout(currentTime);
    }
}

// Initialize auto-timeout manager when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Only initialize if not already initialized
    if (!window.autoTimeoutManager) {
        window.autoTimeoutManager = new AutoTimeoutManager();
    }
});

// Export for use in other scripts
window.AutoTimeoutManager = AutoTimeoutManager;


