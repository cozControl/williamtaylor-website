const dialog = document.querySelector('#admin-navigation-dialog');

if (dialog instanceof HTMLDialogElement) {
    const closeButton = dialog.querySelector('[data-admin-nav-close]');
    let trigger = null;

    document.querySelectorAll('[data-admin-nav-open]').forEach((button) => {
        button.addEventListener('click', () => {
            trigger = button;
            dialog.showModal();
            document.body.classList.add('admin-drawer-open');
            closeButton?.focus();
        });
    });

    closeButton?.addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
    });
    dialog.addEventListener('close', () => {
        document.body.classList.remove('admin-drawer-open');
        trigger?.focus();
    });
}
