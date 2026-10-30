/**
 * RT RW Digital - Universal Responsive & Mobile/Desktop Controller
 * Mengatur responsivitas, sidebar drawer, mode tampilan (HP/Desktop), dan navigasi bottom bar.
 */

(function () {
    const STORAGE_KEY_MODE = 'appViewMode';
    const STORAGE_KEY_SIDEBAR = 'dashboardSidebarOpen';

    function isMobileDevice() {
        return window.innerWidth <= 980 || /Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    }

    function isMobileLayout() {
        const body = document.body;
        if (!body) return window.innerWidth <= 980;
        if (body.classList.contains('mode-desktop')) return false;
        if (body.classList.contains('mode-mobile')) return true;
        return window.innerWidth <= 980;
    }

    function getViewMode() {
        return localStorage.getItem(STORAGE_KEY_MODE) || 'auto';
    }

    function applyViewMode(mode) {
        const body = document.body;
        if (!body) return;

        if (mode === 'desktop') {
            body.classList.add('mode-desktop');
            body.classList.remove('mode-mobile');
        } else if (mode === 'mobile') {
            body.classList.add('mode-mobile');
            body.classList.remove('mode-desktop');
        } else {
            body.classList.remove('mode-desktop', 'mode-mobile');
        }

        updateToggleButtonUi();
    }

    function updateToggleButtonUi() {
        const buttons = document.querySelectorAll('.view-mode-btn');
        const isDesktopMode = document.body.classList.contains('mode-desktop');

        buttons.forEach(function (btn) {
            if (isDesktopMode) {
                btn.innerHTML = '<i class="fa-solid fa-mobile-screen"></i> Mode HP';
                btn.title = 'Beralih ke tampilan responsif HP';
            } else {
                btn.innerHTML = '<i class="fa-solid fa-desktop"></i> Mode Desktop';
                btn.title = 'Beralih ke tampilan layar lebar Desktop';
            }
        });
    }

    function updateToggleButtons(isOpen) {
        const buttons = document.querySelectorAll('#toggle-sidebar, .hamburger-btn, .app-topbar__toggle');
        buttons.forEach(function (btn) {
            btn.classList.toggle('is-open', isOpen);
            btn.setAttribute('aria-expanded', String(isOpen));
        });
    }

    function setDesktopSidebar(collapsed, save = true) {
        const body = document.body;
        body.classList.remove('sidebar-open');
        body.classList.toggle('sidebar-collapsed', collapsed);
        if (save) {
            localStorage.setItem(STORAGE_KEY_SIDEBAR, String(!collapsed));
        }
        updateToggleButtons(!collapsed);
    }

    function setMobileSidebar(open) {
        const body = document.body;
        body.classList.remove('sidebar-collapsed');
        body.classList.toggle('sidebar-open', open);
        const backdrop = document.getElementById('app-backdrop');
        if (backdrop) {
            backdrop.style.opacity = open ? '1' : '0';
            backdrop.style.visibility = open ? 'visible' : 'hidden';
            backdrop.style.pointerEvents = open ? 'auto' : 'none';
        }
        updateToggleButtons(open);
    }

    function toggleSidebar(e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }

        // Hapus style inline yang mungkin ditinggalkan oleh script lama
        const sidebar = document.getElementById('app-sidebar') || document.getElementById('sidebar');
        const mainContent = document.querySelector('.app-main, .main-content');
        if (sidebar) {
            sidebar.style.marginLeft = '';
            sidebar.style.transform = '';
        }
        if (mainContent) {
            mainContent.style.width = '';
            mainContent.style.marginLeft = '';
        }

        if (isMobileLayout()) {
            const isOpen = document.body.classList.contains('sidebar-open');
            setMobileSidebar(!isOpen);
        } else {
            const isCollapsed = document.body.classList.contains('sidebar-collapsed');
            setDesktopSidebar(!isCollapsed, true);
        }
    }

    function initSidebar() {
        const sidebar = document.getElementById('app-sidebar') || document.getElementById('sidebar');
        const mainContent = document.querySelector('.app-main, .main-content');
        if (sidebar) {
            sidebar.style.marginLeft = '';
            sidebar.style.transform = '';
        }
        if (mainContent) {
            mainContent.style.width = '';
            mainContent.style.marginLeft = '';
        }

        if (isMobileLayout()) {
            setMobileSidebar(false);
        } else {
            const saved = localStorage.getItem(STORAGE_KEY_SIDEBAR);
            // Default desktop adalah TERBUKA (collapsed = false)
            const shouldBeOpen = (saved === null || saved === 'true');
            setDesktopSidebar(!shouldBeOpen, false);
        }
    }

    function bindToggleButtons() {
        const toggleButtons = document.querySelectorAll('#toggle-sidebar, .hamburger-btn, .app-topbar__toggle');
        toggleButtons.forEach(function (btn) {
            if (btn.dataset.sidebarBound === 'true') return;

            // Clone element untuk menghapus listener lama yang konflik
            const cleanBtn = btn.cloneNode(true);
            cleanBtn.dataset.sidebarBound = 'true';
            btn.parentNode.replaceChild(cleanBtn, btn);

            cleanBtn.addEventListener('click', function (e) {
                toggleSidebar(e);
            });
        });
    }

    // Expose global methods
    window.toggleSidebar = toggleSidebar;
    window.toggleMobileSidebar = toggleSidebar;
    window.toggleViewMode = function () {
        const current = getViewMode();
        let nextMode;
        if (current === 'desktop') {
            nextMode = 'mobile';
        } else if (current === 'mobile') {
            nextMode = 'desktop';
        } else {
            nextMode = isMobileDevice() ? 'desktop' : 'mobile';
        }
        localStorage.setItem(STORAGE_KEY_MODE, nextMode);
        applyViewMode(nextMode);
        initSidebar();
    };

    // Inisialisasi saat DOM siap
    function init() {
        const body = document.body;
        if (!body) return;

        const currentPath = window.location.pathname.toLowerCase();
        const inDashboardFolder = currentPath.indexOf('folder%20dashboard') !== -1 || currentPath.indexOf('folder dashboard') !== -1;
        const pathPrefix = inDashboardFolder ? '' : 'folder dashboard/';

        // 1. Terapkan mode tampilan
        applyViewMode(getViewMode());

        // 2. Pastikan Backdrop ada
        let backdrop = document.getElementById('app-backdrop');
        if (!backdrop) {
            backdrop = document.createElement('div');
            backdrop.id = 'app-backdrop';
            backdrop.className = 'app-backdrop';
            body.appendChild(backdrop);
        }
        backdrop.addEventListener('click', function () {
            setMobileSidebar(false);
        });

        // 3. Inisialisasi Sidebar & Tombol Toggle
        bindToggleButtons();
        initSidebar();

        // 4. Injeksi Tombol Mode Switcher ke Topbar jika belum ada
        const topbarActions = document.querySelector('.app-topbar__actions, .top-navbar');
        if (topbarActions && !document.querySelector('.view-mode-btn')) {
            const modeBtn = document.createElement('button');
            modeBtn.type = 'button';
            modeBtn.className = 'view-mode-btn';
            modeBtn.onclick = window.toggleViewMode;
            modeBtn.innerHTML = '<i class="fa-solid fa-desktop"></i> Mode Desktop';
            topbarActions.prepend(modeBtn);
            updateToggleButtonUi();
        }

        // 5. Injeksi Bottom Navigation Bar untuk HP jika belum ada
        if (!document.querySelector('.mobile-bottom-nav')) {
            const isUserAdmin = body.innerHTML.indexOf('kelola_warga.php') !== -1 || (window.userRole && window.userRole.toUpperCase() === 'ADMIN');
            const pageName = currentPath.substring(currentPath.lastIndexOf('/') + 1);

            const bNav = document.createElement('nav');
            bNav.className = 'mobile-bottom-nav';
            bNav.setAttribute('aria-label', 'Navigasi Mobile Bawah');

            const itemDashboardActive = pageName === 'dashboard.php' || pageName === '' ? 'active' : '';
            const itemWargaActive = (pageName === 'kelola_warga.php' || pageName === 'pengajuan_surat.php') ? 'active' : '';
            const itemKasActive = pageName === 'input_kas.php' ? 'active' : '';
            const itemKkActive = (pageName === 'kelola_kk.php' || pageName === 'kelola_kk_mobile.php' || pageName === 'laporan.php') ? 'active' : '';

            let middleItemHtml = '';
            if (isUserAdmin) {
                middleItemHtml = `
                    <a href="${pathPrefix}kelola_warga.php" class="mobile-nav-link ${itemWargaActive}">
                        <i class="fa-solid fa-users"></i>
                        <span>Warga</span>
                    </a>
                    <a href="${pathPrefix}input_kas.php" class="mobile-nav-link ${itemKasActive}">
                        <i class="fa-solid fa-money-bill-transfer"></i>
                        <span>Kas RT</span>
                    </a>
                    <a href="${pathPrefix}kelola_kk.php" class="mobile-nav-link ${itemKkActive}">
                        <i class="fa-solid fa-people-roof"></i>
                        <span>Kartu KK</span>
                    </a>
                `;
            } else {
                middleItemHtml = `
                    <a href="${pathPrefix}input_kas.php" class="mobile-nav-link ${itemKasActive}">
                        <i class="fa-solid fa-money-bill-transfer"></i>
                        <span>Kas RT</span>
                    </a>
                    <a href="${pathPrefix}pengajuan_surat.php" class="mobile-nav-link ${itemWargaActive}">
                        <i class="fa-solid fa-file-signature"></i>
                        <span>Surat</span>
                    </a>
                    <a href="${pathPrefix}laporan.php" class="mobile-nav-link ${itemKkActive}">
                        <i class="fa-solid fa-file-lines"></i>
                        <span>Laporan</span>
                    </a>
                `;
            }

            bNav.innerHTML = `
                <a href="${pathPrefix}dashboard.php" class="mobile-nav-link ${itemDashboardActive}">
                    <i class="fa-solid fa-house"></i>
                    <span>Dashboard</span>
                </a>
                ${middleItemHtml}
                <button type="button" class="mobile-nav-link" onclick="toggleMobileSidebar(event)">
                    <i class="fa-solid fa-bars"></i>
                    <span>Menu</span>
                </button>
            `;

            body.appendChild(bNav);
        }

        // 6. Injeksi Kartu Lapor Admin ke Sidebar jika belum ada
        if (!document.querySelector('.sidebar-help-card')) {
            const nav = document.querySelector('.app-sidebar__nav, .sidebar-menu');
            if (nav) {
                const card = document.createElement('div');
                card.className = 'sidebar-help-card';
                card.innerHTML = `
                    <div class="sidebar-help-card__header">
                        <i class="fa-solid fa-headset"></i>
                        <span>Lapor Admin</span>
                    </div>
                    <div class="sidebar-help-card__desc">
                        Kendala bug atau file tidak tersimpan?
                    </div>
                    <div class="sidebar-help-card__admin">
                        <i class="fa-solid fa-user-shield"></i>
                        <span>Reival Al Khafi</span>
                    </div>
                    <a href="https://wa.me/628986450758?text=Halo%20Admin%20Reival%20Al%20Khafi%2C%20saya%20ingin%20melaporkan%20kendala%20%2F%20bug%20pada%20website%20RT%20RW%20Digital%3A" target="_blank" rel="noopener noreferrer" class="sidebar-help-card__btn" title="Klik untuk hubungi WhatsApp Reival Al Khafi">
                        <i class="fa-brands fa-whatsapp"></i>
                        <span>08986450758</span>
                    </a>
                `;

                const profileLink = nav.querySelector('a[href*="profil.php"]');
                const footer = nav.querySelector('.sidebar-footer, .app-sidebar__footer');

                if (profileLink && profileLink.parentNode === nav) {
                    profileLink.insertAdjacentElement('afterend', card);
                } else if (footer) {
                    nav.insertBefore(card, footer);
                } else {
                    nav.appendChild(card);
                }
            }
        }

        // 7. Tombol ESC untuk menutup drawer mobile
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && body.classList.contains('sidebar-open')) {
                setMobileSidebar(false);
            }
        });

        // 8. Handle Resize dengan Debounce
        let resizeTimer;
        window.addEventListener('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(initSidebar, 150);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
