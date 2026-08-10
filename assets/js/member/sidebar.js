document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const body = document.body;

    // Debug logging
    console.log('Member Sidebar script loaded');
    console.log('Toggle button:', toggleBtn);
    console.log('Sidebar:', sidebar);

    if (toggleBtn && sidebar) {
        // Enhanced toggle with smooth animation
        toggleBtn.addEventListener('click', () => {
            console.log('Toggle button clicked');
            sidebar.classList.toggle('hidden');
            body.classList.toggle('sidebar-hidden');
            sidebar.style.transition = 'transform 0.4s cubic-bezier(0.25, 0.8, 0.25, 1.2)';
            
            // Add visual feedback to toggle button
            toggleBtn.style.transform = 'scale(0.95)';
            setTimeout(() => {
                toggleBtn.style.transform = '';
            }, 150);
            
            // Store sidebar state in localStorage
            const isHidden = sidebar.classList.contains('hidden');
            localStorage.setItem('sidebar-hidden', isHidden);
            
            // Trigger resize event to update layout
            window.dispatchEvent(new Event('resize'));
        });

        // Set active menu item based on current page
        const currentPage = window.location.pathname.split('/').pop();
        const menuItems = document.querySelectorAll('.sidebar-menu ul li a');
        
        menuItems.forEach(item => {
            const href = item.getAttribute('href');
            // Check if current page matches the href or if it's dashboard and we're on index
            if (href === currentPage || (href === 'dashboard.php' && (currentPage === 'index.php' || currentPage === ''))) {
                item.classList.add('active');
            }
            
            item.addEventListener('click', function() {
                menuItems.forEach(i => i.classList.remove('active'));
                this.classList.add('active');
                
                // On mobile, close sidebar after clicking a menu item
                if (window.innerWidth <= 992) {
                    setTimeout(() => {
                        sidebar.classList.add('hidden');
                        body.classList.add('sidebar-hidden');
                    }, 150);
                }
            });
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            if (window.innerWidth <= 992 && 
                !sidebar.contains(event.target) && 
                event.target !== toggleBtn &&
                !toggleBtn.contains(event.target)) {
                sidebar.classList.add('hidden');
                body.classList.add('sidebar-hidden');
            }
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 992) {
                // On desktop, restore sidebar state from localStorage
                const sidebarHidden = localStorage.getItem('sidebar-hidden');
                if (sidebarHidden === 'true') {
                    sidebar.classList.add('hidden');
                    body.classList.add('sidebar-hidden');
                } else {
                    sidebar.classList.remove('hidden');
                    body.classList.remove('sidebar-hidden');
                }
            } else {
                // On mobile, ensure sidebar starts hidden
                sidebar.classList.add('hidden');
                body.classList.add('sidebar-hidden');
            }
        });

        // Handle escape key to close sidebar
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && window.innerWidth <= 992) {
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
