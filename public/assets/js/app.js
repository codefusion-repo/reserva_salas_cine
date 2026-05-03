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
        selector.dispatchEvent(new CustomEvent('ticket-count-change', { bubbles: true }));
    };

    decrease.addEventListener('click', () => {
        writeValue(readValue() - 1);
    });

    increase.addEventListener('click', () => {
        writeValue(readValue() + 1);
    });

    writeValue(readValue());
});

document.querySelectorAll('[data-showtime-form]').forEach((form) => {
    const showtimeInput = form.querySelector('[data-selected-showtime]');
    const ticketInput = form.querySelector('[data-ticket-total]');
    const continueButton = form.querySelector('[data-visual-continue]');
    const choices = Array.from(form.querySelectorAll('[data-showtime-choice]'));
    const maxTickets = 10;

    if (showtimeInput === null || ticketInput === null || continueButton === null) {
        return;
    }

    const readTicketTotal = () => Array.from(form.querySelectorAll('[data-ticket-count]'))
        .reduce((total, item) => total + Number.parseInt(item.value || item.textContent || '0', 10), 0);

    const updateContinueState = () => {
        const ticketTotal = readTicketTotal();
        const hasShowtime = showtimeInput.value !== '';
        const canContinue = hasShowtime && ticketTotal > 0 && ticketTotal <= maxTickets;

        ticketInput.value = String(ticketTotal);
        continueButton.disabled = !canContinue;
        continueButton.setAttribute('aria-disabled', canContinue ? 'false' : 'true');
    };

    choices.forEach((choice) => {
        choice.addEventListener('click', () => {
            const selectedShowtime = choice.dataset.showtimeChoice || '';

            showtimeInput.value = selectedShowtime;

            choices.forEach((item) => {
                item.classList.toggle('is-selected', item === choice);
            });

            updateContinueState();
        });
    });

    form.addEventListener('ticket-count-change', updateContinueState);
    form.addEventListener('submit', (event) => {
        updateContinueState();

        if (continueButton.disabled) {
            event.preventDefault();
        }
    });

    updateContinueState();
});

document.querySelectorAll('[data-seat-form]').forEach((form) => {
    const ticketCount = Number.parseInt(form.dataset.ticketCount || '0', 10);
    const checkboxes = Array.from(form.querySelectorAll('[data-seat-checkbox]'));
    const selectedCount = form.querySelector('[data-seat-selected-count]');
    const selectedList = form.querySelector('[data-seat-selected-list]');
    const submitButton = form.querySelector('[data-seat-submit]');

    if (ticketCount <= 0 || selectedCount === null || selectedList === null || submitButton === null) {
        return;
    }

    const seatLabelFor = (checkbox) => checkbox.value.replace('-', '');

    const updateSeatState = () => {
        const selected = checkboxes.filter((checkbox) => checkbox.checked);
        const selectedLabels = selected.map(seatLabelFor);
        const hasExactSelection = selected.length === ticketCount;

        checkboxes.forEach((checkbox) => {
            const seat = checkbox.closest('.seat-cell');
            const isOccupied = seat !== null && seat.classList.contains('is-occupied');

            if (seat !== null) {
                seat.classList.toggle('is-selected', checkbox.checked && !isOccupied);
            }

            if (!isOccupied && !checkbox.checked) {
                checkbox.disabled = selected.length >= ticketCount;
            }
        });

        selectedCount.textContent = String(selected.length);
        selectedList.textContent = selectedLabels.length > 0 ? selectedLabels.join(', ') : 'Sin butacas';
        submitButton.disabled = !hasExactSelection;
        submitButton.setAttribute('aria-disabled', hasExactSelection ? 'false' : 'true');
    };

    checkboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', updateSeatState);
    });

    form.addEventListener('submit', (event) => {
        updateSeatState();

        if (submitButton.disabled) {
            event.preventDefault();
        }
    });

    updateSeatState();
});

document.querySelectorAll('[data-cancel-reservation]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm('Cancelar esta reserva?')) {
            event.preventDefault();
        }
    });
});

document.querySelectorAll('[data-confirm-action]').forEach((button) => {
    button.addEventListener('click', (event) => {
        const message = button.dataset.confirmAction || 'Confirmar accion?';

        if (!window.confirm(message)) {
            event.preventDefault();
        }
    });
});
