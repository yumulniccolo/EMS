import { Modal } from 'bootstrap';

// The one-time temporary password window on the Accounts page (see accounts/index.blade.php). It opens by itself on the page the
// server sends right after a password is issued. Copy Password puts it on the clipboard and says so briefly. Closing the window
// wipes the password and removes the window from the page, so it cannot be shown again. A page reached with Back or Forward is an
// old copy from the browser's history, so there the window is removed without being shown.
const modalElement = document.querySelector('[data-issued-password]');
const isHistoryVisit = performance.getEntriesByType('navigation')[0]?.type === 'back_forward';

function discard(element) {
    element.querySelector('[data-issued-password-input]')?.removeAttribute('value');
    element.remove();
}

if (modalElement && isHistoryVisit) {
    discard(modalElement);
} else if (modalElement) {
    const input = modalElement.querySelector('[data-issued-password-input]');
    const copyButton = modalElement.querySelector('[data-issued-password-copy]');
    const copyLabel = modalElement.querySelector('[data-issued-password-copy-label]');
    const status = modalElement.querySelector('[data-issued-password-status]');
    let resetTimer = null;

    function confirmCopy(message) {
        copyLabel.textContent = message;
        status.textContent = message === 'Copied' ? 'The temporary password was copied to the clipboard.' : message;
        clearTimeout(resetTimer);
        resetTimer = setTimeout(() => {
            copyLabel.textContent = 'Copy Password';
            status.textContent = '';
        }, 2000);
    }

    copyButton.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(input.value);
            confirmCopy('Copied');
        } catch {
            confirmCopy('Could not copy. Show the password and copy it by hand.');
        }
    });

    modalElement.addEventListener('hidden.bs.modal', () => {
        clearTimeout(resetTimer);
        input.value = '';
        Modal.getInstance(modalElement)?.dispose();
        discard(modalElement);
    });

    Modal.getOrCreateInstance(modalElement).show();
}

// A page restored from the back-forward cache keeps its scripts' state; drop a window that was still on it when the page was left.
window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        document.querySelectorAll('[data-issued-password]').forEach((element) => {
            const modal = Modal.getInstance(element);

            if (modal) {
                modal.hide();
            } else {
                discard(element);
            }
        });
    }
});
