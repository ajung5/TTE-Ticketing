/* TTE v6.14: reveal/require custom dates only when the admin selects them. */
(() => {
    'use strict';
    const period = document.getElementById('tte-ux-period');
    const group = document.getElementById('tte-ux-period-custom-fields');
    const start = document.getElementById('tte-ux-date-from');
    const end = document.getElementById('tte-ux-date-to');
    if (!period || !group || !start || !end) return;

    const update = () => {
        const custom = period.value === 'custom';
        group.hidden = !custom;
        for (const input of [start, end]) {
            input.required = custom;
            input.disabled = !custom;
        }
        start.max = end.value || '';
        end.min = start.value || '';
    };
    period.addEventListener('change', update);
    start.addEventListener('change', update);
    end.addEventListener('change', update);
    update();
})();
