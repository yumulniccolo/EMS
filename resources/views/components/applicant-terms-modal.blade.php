@props(['required' => false, 'cancelUrl' => null])

{{--
    The Terms and Conditions with the Privacy Notice, accepted before Create Account opens (resources/js/applicant-terms.js).
    On the Careers pages a Create Account or Apply Now link marked data-terms-gate opens it; Agree and Continue then goes on to
    that link's address (keeping the chosen position). On the Create Account page itself it opens by itself when the terms were
    not accepted yet (required); closing it there goes back to cancelUrl.
--}}
<div class="modal fade" id="applicant-terms-modal" tabindex="-1" aria-labelledby="applicant-terms-title" aria-hidden="true"
    data-applicant-terms @if ($required) data-terms-required data-bs-backdrop="static" data-terms-cancel-url="{{ $cancelUrl }}" @endif>
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="applicant-terms-title">Terms and Conditions and Privacy Notice</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <ol class="small">
                    <li>The Applicant Portal lets you create an account, apply for open positions, and follow your applications.</li>
                    <li>Give true, complete, and current information. False or misleading information may disqualify your application.</li>
                    <li>Keep your login details to yourself. You are responsible for what is done with your account.</li>
                    <li>Applying does not guarantee a demo interview or a job offer. HR decides how each application moves forward.</li>
                    <li>Interview schedules are set by HR and may change. Check your Calendar and Applications pages for the latest.</li>
                </ol>
                <p class="small mb-3">
                    We collect the personal information you give, such as your name, contact details, birthdate, and the documents you attach, to assess
                    your application, contact you, and schedule your interview. Only the HR staff who handle recruitment can view it. If you are hired,
                    the details are used to start your employee record. This follows the Data Privacy Act of 2012 (Republic Act No. 10173).
                </p>

                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" id="applicant-terms-agree" data-terms-agree>
                    <label class="form-check-label" for="applicant-terms-agree">I have read and agree to the Terms and Conditions and the Privacy Notice.</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" data-terms-continue disabled>Agree and Continue</button>
            </div>
        </div>
    </div>
</div>
