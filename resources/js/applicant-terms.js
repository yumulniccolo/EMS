import { Modal } from 'bootstrap';

// The Terms and Conditions window before Create Account (see components/applicant-terms-modal.blade.php).
// - A link marked data-terms-gate (Create Account, or Apply Now for a visitor) opens the window instead of the page. Agree and
//   Continue then goes to the link's address with terms=accepted added, so a chosen position stays in the address.
// - On the Create Account page, when the terms were not accepted yet (data-terms-required), the window opens by itself. Agreeing
//   fills the form's hidden terms field; closing it without agreeing goes back to the Careers website.
// Agree and Continue stays disabled until the box is checked; the box is cleared each time the window opens.
const modalElement = document.querySelector('[data-applicant-terms]');

if (modalElement) {
    const agreeBox = modalElement.querySelector('[data-terms-agree]');
    const continueButton = modalElement.querySelector('[data-terms-continue]');
    const isRequired = modalElement.hasAttribute('data-terms-required');
    const modal = Modal.getOrCreateInstance(modalElement);
    let pendingUrl = null;
    let isAccepted = false;

    agreeBox.addEventListener('change', () => {
        continueButton.disabled = !agreeBox.checked;
    });

    modalElement.addEventListener('show.bs.modal', () => {
        agreeBox.checked = false;
        continueButton.disabled = true;
        isAccepted = false;
    });

    continueButton.addEventListener('click', () => {
        if (!agreeBox.checked) {
            return;
        }

        isAccepted = true;

        if (pendingUrl) {
            const url = new URL(pendingUrl, window.location.href);
            url.searchParams.set('terms', 'accepted');
            window.location.assign(url.toString());

            return;
        }

        document.querySelectorAll('[data-terms-input]').forEach((input) => {
            input.value = '1';
        });
        modal.hide();
    });

    modalElement.addEventListener('hidden.bs.modal', () => {
        pendingUrl = null;

        if (isRequired && !isAccepted) {
            window.location.assign(modalElement.getAttribute('data-terms-cancel-url'));
        }
    });

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[data-terms-gate]');

        // A new-tab or modified click opens the Create Account page, which asks for the terms itself.
        if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        event.preventDefault();
        pendingUrl = link.href;
        modal.show();
    });

    if (isRequired) {
        modal.show();
    }
}
