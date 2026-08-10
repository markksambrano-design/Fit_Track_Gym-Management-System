<!-- Loading Indicator -->
<div id="page-loading" class="loading-overlay" style="display: none;">
    <div class="loading-container">
        <div class="loading-spinner">
            <div class="spinner"></div>
        </div>
        <div class="loading-text">
            <h3>Loading...</h3>
            <p>Please wait while we load the page</p>
        </div>
    </div>
</div>

<style>
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.loading-container {
    text-align: center;
    color: white;
    background: rgba(255, 255, 255, 0.1);
    padding: 40px;
    border-radius: 15px;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.loading-spinner {
    margin-bottom: 20px;
}

.spinner {
    width: 50px;
    height: 50px;
    border: 4px solid rgba(255, 255, 255, 0.3);
    border-top: 4px solid #3B82F6;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.loading-text h3 {
    margin: 0 0 10px 0;
    font-size: 24px;
    font-weight: 600;
}

.loading-text p {
    margin: 0;
    opacity: 0.8;
    font-size: 16px;
}

/* Fade in animation - faster */
.loading-overlay.show {
    animation: fadeIn 0.1s ease-in-out;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
</style>

<script>
// Global loading functions - optimized for speed
function showLoading() {
    const loading = document.getElementById('page-loading');
    if (loading) {
        // Show immediately without animation delay
        loading.style.display = 'flex';
        loading.style.opacity = '1';
        loading.classList.add('show');
    }
}

function hideLoading() {
    const loading = document.getElementById('page-loading');
    if (loading) {
        // Hide immediately
        loading.style.display = 'none';
        loading.style.opacity = '0';
        loading.classList.remove('show');
    }
}

// Hide loading when DOM is ready (much faster than waiting for all resources)
document.addEventListener('DOMContentLoaded', function() {
    // Hide loading immediately when page loads
    hideLoading();
    
    // Navigation is now instant - no loading overlay blocking
    // Loading overlay only shows for form submissions
});

// Also hide on full load as fallback
window.addEventListener('load', function() {
    hideLoading();
});

// Show loading on form submissions
document.addEventListener('submit', function(e) {
    if (e.target.tagName === 'FORM') {
        showLoading();
    }
});
</script>



