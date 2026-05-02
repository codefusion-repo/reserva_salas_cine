document.documentElement.dataset.appReady = 'true';

document.querySelectorAll('[data-movie-detail]').forEach((detail) => {
    const tabs = Array.from(detail.querySelectorAll('[data-movie-date-tab]'));
    const panels = Array.from(detail.querySelectorAll('[data-movie-date-panel]'));

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            const target = tab.dataset.movieDateTab;

            tabs.forEach((item) => {
                const isActive = item === tab;
                item.classList.toggle('is-active', isActive);
                item.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });

            panels.forEach((panel) => {
                const isActive = panel.dataset.movieDatePanel === target;
                panel.classList.toggle('is-active', isActive);
                panel.hidden = !isActive;
            });
        });
    });
});

document.querySelectorAll('[data-ticket-selector]').forEach((selector) => {
    const count = selector.querySelector('[data-ticket-count]');
    const decrease = selector.querySelector('[data-ticket-action="decrease"]');
    const increase = selector.querySelector('[data-ticket-action="increase"]');
    const min = Number.parseInt(selector.dataset.min || '0', 10);
    const max = Number.parseInt(selector.dataset.max || '10', 10);

    if (count === null || decrease === null || increase === null) {
        return;
    }

    const readValue = () => Number.parseInt(count.value || count.textContent || '0', 10);
    const writeValue = (value) => {
        const normalized = Math.min(Math.max(value, min), max);
        count.value = String(normalized);
        count.textContent = String(normalized);
        selector.classList.toggle('is-selected', normalized > 0);
    };

    decrease.addEventListener('click', () => {
        writeValue(readValue() - 1);
    });

    increase.addEventListener('click', () => {
        writeValue(readValue() + 1);
    });

    writeValue(readValue());
});
