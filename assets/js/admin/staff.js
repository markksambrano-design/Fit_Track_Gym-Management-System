// Tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl)
});

// Staff table search filter
document.addEventListener('DOMContentLoaded', function() {
    // Add search functionality for staff tables
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener("keyup", function () {
            const filter = this.value.toLowerCase();
            const activeTab = document.querySelector('.tab-pane.active');
            if (activeTab) {
                const rows = activeTab.querySelectorAll("tbody tr");
                rows.forEach(row => {
                    row.style.display = row.innerText.toLowerCase().includes(filter) ? "" : "none";
                });
            }
        });
    }
});
