@if (filament()->auth()->check() && filament()->auth()->user()?->can(config('filament-access-inspector.permission', 'inspect_access')))
    <div class="flex items-center"
        x-data="{
            status: 'loading',
            allowedCount: null,
            panelHtml: '',
            timer: null,
            panelMaxHeight: 576,
            get busy() { return this.status === 'loading' },
            get hasCount() { return this.status === 'completed' && Number.isInteger(this.allowedCount) && this.allowedCount >= 0 },
            get tooltip() {
                if (this.busy) return 'Checking page access...';
                if (this.hasCount) return this.allowedCount + (this.allowedCount === 1 ? ' user can' : ' users can') + ' access this page';
                return 'Access count unavailable. Open the inspector for details.';
            },
            init() {
                this.waitForResult();
                this.$nextTick(() => this.updatePanelHeight());
            },
            updatePanelHeight() {
                const trigger = this.$root.querySelector('[data-access-inspector-trigger]');
                if (! trigger) return;
                const viewport = window.visualViewport;
                const bottom = viewport ? viewport.offsetTop + viewport.height : window.innerHeight;
                this.panelMaxHeight = Math.max(0, Math.floor(bottom - trigger.getBoundingClientRect().bottom - 8 - 16));
            },
            destroy() { clearTimeout(this.timer) },
            waitForResult() {
                clearTimeout(this.timer);
                this.status = 'loading';
                this.allowedCount = null;
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
                this.allowedCount = result.status === 'completed' ? result.allowedCount : null;
            },
            recheck() {
                if (this.busy) return;
                this.waitForResult();
                this.$dispatch('access-inspector-recheck');
            }
        }"
        x-on:access-inspector-loaded.window="receive($event.detail)"
        x-on:livewire:navigating.window="panelHtml = ''; waitForResult()"
        x-on:click.capture="updatePanelHeight()"
        x-on:resize.window="updatePanelHeight()"
        x-on:scroll.window.throttle.100ms="updatePanelHeight()"
    >
        <template x-ref="failure">
            <div class="space-y-2 p-4 text-sm text-gray-600 dark:text-gray-300">
                <p>The access check did not finish. Try again.</p>
                <button type="button" x-on:click.stop="recheck()" x-bind:disabled="busy" class="font-medium">Recheck</button>
            </div>
        </template>

        <x-filament::dropdown placement="bottom-end" width="sm" :flip="false" :shift="true">
            <x-slot name="trigger">
                <button type="button" data-access-inspector-trigger aria-label="Access inspector" title="Access inspector" disabled
                    x-bind:disabled="busy" x-bind:aria-busy="busy"
                    x-bind:title="tooltip" x-bind:aria-label="'Access inspector. ' + tooltip"
                    class="fi-icon-btn relative flex h-10 w-10 items-center justify-center rounded-lg outline-none transition duration-75 focus-visible:ring-2 disabled:cursor-wait disabled:opacity-50"
                    x-bind:class="status === 'completed' ? 'fi-color-custom fi-color-primary text-custom-500 hover:text-custom-600 dark:text-custom-400' : 'text-gray-400 dark:text-gray-500'"
                    style="{{ \Filament\Support\get_color_css_variables('primary', shades: [300, 400, 500, 600], alias: 'icon-button') }}">
                    <x-filament::icon icon="heroicon-o-shield-check" class="fi-icon-btn-icon h-8 w-8" style="width: 2rem; height: 2rem" />
                    <x-filament::badge size="xs" color="primary" x-cloak x-show="hasCount" aria-hidden="true"
                        style="position: absolute; top: 0; inset-inline-end: 0; min-width: 1rem; height: 1rem; padding: 0 0.2rem; border-radius: 9999px; line-height: 1; pointer-events: none; background-color: rgb(var(--c-600)); color: #fff">
                        <span x-text="allowedCount"></span>
                    </x-filament::badge>
                    <x-filament::badge size="xs" color="danger" x-cloak x-show="! busy &amp;&amp; ! hasCount" aria-hidden="true"
                        style="position: absolute; top: 0; inset-inline-end: 0; min-width: 1rem; height: 1rem; padding: 0 0.2rem; border-radius: 9999px; line-height: 1; pointer-events: none">
                        !
                    </x-filament::badge>
                </button>
            </x-slot>
            <div x-html="panelHtml" x-bind:style="{ maxHeight: panelMaxHeight + 'px', overflow: 'hidden' }"></div>
        </x-filament::dropdown>
    </div>
@endif
