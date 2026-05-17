(function () {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    const setHint = (element, message, type) => {
        if (!element) {
            return;
        }
        element.textContent = message;
        element.classList.toggle('success', type === 'success');
        element.classList.toggle('error', type === 'error');
    };

    const getPasswordInput = (button) => {
        const scope = button.closest('.password-group, .password-wrapper') || document;
        return scope.querySelector('input[type="password"], input[type="text"]');
    };

    document.querySelectorAll('[data-toggle-password]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = getPasswordInput(button);
            const icon = button.querySelector('i');
            if (!input) {
                return;
            }

            const showPassword = input.type === 'password';
            input.type = showPassword ? 'text' : 'password';
            button.setAttribute('aria-label', showPassword ? 'Hide password' : 'Show password');

            if (icon) {
                icon.classList.toggle('fa-eye', !showPassword);
                icon.classList.toggle('fa-eye-slash', showPassword);
            }
        });
    });

    const registerForm = document.querySelector('[data-register-form]');
    if (!registerForm) {
        return;
    }

    const nameInput = registerForm.querySelector('#name');
    const emailInput = registerForm.querySelector('#email');
    const passwordInput = registerForm.querySelector('#password');
    const confirmInput = registerForm.querySelector('#confirm');
    const emailMsg = registerForm.querySelector('#emailMsg');
    const strengthBar = registerForm.querySelector('#strengthBar');
    const strengthMsg = registerForm.querySelector('#strengthMsg');
    const matchMsg = registerForm.querySelector('#matchMsg');

    const checkEmail = () => {
        const email = (emailInput?.value || '').trim();
        if (email === '') {
            setHint(emailMsg, '', '');
            return false;
        }
        if (!emailRegex.test(email)) {
            setHint(emailMsg, 'Invalid email format', 'error');
            return false;
        }
        setHint(emailMsg, 'Valid email', 'success');
        return true;
    };

    const checkStrength = () => {
        const password = passwordInput?.value || '';
        if (!strengthBar || !strengthMsg) {
            return password.length >= 8;
        }
        if (password.length === 0) {
            strengthBar.className = 'strength-bar';
            setHint(strengthMsg, '', '');
            return false;
        }
        if (password.length < 8) {
            strengthBar.className = 'strength-bar';
            setHint(strengthMsg, 'Too short. Minimum 8 characters', 'error');
            return false;
        }
        if (password.length < 10) {
            strengthBar.className = 'strength-bar weak';
            setHint(strengthMsg, 'Weak password. Try adding more characters', 'error');
            return true;
        }
        if (password.length < 14) {
            strengthBar.className = 'strength-bar fair';
            setHint(strengthMsg, 'Fair password. Consider adding special characters', 'error');
            return true;
        }
        strengthBar.className = 'strength-bar strong';
        setHint(strengthMsg, 'Strong password', 'success');
        return true;
    };

    const matchPassword = () => {
        const password = passwordInput?.value || '';
        const confirm = confirmInput?.value || '';
        if (confirm === '') {
            setHint(matchMsg, '', '');
            return false;
        }
        if (password === confirm) {
            setHint(matchMsg, 'Passwords match', 'success');
            return true;
        }
        setHint(matchMsg, 'Passwords do not match', 'error');
        return false;
    };

    emailInput?.addEventListener('input', checkEmail);
    passwordInput?.addEventListener('input', () => {
        checkStrength();
        if (confirmInput?.value) {
            matchPassword();
        }
    });
    confirmInput?.addEventListener('input', matchPassword);

    registerForm.addEventListener('submit', (event) => {
        const name = (nameInput?.value || '').trim();
        const emailOk = checkEmail();
        const strengthOk = checkStrength();
        const passwordsMatch = matchPassword();

        if (name === '') {
            event.preventDefault();
            nameInput?.focus();
            return;
        }

        if (!emailOk || !strengthOk || !passwordsMatch) {
            event.preventDefault();
        }
    });
})();
