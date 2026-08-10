 // General admin JS functions
document.addEventListener('DOMContentLoaded', function() {
    // Activate tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // DataTables initialization for all tables
    $('table').each(function() {
        if (!$.fn.DataTable.isDataTable(this)) {
            $(this).DataTable({
                responsive: true
            });
        }
    });
});

// QR Scanner toggle function
function toggleScanner() {
    const scanner = document.getElementById('scanner-container');
    const placeholder = document.getElementById('scanner-placeholder');
    
    if (scanner.style.display === 'none') {
        scanner.style.display = 'block';
        placeholder.style.display = 'none';
    } else {
        scanner.style.display = 'none';
        placeholder.style.display = 'block';
    }
}