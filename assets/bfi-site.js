document.addEventListener('DOMContentLoaded', function () {
    const header = document.querySelector('.header');
    const syncHeaderState = function () {
        if (!header) {
            return;
        }
        header.classList.toggle('header-scrolled', window.scrollY > 24);
    };

    syncHeaderState();
    window.addEventListener('scroll', syncHeaderState, { passive: true });

    document.querySelectorAll('.footer-bottom p').forEach(function (node) {
        node.innerHTML = node.innerHTML
            .replace(/&copy;\s*\d{4}/i, '&copy; ' + new Date().getFullYear())
            .replace(/©\s*\d{4}/i, '© ' + new Date().getFullYear());
    });
});
