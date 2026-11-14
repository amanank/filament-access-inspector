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
use Illuminate\Support\Str;
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
            'filament-access-inspector:v3',
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
        $expectedPermission = $this->expectedListPermission($resource);
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
                    $hasSuperAdminRole = method_exists($user, 'hasRole')
                        && $user->hasRole(config('filament-shield.super_admin.name', 'super_admin'));
                    $superAdminBypassConfigured = (bool) config('filament-shield.super_admin.enabled', false)
                        && (bool) config('filament-shield.super_admin.define_via_gate', false)
                        && config('filament-shield.super_admin.intercept_gate') === 'before';
                    $message = trim((string) $decision->message());
                    $reason = $hasSuperAdminRole && $superAdminBypassConfigured
                        ? 'Super Admin bypass'
                        : 'Access allowed - Source unknown';

                    $users[] = [
                        'id' => $user->getKey(),
                        'name' => $user->name ?? (string) $user->getKey(),
                        'reason' => $reason,
                        'evidence' => $this->accessEvidence($user, $expectedPermission, $message, $hasSuperAdminRole && $superAdminBypassConfigured),
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

        $name = class_basename($class ?? 'Relation manager');

        if ($class && class_exists($class) && method_exists($class, 'getRelationshipName')) {
            $relationshipName = $class::getRelationshipName();

            if (filled($relationshipName)) {
                $name = Str::headline($relationshipName);
            }
        }

        return [
            'name' => $name,
            'limitation' => 'Not evaluated',
        ];
    }

    private function expectedListPermission(string $resource): ?string
    {
        $configured = config('filament-access-inspector.list_permissions', []);
        if (isset($configured[$resource])) {
            return $configured[$resource];
        }

        foreach (['Amanank\\FilamentShield\\Facades\\FilamentShield', 'BezhanSalleh\\FilamentShield\\Facades\\FilamentShield'] as $shield) {
            if (class_exists($shield)) {
                return 'view_any_' . $shield::getPermissionIdentifier($resource);
            }
        }

        return null;
    }

    private function accessEvidence(object $user, ?string $permission, string $decisionMessage, bool $superAdminBypass): array
    {
        $evidence = [['label' => 'Decision', 'value' => 'Allowed (viewAny)']];
        $roles = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->all() : [];
        $evidence[] = ['label' => 'Assigned roles', 'value' => $roles === [] ? 'None' : implode(', ', $roles)];

        if ($superAdminBypass) {
            $evidence[] = ['label' => 'Super Admin', 'value' => 'Shield Gate bypass is configured for this role.'];
        }

        if ($permission !== null) {
            $evidence[] = ['label' => 'Expected permission', 'value' => $permission];
            $direct = method_exists($user, 'getDirectPermissions')
                && $user->getDirectPermissions()->contains('name', $permission);
            $matchingRoles = method_exists($user, 'getRoleNames')
                ? $user->roles->filter(fn ($role): bool => $role->permissions->contains('name', $permission))->pluck('name')->all()
                : [];
            $evidence[] = ['label' => 'Direct assignment', 'value' => $direct ? $permission : 'None matching'];
            $evidence[] = ['label' => 'Matching roles', 'value' => $matchingRoles === [] ? 'None' : implode(', ', $matchingRoles)];
        }

        if ($decisionMessage !== '') {
            $evidence[] = ['label' => 'Policy message', 'value' => $decisionMessage];
        }

        return $evidence;
    }

    private function unsupported(string $reason): array
    {
        return ['status' => 'unsupported', 'users' => [], 'unknown' => [['name' => '', 'reason' => $reason]], 'relations' => [], 'audited_at' => null];
    }
}
