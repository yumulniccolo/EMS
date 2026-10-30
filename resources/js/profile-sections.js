import { Modal } from 'bootstrap';

// My Profile (employee/profile.blade.php): each part of the record is a window ([data-profile-section="key"]).
// - A form sent from a window remembers that window, and the returned page opens it again, so the saved message or the
//   validation errors (with what was typed) show where the person was working.
// - #key in the address (the header menu's Security link is #security) opens that window, also when only the hash changes.
const root = document.querySelector('[data-profile-sections]');
const reopenKey = 'ems-profile-section';

function openSection(key) {
    const modalElement = document.querySelector(`[data-profile-section="${CSS.escape(key)}"]`);

    if (modalElement) {
        Modal.getOrCreateInstance(modalElement).show();
    }

    return Boolean(modalElement);
}

if (root) {
    document.querySelectorAll('[data-profile-section] form').forEach((form) => {
        form.addEventListener('submit', () => {
            try {
                sessionStorage.setItem(reopenKey, form.closest('[data-profile-section]').getAttribute('data-profile-section'));
            } catch {
                // Storage unavailable; the window simply stays closed after the page returns.
            }
        });
    });

    let pending = null;

    try {
        pending = sessionStorage.getItem(reopenKey);
        sessionStorage.removeItem(reopenKey);
    } catch {
        pending = null;
    }

    if (!(pending && openSection(pending)) && window.location.hash) {
        openSection(window.location.hash.slice(1));
    }

    window.addEventListener('hashchange', () => {
        openSection(window.location.hash.slice(1));
    });
}
