@if ($audit['status'] === 'loading')
    <div class="flex items-center justify-between gap-4 p-4">
        <p class="text-sm text-gray-600 dark:text-gray-300">Checking access...</p>
        <button type="button" x-on:click.stop="recheck()" x-bind:disabled="busy" class="text-sm font-medium text-gray-600 hover:text-gray-950 dark:text-gray-300 dark:hover:text-white">Check now</button>
    </div>
@elseif ($audit['status'] === 'failed')
    <div class="flex items-center justify-between gap-4 p-4">
        <p class="text-sm text-danger-600 dark:text-danger-400">The access check failed.</p>
        <button type="button" x-on:click.stop="recheck()" x-bind:disabled="busy" class="text-sm font-medium text-gray-600 hover:text-gray-950 dark:text-gray-300 dark:hover:text-white">Try again</button>
    </div>
@elseif ($audit['status'] === 'unsupported')
    <div class="p-4 text-sm text-gray-600 dark:text-gray-300">{{ $audit['unknown'][0]['reason'] ?? 'This page is not supported.' }}</div>
@else
    <div class="p-3">
        <div class="border-b border-gray-100 px-1 pb-3 dark:border-white/10">
            <p class="text-sm font-semibold text-gray-950 dark:text-white">Page Access &middot; {{ $resourceLabel }}</p>
            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">List &middot; {{ count($audit['users']) }} {{ \Illuminate\Support\Str::plural('user', count($audit['users'])) }} allowed</p>
        </div>

        @if (count($audit['users']))
            <ul class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($audit['users'] as $user)
                    <li x-data="{ open: false }" class="py-2.5">
                        <div class="flex items-center gap-2.5">
                            <span class="flex h-8 w-8 flex-none items-center justify-center rounded-full bg-gray-100 text-xs font-medium text-gray-700 dark:bg-white/10 dark:text-gray-200">{{ $user['initials'] }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-gray-950 dark:text-white">{{ $user['name'] }}</p>
                                <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $user['reason'] }}</p>
                            </div>
                            <button type="button" x-on:click.stop="open = ! open" x-bind:aria-expanded="open" aria-label="Access details for {{ $user['name'] }}" class="flex h-8 w-8 flex-none items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-500 dark:hover:bg-white/10 dark:hover:text-gray-200">
                                <x-filament::icon icon="heroicon-o-information-circle" class="h-5 w-5" />
                            </button>
                        </div>
                        <div x-cloak x-show="open" x-transition class="ml-10 mt-2 rounded-md bg-gray-50 p-2.5 text-xs text-gray-600 dark:bg-white/5 dark:text-gray-300">
                            <p class="font-medium text-gray-700 dark:text-gray-200">Authorization evidence</p>
                            <ul class="mt-1 list-inside list-disc space-y-1">
                                @foreach ($user['evidence'] ?? [] as $evidence)
                                    <li>{{ $evidence }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="py-3 text-sm text-gray-500 dark:text-gray-400">No users were found with access.</p>
        @endif

        @if (count($audit['unknown']))
            <details class="border-t border-gray-100 py-2.5 text-xs dark:border-white/10">
                <summary class="cursor-pointer font-medium text-amber-700 dark:text-amber-300">{{ count($audit['unknown']) }} could not be determined</summary>
                <ul class="mt-2 space-y-1 text-gray-600 dark:text-gray-300">
                    @foreach ($audit['unknown'] as $unknown)
                        <li>{{ $unknown['name'] ? $unknown['name'] . ': ' : '' }}{{ $unknown['reason'] }}</li>
                    @endforeach
                </ul>
            </details>
        @endif

        @if (count($audit['relations']))
            <div class="border-t border-gray-100 dark:border-white/10">
                @foreach ($audit['relations'] as $relation)
                    <div x-data="{ expanded: false }" class="border-b border-gray-100 last:border-b-0 dark:border-white/5">
                        <button type="button" x-on:click="expanded = ! expanded" x-bind:aria-expanded="expanded" class="flex w-full items-center gap-2 py-2.5 text-start text-sm text-gray-700 hover:text-gray-950 dark:text-gray-200 dark:hover:text-white">
                            <x-filament::icon icon="heroicon-m-chevron-right" x-bind:class="expanded ? 'rotate-90' : ''" class="h-4 w-4 flex-none text-gray-400 transition-transform" />
                            <span class="min-w-0 flex-1 truncate font-medium">{{ $relation['name'] }}</span>
                            <span class="text-xs text-gray-400 dark:text-gray-500">{{ $relation['limitation'] }}</span>
                        </button>
                        <div x-cloak x-show="expanded" x-transition class="pb-2 ps-6 text-xs text-gray-500 dark:text-gray-400">Relationship users are not audited yet.</div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="flex items-center justify-between gap-3 border-t border-gray-100 px-1 pt-2.5 dark:border-white/10">
            <p class="text-xs text-gray-500 dark:text-gray-400">Last checked: {{ $auditedAt ?? '-' }}</p>
            <button type="button" x-on:click.stop="recheck()" x-bind:disabled="busy" class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-gray-600 transition hover:bg-gray-100 hover:text-gray-950 dark:text-gray-300 dark:hover:bg-white/10 dark:hover:text-white">
                <x-filament::icon icon="heroicon-o-arrow-path" class="h-4 w-4" />
                Recheck
            </button>
        </div>
    </div>
@endif