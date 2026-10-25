// Sign-in page: the show-password button, and the countdown after too many attempts.
// Kept in a file because the Content Security Policy blocks inline scripts.

// The eye button beside the password shows what was typed, and hides it again.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        const input = document.getElementById(button.dataset.passwordToggle);
        if (!input) {
            return;
        }
        button.addEventListener('click', () => {
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(show));
            button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            button.title = show ? 'Hide password' : 'Show password';
            button.querySelector('[data-eye="show"]').hidden = show;
            button.querySelector('[data-eye="hide"]').hidden = !show;
            input.focus();
        });
    });
});

document.addEventListener('DOMContentLoaded', () => {
    const box = document.querySelector('[data-retry-seconds]');
    if (!box) {
        return;
    }

    const text = box.querySelector('[data-retry-text]');
    const button = document.querySelector('[data-retry-lock]');
    const end = Date.now() + (parseInt(box.dataset.retrySeconds, 10) || 0) * 1000;
    const pad = (n) => String(n).padStart(2, '0');

    const tick = () => {
        const left = Math.max(0, Math.ceil((end - Date.now()) / 1000));
        if (left === 0) {
            box.textContent = 'You can try again now.';
            if (button) {
                button.disabled = false;
            }
            clearInterval(timer);

            return;
        }
        const h = Math.floor(left / 3600), m = Math.floor((left % 3600) / 60), s = left % 60;
        text.textContent = h > 0 ? `${h}:${pad(m)}:${pad(s)}` : `${pad(m)}:${pad(s)}`;
    };

    if (button) {
        button.disabled = true;
    }
    const timer = setInterval(tick, 1000);
    tick();
});
