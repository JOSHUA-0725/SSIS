// SSIS front-end helpers. Kept in a file because the Content Security Policy blocks inline scripts.

const peso = (value) => '₱' + value.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// Wires up everything inside `root`. Runs at load and again after a live update replaces the page content.
function init(root) {
    // <form data-confirm="Are you sure?"> asks before submitting.
    root.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });

    // <button data-print> prints the page.
    root.querySelectorAll('[data-print]').forEach((button) => {
        button.addEventListener('click', () => window.print());
    });

    // <select data-autosubmit> submits its form on change (term and status filters).
    root.querySelectorAll('select[data-autosubmit]').forEach((select) => {
        select.addEventListener('change', () => select.form.submit());
    });

    // Document request form: plus and minus buttons, and the running total.
    const copies = root.querySelector('#copies');
    const total = root.querySelector('#total_fee');
    if (copies && total) {
        const min = parseInt(copies.min, 10) || 1;
        const max = parseInt(copies.max, 10) || 10;
        const update = () => {
            const chosen = root.querySelector('input[name="document_type_id"]:checked');
            const count = Math.min(Math.max(parseInt(copies.value, 10) || min, min), max);
            total.textContent = peso(parseFloat(chosen ? chosen.dataset.fee : '0') * count);
        };
        root.querySelectorAll('[data-step]').forEach((button) => {
            button.addEventListener('click', () => {
                const next = (parseInt(copies.value, 10) || min) + parseInt(button.dataset.step, 10);
                copies.value = Math.min(Math.max(next, min), max);
                copies.dispatchEvent(new Event('input', { bubbles: true }));
            });
        });
        root.querySelectorAll('input[name="document_type_id"]').forEach((radio) => radio.addEventListener('change', update));
        copies.addEventListener('input', update);
        update();
    }

    // Payments page: the button says what the chosen method will do.
    const payButton = root.querySelector('[data-pay-button]');
    if (payButton) {
        const apply = () => {
            const chosen = root.querySelector('input[name="method"]:checked');
            payButton.textContent = chosen ? chosen.dataset.action : payButton.textContent;
        };
        root.querySelectorAll('input[name="method"]').forEach((radio) => radio.addEventListener('change', apply));
        apply();
    }
}

document.addEventListener('DOMContentLoaded', () => {
    init(document);

    // ----- Sidebar: the menu button opens and closes it -----
    const sidebar = document.querySelector('#sidebar');
    const narrow = window.matchMedia('(max-width: 899.98px)');
    const html = document.documentElement;
    if (sidebar) {
        const isOpen = () => (narrow.matches ? html.classList.contains('side-open') : !html.classList.contains('side-closed'));
        const reflect = () => document.querySelectorAll('button[data-sidebar-toggle]').forEach((button) => button.setAttribute('aria-expanded', isOpen()));
        document.querySelectorAll('[data-sidebar-toggle]').forEach((toggle) => {
            toggle.addEventListener('click', () => {
                if (narrow.matches) {
                    // On small screens the menu slides over the page and is not remembered.
                    html.classList.toggle('side-open');
                } else {
                    const closed = html.classList.toggle('side-closed');
                    try {
                        window.localStorage.setItem('ssis-sidebar', closed ? 'closed' : 'open');
                    } catch (error) {
                        // Not remembered when storage is unavailable.
                    }
                }
                reflect();
            });
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                html.classList.remove('side-open');
                reflect();
            }
        });
        reflect();
    }

    startLiveUpdates();
});

// ----- Live updates -----
// Every few seconds the page asks the server for a short signature of the data this user can see.
// When it changes (a payment, a new subject, a clearance sign-off...), the page content and the
// menu badges are replaced in place, without a reload.
function startLiveUpdates() {
    const url = document.body.dataset.liveUrl;
    const page = document.querySelector('[data-live="page"]');
    if (!url || !page) {
        return;
    }

    const POLL_MS = 4000;
    const IDLE_LIMIT_MS = 15 * 60 * 1000; // stop when nobody is using the page, so sessions still time out
    const toast = document.querySelector('[data-live-toast]');
    let signature = null;
    let dirty = false;
    let lastActivity = Date.now();
    let busy = false;

    ['pointerdown', 'keydown', 'scroll'].forEach((type) => window.addEventListener(type, () => { lastActivity = Date.now(); }, { passive: true }));
    // Once the user types or chooses something, the page is theirs: never replace it under them.
    page.addEventListener('input', () => { dirty = true; });
    page.addEventListener('change', (event) => { if (!event.target.matches('[data-autosubmit]')) { dirty = true; } });

    const editing = () => dirty
        || page.querySelector('[data-errors]')
        || (document.activeElement && page.contains(document.activeElement) && document.activeElement.matches('input, select, textarea'));

    const refresh = async () => {
        const response = await fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
        if (!response.ok || response.redirected) {
            // Signed out or the page is gone: load normally and let the server decide.
            window.location.reload();
            return;
        }
        const fresh = new DOMParser().parseFromString(await response.text(), 'text/html');
        const freshPage = fresh.querySelector('[data-live="page"]');
        const freshNav = fresh.querySelector('[data-live="nav"]');
        if (!freshPage) {
            return;
        }
        // Keep the message from the user's last action; the fresh copy no longer carries it.
        const flash = page.querySelector('[data-flash]');
        const scroll = window.scrollY;
        page.replaceChildren(...freshPage.childNodes);
        if (flash && !page.querySelector('[data-flash]')) {
            page.querySelector('.page-head').after(flash);
        }
        const nav = document.querySelector('[data-live="nav"]');
        if (nav && freshNav) {
            nav.replaceChildren(...freshNav.childNodes);
        }
        document.title = fresh.title;
        init(page);
        dirty = false;
        window.scrollTo(0, scroll);
        toast.hidden = true;
    };

    toast.querySelector('[data-live-refresh]').addEventListener('click', () => { dirty = false; refresh(); });

    const tick = async () => {
        if (busy || document.hidden || Date.now() - lastActivity > IDLE_LIMIT_MS) {
            return;
        }
        busy = true;
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (response.status === 401 || response.status === 419 || response.redirected) {
                window.location.reload();
                return;
            }
            if (!response.ok) {
                return;
            }
            const next = (await response.json()).signature;
            if (signature !== null && next !== signature) {
                if (editing()) {
                    toast.hidden = false;
                } else {
                    await refresh();
                }
            }
            signature = next;
        } catch (error) {
            // Offline or the server is restarting: try again on the next tick.
        } finally {
            busy = false;
        }
    };

    tick();
    window.setInterval(tick, POLL_MS);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) { lastActivity = Date.now(); tick(); } });
}
