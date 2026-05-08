document.documentElement.dataset.appReady = 'true';

document.querySelectorAll('[data-filter-form]').forEach((form) => {
    const search = form.querySelector('input[type="search"][name="q"]');
    let searchSubmitTimer = null;

    const submitFilters = () => {
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
            return;
        }

        form.submit();
    };

    form.querySelectorAll('input[type="radio"]').forEach((input) => {
        input.addEventListener('change', submitFilters);
    });

    if (search === null) {
        return;
    }

    search.addEventListener('input', () => {
        window.clearTimeout(searchSubmitTimer);
        searchSubmitTimer = window.setTimeout(submitFilters, 450);
    });

    search.addEventListener('change', () => {
        window.clearTimeout(searchSubmitTimer);
        submitFilters();
    });
});

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
    const ticketTotal = form.dataset.ticketTotal || '';
    const checkboxes = Array.from(form.querySelectorAll('[data-seat-checkbox]'));
    const selectedCount = form.querySelector('[data-seat-selected-count]');
    const selectedList = form.querySelector('[data-seat-selected-list]');
    const remainingText = form.querySelector('[data-seat-remaining]');
    const currentTotal = form.querySelector('[data-seat-current-total]');
    const submitGuard = form.querySelector('[data-seat-submit-guard]');
    const submitButton = form.querySelector('[data-seat-submit]');
    const submitState = form.querySelector('[data-seat-submit-state]');
    const submitMessage = form.querySelector('[data-seat-submit-message]');

    if (ticketCount <= 0 || selectedCount === null || selectedList === null || submitButton === null) {
        return;
    }

    let hasSubmitAttempt = false;

    const seatLabelFor = (checkbox) => checkbox.value.replace('-', '');
    const seatWord = (amount) => amount === 1 ? 'butaca' : 'butacas';
    const selectionBalanceLabel = (selectedAmount) => {
        const remaining = ticketCount - selectedAmount;

        if (remaining === 0) {
            return 'No faltan butacas';
        }

        if (remaining < 0) {
            const extra = Math.abs(remaining);
            return `Quita ${extra} ${seatWord(extra)}`;
        }

        return `${remaining === 1 ? 'Falta' : 'Faltan'} ${remaining} ${seatWord(remaining)}`;
    };

    const updateSeatState = () => {
        const selected = checkboxes.filter((checkbox) => checkbox.checked);
        const selectedLabels = selected.map(seatLabelFor);
        const remainingSeatLabel = selectionBalanceLabel(selected.length);
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
        selectedList.textContent = selectedLabels.length > 0 ? selectedLabels.join(', ') : 'Sin butacas seleccionadas';

        if (remainingText !== null) {
            remainingText.textContent = remainingSeatLabel;
        }

        if (currentTotal !== null && ticketTotal !== '') {
            currentTotal.textContent = ticketTotal;
        }

        submitButton.disabled = !hasExactSelection;
        submitButton.setAttribute('aria-disabled', hasExactSelection ? 'false' : 'true');

        if (submitGuard !== null) {
            submitGuard.classList.toggle('is-disabled', !hasExactSelection);
        }

        if (submitState !== null) {
            submitState.textContent = hasExactSelection
                ? 'Boton activo'
                : `Boton deshabilitado: ${remainingSeatLabel.toLowerCase()}`;
        }

        if (submitMessage !== null) {
            submitMessage.textContent = hasExactSelection
                ? ''
                : `Selecciona ${ticketCount} ${seatWord(ticketCount)} para reservar. ${remainingSeatLabel}.`;
            submitMessage.hidden = hasExactSelection || !hasSubmitAttempt;
        }
    };

    checkboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', updateSeatState);
    });

    form.addEventListener('submit', (event) => {
        hasSubmitAttempt = true;
        updateSeatState();

        if (submitButton.disabled) {
            event.preventDefault();
        }
    });

    if (submitGuard !== null) {
        submitGuard.addEventListener('click', (event) => {
            if (!submitButton.disabled) {
                return;
            }

            event.preventDefault();
            hasSubmitAttempt = true;
            updateSeatState();
        });
    }

    updateSeatState();
});

document.querySelectorAll('[data-cancel-reservation]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm('Cancelar esta reserva?')) {
            event.preventDefault();
        }
    });
});

document.querySelectorAll('[data-print-ticket]').forEach((button) => {
    button.addEventListener('click', () => {
        window.print();
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
