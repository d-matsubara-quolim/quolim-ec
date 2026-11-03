(() => {
    const header = document.querySelector('[data-site-header]');
    if (!header) {
        return;
    }

    const threshold = 24;
    let ticking = false;

    const sync = () => {
        const revealed = window.scrollY > threshold;
        header.classList.toggle('is-revealed', revealed);
        header.toggleAttribute('inert', !revealed);
        ticking = false;
    };

    const onScroll = () => {
        if (ticking) {
            return;
        }
        ticking = true;
        window.requestAnimationFrame(sync);
    };

    sync();
    window.addEventListener('scroll', onScroll, { passive: true });
})();
