// Walk-in Management JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Initialize walk-in functionality
    initializeWalkIn();
    
    // Add event listeners
    addEventListeners();
});

function initializeWalkIn() {
    // Auto-refresh stats every 30 seconds
    setInterval(refreshStats, 30000);
    
    // Initialize search functionality
    initializeSearch();
}

function addEventListeners() {
    // Search form
    const searchForm = document.getElementById('searchForm');
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            searchWalkIns();
        });
    }
    
    // Export button
    const exportBtn = document.getElementById('exportBtn');
    if (exportBtn) {
        exportBtn.addEventListener('click', exportWalkIns);
    }
    
    // Stats period selector
    const statsPeriod = document.getElementById('statsPeriod');
    if (statsPeriod) {
        statsPeriod.addEventListener('change', function() {
            refreshStats(this.value);
        });
    }
}

function searchWalkIns() {
    const searchTerm = document.getElementById('searchTerm').value;
    const dateFrom = document.getElementById('dateFrom').value;
    const dateTo = document.getElementById('dateTo').value;
    const status = document.getElementById('statusFilter').value;
    
    const formData = new FormData();
    formData.append('action', 'search_walk_ins');
    formData.append('search', searchTerm);
    formData.append('date_from', dateFrom);
    formData.append('date_to', dateTo);
    formData.append('status', status);
    
    fetch('walk_in_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateWalkInTable(data.data);
        } else {
            showAlert('Error searching walk-ins: ' + data.error, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error searching walk-ins', 'error');
    });
}

function updateWalkInTable(walkIns) {
    const tbody = document.querySelector('#walkInTable tbody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    if (walkIns.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center">No walk-ins found</td></tr>';
        return;
    }
    
    walkIns.forEach(walkIn => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${walkIn.id}</td>
            <td>${escapeHtml(walkIn.first_name + ' ' + walkIn.last_name)}</td>
            <td>${escapeHtml(walkIn.address || '-')}</td>
            <td>${formatPurpose(walkIn.purpose)}</td>
            <td>${formatDateTime(walkIn.time_in)}</td>
            <td>${walkIn.time_out ? formatDateTime(walkIn.time_out) : '-'}</td>
            <td>₱${parseFloat(walkIn.payment_amount || 0).toFixed(2)}</td>
            <td><span class="status-${walkIn.status}">${capitalizeFirst(walkIn.status)}</span></td>
            <td>
                ${walkIn.status === 'active' ? `
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="checkout_walk_in">
                        <input type="hidden" name="walk_in_id" value="${walkIn.id}">
                        <button type="submit" class="btn btn-success" onclick="return confirm('Check out this customer?')">
                            <i class="fas fa-sign-out-alt"></i> Checkout
                        </button>
                    </form>
                ` : ''}
                <button class="btn btn-warning" onclick="editWalkIn(${walkIn.id})">
                    <i class="fas fa-edit"></i> Edit
                </button>
                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this walk-in?')">
                    <input type="hidden" name="action" value="delete_walk_in">
                    <input type="hidden" name="walk_in_id" value="${walkIn.id}">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </form>
            </td>
        `;
        tbody.appendChild(row);
    });
}

function editWalkIn(id) {
    const formData = new FormData();
    formData.append('action', 'get_walk_in');
    formData.append('id', id);
    
    fetch('walk_in_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            populateEditForm(data.data);
            showEditModal();
        } else {
            showAlert('Error loading walk-in data: ' + data.error, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error loading walk-in data', 'error');
    });
}

function populateEditForm(walkIn) {
    document.getElementById('edit_walk_in_id').value = walkIn.id;
    document.getElementById('edit_first_name').value = walkIn.first_name;
    document.getElementById('edit_last_name').value = walkIn.last_name;
    document.getElementById('edit_address').value = walkIn.address || '';
    document.getElementById('edit_email').value = walkIn.email || '';
    document.getElementById('edit_gender').value = walkIn.gender || '';
    document.getElementById('edit_age').value = walkIn.age || '';
    document.getElementById('edit_address').value = walkIn.address || '';
    document.getElementById('edit_purpose').value = walkIn.purpose;
    document.getElementById('edit_payment_amount').value = walkIn.payment_amount || '';
    document.getElementById('edit_payment_method').value = walkIn.payment_method || '';
    document.getElementById('edit_notes').value = walkIn.notes || '';
}

function showEditModal() {
    const modal = document.getElementById('editWalkInModal');
    if (modal) {
        modal.style.display = 'block';
    }
}

function hideEditModal() {
    const modal = document.getElementById('editWalkInModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function refreshStats(period = 'today') {
    const formData = new FormData();
    formData.append('action', 'get_walk_in_stats');
    formData.append('period', period);
    
    fetch('walk_in_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateStatsDisplay(data.data);
        }
    })
    .catch(error => {
        console.error('Error refreshing stats:', error);
    });
}

function updateStatsDisplay(stats) {
    // Update stat cards
    const todayWalkIns = document.getElementById('todayWalkIns');
    const totalWalkIns = document.getElementById('totalWalkIns');
    const totalRevenue = document.getElementById('totalRevenue');
    const activeWalkIns = document.getElementById('activeWalkIns');
    
    if (todayWalkIns) todayWalkIns.textContent = stats.total_walk_ins;
    if (totalWalkIns) totalWalkIns.textContent = stats.total_walk_ins;
    if (totalRevenue) totalRevenue.textContent = '₱' + parseFloat(stats.total_revenue).toFixed(2);
    if (activeWalkIns) activeWalkIns.textContent = stats.active_walk_ins;
}

function exportWalkIns() {
    const dateFrom = document.getElementById('dateFrom').value;
    const dateTo = document.getElementById('dateTo').value;
    
    const formData = new FormData();
    formData.append('action', 'export_walk_ins');
    formData.append('date_from', dateFrom);
    formData.append('date_to', dateTo);
    
    // Create a temporary form to submit the export request
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'walk_in_actions.php';
    form.target = '_blank';
    
    // Add form data
    for (let [key, value] of formData.entries()) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = value;
        form.appendChild(input);
    }
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

function initializeSearch() {
    // Add search functionality to the search input
    const searchInput = document.getElementById('searchTerm');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                searchWalkIns();
            }, 500);
        });
    }
}

// Utility functions
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDateTime(dateTimeString) {
    if (!dateTimeString) return '-';
    const date = new Date(dateTimeString);
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function formatPurpose(purpose) {
    return purpose.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
}

function capitalizeFirst(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}

function showAlert(message, type = 'info') {
    // Create alert element
    const alert = document.createElement('div');
    alert.className = `alert alert-${type === 'error' ? 'danger' : type}`;
    alert.textContent = message;
    
    // Add to page
    const container = document.querySelector('.walk-in-container');
    if (container) {
        container.insertBefore(alert, container.firstChild);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                alert.parentNode.removeChild(alert);
            }
        }, 5000);
    }
}

// Form validation
function validateWalkInForm() {
    const firstName = document.getElementById('first_name').value.trim();
    const lastName = document.getElementById('last_name').value.trim();
    const purpose = document.getElementById('purpose').value;
    
    if (!firstName) {
        showAlert('First name is required', 'error');
        return false;
    }
    
    if (!lastName) {
        showAlert('Last name is required', 'error');
        return false;
    }
    
    if (!purpose) {
        showAlert('Purpose is required', 'error');
        return false;
    }
    
    return true;
}

// Add form validation to the walk-in form
document.addEventListener('DOMContentLoaded', function() {
    const walkInForm = document.querySelector('form[action*="add_walk_in"]');
    if (walkInForm) {
        walkInForm.addEventListener('submit', function(e) {
            if (!validateWalkInForm()) {
                e.preventDefault();
            }
        });
    }
});
