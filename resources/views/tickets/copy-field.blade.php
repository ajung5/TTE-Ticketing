<div class="field-value applicant-copy-field">
    @if (filled($value) && $value !== '-')
        <span id="{{ $fieldId }}">{{ $value }}</span>
        <button
            type="button"
            class="btn btn-outline applicant-copy-button"
            data-copy-target="{{ $fieldId }}"
            data-copy-label="{{ $label }}"
            aria-label="Salin {{ $label }}"
            title="Salin {{ $label }}">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                <rect x="8" y="8" width="12" height="13" rx="2" />
                <path d="M15 8V5a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h2" />
            </svg>
        </button>
    @else
        <span>-</span>
    @endif
</div>
