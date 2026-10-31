@props(['label'])

{{-- The handle that moves a row of a sortable admin list (a tbody marked data-sortable-list; resources/js/admin-widgets.js). --}}
<td class="sortable-handle-cell">
    <button type="button" class="sortable-handle" aria-label="Move {{ $label }}" title="Drag, or press the up and down arrow keys, to move this row">
        <x-icon name="grip" />
    </button>
</td>
