document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form[data-confirm]').forEach(form => form.addEventListener('submit', event => {
        if (!window.confirm(form.dataset.confirm)) event.preventDefault();
    }));
    const selection = document.getElementById('supplier-selection');
    if (selection) {
        const sync = () => {
            const checks = selection.querySelectorAll('.supplier-check');
            let selected = 0;
            checks.forEach(check => {
                if (check.checked) selected++;
                const contact = document.getElementById(check.dataset.contact);
                if (contact) contact.disabled = !check.checked;
            });
            document.getElementById('selection-count').textContent = String(selected);
        };
        selection.addEventListener('change', sync);
        // Without JavaScript, contacts stay disabled; selected supplier IDs still submit normally.
        sync();
    }
    const errors = document.getElementById('form-errors');
    if (errors) errors.focus({ preventScroll: true });
});
