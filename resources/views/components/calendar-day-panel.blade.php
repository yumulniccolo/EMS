@props(['details' => false])

{{--
    The calendar's right-hand column: the events of the selected day, filled in by resources/js/page-behaviors.js from the
    chips already on the month grid. Click a day to select it. On the HR calendar choosing an event opens its details
    window; on the employee calendar (details) it shows the event in the card underneath.
--}}
<div class="col-xl-4 d-grid gap-4" data-day-panel>
    <section class="card employee-form-card" aria-labelledby="calendar-day-title">
        <h2 class="employee-form-section" id="calendar-day-title" data-day-title></h2>
        <div class="list-group list-group-flush calendar-day-list" data-day-events></div>
        <p class="text-body-secondary mb-0 d-none" data-day-empty>Nothing is scheduled for this day.</p>
    </section>

    @if ($details)
        <section class="card employee-form-card" aria-labelledby="calendar-details-title">
            <h2 class="employee-form-section" id="calendar-details-title">Details</h2>
            <dl class="row row-cols-1 g-2 mb-0 employee-details d-none" data-day-details>
                <div class="col"><dt>What</dt><dd class="mb-0" data-day-detail="title"></dd></div>
                <div class="col"><dt>When</dt><dd class="mb-0" data-day-detail="when"></dd></div>
                <div class="col"><dt>Status</dt><dd class="mb-0" data-day-detail="status"></dd></div>
                <div class="col"><dt>Where</dt><dd class="mb-0" data-day-detail="place"></dd></div>
            </dl>
            <p class="text-body-secondary mb-0" data-day-details-empty>Select an event to view its details.</p>
        </section>
    @endif
</div>
