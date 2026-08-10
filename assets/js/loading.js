/**
 * Optimized Loading System
 * Handles page transitions and loading states efficiently
 */

class LoadingManager {
    constructor() {
        this.loadingOverlay = null;
        this.isLoading = false;
        this.init();
    }

    init() {
        this.createLoadingOverlay();
        this.bindEvents();
        this.optimizePageTransitions();
    }

    createLoadingOverlay() {
        this.loadingOverlay = document.createElement('div');
        this.loadingOverlay.className = 'loading-overlay';
        this.loadingOverlay.innerHTML = `
            <div style="text-align: center;">
                <div class="loading-spinner"></div>
                <div class="loading-text">Loading...</div>
            </div>
        `;
        this.loadingOverlay.style.display = 'none';
        document.body.appendChild(this.loadingOverlay);
    }

    bindEvents() {
        // Show loading on form submissions
        document.addEventListener('submit', (e) => {
            if (e.target.tagName === 'FORM') {
                this.showFormLoading(e.target);
            }
        });

        // Show loading on navigation links - but only briefly
        document.addEventListener('click', (e) => {
            if (e.target.matches('a[href]') && !e.target.matches('a[href^="#"]')) {
                const href = e.target.getAttribute('href');
                if (this.shouldShowLoading(href)) {
                    this.showLoading('Navigating...');
                    // Auto-hide after 500ms max
                    setTimeout(() => {
                        this.hideLoading();
                    }, 500);
                }
            }
        });

        // Hide loading when DOM is ready (much faster)
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                this.hideLoading();
            });
        } else {
            // DOM already loaded
            this.hideLoading();
        }
        
        // Also hide on full load as fallback
        window.addEventListener('load', () => {
            this.hideLoading();
        });

        // Handle browser back/forward
        window.addEventListener('popstate', () => {
            this.showLoading('Loading page...');
        });
    }

    shouldShowLoading(href) {
        // Don't show loading for external links
        if (href.startsWith('http') && !href.includes(window.location.hostname)) {
            return false;
        }
        
        // Don't show loading for same page anchors
        if (href.startsWith('#')) {
            return false;
        }
        
        // Don't show loading for JavaScript links
        if (href.startsWith('javascript:')) {
            return false;
        }
        
        return true;
    }

    showLoading(message = 'Loading...') {
        if (this.isLoading) return;
        
        this.isLoading = true;
        const loadingText = this.loadingOverlay.querySelector('.loading-text');
        if (loadingText) {
            loadingText.textContent = message;
        }
        this.loadingOverlay.style.display = 'flex';
        
        // Force immediate display for better UX
        requestAnimationFrame(() => {
            this.loadingOverlay.style.opacity = '1';
        });
        
        // Add loading class to body
        document.body.classList.add('loading');
        
        // Auto-hide after 10 seconds as fallback
        setTimeout(() => {
            this.hideLoading();
        }, 10000);
    }

    hideLoading() {
        if (!this.isLoading) return;
        
        this.isLoading = false;
        this.loadingOverlay.style.display = 'none';
        document.body.classList.remove('loading');
    }

    showFormLoading(form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            const originalText = submitBtn.innerHTML;
            submitBtn.classList.add('btn-loading');
            submitBtn.disabled = true;
            
            // Store original text for restoration
            submitBtn.dataset.originalText = originalText;
            
            // Re-enable after 5 seconds as fallback
            setTimeout(() => {
                this.hideFormLoading(submitBtn);
            }, 5000);
        }
    }

    hideFormLoading(submitBtn) {
        if (submitBtn) {
            submitBtn.classList.remove('btn-loading');
            submitBtn.disabled = false;
            if (submitBtn.dataset.originalText) {
                submitBtn.innerHTML = submitBtn.dataset.originalText;
            }
        }
    }

    showCardLoading(card) {
        if (card) {
            card.classList.add('card-loading');
        }
    }

    hideCardLoading(card) {
        if (card) {
            card.classList.remove('card-loading');
        }
    }

    showTableLoading(table) {
        if (table) {
            table.classList.add('table-loading');
        }
    }

    hideTableLoading(table) {
        if (table) {
            table.classList.remove('table-loading');
        }
    }

    optimizePageTransitions() {
        // Add transition class to main content
        const mainContent = document.querySelector('.main-content, .container, .dashboard-container');
        if (mainContent) {
            mainContent.classList.add('page-transition');
            
            // Trigger loaded state after a short delay
            setTimeout(() => {
                mainContent.classList.add('loaded');
            }, 100);
        }
    }

    // AJAX loading helper
    async loadWithAjax(url, options = {}) {
        this.showLoading(options.message || 'Loading...');
        
        try {
            const response = await fetch(url, {
                method: options.method || 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    ...options.headers
                },
                ...options
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            this.hideLoading();
            return data;
            
        } catch (error) {
            this.hideLoading();
            console.error('AJAX loading error:', error);
            throw error;
        }
    }

    // Progressive loading for large datasets
    async loadProgressive(url, container, options = {}) {
        const { batchSize = 10, delay = 100 } = options;
        
        this.showLoading('Loading data...');
        
        try {
            let page = 1;
            let hasMore = true;
            
            while (hasMore) {
                const response = await fetch(`${url}?page=${page}&limit=${batchSize}`);
                const data = await response.json();
                
                if (data.items && data.items.length > 0) {
                    // Append items to container
                    data.items.forEach(item => {
                        const element = this.createElementFromData(item, options.template);
                        container.appendChild(element);
                    });
                    
                    // Check if there are more items
                    hasMore = data.hasMore || false;
                    page++;
                    
                    // Small delay to prevent overwhelming the browser
                    if (hasMore) {
                        await new Promise(resolve => setTimeout(resolve, delay));
                    }
                } else {
                    hasMore = false;
                }
            }
            
            this.hideLoading();
            
        } catch (error) {
            this.hideLoading();
            console.error('Progressive loading error:', error);
            throw error;
        }
    }

    createElementFromData(data, template) {
        if (typeof template === 'function') {
            return template(data);
        }
        
        // Default template
        const element = document.createElement('div');
        element.className = 'data-item';
        element.textContent = JSON.stringify(data);
        return element;
    }
}

// Initialize loading manager
document.addEventListener('DOMContentLoaded', function() {
    window.loadingManager = new LoadingManager();
});

// Export for global use
window.LoadingManager = LoadingManager;






