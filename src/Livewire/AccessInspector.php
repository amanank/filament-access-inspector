<?php

declare(strict_types=1);

namespace Amanank\FilamentAccessInspector\Livewire;

use Amanank\FilamentAccessInspector\Services\ListAccessAuditor;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class AccessInspector extends Component
{
    #[Locked]
    public ?string $pageClass = null;

    #[Locked]
    public string $pageUrl = '';

    public function mount(): void
    {
        $this->pageUrl = request()->url();
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
            $this->renderAudit([
                'status' => 'unsupported',
                'users' => [],
                'unknown' => [['name' => '', 'reason' => 'Access inspection is available on Filament resource List pages.']],
                'relations' => [],
                'audited_at' => null,
            ]);
            return;
        }

        try {
            $audit = app(ListAccessAuditor::class)->audit($this->pageClass);
        } catch (\Throwable $exception) {
            report($exception);
            $audit = ['status' => 'failed', 'users' => [], 'unknown' => [['name' => '', 'reason' => 'The access audit failed. Check the application log for details.']], 'relations' => [], 'audited_at' => null];
        }

        $this->renderAudit($audit);
    }

    #[On('access-inspector-recheck')]
    public function recheck(): void
    {
        $this->authorizeInspector();

        if (! $this->pageClass) {
            $this->loadAudit();
            return;
        }

        try {
            $audit = app(ListAccessAuditor::class)->audit($this->pageClass, refresh: true);
        } catch (\Throwable $exception) {
            report($exception);
            $audit = ['status' => 'failed', 'users' => [], 'unknown' => [['name' => '', 'reason' => 'The access audit failed. Check the application log for details.']], 'relations' => [], 'audited_at' => null];
        }

        $this->renderAudit($audit);
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

    public function getInitials(string $name): string
    {
        return collect(preg_split('/\s+/u', trim($name)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }

    private function renderAudit(array $audit): void
    {
        foreach ($audit['users'] as &$user) {
            $user['initials'] = $this->getInitials($user['name']);
        }
        unset($user);

        $resourceLabel = $this->getResourceLabel();
        $auditedAt = $audit['audited_at']
            ? Carbon::parse($audit['audited_at'])->timezone(config('app.timezone'))->format('H:i')
            : null;

        $html = view('filament-access-inspector::livewire.access-inspector-content', compact('audit', 'resourceLabel', 'auditedAt'))->render();

        $this->skipRender();

        $this->dispatch(
            'access-inspector-result',
            html: $html,
            status: $audit['status'],
            pageUrl: $this->pageUrl,
            allowedCount: $audit['status'] === 'completed' ? count($audit['users']) : null,
        );
    }

    public function render()
    {
        return view('filament-access-inspector::livewire.access-inspector');
    }
}
