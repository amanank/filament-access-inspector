<div wire:init="loadAudit" class="flex items-center">
    <x-filament::icon-button
        icon="heroicon-o-shield-check"
        :color="$audit['status'] === 'completed' ? 'primary' : 'gray'"
        :disabled="in_array($audit['status'], ['loading'], true)"
        :label="__('Access inspector')"
        x-on:click="$dispatch('open-modal', { id: 'filament-access-inspector-modal' })"
    />

    <x-filament::modal id="filament-access-inspector-modal" width="2xl">
        <x-slot name="heading">Page Access</x-slot>

        @if ($audit['status'] === 'loading')
            <p class="text-sm text-gray-600 dark:text-gray-300">Checking access…</p>
        @elseif ($audit['status'] === 'failed')
            <div class="space-y-3">
                <p class="text-sm text-danger-600">The audit failed. Try again or check the application log.</p>
                @if ($pageClass)
                    <x-filament::button wire:click="recheck" wire:loading.attr="disabled">Try again</x-filament::button>
                @endif
            </div>
        @elseif ($audit['status'] === 'unsupported')
            <p class="text-sm text-warning-700">{{ $audit['unknown'][0]['reason'] ?? 'This page is not supported.' }}</p>
        @else
            <div class="space-y-5">
                <div>
                    <p class="font-medium">{{ $this->getResourceLabel() }} / List</p>
                    <p class="text-sm text-gray-600 dark:text-gray-300">{{ count($audit['users']) }} users can pass the viewAny authorization check.</p>
                    <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">This checks Laravel Gate as each user. Rules that depend on the active request, session, or Auth facade may need a custom resolver and are not guaranteed to be reproduced.</p>
                </div>

                @if (count($audit['users']))
                    <ul class="divide-y divide-gray-200 dark:divide-white/10">
                        @foreach ($audit['users'] as $user)
                            <li class="flex justify-between gap-4 py-2 text-sm">
                                <span>{{ $user['name'] }}</span>
                                <span class="text-gray-600 dark:text-gray-300">{{ $user['reason'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-sm text-gray-600 dark:text-gray-300">No users passed the viewAny check.</p>
                @endif

                @if (count($audit['unknown']))
                    <section class="space-y-2">
                        <h3 class="font-medium">Could not determine</h3>
                        @foreach ($audit['unknown'] as $unknown)
                            <p class="text-sm text-amber-700 dark:text-amber-300">{{ $unknown['name'] ? $unknown['name'] . ': ' : '' }}{{ $unknown['reason'] }}</p>
                        @endforeach
                    </section>
                @endif

                @if (count($audit['relations']))
                    <section class="space-y-2">
                        <h3 class="font-medium">Related sections</h3>
                        <ul class="space-y-3">
                            @foreach ($audit['relations'] as $relation)
                                <li class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                                    <p class="font-medium">{{ $relation['name'] }}</p>
                                    <p class="mt-1 text-sm text-amber-700 dark:text-amber-300">{{ $relation['limitation'] }}</p>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                <div class="flex items-center justify-between border-t border-gray-200 pt-4 dark:border-white/10">
                    <p class="text-xs text-gray-500">Audited {{ $audit['audited_at'] ?? '—' }}. Results can change after this check.</p>
                    <x-filament::button wire:click="recheck" wire:loading.attr="disabled">Recheck now</x-filament::button>
                </div>
            </div>
        @endif
    </x-filament::modal>
</div>
