<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Applicant\ApplicationController as ApplicantApplicationController;
use App\Http\Controllers\Applicant\CalendarController as ApplicantCalendarController;
use App\Http\Controllers\Applicant\DashboardController as ApplicantDashboardController;
use App\Http\Controllers\Applicant\ProfileController as ApplicantProfileController;
use App\Http\Controllers\Applicant\RegisterController as ApplicantRegisterController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CalendarEventController;
use App\Http\Controllers\CollegeController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EmergencyContactController;
use App\Http\Controllers\EmployeeAssignmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeDocumentController;
use App\Http\Controllers\EmployeeLeaveController;
use App\Http\Controllers\EmployeeOfferController;
use App\Http\Controllers\EmployeePortalController;
use App\Http\Controllers\EmployeeReportController;
use App\Http\Controllers\EmployeeStatusController;
use App\Http\Controllers\ExportLogController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\GovernmentIdController;
use App\Http\Controllers\InterviewController;
use App\Http\Controllers\JobPositionController;
use App\Http\Controllers\JobTitleController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OfficeController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PasswordChangeController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\RateTierController;
use App\Http\Controllers\RecruitmentActionController;
use App\Http\Controllers\RecruitmentController;
use App\Http\Controllers\TwoFactorSetupController;
use Illuminate\Support\Facades\Route;

// The EMS opens on the sign-in page; PLP Careers, the public careers website, is linked from it.
Route::get('/', fn () => redirect()->route('login'))->name('home');

// Staff cannot reset their own password. This page tells them to email PLP HR, which issues a temporary password.
Route::get('/forgot-password', ForgotPasswordController::class)->name('password.help');

// The sign-in page, sign-out, two-factor challenge, and password confirmation are Laravel Fortify routes
// (app/Providers/FortifyServiceProvider.php). HR, employees, and applicants all sign in there.

// Pages for any signed-in staff account, before the temporary-password and two-factor checks: choosing a password and
// setting up the authenticator app must stay reachable.
Route::middleware('account')->group(function () {
    Route::get('/account/password', [PasswordChangeController::class, 'show'])->name('password.change');

    Route::put('/account/password', [PasswordChangeController::class, 'update'])->name('password.change.update');

    Route::get('/account/setup', [OnboardingController::class, 'show'])->name('onboarding.show')->middleware('password.fresh');
    Route::post('/account/setup', [OnboardingController::class, 'store'])->name('onboarding.store')->middleware('password.fresh');

    Route::get('/account/two-factor', [TwoFactorSetupController::class, 'show'])->name('two-factor.setup');
});

// HR pages (HR Admin and HR Staff), all from the database.
Route::middleware(['staff', 'role:hr_admin,hr_staff'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Employees come from the database. Everyone in HR reads, adds, and edits them; the actions that change an
    // assignment, archive a record, or reveal a government ID are limited to the HR Admin by EmployeePolicy.
    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::get('/employees/suggest', [EmployeeController::class, 'suggest'])->name('employees.suggest');
    Route::get('/employees/create', [EmployeeController::class, 'create'])->name('employees.create');
    Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');

    Route::prefix('/employees/{employee}')->where(['employee' => '[0-9]{2}-[0-9]{5}'])->group(function () {
        Route::get('/', [EmployeeController::class, 'show'])->name('employees.show');
        Route::get('/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
        Route::put('/', [EmployeeController::class, 'update'])->name('employees.update');
        Route::post('/transfer', [EmployeeAssignmentController::class, 'transfer'])->name('employees.transfer');
        Route::post('/renew', [EmployeeAssignmentController::class, 'renew'])->name('employees.renew');
        Route::post('/documents', [EmployeeDocumentController::class, 'store'])->name('employees.documents.store');
        Route::post('/offers/{offer}/withdraw', [EmployeeAssignmentController::class, 'withdraw'])->name('employees.offers.withdraw');
        Route::put('/status', [EmployeeStatusController::class, 'update'])->name('employees.status');
        Route::post('/emergency-contacts', [EmergencyContactController::class, 'store'])->name('emergency-contacts.store');
        Route::delete('/emergency-contacts/{contact}', [EmergencyContactController::class, 'destroy'])->name('emergency-contacts.destroy');
        Route::post('/government-ids/{type}/reveal', [GovernmentIdController::class, 'reveal'])->name('government-ids.reveal');
    });

    // Colleges, offices, job titles, and pay rates: everyone in HR reads them; the HR Admin changes them (the
    // controllers and form requests check the manage-reference-data gate).
    Route::get('/colleges', [CollegeController::class, 'index'])->name('colleges.index');
    Route::post('/colleges', [CollegeController::class, 'store'])->name('colleges.store');
    Route::get('/colleges/{college}/edit', [CollegeController::class, 'edit'])->name('colleges.edit');
    Route::put('/colleges/{college}', [CollegeController::class, 'update'])->name('colleges.update');

    Route::get('/offices', [OfficeController::class, 'index'])->name('offices.index');
    Route::post('/offices', [OfficeController::class, 'store'])->name('offices.store');
    Route::get('/offices/{office}/edit', [OfficeController::class, 'edit'])->name('offices.edit');
    Route::put('/offices/{office}', [OfficeController::class, 'update'])->name('offices.update');

    Route::get('/job-titles', [JobTitleController::class, 'index'])->name('job-titles.index');
    Route::get('/job-titles/create', [JobTitleController::class, 'create'])->name('job-titles.create');
    Route::post('/job-titles', [JobTitleController::class, 'store'])->name('job-titles.store');
    Route::get('/job-titles/{jobTitle}/edit', [JobTitleController::class, 'edit'])->name('job-titles.edit');
    Route::put('/job-titles/{jobTitle}', [JobTitleController::class, 'update'])->name('job-titles.update');

    Route::get('/rates', [RateTierController::class, 'index'])->name('rates.index');
    Route::post('/rates', [RateTierController::class, 'store'])->name('rates.store');

    // Recruitment: the pipeline, one application at a time (documents, schedule, offer), and the demo-interview actions. Offers and
    // hiring are limited to the HR Admin inside the actions.
    Route::get('/recruitment', [RecruitmentController::class, 'index'])->name('recruitment.index');
    Route::prefix('recruitment')->name('recruitment.')->group(function () {
        Route::get('/applications/{application}', [RecruitmentController::class, 'show'])->whereNumber('application')->name('show');
        Route::post('/applications/{application}/interviews', [InterviewController::class, 'store'])->name('interviews.store');
        Route::post('/applications/{application}/reject', [RecruitmentActionController::class, 'reject'])->name('reject');
        Route::post('/applications/{application}/reopen', [RecruitmentActionController::class, 'reopen'])->name('reopen');
        Route::post('/applications/{application}/offers', [RecruitmentActionController::class, 'issueOffer'])->name('offers.store');
        Route::put('/applications/{application}/offers/{offer}', [RecruitmentActionController::class, 'updateOffer'])->name('offers.update');
        Route::post('/applications/{application}/hire', [RecruitmentActionController::class, 'hire'])->name('hire');
        Route::patch('/interviews/{interview}', [InterviewController::class, 'reschedule'])->name('interviews.reschedule');
        Route::patch('/interviews/{interview}/interviewer', [InterviewController::class, 'changeInterviewer'])->name('interviews.interviewer');
        Route::post('/interviews/{interview}/cancel', [InterviewController::class, 'cancel'])->name('interviews.cancel');
        Route::post('/interviews/{interview}/complete', [InterviewController::class, 'complete'])->name('interviews.complete');
    });

    // The job postings: job titles HR has opened. Everyone in HR reads them; the HR Admin opens, edits, and closes them.
    Route::get('/job-positions', [JobPositionController::class, 'index'])->name('job-positions.index');
    Route::get('/job-positions/open', [JobPositionController::class, 'open'])->name('job-positions.open');
    Route::get('/job-positions/{jobTitle}/edit', [JobPositionController::class, 'edit'])->name('job-positions.edit');
    Route::put('/job-positions/{jobTitle}', [JobPositionController::class, 'update'])->name('job-positions.update');
    Route::post('/job-positions/{jobTitle}/close', [JobPositionController::class, 'close'])->name('job-positions.close');

    // The calendar of interviews, HR events, and approved leave; interviews are moved through the recruitment routes.
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::post('/calendar/events', [CalendarEventController::class, 'store'])->name('calendar.events.store');
    Route::put('/calendar/events/{event}', [CalendarEventController::class, 'update'])->name('calendar.events.update');
    Route::patch('/calendar/events/{event}/move', [CalendarEventController::class, 'move'])->name('calendar.events.move');
    Route::post('/calendar/events/{event}/cancel', [CalendarEventController::class, 'cancel'])->name('calendar.events.cancel');

    // Leave requests come from the database. HR Admin and HR Staff see them; only an HR Admin approves or rejects (never their own); a decision is final.
    Route::get('/leave-requests', [LeaveRequestController::class, 'index'])->name('leave.index');
    Route::post('/leave-requests/{leaveRequest}/approve', [LeaveRequestController::class, 'approve'])->name('leave.approve');
    Route::post('/leave-requests/{leaveRequest}/reject', [LeaveRequestController::class, 'reject'])->name('leave.reject');

    // HR Staff and HR Admin file their own leave here; only an HR Admin (never themselves) decides it.
    Route::get('/my-leave', [EmployeeLeaveController::class, 'index'])->name('my-leave');
    Route::post('/my-leave', [EmployeeLeaveController::class, 'store'])->name('my-leave.store');
    Route::post('/my-leave/{leaveRequest}/cancel', [EmployeeLeaveController::class, 'cancel'])->name('my-leave.cancel');

    // The signed-in HR user's own profile.
    Route::view('/profile', 'profile.show')->name('profile.show');

    // The HR user's Account Settings.
    Route::view('/account-settings', 'account.settings')->name('account.settings');
});

// HR Admin only: reports and exports, the archive (where records are deleted for good), the audit ledger, and account
// administration. HR Staff cannot reach these.
Route::middleware(['staff', 'role:hr_admin'])->group(function () {
    // Starts from the Employees list's tab, search, filters, and sort, carried in the query string.
    Route::get('/employees/report', [EmployeeReportController::class, 'show'])->name('employees.report');

    // Every report export is written to the audit log before the file is built.
    Route::post('/exports', [ExportLogController::class, 'store'])->name('exports.store');

    // Employees who were archived (Inactive, Resigned, Retired, or Terminated). Records are never deleted; they can be
    // restored from here.
    Route::get('/employees/archived', [EmployeeStatusController::class, 'index'])->name('employees.archived');

    // Recruitment and audit reports, as CSV files with the page's filters; each download is logged.
    Route::get('/recruitment/report', [RecruitmentController::class, 'report'])->name('recruitment.report');
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit.index');
    Route::get('/audit-logs/report', [AuditLogController::class, 'report'])->name('audit.report');

    // Contracts that are ending: send a new contract offer or terminate.
    Route::get('/contracts', [ContractController::class, 'index'])->name('contracts.index');
    Route::prefix('accounts')->name('accounts.')->controller(AccountController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::post('/{user}/reset-password', 'resetPassword')->name('reset-password');
        Route::post('/{user}/deactivate', 'deactivate')->name('deactivate');
        Route::post('/{user}/reactivate', 'reactivate')->name('reactivate');
        Route::put('/{user}/role', 'updateRole')->name('role');
    });
});

// In-app notifications for every signed-in role.
Route::middleware('staff')->prefix('notifications')->name('notifications.')->controller(NotificationController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/read-all', 'readAll')->name('read-all');
    Route::post('/{notification}/open', 'open')->name('open');
});

// The public Careers website: open positions and job details anyone can browse without an account, from the database.
Route::get('/careers', [PositionController::class, 'index'])->name('careers.index');
Route::get('/careers/{position}', [PositionController::class, 'show'])->where('position', '[0-9]+')->name('careers.show');

// Creating an applicant account (guests only) and the old portal address.
Route::redirect('/applicant', '/applicant/dashboard');

Route::middleware('guest')->prefix('applicant')->name('applicant.')->group(function () {
    Route::get('/register', [ApplicantRegisterController::class, 'show'])->name('register');
    Route::post('/register', [ApplicantRegisterController::class, 'store'])->name('register.store');
    Route::redirect('/portal', '/applicant/register')->name('portal');
});

// The Applicant interface, for a signed-in applicant: profile (personal and contact information, once), open positions, the
// application form (resume and portfolio per application), applications with the demo interview and the offer, and a calendar.
Route::middleware(['staff', 'role:applicant'])->prefix('applicant')->name('applicant.')->group(function () {
    Route::get('/dashboard', [ApplicantDashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ApplicantProfileController::class, 'show'])->name('profile');
    Route::put('/profile', [ApplicantProfileController::class, 'update'])->name('profile.update');

    Route::get('/calendar', [ApplicantCalendarController::class, 'index'])->name('calendar');

    Route::get('/positions', [PositionController::class, 'index'])->name('positions');
    Route::get('/positions/{position}', [PositionController::class, 'show'])->where('position', '[0-9]+')->name('positions.show');

    Route::get('/apply', [ApplicantApplicationController::class, 'create'])->name('apply');
    Route::post('/applications', [ApplicantApplicationController::class, 'store'])->name('applications.store');
    Route::get('/applications', [ApplicantApplicationController::class, 'index'])->name('applications');
    Route::get('/applications/{application}', [ApplicantApplicationController::class, 'show'])->whereNumber('application')->name('applications.show');
    Route::post('/applications/{application}/cancel', [ApplicantApplicationController::class, 'cancel'])->name('applications.cancel');
    Route::post('/applications/{application}/interviews/{interview}/reschedule', [ApplicantApplicationController::class, 'requestReschedule'])->name('interviews.reschedule');
    Route::post('/applications/{application}/offers/{offer}/accept', [ApplicantApplicationController::class, 'acceptOffer'])->name('offers.accept');
    Route::post('/applications/{application}/offers/{offer}/decline', [ApplicantApplicationController::class, 'declineOffer'])->name('offers.decline');
});

// Uploaded documents (resumes, portfolios, employee files), opened by the people allowed to see them.
Route::middleware('staff')->get('/documents/{uuid}', [DocumentController::class, 'show'])->name('documents.show');
// The Employee interface: a separate dashboard and sidebar from the HR pages above, from the database. Only the Employee role reaches it.
Route::middleware(['staff', 'role:employee'])->group(function () {
    Route::redirect('/employee', '/employee/dashboard');

    Route::prefix('employee')->name('employee.')->group(function () {
        Route::get('/dashboard', [EmployeePortalController::class, 'dashboard'])->name('dashboard');

        Route::get('/profile', [EmployeePortalController::class, 'profile'])->name('profile');
        Route::put('/profile', [EmployeePortalController::class, 'updateProfile'])->name('profile.update');
        Route::post('/emergency-contacts', [EmployeePortalController::class, 'storeEmergencyContact'])->name('emergency-contacts.store');
        Route::delete('/emergency-contacts/{contact}', [EmployeePortalController::class, 'destroyEmergencyContact'])->name('emergency-contacts.destroy');

        Route::get('/files', [EmployeePortalController::class, 'files'])->name('files');
        Route::post('/files', [EmployeePortalController::class, 'storeFile'])->name('files.store');

        Route::get('/benefits', [EmployeePortalController::class, 'benefits'])->name('benefits');

        Route::get('/directory', [EmployeePortalController::class, 'directory'])->name('directory');

        Route::get('/calendar', [EmployeePortalController::class, 'calendar'])->name('calendar');

        // Contract offers and changes of assignment the employee accepts (signs) or declines.
        Route::get('/offers', [EmployeeOfferController::class, 'index'])->name('offers');
        Route::get('/offers/{offer}', [EmployeeOfferController::class, 'show'])->name('offers.show');
        Route::post('/offers/{offer}/accept', [EmployeeOfferController::class, 'accept'])->name('offers.accept');
        Route::post('/offers/{offer}/decline', [EmployeeOfferController::class, 'decline'])->name('offers.decline');
        // The employee's own leave, from the database: credits, requests, filing, and cancelling a pending request.
        Route::get('/leave', [EmployeeLeaveController::class, 'index'])->name('leave');
        Route::post('/leave', [EmployeeLeaveController::class, 'store'])->name('leave.store');
        Route::post('/leave/{leaveRequest}/cancel', [EmployeeLeaveController::class, 'cancel'])->name('leave.cancel');
    });
});
