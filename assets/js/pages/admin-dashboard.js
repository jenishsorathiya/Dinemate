(function () {
    const init = () => {
    const closeModal = (modal) => {
        if (!modal) {
            return;
        }
        modal.hidden = true;
        document.body.classList.remove('admin-modal-open');
    };

    const openModal = (modal) => {
        if (!modal) {
            return;
        }
        modal.hidden = false;
        document.body.classList.add('admin-modal-open');
        modal.querySelector('input, select, textarea, button, a')?.focus();
    };

    document.querySelectorAll('[data-admin-modal-close]').forEach((button) => {
        button.addEventListener('click', () => closeModal(button.closest('.admin-modal')));
    });

    document.querySelectorAll('.admin-modal').forEach((modal) => {
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal(modal);
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }
        document.querySelectorAll('.admin-modal:not([hidden])').forEach(closeModal);
    });

    const createModal = document.querySelector('[data-admin-booking-create-modal]');
    const createForm = document.querySelector('[data-admin-booking-create-form]');
    const setCreateValue = (name, value) => {
        const field = createForm?.querySelector(`[name="${name}"]`);
        if (field && value) {
            field.value = value;
        }
    };

    document.querySelectorAll('[data-admin-booking-create-open]').forEach((button) => {
        button.addEventListener('click', () => {
            if (createForm) {
                createForm.reset();
                setCreateValue('booking_date', button.dataset.createDate);
                setCreateValue('name', button.dataset.createName);
                setCreateValue('customer_email', button.dataset.createEmail);
                setCreateValue('customer_phone', button.dataset.createPhone);
            }
            openModal(createModal);
        });
    });

    if (createForm) {
        const message = createForm.querySelector('[data-admin-booking-create-message]');
        createForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const submitButton = createForm.querySelector('button[type="submit"]');
            const formData = new FormData(createForm);
            const payload = {};

            formData.forEach((value, key) => {
                payload[key] = typeof value === 'string' ? value.trim() : value;
            });

            if (message) {
                message.hidden = true;
                message.textContent = '';
                message.classList.remove('is-error', 'is-success');
            }

            if (submitButton) {
                submitButton.disabled = true;
            }

            try {
                const response = await fetch(createForm.dataset.createEndpoint || '../actions/create-booking.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'Could not create booking');
                }

                if (message) {
                    message.textContent = 'Booking created. Refreshing the dashboard...';
                    message.classList.add('is-success');
                    message.hidden = false;
                }

                const params = new URLSearchParams(window.location.search);
                params.set('date', payload.booking_date || '');
                params.set('dashboard_tab', payload.booking_type === 'function' ? 'functions' : (payload.booking_type === 'trivia' ? 'trivia' : 'bookings'));
                window.setTimeout(() => {
                    window.location.href = `admin_home.php?${params.toString()}`;
                }, 650);
            } catch (error) {
                if (message) {
                    message.textContent = error.message;
                    message.classList.add('is-error');
                    message.hidden = false;
                } else {
                    alert(error.message);
                }
            } finally {
                if (submitButton) {
                    submitButton.disabled = false;
                }
            }
        });
    }

    const detailModal = document.querySelector('[data-admin-booking-detail-modal]');
    if (!detailModal) {
        return;
    }

    const setText = (selector, value) => {
        const element = detailModal.querySelector(selector);
        if (element) {
            element.textContent = value || '-';
        }
    };

    document.querySelectorAll('[data-admin-booking-open]').forEach((button) => {
        button.addEventListener('click', () => {
            const data = button.dataset;
            const contact = data.bookingPhone || data.bookingEmail || '-';
            const table = [data.bookingTable, data.bookingArea].filter(Boolean).join(' - ');
            const action = detailModal.querySelector('[data-booking-detail-action]');

            setText('[data-booking-detail-name]', data.bookingName || 'Booking');
            setText('[data-booking-detail-subtitle]', data.bookingId ? `Booking #${data.bookingId}` : '');
            setText('[data-booking-detail-date]', data.bookingDate || '-');
            setText('[data-booking-detail-time]', data.bookingTime || '-');
            setText('[data-booking-detail-guests]', data.bookingGuests ? `${data.bookingGuests} guests` : '-');
            setText('[data-booking-detail-table]', table || '-');
            setText('[data-booking-detail-status]', data.bookingStatus || '-');
            setText('[data-booking-detail-contact]', contact);
            setText('[data-booking-detail-notes]', data.bookingNotes || 'No notes recorded.');

            if (action) {
                action.href = data.bookingActionUrl || 'admin_bookings.php';
            }

            openModal(detailModal);
        });
    });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
