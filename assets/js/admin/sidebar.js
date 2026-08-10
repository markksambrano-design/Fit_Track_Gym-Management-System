document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const body = document.body;

    // Debug logging
    console.log('Sidebar script loaded');
    console.log('Toggle button:', toggleBtn);
    console.log('Sidebar:', sidebar);

    if (toggleBtn && sidebar) {
        // Enhanced toggle with smooth animation
        toggleBtn.addEventListener('click', () => {
            console.log('Toggle button clicked');
            sidebar.classList.toggle('hidden');
            body.classList.toggle('sidebar-hidden');
            sidebar.style.transition = 'transform 0.4s cubic-bezier(0.25, 0.8, 0.25, 1.2)';
        });

        // Set active menu item based on current page
        const currentPage = window.location.pathname.split('/').pop();
        const menuItems = document.querySelectorAll('.sidebar-menu ul li a');
        
        menuItems.forEach(item => {
            if (item.getAttribute('href') === currentPage) {
                item.classList.add('active');
            }
            
            item.addEventListener('click', function() {
                menuItems.forEach(i => i.classList.remove('active'));
                this.classList.add('active');
            });
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            if (window.innerWidth <= 992 && 
                !sidebar.contains(event.target) && 
                event.target !== toggleBtn) {
                sidebar.classList.add('hidden');
                body.classList.add('sidebar-hidden');
            }
        });

        // Add hover effect delay for smoothness
        menuItems.forEach(item => {
            item.addEventListener('mouseenter', () => {
                item.style.transitionDelay = '0.1s';
            });
            item.addEventListener('mouseleave', () => {
                item.style.transitionDelay = '0s';
            });
        });
    } else {
        console.error('Toggle button or sidebar not found');
    }
});
