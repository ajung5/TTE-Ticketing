/* TTE v59: small-screen return-to-top action, only near page end.
 * Passive scroll listening; no impact on desktop or workflow state.
 */
(() => {
    'use strict';
    const button = document.querySelector('[data-tte-back-top]');
    if (!button) return;
    const mobileQuery = window.matchMedia('(max-width: 680px)');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let scheduled = false;
    const update = () => {
        scheduled = false;
        const root = document.documentElement;
        const documentHeight = Math.max(root.scrollHeight, document.body.scrollHeight);
        const scrollPosition = window.scrollY || root.scrollTop || 0;
        const nearBottom = documentHeight - (scrollPosition + window.innerHeight) <= 180;
        button.hidden = !(mobileQuery.matches && scrollPosition > 280 && nearBottom);
    };
    const queueUpdate = () => {
        if (scheduled) return;
        scheduled = true;
        window.requestAnimationFrame(update);
    };
    button.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: reducedMotion.matches ? 'auto' : 'smooth' });
        button.hidden = true;
    });
    window.addEventListener('scroll', queueUpdate, { passive: true });
    window.addEventListener('resize', queueUpdate, { passive: true });
    window.addEventListener('pageshow', queueUpdate);
    if (mobileQuery.addEventListener) mobileQuery.addEventListener('change', queueUpdate);
    else mobileQuery.addListener(queueUpdate);
    update();
})();
