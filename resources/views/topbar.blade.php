@if (filament()->auth()->check() && filament()->auth()->user()?->can(config('filament-access-inspector.permission', 'inspect_access')))
    <div class="flex items-center"
        x-data="{
            status: 'loading',
            panelHtml: '',
            timer: null,
            get busy() { return this.status === 'loading' },
            init() { this.waitForResult() },
            destroy() { clearTimeout(this.timer) },
            waitForResult() {
                clearTimeout(this.timer);
                this.status = 'loading';
                this.timer = setTimeout(() => {
                    this.status = 'failed';
                    this.panelHtml = this.$refs.failure.innerHTML;
                }, 30000);
            },
            receive(result) {
                if (result.pageUrl !== window.location.origin + window.location.pathname) return;
                clearTimeout(this.timer);
                this.panelHtml = result.html;
                this.status = result.status;
            },
            recheck() {
                if (this.busy) return;
                this.waitForResult();
                this.$dispatch('access-inspector-recheck');
            }
        }"
        x-on:access-inspector-loaded.window="receive($event.detail)"
        x-on:livewire:navigating.window="panelHtml = ''; waitForResult()"
    >
        <template x-ref="failure">
            <div class="space-y-2 p-4 text-sm text-gray-600 dark:text-gray-300">
                <p>The access check did not finish. Try again.</p>
                <button type="button" x-on:click.stop="recheck()" x-bind:disabled="busy" class="font-medium">Recheck</button>
            </div>
        </template>

        <x-filament::dropdown placement="bottom-end" width="md" :size="true" :shift="true" :available-width="true" :available-height="true" max-height="calc(100vh - 5rem)">
            <x-slot name="trigger">
                <button type="button" aria-label="Access inspector" title="Access inspector" disabled
                    x-bind:disabled="busy" x-bind:aria-busy="busy"
                    class="fi-icon-btn relative flex h-10 w-10 items-center justify-center rounded-lg outline-none transition duration-75 focus-visible:ring-2 disabled:cursor-wait disabled:opacity-50"
                    x-bind:class="status === 'completed' ? 'fi-color-custom fi-color-primary text-custom-500 hover:text-custom-600 dark:text-custom-400' : 'text-gray-400 dark:text-gray-500'"
                    style="{{ \Filament\Support\get_color_css_variables('primary', shades: [300, 400, 500, 600], alias: 'icon-button') }}">
                    <x-filament::icon icon="heroicon-o-shield-check" class="fi-icon-btn-icon h-8 w-8" style="width: 2rem; height: 2rem" />
                </button>
            </x-slot>
            <div x-html="panelHtml"></div>
        </x-filament::dropdown>
    </div>
@endif