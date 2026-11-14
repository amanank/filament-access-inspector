<?php

declare(strict_types=1);

namespace Amanank\FilamentAccessInspector;

use Amanank\FilamentAccessInspector\Livewire\AccessInspector;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AccessInspectorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/filament-access-inspector.php', 'filament-access-inspector');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'filament-access-inspector');
        $this->publishes([
            __DIR__ . '/../config/filament-access-inspector.php' => config_path('filament-access-inspector.php'),
        ], 'filament-access-inspector-config');

        Livewire::component('filament-access-inspector', AccessInspector::class);

        FilamentView::registerRenderHook(
            PanelsRenderHook::USER_MENU_BEFORE,
            fn (): string => view('filament-access-inspector::topbar')->render(),
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn (): string => view('filament-access-inspector::loader')->render(),
        );
    }
}
