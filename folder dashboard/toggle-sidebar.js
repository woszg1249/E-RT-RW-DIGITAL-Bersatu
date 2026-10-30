document.addEventListener('DOMContentLoaded', function () {
    const body = document.body;
    const toggleBtn = document.getElementById('toggle-sidebar');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');

    if (!toggleBtn || !sidebar) {
        return;
    }

    function setSidebarState(open) {
        sidebar.style.transform = 'none';
        sidebar.style.position = 'fixed';

        if (mainContent) {
            mainContent.classList.toggle('content-full', !open);
            mainContent.classList.toggle('content-with-sidebar', open);
            mainContent.style.width = open ? 'calc(100% - 260px)' : '100%';
            mainContent.style.marginLeft = open ? '260px' : '0';
        }

        sidebar.classList.toggle('collapsed', !open);
        toggleBtn.classList.toggle('is-open', open);
        toggleBtn.setAttribute('aria-expanded', String(open));
        localStorage.setItem('rtSidebarOpen', String(open));
    }

    const savedState = localStorage.getItem('rtSidebarOpen');
    const shouldOpen = savedState === null ? true : savedState === 'true';
    setSidebarState(shouldOpen);

    toggleBtn.addEventListener('click', function () {
        const isOpen = !sidebar.classList.contains('collapsed');
        setSidebarState(!isOpen);
    });
});
