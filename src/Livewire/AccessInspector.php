<?php

declare(strict_types=1);

namespace Amanank\FilamentAccessInspector\Livewire;

use Amanank\FilamentAccessInspector\Services\ListAccessAuditor;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Route;
use Livewire\Component;

class AccessInspector extends Component
{
    public ?string $pageClass = null;

    public array $audit = ['status' => 'loading', 'users' => [], 'unknown' => [], 'relations' => [], 'audited_at' => null];

    public function mount(): void
    {
        $action = Route::current()?->getActionName() ?? '';
        $candidate = str_contains($action, '@') ? strstr($action, '@', true) : $action;

        if (is_string($candidate) && class_exists($candidate) && is_subclass_of($candidate, ListRecords::class)) {
            $this->pageClass = $candidate;
        }
    }

    public function loadAudit(): void
    {
        $this->authorizeInspector();

        if (! $this->pageClass) {
            $this->audit['status'] = 'unsupported';
            $this->audit['unknown'] = [['name' => '', 'reason' => 'Access inspection is available on Filament resource List pages.']];
            return;
        }

        try {
            $this->audit = app(ListAccessAuditor::class)->audit($this->pageClass);
        } catch (\Throwable $exception) {
            report($exception);
            $this->audit = ['status' => 'failed', 'users' => [], 'unknown' => [['name' => '', 'reason' => 'The access audit failed. Check the application log for details.']], 'relations' => [], 'audited_at' => null];
        }
    }

    public function recheck(): void
    {
        $this->authorizeInspector();

        if (! $this->pageClass) {
            return;
        }

        $this->audit = ['status' => 'loading', 'users' => [], 'unknown' => [], 'relations' => [], 'audited_at' => null];

        try {
            $this->audit = app(ListAccessAuditor::class)->audit($this->pageClass, refresh: true);
        } catch (\Throwable $exception) {
            report($exception);
            $this->audit = ['status' => 'failed', 'users' => [], 'unknown' => [['name' => '', 'reason' => 'The access audit failed. Check the application log for details.']], 'relations' => [], 'audited_at' => null];
        }
    }

    private function authorizeInspector(): void
    {
        abort_unless(Filament::auth()->user()?->can(config('filament-access-inspector.permission', 'inspect_access')), 403);
    }

    public function getResourceLabel(): string
    {
        if (! $this->pageClass) {
            return 'Resource';
        }

        $resource = $this->pageClass::getResource();

        return $resource::getPluralModelLabel();
    }

    public function render()
    {
        return view('filament-access-inspector::livewire.access-inspector');
    }
}
