/**
 * Staff Dashboard JavaScript
 * Enhanced functionality for staff dashboard
 */

// Dashboard initialization
document.addEventListener('DOMContentLoaded', function() {
    console.log('Staff Dashboard initialized');
    
    // Initialize all dashboard components
    initializeDashboard();
    initializeTimeUpdates();
    initializeAnnouncements();
    initializeQuickActions();
});

/**
 * Initialize dashboard components
 */
function initializeDashboard() {
    console.log('Initializing dashboard components...');
    
    // Add loading animation
    addLoadingAnimation();
    
    // Initialize responsive behavior
    initializeResponsive();
}

/**
 * Initialize time updates
 */
function initializeTimeUpdates() {
    console.log('Initializing time updates...');
    
    // Update time every second
    setInterval(updateCurrentTime, 1000);
    updateCurrentTime(); // Initial call
}

/**
 * Update current time display
 */
function updateCurrentTime() {
    const timeElement = document.getElementById('currentTime');
    if (timeElement) {
        const now = new Date();
        const timeString = now.toLocaleTimeString('en-US', { 
            hour12: true, 
            hour: '2-digit', 
            minute: '2-digit', 
            second: '2-digit'
        });
        timeElement.textContent = timeString;
    }
}

/**
 * Initialize announcements functionality
 */
function initializeAnnouncements() {
    console.log('Initializing announcements...');
    
    // Mark announcement as read
    const markReadButtons = document.querySelectorAll('[onclick*="markAsRead"]');
    markReadButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const announcementId = this.getAttribute('onclick').match(/\d+/)[0];
            markAnnouncementAsRead(announcementId);
        });
    });
    
    // Refresh announcements
    const refreshAnnouncementsBtn = document.querySelector('[onclick*="refreshAnnouncements"]');
    if (refreshAnnouncementsBtn) {
        refreshAnnouncementsBtn.addEventListener('click', function(e) {
            e.preventDefault();
            refreshAnnouncements();
        });
    }
}

/**
 * Mark announcement as read
 */
function markAnnouncementAsRead(announcementId) {
    console.log('Marking announcement as read:', announcementId);
    
    // Add loading state
    const button = document.querySelector(`[onclick*="markAsRead(${announcementId})"]`);
    if (button) {
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Marking...';
        button.disabled = true;
    }
    
    // Simulate API call
    setTimeout(() => {
        if (button) {
            button.innerHTML = '<i class="fas fa-check"></i> Marked Read';
            button.classList.add('btn-success');
            button.classList.remove('btn-outline-primary');
        }
        
        // Show success message
        showNotification('Announcement marked as read', 'success');
    }, 1000);
}

/**
 * Refresh announcements
 */
function refreshAnnouncements() {
    console.log('Refreshing announcements...');
    
    // Add loading state
    const container = document.querySelector('.announcements-list');
    if (container) {
        container.style.opacity = '0.6';
        container.style.pointerEvents = 'none';
    }
    
    // Simulate refresh
    setTimeout(() => {
        if (container) {
            container.style.opacity = '1';
            container.style.pointerEvents = 'auto';
        }
        
        // Show success message
        showNotification('Announcements refreshed', 'success');
    }, 1500);
}

/**
 * Initialize quick actions
 */
function initializeQuickActions() {
    console.log('Initializing quick actions...');
    
    const actionItems = document.querySelectorAll('.action-item');
    actionItems.forEach(item => {
        item.addEventListener('click', function(e) {
            // Add click animation
            this.style.transform = 'scale(0.95)';
            setTimeout(() => {
                this.style.transform = '';
            }, 150);
        });
    });
}

/**
 * Add loading animation
 */
function addLoadingAnimation() {
    const dashboardContainer = document.querySelector('.dashboard-container');
    if (dashboardContainer) {
        dashboardContainer.style.opacity = '0';
        dashboardContainer.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            dashboardContainer.style.transition = 'all 0.5s ease';
            dashboardContainer.style.opacity = '1';
            dashboardContainer.style.transform = 'translateY(0)';
        }, 100);
    }
}

/**
 * Initialize responsive behavior
 */
function initializeResponsive() {
    // Handle window resize
    window.addEventListener('resize', function() {
        debounce(handleResize, 250)();
    });
    
    // Initial responsive setup
    handleResize();
}

/**
 * Handle window resize
 */
function handleResize() {
    const width = window.innerWidth;
    
    // Adjust layout for mobile
    if (width < 768) {
        // Mobile-specific adjustments
        const dashboardContent = document.querySelector('.dashboard-content');
        if (dashboardContent) {
            dashboardContent.style.gridTemplateColumns = '1fr';
        }
    } else {
        // Desktop layout
        const dashboardContent = document.querySelector('.dashboard-content');
        if (dashboardContent) {
            dashboardContent.style.gridTemplateColumns = 'minmax(0, 1fr) minmax(0, 400px)';
        }
    }
}

/**
 * Show notification
 */
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#17a2b8'};
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        z-index: 1000;
        animation: slideInRight 0.3s ease;
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => {
            if (document.body.contains(notification)) {
                document.body.removeChild(notification);
            }
        }, 300);
    }, 3000);
}

/**
 * Debounce function
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

// Export functions for global access
window.StaffDashboard = {
    markAnnouncementAsRead,
    refreshAnnouncements,
    showNotification
};