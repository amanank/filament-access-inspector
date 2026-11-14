@if (filament()->auth()->check() && filament()->auth()->user()?->can(config('filament-access-inspector.permission', 'inspect_access')))
    @livewire('filament-access-inspector', [], key('access-inspector-loader-' . hash('sha256', request()->url())))
@endif
