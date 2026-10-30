<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Enums\AuditAction;
use App\Enums\EmploymentType;
use App\Enums\InterviewStatus;
use App\Enums\Role;
use App\Models\ApplicantProfile;
use App\Models\Application;
use App\Models\Benefit;
use App\Models\College;
use App\Models\JobTitle;
use App\Models\Office;
use App\Models\RateTier;
use App\Models\User;
use App\Services\AcademicCalendar;
use App\Services\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * HR's view of recruitment: the pipeline (Applied, Demo Interview, Offered, Hired, Rejected, and Cancelled by the applicant), the
 * list of applications, and one application with its documents, applicant information, schedule, offer, and the next action.
 */
class RecruitmentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:'.implode(',', array_column(ApplicationStatus::cases(), 'value'))],
            'q' => ['nullable', 'string', 'max:100'],
            'interview' => ['nullable', 'in:upcoming,finished,none'],
            'college' => ['nullable', 'integer'],
            'office' => ['nullable', 'integer'],
            'applied_from' => ['nullable', 'date'],
            'applied_to' => ['nullable', 'date', 'after_or_equal:applied_from'],
            'sort' => ['nullable', 'in:name,position,date,status'],
            'dir' => ['nullable', 'in:asc,desc'],
        ]);

        $query = Application::query()
            ->with(['applicant.applicantProfile', 'jobTitle.college', 'jobTitle.office', 'interviews', 'documents'])
            ->when($filters['status'] ?? null, fn ($builder, $status) => $builder->where('status', $status))
            ->when($filters['q'] ?? null, fn ($builder, $term) => $builder->search($term))
            ->when($filters['interview'] ?? null, fn ($builder, $state) => match ($state) {
                'upcoming' => $builder->whereHas('interviews', fn ($interviews) => $interviews->where('status', InterviewStatus::Scheduled)),
                'finished' => $builder->whereHas('interviews', fn ($interviews) => $interviews->where('status', InterviewStatus::Completed)),
                default => $builder->whereDoesntHave('interviews'),
            })
            ->when($filters['college'] ?? null, fn ($builder, $college) => $builder->whereHas('jobTitle', fn ($titles) => $titles->where('college_id', $college)))
            ->when($filters['office'] ?? null, fn ($builder, $office) => $builder->whereHas('jobTitle', fn ($titles) => $titles->where('office_id', $office)))
            ->when($filters['applied_from'] ?? null, fn ($builder, $date) => $builder->whereDate('applied_on', '>=', $date))
            ->when($filters['applied_to'] ?? null, fn ($builder, $date) => $builder->whereDate('applied_on', '<=', $date));

        $direction = ($filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        match ($filters['sort'] ?? 'date') {
            'name' => $query->orderBy(ApplicantProfile::query()->select('last_name')->whereColumn('applicant_profiles.user_id', 'applications.user_id'), $direction),
            'position' => $query->orderBy(JobTitle::query()->select('title')->whereColumn('job_titles.id', 'applications.job_title_id'), $direction),
            'status' => $query->orderBy('status', $direction),
            default => $query->orderBy('applied_on', $direction)->orderBy('id', $direction),
        };

        $counts = Application::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('recruitment.index', [
            'applications' => $query->paginate(15)->withQueryString(),
            'filters' => $filters,
            'pipeline' => collect(ApplicationStatus::pipeline())->map(fn (ApplicationStatus $status): array => ['status' => $status, 'count' => (int) ($counts[$status->value] ?? 0)]),
            'total' => (int) $counts->sum(),
            'colleges' => College::query()->orderBy('name')->pluck('name', 'id'),
            'offices' => Office::query()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    /**
     * A CSV of the applications (with the same filters as the list), for the HR Admin. Generating it is logged.
     */
    public function report(Request $request, AuditLogger $audit): StreamedResponse
    {
        abort_unless($request->user()->isHrAdmin(), 403);

        $filters = $request->validate([
            'status' => ['nullable', 'in:'.implode(',', array_column(ApplicationStatus::cases(), 'value'))],
            'college' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
        $audit->log(AuditAction::Export, null, ['report' => 'recruitment', 'filters' => array_filter($filters)]);

        return response()->streamDownload(function () use ($filters): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Applicant', 'Position', 'College or Office', 'Applied on', 'Status', 'Demo interview', 'Reason']);

            Application::query()
                ->with(['applicant.applicantProfile', 'jobTitle.college', 'jobTitle.office', 'interviews'])
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->when($filters['college'] ?? null, fn ($query, $college) => $query->whereHas('jobTitle', fn ($titles) => $titles->where('college_id', $college)))
                ->when($filters['from'] ?? null, fn ($query, $date) => $query->whereDate('applied_on', '>=', $date))
                ->when($filters['to'] ?? null, fn ($query, $date) => $query->whereDate('applied_on', '<=', $date))
                ->orderBy('applied_on')->orderBy('id')
                ->chunk(200, function ($applications) use ($out): void {
                    foreach ($applications as $application) {
                        $interview = $application->interviews->last();
                        fputcsv($out, [$application->applicant->applicantProfile->full_name, $application->jobTitle->title, $application->jobTitle->college?->name ?? $application->jobTitle->office?->name, $application->applied_on->toDateString(), $application->status->label(), $interview?->starts_at->format('Y-m-d H:i'), $application->status_reason]);
                    }
                });

            fclose($out);
        }, 'recruitment-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function show(Request $request, Application $application, AcademicCalendar $calendar): View
    {
        $application->load([
            'applicant.applicantProfile', 'jobTitle.college', 'jobTitle.office', 'interviews.interviewer', 'interviews.scheduler',
            'offers.issuer', 'events.actor', 'documents', 'employee',
        ]);

        $interview = $application->activeInterview();
        $position = $application->jobTitle;
        $defaultType = EmploymentType::tryFrom((string) $position->employment_type) ?? EmploymentType::FullTime;

        return view('recruitment.show', [
            'application' => $application,
            'profile' => $application->applicant->applicantProfile,
            'interview' => $interview,
            'lastInterview' => $application->interviews->last(),
            'offer' => $application->offers->first(),
            'interviewers' => User::query()->whereIn('role', [Role::HrAdmin, Role::HrStaff])->where('is_active', true)->orderBy('name')->get(),
            'benefits' => Benefit::query()->where('is_active', true)->orderBy('name')->get(),
            'pay' => RateTier::scheduleOn()[$position->salary_grade] ?? null,
            'defaultType' => $defaultType,
            'defaultStart' => now()->addWeek()->toDateString(),
            'defaultEnd' => $position->contractEndDate()?->toDateString(),
            'termLabel' => $position->contract_term && $position->academic_year ? $position->contract_term->label().', AY '.$calendar->yearLabel($position->academic_year) : null,
            'academicYears' => $calendar->selectableYears(),
            'canOffer' => $request->user()->isHrAdmin(),
        ]);
    }
}
