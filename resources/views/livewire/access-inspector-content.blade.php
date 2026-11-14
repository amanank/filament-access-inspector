@if ($audit['status'] === 'failed')
    <div class="flex items-center justify-between gap-3 p-4">
        <p class="text-sm text-danger-600 dark:text-danger-400">The access check failed.</p>
        <button type="button" x-on:click.stop="recheck()" x-bind:disabled="busy" class="text-sm font-medium text-gray-600 dark:text-gray-300">Try again</button>
    </div>
@elseif ($audit['status'] === 'unsupported')
    <div class="p-4 text-sm text-gray-600 dark:text-gray-300">{{ $audit['unknown'][0]['reason'] ?? 'This page is not supported.' }}</div>
@else
    <div x-data="{ activeSection: null, toggleSection(key) { this.activeSection = this.activeSection === key ? null : key } }"
        class="flex flex-col" style="max-height: min(36rem, calc(100dvh - 6rem)); overflow: hidden"
        x-bind:style="{ maxHeight: Math.min(576, panelMaxHeight) + 'px' }">
        <header class="border-b border-gray-100 p-3 dark:border-white/10" style="flex-shrink: 0">
            <p class="text-sm font-semibold text-gray-950 dark:text-white">Page Access &middot; {{ $resourceLabel }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">List &middot; {{ count($audit['users']) }} {{ \Illuminate\Support\Str::plural('user', count($audit['users'])) }} allowed</p>
        </header>

        <div style="min-height: 0; overflow-y: auto; overscroll-behavior: contain">
            @if (count($audit['users']))
                <ul class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($audit['users'] as $user)
                        @php($userKey = (string) $user['id'])
                        @php($evidenceId = 'access-inspector-evidence-' . sha1($userKey))
                        @php($sectionKey = 'user:' . $userKey)
                        <li class="px-3 py-3">
                            <div class="flex items-center gap-3" style="gap: 0.75rem">
                                <span class="flex h-8 w-8 flex-none items-center justify-center rounded-full bg-gray-100 text-xs font-medium text-gray-700 dark:bg-white/10 dark:text-gray-200" aria-hidden="true">{{ $user['initials'] }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-gray-950 dark:text-white">{{ $user['name'] }}</p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $user['reason'] }}</p>
                                </div>
                                <button type="button" x-on:click.stop="toggleSection(@js($sectionKey))" x-bind:aria-expanded="activeSection === @js($sectionKey)"
                                    aria-controls="{{ $evidenceId }}" aria-label="Access details for {{ $user['name'] }}"
                                    class="flex h-8 w-8 flex-none items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 dark:text-gray-500 dark:hover:bg-white/10 dark:hover:text-gray-200">
                                    <x-filament::icon icon="heroicon-o-information-circle" class="h-5 w-5" />
                                </button>
                            </div>
                            <div id="{{ $evidenceId }}" x-cloak x-show="activeSection === @js($sectionKey)"
                                role="region" aria-label="Authorization evidence for {{ $user['name'] }}"
                                class="mt-3 rounded-lg bg-gray-50 p-3 text-xs text-gray-600 dark:bg-white/5 dark:text-gray-300"
                                style="margin-inline-start: 2.75rem; overflow-wrap: anywhere">
                                <dl class="space-y-2">
                                    @foreach ($user['evidence'] ?? [] as $evidence)
                                        <div>
                                            <dt class="font-medium text-gray-700 dark:text-gray-200">{{ $evidence['label'] }}</dt>
                                            <dd class="mt-1">{{ $evidence['value'] }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                                <p class="mt-3 text-gray-500 dark:text-gray-400">Matching assignments are evidence; they may not be the source of the decision.</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="p-3 text-sm text-gray-500 dark:text-gray-400">No users were found with access.</p>
            @endif

            @if (count($audit['unknown']))
                <div class="border-t border-gray-100 p-3 text-xs dark:border-white/10">
                    <button type="button" x-on:click.stop="toggleSection('unknown')" x-bind:aria-expanded="activeSection === 'unknown'" aria-controls="access-inspector-unknown" class="w-full text-start font-medium text-amber-700 dark:text-amber-300">{{ count($audit['unknown']) }} could not be determined</button>
                    <ul id="access-inspector-unknown" x-cloak x-show="activeSection === 'unknown'" class="mt-2 space-y-2 text-gray-600 dark:text-gray-300">
                        @foreach ($audit['unknown'] as $unknown)
                            <li>{{ $unknown['name'] ? $unknown['name'] . ': ' : '' }}{{ $unknown['reason'] }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (count($audit['relations']))
                <div class="border-t border-gray-100 dark:border-white/10">
                    @foreach ($audit['relations'] as $relation)
                        @php($relationId = 'access-inspector-relation-' . $loop->index)
                        @php($sectionKey = 'relation:' . $loop->index)
                        <div class="border-b border-gray-100 last:border-b-0 dark:border-white/5">
                            <button type="button" x-on:click.stop="toggleSection(@js($sectionKey))" x-bind:aria-expanded="activeSection === @js($sectionKey)" aria-controls="{{ $relationId }}"
                                class="flex w-full items-center gap-3 p-3 text-start text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5" style="gap: 0.75rem">
                                <span class="inline-flex h-4 w-4 flex-none items-center justify-center" aria-hidden="true"
                                    style="transition: transform 150ms ease"
                                    x-bind:style="{ transform: activeSection === @js($sectionKey) ? 'rotate(90deg)' : 'rotate(0deg)' }">
                                    <x-filament::icon icon="heroicon-m-chevron-right" class="h-4 w-4 text-gray-400" />
                                </span>
                                <span class="min-w-0 flex-1 truncate font-medium">{{ $relation['name'] }}</span>
                                <span class="text-xs text-gray-400 dark:text-gray-500">{{ $relation['limitation'] }}</span>
                            </button>
                            <div id="{{ $relationId }}" x-cloak x-show="activeSection === @js($sectionKey)" class="px-3 pb-3 text-xs text-gray-500 dark:text-gray-400" style="padding-inline-start: 2.5rem">Relationship users are not audited yet.</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <footer class="flex items-center justify-between gap-3 border-t border-gray-100 px-3 py-2 dark:border-white/10" style="flex-shrink: 0">
            <p class="text-xs text-gray-500 dark:text-gray-400">Last checked: {{ $auditedAt ?? '-' }}</p>
            <button type="button" x-on:click.stop="recheck()" x-bind:disabled="busy" class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-gray-600 hover:bg-gray-100 disabled:opacity-50 dark:text-gray-300 dark:hover:bg-white/10">
                <x-filament::icon icon="heroicon-o-arrow-path" class="h-4 w-4" />
                <span x-text="busy ? 'Checking...' : 'Recheck'">Recheck</span>
            </button>
        </footer>
    </div>
@endif
