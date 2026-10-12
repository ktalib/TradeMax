@if (session('status'))
    <div class="mb-5 flex gap-2.5 rounded-[var(--radius)] bg-brand-soft px-3.5 py-3 text-[13px]" role="status">
        <x-ui.icon name="info" :size="16" class="mt-0.5 flex-none text-brand" />
        <span>{{ session('status') }}</span>
    </div>
@endif
@error('captcha')
    <div class="mb-5 rounded-[var(--radius)] bg-down-soft px-3.5 py-3 text-[13px] text-down" role="alert">{{ $message }}</div>
@enderror
