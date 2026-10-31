@extends('layouts.employee')

@section('title', $offer->kind->label().' · '.config('app.name'))

@section('content')
    <div class="d-grid gap-4">
        <div><a class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" href="{{ route('employee.offers') }}"><x-icon name="back" /> Back</a></div>

        <section class="card employee-form-card">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <h1 class="page-title">{{ $offer->kind->label() }}</h1>
                <span class="status-pill">{{ $offer->status->label() }}</span>
            </div>
            @include('offers._body', ['offer' => $offer])
            @if ($offer->signed_name)
                <p class="mt-3 mb-0"><strong>Signed by {{ $offer->signed_name }}</strong> on {{ $offer->responded_at->format('M j, Y g:i A') }}.</p>
            @endif
        </section>

        @if ($offer->isPending())
            <section class="card employee-form-card">
                <form method="post" action="{{ route('employee.offers.accept', $offer) }}" class="d-grid gap-3" novalidate>
                    @csrf
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="agree" name="agree" value="1">
                        <label class="form-check-label" for="agree">I have read and understood this agreement and its terms.</label>
                    </div>
                    <x-field name="signed_name" label="Type your full name to sign" required autocomplete="off" hint="Your typed name is your signature and confirmation." />
                    <div class="d-flex justify-content-end"><button type="submit" class="btn btn-primary">Accept and Sign</button></div>
                </form>
                <form method="post" action="{{ route('employee.offers.decline', $offer) }}" class="d-flex gap-2 mt-2" data-confirm-title="Decline offer"
                    data-confirm="Decline this offer? You stay as you are until your current contract ends." data-confirm-button="Decline" data-confirm-cancel="Keep Offer">
                    @csrf
                    <label class="visually-hidden" for="decline-reason">Reason (optional)</label>
                    <input type="text" class="form-control" id="decline-reason" name="reason" placeholder="Reason for declining (optional)">
                    <button type="submit" class="btn btn-outline-danger text-nowrap">Decline</button>
                </form>
                <p class="form-text mb-0 mt-2">If you decline, you stay as you are until your current contract ends.</p>
            </section>
        @endif
    </div>
@endsection
