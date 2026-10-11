// Runs in <head>: restore the sidebar state before the page paints, so it does not flash open.
try {
    if (window.localStorage.getItem('ssis-sidebar') === 'closed') {
        document.documentElement.classList.add('side-closed');
    }
} catch (error) {
    // Storage can be unavailable (private windows); the sidebar then simply starts open.
}
