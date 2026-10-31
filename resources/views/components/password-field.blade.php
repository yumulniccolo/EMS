@props(['name', 'label', 'required' => false, 'hint' => null, 'autocomplete' => 'new-password', 'iconOnly' => false])

{{-- A password input with a Show/Hide control. The button points at the input so the shared password-toggle.js can find it. With iconOnly the button shows just the eye; "Show"/"Hide" stays in the text read aloud. --}}
@php
    $inputId = 'field-'.str_replace(['[', ']'], ['-', ''], $name);
@endphp
<label for="{{ $inputId }}" class="form-label{{ $required ? ' is-required' : '' }}">{{ $label }}</label>
<div class="login-password position-relative">
    <input type="password" class="form-control{{ $errors->has($name) ? ' is-invalid' : '' }}{{ $iconOnly ? ' pe-5' : '' }}" id="{{ $inputId }}"
        name="{{ $name }}" autocomplete="{{ $autocomplete }}" @required($required)
        @if ($hint) aria-describedby="{{ $inputId }}-hint" @endif {{ $attributes }}>
    <button type="button" class="login-password-toggle" aria-controls="{{ $inputId }}" aria-pressed="false" data-password-toggle>
        <x-icon name="eye" />
        <span data-password-toggle-label @class(['visually-hidden' => $iconOnly])>Show</span><span class="visually-hidden"> password</span>
    </button>
</div>
@if ($hint)
    <div class="form-text" id="{{ $inputId }}-hint">{{ $hint }}</div>
@endif
@error($name)
    <div class="invalid-feedback">{{ $message }}</div>
@enderror
