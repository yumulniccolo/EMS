import { showToast } from './toast.js';

// Draft applications, kept in this browser (localStorage) per applicant and position; nothing is sent to the server until the
// applicant presses Submit Application, so the server's Submit checks are untouched.
// - Apply form ([data-application-draft]): Save Draft stores every typed field (files cannot be kept) and shows a toast. Opening
//   the form again fills it from the draft, unless the server sent the form back with what was just typed (validation errors).
// - Submit marks the position; the draft is deleted only on the page that follows a successful submission (the application
//   page, [data-submitted-position]). A refused submission returns to the form and the draft stays.
// - My Applications ([data-draft-list]): each draft is added as a Draft row that opens the Apply form for its position.
const storagePrefix = 'ems-application-drafts:';
const submittingKey = 'ems-application-submitting';

function readDrafts(owner) {
    try {
        return JSON.parse(localStorage.getItem(storagePrefix + owner) ?? '{}') ?? {};
    } catch {
        return {};
    }
}

function writeDrafts(owner, drafts) {
    try {
        if (Object.keys(drafts).length === 0) {
            localStorage.removeItem(storagePrefix + owner);
        } else {
            localStorage.setItem(storagePrefix + owner, JSON.stringify(drafts));
        }

        return true;
    } catch {
        return false;
    }
}

function draftFields(form) {
    const fields = {};

    form.querySelectorAll('input[name], textarea[name], select[name]').forEach((field) => {
        if (field.type === 'file' || field.type === 'hidden' || field.name === '_token') {
            return;
        }

        fields[field.name] = field.type === 'checkbox' ? field.checked : field.value;
    });

    return fields;
}

function fillFields(form, fields) {
    Object.entries(fields).forEach(([name, value]) => {
        const field = form.querySelector(`[name="${CSS.escape(name)}"]`);

        if (!field || field.type === 'file') {
            return;
        }

        if (field.type === 'checkbox') {
            field.checked = Boolean(value);
        } else {
            field.value = value;
        }
    });
}

// Apply for a Position
const form = document.querySelector('form[data-application-draft]');

if (form) {
    const owner = form.dataset.draftOwner;
    const position = form.dataset.draftPosition;
    const draft = readDrafts(owner)[position];

    if (draft && form.dataset.draftHasOld !== '1') {
        fillFields(form, draft.fields ?? {});
        document.querySelector('[data-draft-restored]')?.classList.remove('d-none');
    }

    form.querySelector('[data-save-draft]')?.addEventListener('click', () => {
        const drafts = readDrafts(owner);

        drafts[position] = {
            title: form.dataset.draftTitle,
            unit: form.dataset.draftUnit,
            savedAt: new Date().toISOString(),
            fields: draftFields(form),
        };

        if (writeDrafts(owner, drafts)) {
            showToast('Draft application saved successfully.');
        } else {
            showToast('The draft could not be saved in this browser.', { isError: true });
        }
    });

    form.addEventListener('submit', () => {
        try {
            sessionStorage.setItem(submittingKey, JSON.stringify({ owner, position }));
        } catch {
            // Without storage the draft simply stays until the applicant deletes it by submitting again.
        }
    });
}

// The page after a successful submission: the draft for that position is no longer needed.
let submitting = null;

try {
    submitting = JSON.parse(sessionStorage.getItem(submittingKey) ?? 'null');
    sessionStorage.removeItem(submittingKey);
} catch {
    submitting = null;
}

const submittedPage = document.querySelector('[data-submitted-position]');

if (submitting && submittedPage && submittedPage.dataset.submittedPosition === String(submitting.position)) {
    const drafts = readDrafts(submitting.owner);
    delete drafts[submitting.position];
    writeDrafts(submitting.owner, drafts);
}

// My Applications: Draft rows above the submitted ones. A position with an open submitted application shows no draft.
const list = document.querySelector('[data-draft-list]');
const template = document.querySelector('template[data-draft-row-template]');

if (list && template) {
    const drafts = readDrafts(list.dataset.draftOwner);
    const submittedPositions = new Set(Array.from(list.querySelectorAll('[data-application-open-position]'), (row) => row.dataset.applicationOpenPosition));
    const entries = Object.entries(drafts)
        .filter(([position]) => !submittedPositions.has(position))
        .sort(([, a], [, b]) => String(b.savedAt).localeCompare(String(a.savedAt)));

    entries.forEach(([position, draft]) => {
        const url = new URL(list.dataset.applyUrl, window.location.href);
        url.searchParams.set('position', position);

        const row = template.content.firstElementChild.cloneNode(true);
        row.dataset.rowHref = url.toString();
        row.querySelector('[data-draft-link]').href = url.toString();
        row.querySelector('[data-draft-link]').textContent = draft.title ?? 'Draft application';
        row.querySelector('[data-draft-unit]').textContent = draft.unit ?? '';
        row.querySelector('[data-draft-saved]').textContent = draft.savedAt
            ? `Saved ${new Date(draft.savedAt).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })}`
            : '';
        list.prepend(row);
    });

    if (entries.length > 0) {
        list.querySelector('[data-draft-empty]')?.remove();
    }
}
