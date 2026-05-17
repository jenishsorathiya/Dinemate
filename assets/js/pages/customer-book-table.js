document.addEventListener('DOMContentLoaded', function() {
    const bookingForm = document.getElementById('booking-form');
    const bookingDateInput = document.getElementById('booking-date');
    const startTimeInput = document.getElementById('start-time');
    const endTimeInput = document.getElementById('end-time');
    const guestCountInput = document.getElementById('number-of-guests');
    const customerNameInput = document.getElementById('customer-name');
    const customerEmailInput = document.getElementById('customer-email');
    const customerPhoneInput = document.getElementById('customer-phone');
    const customerPhoneCountryInput = document.getElementById('customer-phone-country');
    const customerPhoneLocalInput = document.getElementById('customer-phone-local');
    const submitBtn = document.getElementById('submit-btn');
    const goToDetailsBtn = document.getElementById('goToDetailsBtn');
    const backToBookingBtn = document.getElementById('backToBookingBtn');
    const decreaseGuestsBtn = document.getElementById('decreaseGuestsBtn');
    const increaseGuestsBtn = document.getElementById('increaseGuestsBtn');
    const bookingStepCardBooking = document.getElementById('bookingStepCardBooking');
    const bookingStepCardDetails = document.getElementById('bookingStepCardDetails');
    const bookingStepBooking = document.getElementById('bookingStepBooking');
    const bookingStepDetails = document.getElementById('bookingStepDetails');
    const guestCountError = document.getElementById('guest-count-error');
    const selectedYearLabel = document.getElementById('selectedYearLabel');
    const selectedDayLabel = document.getElementById('selectedDayLabel');
    const selectedDateLabel = document.getElementById('selectedDateLabel');
    const summaryDateText = document.getElementById('summaryDateText');
    const summaryTimeText = document.getElementById('summaryTimeText');
    const summaryGuestsText = document.getElementById('summaryGuestsText');
    const calendarMonthLabel = document.getElementById('calendarMonthLabel');
    const calendarDays = document.getElementById('bookingCalendarDays');
    const calendarPrevBtn = document.getElementById('calendarPrevBtn');
    const calendarNextBtn = document.getElementById('calendarNextBtn');
    const todayString = bookingForm.dataset.defaultDate;
    let selectedDate = bookingDateInput.value || todayString;
    let visibleMonth = selectedDate.slice(0, 7);

    function parseLocalDate(dateString) {
        const [year, month, day] = String(dateString).split('-').map(Number);
        return new Date(year, month - 1, day);
    }

    function formatLocalDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function formatDisplayDate(dateString, options) {
        return parseLocalDate(dateString).toLocaleDateString(undefined, options);
    }

    function getEndTime(startTimeValue) {
        const [hours, minutes] = String(startTimeValue).split(':').map(Number);
        const date = new Date(2000, 0, 1, hours, minutes || 0, 0);
        date.setMinutes(date.getMinutes() + Number(bookingForm.dataset.minDuration || 60));
        return `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
    }

    function syncSummary() {
        bookingDateInput.value = selectedDate;
        endTimeInput.value = getEndTime(startTimeInput.value);
        selectedYearLabel.textContent = formatDisplayDate(selectedDate, { year: 'numeric' });
        selectedDayLabel.textContent = formatDisplayDate(selectedDate, { weekday: 'short', day: '2-digit' });
        selectedDateLabel.textContent = formatDisplayDate(selectedDate, { month: 'long', day: '2-digit' });
        summaryDateText.textContent = formatDisplayDate(selectedDate, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
        summaryTimeText.textContent = `${formatTimeLabel(startTimeInput.value)} to ${formatTimeLabel(endTimeInput.value)}`;
        summaryGuestsText.textContent = `${guestCountInput.value || 0} ${Number(guestCountInput.value || 0) === 1 ? 'guest' : 'guests'}`;
    }

    function formatTimeLabel(timeValue) {
        if(!timeValue) {
            return '';
        }

        const [hours, minutes] = String(timeValue).split(':').map(Number);
        const date = new Date(2000, 0, 1, hours, minutes || 0, 0);
        return date.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
    }

    function renderCalendar() {
        const [year, month] = visibleMonth.split('-').map(Number);
        const firstDate = new Date(year, month - 1, 1);
        const lastDate = new Date(year, month, 0);
        const offset = (firstDate.getDay() + 6) % 7;
        const totalSlots = Math.ceil((offset + lastDate.getDate()) / 7) * 7;
        const startDate = new Date(year, month - 1, 1 - offset);
        calendarMonthLabel.textContent = firstDate.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
        calendarDays.innerHTML = '';

        for(let index = 0; index < totalSlots; index += 1) {
            const currentDate = new Date(startDate);
            currentDate.setDate(startDate.getDate() + index);
            const currentDateValue = formatLocalDate(currentDate);
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'booking-day';
            button.textContent = String(currentDate.getDate());

            if(currentDate.getMonth() !== firstDate.getMonth()) {
                button.classList.add('is-muted');
            }

            if(currentDateValue < todayString) {
                button.disabled = true;
            }

            if(currentDateValue === todayString) {
                button.classList.add('is-today');
            }

            if(currentDateValue === selectedDate) {
                button.classList.add('is-selected');
            }

            button.addEventListener('click', function() {
                if(button.disabled) {
                    return;
                }
                selectedDate = currentDateValue;
                visibleMonth = selectedDate.slice(0, 7);
                syncSummary();
                renderCalendar();
            });

            calendarDays.appendChild(button);
        }
    }

    function showBookingStep() {
        bookingStepCardBooking.hidden = false;
        bookingStepCardDetails.hidden = true;
        bookingStepBooking.classList.add('is-active');
        bookingStepBooking.classList.remove('is-complete');
        bookingStepDetails.classList.remove('is-active');
    }

    function showDetailsStep() {
        bookingStepCardBooking.hidden = true;
        bookingStepCardDetails.hidden = false;
        bookingStepBooking.classList.remove('is-active');
        bookingStepBooking.classList.add('is-complete');
        bookingStepDetails.classList.add('is-active');
    }

    function validateBookingStep() {
        const guestCount = Number(guestCountInput.value || 0);
        guestCountError.style.display = 'none';

        if(!selectedDate) {
            window.alert('Please choose a booking date.');
            return false;
        }

        if(!startTimeInput.value) {
            window.alert('Please choose a preferred time.');
            return false;
        }

        if(!guestCount || guestCount < 1) {
            guestCountError.textContent = 'Please choose at least 1 guest.';
            guestCountError.style.display = 'block';
            guestCountInput.focus();
            return false;
        }

        return true;
    }

    function validateDetailsStep() {
        const normalizedPhone = String(customerPhoneLocalInput.value || '').trim();
        const normalizedDigits = normalizedPhone.replace(/[^\d]/g, '');

        if(customerNameInput.value.trim().length < 2) {
            window.alert('Please enter your full name.');
            customerNameInput.focus();
            return false;
        }

        if(!customerEmailInput.value.trim()) {
            window.alert('Please enter your email address.');
            customerEmailInput.focus();
            return false;
        }

        if(!customerEmailInput.checkValidity()) {
            window.alert('Please enter a valid email address.');
            customerEmailInput.focus();
            return false;
        }

        if(!normalizedPhone) {
            window.alert('Please enter your phone number.');
            customerPhoneLocalInput.focus();
            return false;
        }

        if(normalizedDigits.length < 6) {
            window.alert('Please enter a valid phone number.');
            customerPhoneLocalInput.focus();
            return false;
        }

        customerPhoneInput.value = `${customerPhoneCountryInput.value} ${normalizedPhone}`.trim();
        return true;
    }

    function adjustGuests(delta) {
        const currentValue = Number(guestCountInput.value || 0);
        const nextValue = Math.max(1, currentValue + delta);
        guestCountInput.value = String(nextValue);
        guestCountError.style.display = 'none';
        syncSummary();
    }

    decreaseGuestsBtn.addEventListener('click', function() {
        adjustGuests(-1);
    });

    increaseGuestsBtn.addEventListener('click', function() {
        adjustGuests(1);
    });

    guestCountInput.addEventListener('input', function() {
        if(Number(guestCountInput.value || 0) < 1) {
            guestCountInput.value = '1';
        }
        guestCountError.style.display = 'none';
        syncSummary();
    });

    startTimeInput.addEventListener('change', syncSummary);

    goToDetailsBtn.addEventListener('click', function() {
        if(!validateBookingStep()) {
            return;
        }

        showDetailsStep();
        requestAnimationFrame(function() {
            customerNameInput.focus();
        });
    });

    backToBookingBtn.addEventListener('click', function() {
        showBookingStep();
    });

    calendarPrevBtn.addEventListener('click', function() {
        const [year, month] = visibleMonth.split('-').map(Number);
        const date = new Date(year, month - 2, 1);
        visibleMonth = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
        renderCalendar();
    });

    calendarNextBtn.addEventListener('click', function() {
        const [year, month] = visibleMonth.split('-').map(Number);
        const date = new Date(year, month, 1);
        visibleMonth = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
        renderCalendar();
    });

    const bookingMenuItemsInput = document.getElementById('booking-menu-items');

    const syncMenuItemsToForm = () => {
        if (!bookingMenuItemsInput) {
            return;
        }

        let cart = {};
        try {
            cart = JSON.parse(window.localStorage.getItem('dinemate_menu_cart') || '{}');
        } catch (error) {
            cart = {};
        }

        const items = Object.values(cart).filter((item) => item && Number(item.qty) > 0);
        bookingMenuItemsInput.value = JSON.stringify(items);
    };

    bookingForm.addEventListener('submit', function(event) {
        syncMenuItemsToForm();

        if(!validateBookingStep()) {
            event.preventDefault();
            showBookingStep();
            return;
        }

        if(!validateDetailsStep()) {
            event.preventDefault();
            showDetailsStep();
            return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = 'Submitting...';
    });

    syncSummary();
    renderCalendar();
    showBookingStep();
});
