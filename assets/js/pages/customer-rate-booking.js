(function () {
    document.querySelectorAll('.rating-option input').forEach((input) => {
        input.addEventListener('change', () => {
            document.querySelectorAll('.rating-option').forEach((option) => {
                option.classList.toggle('selected', Boolean(option.querySelector('input:checked')));
            });
        });
    });
})();
