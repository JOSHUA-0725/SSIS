// SSIS front-end helpers. Kept in a file because the Content Security Policy blocks inline scripts.

document.addEventListener('DOMContentLoaded', () => {
    const peso = (value) => '₱' + value.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    // <form data-confirm="Are you sure?"> asks before submitting.
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });

    // <button data-print> prints the page.
    document.querySelectorAll('[data-print]').forEach((button) => {
        button.addEventListener('click', () => window.print());
    });

    // <select data-autosubmit> submits its form on change (term and status filters).
    document.querySelectorAll('select[data-autosubmit]').forEach((select) => {
        select.addEventListener('change', () => select.form.submit());
    });

    // Sidebar on small screens.
    const sidebar = document.querySelector('#sidebar');
    document.querySelectorAll('[data-sidebar-toggle]').forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const open = sidebar.classList.toggle('open');
            document.querySelectorAll('button[data-sidebar-toggle]').forEach((button) => button.setAttribute('aria-expanded', open));
        });
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && sidebar) {
            sidebar.classList.remove('open');
        }
    });

    // Sign-in page: the chosen module sets the field label, the example and the button text.
    const portals = document.querySelectorAll('input[name="portal"]');
    if (portals.length) {
        const label = document.querySelector('[data-portal-label]');
        const input = document.querySelector('#username');
        const button = document.querySelector('[data-portal-button]');
        const apply = () => {
            const chosen = document.querySelector('input[name="portal"]:checked');
            if (!chosen) {
                return;
            }
            label.textContent = chosen.dataset.field;
            input.placeholder = chosen.dataset.hint;
            button.textContent = 'Sign in to ' + chosen.dataset.module;
        };
        portals.forEach((portal) => portal.addEventListener('change', apply));
        apply();
    }

    // Document request form: plus and minus buttons, and the running total.
    const copies = document.querySelector('#copies');
    const total = document.querySelector('#total_fee');
    if (copies && total) {
        const min = parseInt(copies.min, 10) || 1;
        const max = parseInt(copies.max, 10) || 10;
        const update = () => {
            const chosen = document.querySelector('input[name="document_type_id"]:checked');
            const count = Math.min(Math.max(parseInt(copies.value, 10) || min, min), max);
            total.textContent = peso(parseFloat(chosen ? chosen.dataset.fee : '0') * count);
        };
        document.querySelectorAll('[data-step]').forEach((button) => {
            button.addEventListener('click', () => {
                const next = (parseInt(copies.value, 10) || min) + parseInt(button.dataset.step, 10);
                copies.value = Math.min(Math.max(next, min), max);
                update();
            });
        });
        document.querySelectorAll('input[name="document_type_id"]').forEach((radio) => radio.addEventListener('change', update));
        copies.addEventListener('input', update);
        update();
    }

    // Payments page: the button names the chosen method.
    const payButton = document.querySelector('[data-pay-button]');
    if (payButton) {
        const amount = payButton.dataset.amount;
        const apply = () => {
            const chosen = document.querySelector('input[name="method"]:checked');
            payButton.textContent = 'Pay ' + amount + (chosen ? ' with ' + chosen.value : '');
        };
        document.querySelectorAll('input[name="method"]').forEach((radio) => radio.addEventListener('change', apply));
        apply();
    }
});
