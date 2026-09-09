const dialog = document.querySelector('#admin-navigation-dialog');

const revealActiveNavigationLink = (navigation) => {
    if (!(navigation instanceof HTMLElement)) return;

    const activeLink = navigation.querySelector('[data-admin-active-nav-link]');
    if (!(activeLink instanceof HTMLElement)) return;

    const target = activeLink.offsetTop - ((navigation.clientHeight - activeLink.offsetHeight) / 2);
    navigation.scrollTo({ top: Math.max(0, target), behavior: 'auto' });
};

document.querySelectorAll('.admin-sidebar-navigation').forEach(revealActiveNavigationLink);

if (dialog instanceof HTMLDialogElement) {
    const closeButton = dialog.querySelector('[data-admin-nav-close]');
    let trigger = null;

    document.querySelectorAll('[data-admin-nav-open]').forEach((button) => {
        button.addEventListener('click', () => {
            trigger = button;
            dialog.showModal();
            document.body.classList.add('admin-drawer-open');
            revealActiveNavigationLink(dialog.querySelector('.admin-drawer-navigation'));
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
