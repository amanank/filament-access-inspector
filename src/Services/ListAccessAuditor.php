<?php

declare(strict_types=1);

namespace Amanank\FilamentAccessInspector\Services;

use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\RelationManagers\RelationGroup;
use Filament\Resources\RelationManagers\RelationManagerConfiguration;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use ReflectionMethod;

class ListAccessAuditor
{
    /** @return array{status: string, users: array<int, array{id: int|string, name: string, reason: string}>, unknown: array<int, array{name: string, reason: string}>, relations: array<int, array{name: string, limitation: string}>, audited_at: string|null} */
    public function audit(string $pageClass, bool $refresh = false): array
    {
        if (! is_subclass_of($pageClass, ListRecords::class)) {
            return $this->unsupported('Only Filament resource List pages are supported in this version.');
        }

        $resource = $pageClass::getResource();
        $panel = Filament::getCurrentPanel();
        $model = config('filament-access-inspector.user_model')
            ?: config('auth.providers.users.model');

        if (! is_string($model) || ! class_exists($model)) {
            return $this->unsupported('Configure filament-access-inspector.user_model to the user model used by this panel.');
        }

        $cacheKey = implode(':', [
            'filament-access-inspector',
            $panel?->getId() ?? 'panel',
            $resource,
            'viewAny',
            (string) (Filament::getTenant()?->getKey() ?? 'no-tenant'),
            (string) (Filament::auth()->id() ?? 'guest'),
            $model,
        ]);

        if (! $refresh && ($cached = Cache::get($cacheKey)) !== null) {
            return $cached;
        }

        $users = [];
        $unknown = [];
        $customResourceCheck = (new ReflectionMethod($resource, 'canViewAny'))->getDeclaringClass()->getName() !== \Filament\Resources\Resource::class;

        foreach ($model::query()->get() as $user) {
            try {
                if (method_exists($user, 'canAccessPanel') && ! $user->canAccessPanel($panel)) {
                    continue;
                }

                if ($customResourceCheck) {
                    $unknown[] = ['name' => $user->name ?? (string) $user->getKey(), 'reason' => 'This resource overrides canViewAny(); its user-specific logic cannot safely be evaluated as another user.'];
                    continue;
                }

                $decision = Gate::forUser($user)->inspect('viewAny', $resource::getModel());

                if ($decision->allowed()) {
                    $users[] = [
                        'id' => $user->getKey(),
                        'name' => $user->name ?? (string) $user->getKey(),
                        'reason' => $decision->message() ?: 'Allowed by the Laravel viewAny authorization check.',
                    ];
                }
            } catch (AuthorizationException $exception) {
                // A denial is an expected result, not an audit failure.
            } catch (\Throwable $exception) {
                $unknown[] = ['name' => $user->name ?? (string) $user->getKey(), 'reason' => 'Authorization could not be evaluated: ' . $exception->getMessage()];
            }
        }

        $result = [
            'status' => 'completed',
            'users' => $users,
            'unknown' => $unknown,
            'relations' => $this->relations($resource),
            'audited_at' => now()->toIso8601String(),
        ];

        Cache::put($cacheKey, $result, now()->addMinutes((int) config('filament-access-inspector.cache_ttl', 1440)));

        return $result;
    }

    private function relations(string $resource): array
    {
        $relations = [];

        foreach ($resource::getRelations() as $relation) {
            if ($relation instanceof RelationGroup) {
                foreach ($relation->getManagers() as $manager) {
                    $relations[] = $this->relationEntry($manager);
                }
                continue;
            }

            $relations[] = $this->relationEntry($relation);
        }

        return $relations;
    }

    private function relationEntry(mixed $relation): array
    {
        $class = $relation instanceof RelationManagerConfiguration
            ? $relation->relationManager
            : (is_string($relation) ? $relation : null);

        $name = $class && class_exists($class) && method_exists($class, 'getTitle')
            ? $class::getTitle()
            : class_basename($class ?? 'Relation manager');

        return [
            'name' => $name,
            'limitation' => 'Relationship access is not evaluated in this version. Visibility and access may depend on the current user, parent record, tenant, or request context.',
        ];
    }

    private function unsupported(string $reason): array
    {
        return ['status' => 'unsupported', 'users' => [], 'unknown' => [['name' => '', 'reason' => $reason]], 'relations' => [], 'audited_at' => null];
    }
}
