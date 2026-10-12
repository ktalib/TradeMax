{{-- One instance lives in the layout. Raise a toast with $dispatch('toast', { message, tone }). --}}
<div class="toasts" aria-live="polite" x-data>
    <template x-for="t in $store.toasts.items" :key="t.id">
        <div class="toast" x-transition>
            <span :style="`color: var(--${t.tone === 'danger' ? 'down' : t.tone === 'warning' ? 'warning' : 'up'})`">
                <x-ui.icon name="check" :size="16" />
            </span>
            <span x-text="t.message"></span>
            <button type="button" class="icon-btn" style="width:28px;height:28px;margin-inline-start:4px" @click="$store.toasts.dismiss(t.id)" aria-label="{{ __('Dismiss') }}"><x-ui.icon name="close" :size="14" /></button>
        </div>
    </template>
</div>
