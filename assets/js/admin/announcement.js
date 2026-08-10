/**
 * Modern Announcement Management JavaScript
 * Enhanced functionality for the announcement system
 */

$(document).ready(function() {
    console.log('Modern announcement system initialized');
    
    // Initialize Bootstrap components
    initializeBootstrapComponents();
    
    // Initialize view mode switching
    initializeViewModes();
    
    // Initialize filters and search
    initializeFilters();
    
    // Initialize form handling
    initializeFormHandling();
    
    // Initialize announcement actions
    initializeAnnouncementActions();
    
    // Initialize animations and effects
    initializeAnimations();
    
    console.log('Modern announcement system setup complete');
});

/**
 * Initialize Bootstrap components
 */
function initializeBootstrapComponents() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize popovers if any
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    const popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
}

/**
 * Initialize view mode switching
 */
function initializeViewModes() {
    let currentViewMode = 'list'; // Default to list view as shown in the UI
    
    console.log('Initializing view modes...');
    
    $('#viewModeGrid').on('click', function() {
        console.log('Grid view clicked');
        if (currentViewMode !== 'grid') {
            currentViewMode = 'grid';
            switchToGridView();
            updateViewModeButtons();
        }
    });
    
    $('#viewModeList').on('click', function() {
        console.log('List view clicked');
        if (currentViewMode !== 'list') {
            currentViewMode = 'list';
            switchToListView();
            updateViewModeButtons();
        }
    });
    
    // Initialize with list view
    switchToListView();
    updateViewModeButtons();
}

/**
 * Switch to grid view
 */
function switchToGridView() {
    const container = $('#announcementsContainer');
    console.log('Switching to grid view');
    container.removeClass('list-view').addClass('grid-view');
    
    // Add animation to cards
    container.find('.announcement-card').each(function(index) {
        $(this).css({
            'animation-delay': (index * 0.1) + 's'
        }).addClass('fade-in');
    });
}

/**
 * Switch to list view
 */
function switchToListView() {
    const container = $('#announcementsContainer');
    console.log('Switching to list view');
    container.removeClass('grid-view').addClass('list-view');
    
    // Add animation to cards
    container.find('.announcement-card').each(function(index) {
        $(this).css({
            'animation-delay': (index * 0.05) + 's'
        }).addClass('fade-in');
    });
}

/**
 * Update view mode buttons
 */
function updateViewModeButtons() {
    console.log('Updating view mode buttons');
    if ($('#announcementsContainer').hasClass('grid-view')) {
        $('#viewModeGrid').addClass('active');
        $('#viewModeList').removeClass('active');
        console.log('Grid view active');
    } else {
        $('#viewModeList').addClass('active');
        $('#viewModeGrid').removeClass('active');
        console.log('List view active');
    }
}

/**
 * Initialize filters and search functionality
 */
function initializeFilters() {
    // Clear filters
    $('#clearFilters').on('click', function() {
        clearAllFilters();
        filterAnnouncements();
    });
    
    // Bind filter events - auto-filter on change
    $('select[name="status"], select[name="priority"], select[name="audience"]').on('change', function() {
        filterAnnouncements();
    });
    
    // Search with debouncing
    let searchTimeout;
    $('#searchFilter').on('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            filterAnnouncements();
        }, 300);
    });
    
    // Initialize filters
    filterAnnouncements();
}

/**
 * Clear all filters
 */
function clearAllFilters() {
    $('select[name="status"]').val('all');
    $('select[name="priority"]').val('all');
    $('select[name="audience"]').val('all');
    $('#searchFilter').val('');
}

/**
 * Filter announcements based on current filters
 */
function filterAnnouncements() {
    const status = $('select[name="status"]').val();
    const priority = $('select[name="priority"]').val();
    const audience = $('select[name="audience"]').val();
    const search = $('#searchFilter').val().toLowerCase();
    
    let visibleCount = 0;
    
    $('.announcement-card').each(function() {
        const item = $(this);
        const itemStatus = item.data('status');
        const itemPriority = item.data('priority');
        const itemAudience = item.data('audience');
        const title = item.find('.announcement-title').text().toLowerCase();
        const message = item.find('.announcement-message').text().toLowerCase();
        
        let show = true;
        
        // Apply filters
        if (status !== 'all' && itemStatus !== status) show = false;
        if (priority !== 'all' && itemPriority !== priority) show = false;
        if (audience !== 'all' && itemAudience !== audience) show = false;
        if (search && !title.includes(search) && !message.includes(search)) show = false;
        
        if (show) {
            item.removeClass('hidden').addClass('fade-in');
            visibleCount++;
        } else {
            item.addClass('hidden').removeClass('fade-in');
        }
    });
    
    updateAnnouncementCount(visibleCount);
    updateEmptyState(visibleCount);
}

/**
 * Update announcement count display
 */
function updateAnnouncementCount(count) {
    $('#announcementCount').text(count + ' announcement' + (count !== 1 ? 's' : ''));
}

/**
 * Update empty state visibility
 */
function updateEmptyState(visibleCount) {
    if (visibleCount === 0 && $('.announcement-card').length > 0) {
        showNoResultsState();
    } else {
        hideNoResultsState();
    }
}

/**
 * Show no results state
 */
function showNoResultsState() {
    if ($('#noResultsState').length === 0) {
        const noResultsHtml = `
            <div id="noResultsState" class="empty-state-container">
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <h3 class="empty-title">No results found</h3>
                    <p class="empty-description">Try adjusting your filters or search terms</p>
                    <button class="btn btn-outline-primary empty-action" onclick="clearAllFilters(); filterAnnouncements();">
                        <i class="fas fa-refresh me-2"></i>
                        Clear Filters
                    </button>
                </div>
            </div>
        `;
        $('#announcementsList').append(noResultsHtml);
    }
    $('#noResultsState').show();
}

/**
 * Hide no results state
 */
function hideNoResultsState() {
    $('#noResultsState').hide();
}

/**
 * Initialize form handling
 */
function initializeFormHandling() {
    // Form submission
    $('#announcementForm').on('submit', function(e) {
        e.preventDefault();
        handleFormSubmission();
    });
    
    // Reset form when modal is closed
    $('#addAnnouncementModal').on('hidden.bs.modal', function() {
        resetForm();
    });
    
    // Character counter for message
    $('#announcementMessage').on('input', function() {
        updateCharacterCount();
    });
}

/**
 * Handle form submission
 */
function handleFormSubmission() {
    const submitBtn = $('#submitBtn');
    const originalText = submitBtn.html();
    
    // Show loading state
    submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Processing...');
    
    // Add loading class to modal
    $('#addAnnouncementModal .modal-content').addClass('loading');
    
    $.ajax({
        url: 'actions/announcement_actions.php',
        type: 'POST',
        data: $('#announcementForm').serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showToast('success', 'Success!', response.message);
                $('#addAnnouncementModal').modal('hide');
                
                // Reload page after delay
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                showToast('error', 'Error', response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            showToast('error', 'Error', 'An error occurred. Please try again.');
        },
        complete: function() {
            // Reset button and remove loading state
            submitBtn.prop('disabled', false).html(originalText);
            $('#addAnnouncementModal .modal-content').removeClass('loading');
        }
    });
}

/**
 * Reset form to initial state
 */
function resetForm() {
    $('#announcementForm')[0].reset();
    $('#editId').val('');
    $('#submitBtn').html('<i class="fas fa-paper-plane me-1"></i>Post Announcement');
    $('#announcementModalLabel').html('<i class="fas fa-plus me-2"></i>New Announcement');
    $('input[name="action"]').val('create');
    $('.modal-subtitle').text('Share important information with your community');
    
    // Reset character count
    updateCharacterCount();
}

/**
 * Update character count for message
 */
function updateCharacterCount() {
    const message = $('#announcementMessage').val();
    const maxLength = 1000; // Set your desired max length
    const currentLength = message.length;
    
    // Remove existing counter if present
    $('.char-counter').remove();
    
    // Add character counter
    const counterHtml = `
        <div class="char-counter mt-2 text-end">
            <small class="text-muted">
                ${currentLength}/${maxLength} characters
            </small>
        </div>
    `;
    
    $('#announcementMessage').after(counterHtml);
    
    // Add warning class if approaching limit
    if (currentLength > maxLength * 0.9) {
        $('.char-counter small').addClass('text-warning');
    }
    if (currentLength > maxLength) {
        $('.char-counter small').addClass('text-danger');
    }
}

/**
 * Initialize announcement actions (edit, delete, etc.)
 */
function initializeAnnouncementActions() {
    // Edit announcement
    $(document).on('click', '.edit-announcement', function(e) {
        e.preventDefault();
        const id = $(this).data('id');
        handleEditAnnouncement(id);
    });
    
    // Toggle pin
    $(document).on('click', '.toggle-pin', function(e) {
        e.preventDefault();
        const id = $(this).data('id');
        handleTogglePin(id);
    });
    
    // Toggle status
    $(document).on('click', '.toggle-status', function(e) {
        e.preventDefault();
        const id = $(this).data('id');
        handleToggleStatus(id);
    });
    
    // Delete announcement
    $(document).on('click', '.delete-announcement', function(e) {
        e.preventDefault();
        const id = $(this).data('id');
        handleDeleteAnnouncement(id);
    });
}

/**
 * Handle editing an announcement
 */
function handleEditAnnouncement(id) {
    // Close dropdown
    $(this).closest('.dropdown-menu').parent().find('.dropdown-toggle').dropdown('hide');
    
    // Show loading state
    showLoadingState();
    
    $.get('actions/announcement_actions.php', {
        action: 'get_announcement',
        id: id
    }, function(response) {
        if (response.success) {
            populateEditForm(response.announcement);
            showEditModal();
        } else {
            showToast('error', 'Error', response.message);
        }
    }, 'json').fail(function() {
        showToast('error', 'Error', 'Failed to load announcement data.');
    }).always(function() {
        hideLoadingState();
    });
}

/**
 * Populate edit form with announcement data
 */
function populateEditForm(announcement) {
    $('#editId').val(announcement.id);
    $('#announcementTitle').val(announcement.title);
    $('#announcementMessage').val(announcement.message);
    $('#announcementPriority').val(announcement.priority);
    $('#announcementAudience').val(announcement.target_audience);
    $('#announcementPinned').prop('checked', announcement.is_pinned == 1);
    
    if (announcement.expires_at) {
        $('#announcementExpires').val(announcement.expires_at.replace(' ', 'T'));
    } else {
        $('#announcementExpires').val('');
    }
    
    // Update modal
    $('#announcementModalLabel').html('<i class="fas fa-edit me-2"></i>Edit Announcement');
    $('#submitBtn').html('<i class="fas fa-save me-1"></i>Update Announcement');
    $('input[name="action"]').val('update');
    $('.modal-subtitle').text('Update your announcement information');
    
    // Update character count
    updateCharacterCount();
}

/**
 * Show edit modal
 */
function showEditModal() {
    $('#addAnnouncementModal').modal('show');
}

/**
 * Handle toggle pin action
 */
function handleTogglePin(id) {
    $(this).closest('.dropdown-menu').parent().find('.dropdown-toggle').dropdown('hide');
    
    $.post('actions/announcement_actions.php', {
        action: 'toggle_pin',
        id: id
    }, function(response) {
        if (response.success) {
            showToast('success', 'Success!', response.message);
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('error', 'Error', response.message);
        }
    }, 'json').fail(function() {
        showToast('error', 'Error', 'Failed to toggle pin status.');
    });
}

/**
 * Handle toggle status action
 */
function handleToggleStatus(id) {
    $(this).closest('.dropdown-menu').parent().find('.dropdown-toggle').dropdown('hide');
    
    $.post('actions/announcement_actions.php', {
        action: 'toggle_status',
        id: id
    }, function(response) {
        if (response.success) {
            showToast('success', 'Success!', response.message);
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('error', 'Error', response.message);
        }
    }, 'json').fail(function() {
        showToast('error', 'Error', 'Failed to toggle status.');
    });
}

/**
 * Handle delete announcement action
 */
function handleDeleteAnnouncement(id) {
    $(this).closest('.dropdown-menu').parent().find('.dropdown-toggle').dropdown('hide');
    
    showConfirmModal(
        'Delete Announcement',
        'Are you sure you want to delete this announcement? This action cannot be undone.',
        function() {
            $.post('actions/announcement_actions.php', {
                action: 'delete',
                id: id
            }, function(response) {
                if (response.success) {
                    showToast('success', 'Success!', response.message);
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast('error', 'Error', response.message);
                }
            }, 'json').fail(function() {
                showToast('error', 'Error', 'Failed to delete announcement.');
            });
        }
    );
}

/**
 * Initialize animations and effects
 */
function initializeAnimations() {
    // Add entrance animations to cards
    $('.announcement-card').each(function(index) {
        $(this).css({
            'animation-delay': (index * 0.1) + 's'
        }).addClass('fade-in');
    });
    
    // Add hover effects
    $('.announcement-card').hover(
        function() {
            $(this).addClass('card-hover');
        },
        function() {
            $(this).removeClass('card-hover');
        }
    );
    
    // Smooth scroll for anchor links
    $('a[href^="#"]').on('click', function(e) {
        e.preventDefault();
        const target = $(this.getAttribute('href'));
        if (target.length) {
            $('html, body').animate({
                scrollTop: target.offset().top - 100
            }, 800, 'easeInOutQuart');
        }
    });
}

/**
 * Show loading state
 */
function showLoadingState() {
    $('body').append('<div id="globalLoading" class="global-loading"><div class="spinner"></div></div>');
}

/**
 * Hide loading state
 */
function hideLoadingState() {
    $('#globalLoading').remove();
}

/**
 * Show toast notification
 */
function showToast(type, title, message) {
    const toast = $('#announcementToast');
    const toastTitle = $('#toastTitle');
    const toastBody = $('#toastBody');
    
    toastTitle.text(title);
    toastBody.text(message);
    
    // Update toast appearance based on type
    toast.removeClass('bg-success bg-danger bg-warning bg-info');
    if (type === 'success') {
        toast.addClass('bg-success text-white');
        toast.find('.toast-header').addClass('bg-success');
    } else if (type === 'error') {
        toast.addClass('bg-danger text-white');
        toast.find('.toast-header').addClass('bg-danger');
    } else if (type === 'warning') {
        toast.addClass('bg-warning text-dark');
        toast.find('.toast-header').addClass('bg-warning');
    } else {
        toast.addClass('bg-info text-white');
        toast.find('.toast-header').addClass('bg-info');
    }
    
    const bsToast = new bootstrap.Toast(toast[0]);
    bsToast.show();
}

/**
 * Show confirmation modal
 */
function showConfirmModal(title, message, onConfirm) {
    $('#confirmModalTitle').text(title);
    $('#confirmModalBody').text(message);
    
    $('#confirmActionBtn').off('click').on('click', function() {
        onConfirm();
        $('#confirmModal').modal('hide');
    });
    
    $('#confirmModal').modal('show');
}

/**
 * Utility function to format dates
 */
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Utility function to truncate text
 */
function truncateText(text, maxLength) {
    if (text.length <= maxLength) return text;
    return text.substr(0, maxLength) + '...';
}

/**
 * Add smooth scrolling animation
 */
$.easing.easeInOutQuart = function (x, t, b, c, d) {
    if ((t/=d/2) < 1) return c/2*t*t*t*t + b;
    return -c/2 * ((t-=2)*t*t*t - 2) + b;
};

// Export functions for global access
window.announcementSystem = {
    clearFilters: clearAllFilters,
    filterAnnouncements: filterAnnouncements,
    showToast: showToast,
    formatDate: formatDate,
    truncateText: truncateText
};
