// Walk-In History Page JavaScript

document.addEventListener('DOMContentLoaded', function() {
    initializeWalkInHistory();
});

function initializeWalkInHistory() {
    // Initialize tooltips
    initializeTooltips();
    
    // Initialize search functionality
    initializeSearch();
    
    // Initialize filters
    initializeFilters();
    
    // Initialize modals
    initializeModals();
    
    // Initialize pagination
    initializePagination();
    
    // Add loading animations
    addLoadingAnimations();
}

function initializeTooltips() {
    // Add tooltips to action buttons
    const tooltipElements = document.querySelectorAll('[title]');
    tooltipElements.forEach(element => {
        element.addEventListener('mouseenter', function(e) {
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = this.getAttribute('title');
            tooltip.style.position = 'absolute';
            tooltip.style.backgroundColor = '#333';
            tooltip.style.color = 'white';
            tooltip.style.padding = '5px 10px';
            tooltip.style.borderRadius = '4px';
            tooltip.style.fontSize = '12px';
            tooltip.style.zIndex = '1000';
            tooltip.style.pointerEvents = 'none';
            
            document.body.appendChild(tooltip);
            
            const rect = this.getBoundingClientRect();
            tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
            tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';
            
            this.tooltip = tooltip;
        });
        
        element.addEventListener('mouseleave', function() {
            if (this.tooltip) {
                this.tooltip.remove();
                this.tooltip = null;
            }
        });
    });
}

function initializeSearch() {
    const searchInput = document.getElementById('search');
    if (searchInput) {
        let searchTimeout;
        
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            // Only search if query is empty or has at least 3 characters
            if (query.length === 0 || query.length >= 3) {
                searchTimeout = setTimeout(() => {
                    performSearch(query);
                }, 500);
            }
        });
        
        // Add search suggestions
        searchInput.addEventListener('focus', function() {
            showSearchSuggestions();
        });
    }
}

function performSearch(query) {
    const currentUrl = new URL(window.location);
    if (query) {
        currentUrl.searchParams.set('search', query);
    } else {
        currentUrl.searchParams.delete('search');
    }
    currentUrl.searchParams.delete('current_page'); // Reset to first page
    
    // Show loading state
    showTableLoading();
    
    // Navigate to new URL
    window.location.href = currentUrl.toString();
}

function showSearchSuggestions() {
    // This could be enhanced with AJAX to fetch recent searches
    const suggestions = [
        'Recent customers',
        'Today\'s walk-ins',
        'Student customers',
        'Regular customers'
    ];
    
    // Create suggestions dropdown
    const dropdown = document.createElement('div');
    dropdown.className = 'search-suggestions';
    dropdown.style.cssText = `
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 1px solid #ddd;
        border-radius: 4px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        z-index: 1000;
        max-height: 200px;
        overflow-y: auto;
    `;
    
    suggestions.forEach(suggestion => {
        const item = document.createElement('div');
        item.className = 'suggestion-item';
        item.textContent = suggestion;
        item.style.cssText = `
            padding: 10px 15px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        `;
        
        item.addEventListener('click', function() {
            document.getElementById('search').value = suggestion;
            dropdown.remove();
        });
        
        item.addEventListener('mouseenter', function() {
            this.style.backgroundColor = '#f5f5f5';
        });
        
        item.addEventListener('mouseleave', function() {
            this.style.backgroundColor = 'white';
        });
        
        dropdown.appendChild(item);
    });
    
    const searchContainer = document.getElementById('search').parentElement;
    searchContainer.style.position = 'relative';
    searchContainer.appendChild(dropdown);
    
    // Remove dropdown when clicking outside
    document.addEventListener('click', function removeDropdown(e) {
        if (!searchContainer.contains(e.target)) {
            dropdown.remove();
            document.removeEventListener('click', removeDropdown);
        }
    });
}

function initializeFilters() {
    // Auto-submit form when select filters change
    const selectFilters = document.querySelectorAll('select[name="purpose"]');
    selectFilters.forEach(select => {
        select.addEventListener('change', function() {
            this.form.submit();
        });
    });
    
    // Auto-submit form when date filter changes
    const selectDate = document.getElementById('select_date');
    if (selectDate) {
        selectDate.addEventListener('change', function() {
            this.form.submit();
        });
    }
}

function initializeModals() {
    // Details modal
    const detailsModal = document.getElementById('detailsModal');
    if (detailsModal) {
        // Close modal when clicking outside
        detailsModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && detailsModal.style.display === 'block') {
                closeModal();
            }
        });
    }
    
    // Export modal
    const exportModal = document.getElementById('exportModal');
    if (exportModal) {
        exportModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeExportModal();
            }
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && exportModal.style.display === 'block') {
                closeExportModal();
            }
        });
    }
}

function initializePagination() {
    const paginationLinks = document.querySelectorAll('.pagination .page-link');
    paginationLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            showTableLoading();
            window.location.href = this.href;
        });
    });
}

function addLoadingAnimations() {
    // Loading animations removed for cleaner experience
}

function showTableLoading() {
    const tableContainer = document.querySelector('.history-table-container');
    if (tableContainer) {
        const loadingOverlay = document.createElement('div');
        loadingOverlay.className = 'table-loading-overlay';
        loadingOverlay.innerHTML = `
            <div class="loading-spinner">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Loading...</p>
            </div>
        `;
        loadingOverlay.style.cssText = `
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 100;
        `;
        
        tableContainer.style.position = 'relative';
        tableContainer.appendChild(loadingOverlay);
    }
}

// Enhanced view details function with AJAX
function viewDetails(id) {
    const modal = document.getElementById('detailsModal');
    const modalBody = document.getElementById('modalBody');
    
    // Show loading state
    modalBody.innerHTML = `
        <div class="loading-content">
            <i class="fas fa-spinner fa-spin"></i>
            <p>Loading details...</p>
        </div>
    `;
    
    modal.style.display = 'block';
    
    // Fetch walk-in details via AJAX
    fetch(`walk_in_details.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                modalBody.innerHTML = `
                    <div class="details-content">
                        <div class="detail-section">
                            <h3>Customer Information</h3>
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <label>Name:</label>
                                    <span>${data.walk_in.first_name} ${data.walk_in.last_name}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Address:</label>
                                    <span>${data.walk_in.address || 'Not provided'}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Gender:</label>
                                    <span>${data.walk_in.gender ? data.walk_in.gender.charAt(0).toUpperCase() + data.walk_in.gender.slice(1) : 'Not specified'}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Age:</label>
                                    <span>${data.walk_in.age || 'Not specified'}</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="detail-section">
                            <h3>Visit Information</h3>
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <label>Visit Date:</label>
                                    <span>${formatDate(data.walk_in.visit_date)}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Time In:</label>
                                    <span>${formatDateTime(data.walk_in.time_in)}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Purpose:</label>
                                    <span class="purpose-badge ${data.walk_in.purpose}">${formatPurpose(data.walk_in.purpose)}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Status:</label>
                                    <span class="status-badge ${data.walk_in.status}">${data.walk_in.status.charAt(0).toUpperCase() + data.walk_in.status.slice(1)}</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="detail-section">
                            <h3>Payment Information</h3>
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <label>Amount:</label>
                                    <span>${data.walk_in.payment_amount ? '₱' + parseFloat(data.walk_in.payment_amount).toFixed(2) : 'No payment'}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Method:</label>
                                    <span>${data.walk_in.payment_method ? data.walk_in.payment_method.charAt(0).toUpperCase() + data.walk_in.payment_method.slice(1) : 'Not specified'}</span>
                                </div>
                            </div>
                        </div>
                        
                        ${data.walk_in.notes ? `
                        <div class="detail-section">
                            <h3>Notes</h3>
                            <div class="notes-content">
                                ${data.walk_in.notes}
                            </div>
                        </div>
                        ` : ''}
                        
                        <div class="detail-section">
                            <h3>Record Information</h3>
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <label>Created:</label>
                                    <span>${formatDateTime(data.walk_in.created_at)}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Last Updated:</label>
                                    <span>${formatDateTime(data.walk_in.updated_at)}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                modalBody.innerHTML = `
                    <div class="error-content">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>Error loading details: ${data.message}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            modalBody.innerHTML = `
                <div class="error-content">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Error loading details. Please try again.</p>
                </div>
            `;
        });
}

function closeModal() {
    document.getElementById('detailsModal').style.display = 'none';
}

function exportData() {
    document.getElementById('exportModal').style.display = 'block';
}

function closeExportModal() {
    document.getElementById('exportModal').style.display = 'none';
}

function deleteRecord(id) {
    if (confirm('Are you sure you want to delete this walk-in record? This action cannot be undone.')) {
        // Show loading state
        const deleteBtn = event.target.closest('button');
        const originalContent = deleteBtn.innerHTML;
        deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        deleteBtn.disabled = true;
        
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_walk_in">
            <input type="hidden" name="walk_in_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Utility functions
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

function formatDateTime(dateTimeString) {
    const date = new Date(dateTimeString);
    return date.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function formatPurpose(purpose) {
    const purposeMap = {
        'gym_visit': 'Regular',
        'trial': 'Student',
        'consultation': 'Consultation',
        'other': 'Other'
    };
    return purposeMap[purpose] || purpose.charAt(0).toUpperCase() + purpose.slice(1);
}

// Export functions for global access
window.viewDetails = viewDetails;
window.closeModal = closeModal;
window.exportData = exportData;
window.closeExportModal = closeExportModal;
window.deleteRecord = deleteRecord;
