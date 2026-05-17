(function () {
    const modal = document.querySelector('[data-plan-modal-root]');
    if (!modal) {
        return;
    }

    const title = modal.querySelector('[data-plan-title]');
    const price = modal.querySelector('[data-plan-price]');
    const copy = modal.querySelector('[data-plan-copy]');
    const primary = modal.querySelector('[data-plan-primary]');
    const secondary = modal.querySelector('[data-plan-secondary]');
    const closeButtons = modal.querySelectorAll('[data-plan-modal-close]');
    let lastTrigger = null;

    const setText = (element, value) => {
        if (element) {
            element.textContent = value || '';
        }
    };

    const setLink = (element, label, url) => {
        if (!element) {
            return;
        }
        element.textContent = label || '';
        element.href = url || '#';
        element.hidden = !label || !url;
    };

    const closeModal = () => {
        modal.hidden = true;
        document.body.classList.remove('modal-open');
        lastTrigger?.focus();
    };

    const openModal = (trigger) => {
        lastTrigger = trigger;
        setText(title, trigger.dataset.planTitle);
        setText(price, trigger.dataset.planPrice);
        setText(copy, trigger.dataset.planCopy);
        setLink(primary, trigger.dataset.planPrimaryLabel, trigger.dataset.planPrimaryUrl);
        setLink(secondary, trigger.dataset.planSecondaryLabel, trigger.dataset.planSecondaryUrl);
        modal.hidden = false;
        document.body.classList.add('modal-open');
        modal.querySelector('[data-plan-modal-close]')?.focus();
    };

    document.querySelectorAll('[data-plan-modal]').forEach((trigger) => {
        trigger.addEventListener('click', () => openModal(trigger));
    });

    closeButtons.forEach((button) => {
        button.addEventListener('click', closeModal);
    });

    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (!modal.hidden && event.key === 'Escape') {
            closeModal();
        }
    });
})();
