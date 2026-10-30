document.addEventListener('DOMContentLoaded', function () {
    const installButtonClass = 'pwa-install-btn';
    const installBannerClass = 'pwa-banner';

    if (!('serviceWorker' in navigator)) return;

    navigator.serviceWorker.register('./service-worker.js').catch(() => {});

    let deferredPrompt = null;

    const createInstallButton = () => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = installButtonClass;
        button.innerHTML = '<i class="fa-solid fa-download"></i> Download Aplikasi';
        button.addEventListener('click', async () => {
            if (!deferredPrompt) return;
            deferredPrompt.prompt();
            await deferredPrompt.userChoice;
            deferredPrompt = null;
            button.remove();
        });
        return button;
    };

    const createBanner = () => {
        const banner = document.createElement('div');
        banner.className = installBannerClass;
        banner.innerHTML = '<i class="fa-solid fa-download"></i> Download Aplikasi <button type="button">Install</button>';
        banner.querySelector('button').addEventListener('click', async () => {
            if (!deferredPrompt) return;
            deferredPrompt.prompt();
            await deferredPrompt.userChoice;
            deferredPrompt = null;
            banner.remove();
        });
        return banner;
    };

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredPrompt = event;

        const actionContainer = document.querySelector('.app-topbar__actions');
        if (!actionContainer) return;

        const existing = actionContainer.querySelector('.' + installButtonClass);
        if (!existing) {
            const installButton = createInstallButton();
            actionContainer.appendChild(installButton);
        }

        const existingBanner = document.querySelector('.' + installBannerClass);
        if (!existingBanner) {
            const banner = createBanner();
            const root = document.querySelector('.app-main') || document.body;
            root.insertBefore(banner, root.firstChild);
        }
    });
});
