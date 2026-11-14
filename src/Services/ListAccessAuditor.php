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
            'filament-access-inspector:v2',
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
                        'evidence' => $this->accessEvidence($user, $resource::getModel(), $message, $hasSuperAdminRole && $superAdminBypassConfigured),
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

    private function accessEvidence(object $user, string $model, string $decisionMessage, bool $superAdminBypass): array
    {
        $evidence = ["Laravel Gate allowed viewAny for {$model}."];

        if ($superAdminBypass) {
            $evidence[] = 'The configured Shield super-admin role is assigned and its Gate before bypass is enabled.';
        }

        if (method_exists($user, 'getRoleNames')) {
            $roles = $user->getRoleNames()->all();
            if ($roles !== []) {
                $evidence[] = $this->formatEvidenceList('Assigned roles', $roles);
            }
        }

        if (method_exists($user, 'getDirectPermissions')) {
            $permissions = $user->getDirectPermissions()->pluck('name')->all();
            if ($permissions !== []) {
                $evidence[] = $this->formatEvidenceList('Direct permissions', $permissions);
            }
        }

        if (method_exists($user, 'getPermissionsViaRoles')) {
            $permissions = $user->getPermissionsViaRoles()->pluck('name')->all();
            if ($permissions !== []) {
                $evidence[] = $this->formatEvidenceList('Permissions through roles', $permissions);
            }
        }

        if ($decisionMessage !== '') {
            $evidence[] = 'Authorization message: ' . $decisionMessage;
        } else {
            $evidence[] = 'The check result does not identify a single role or permission as the source.';
        }

        return $evidence;
    }

    private function formatEvidenceList(string $label, array $values): string
    {
        $visible = array_slice($values, 0, 8);
        $remaining = count($values) - count($visible);
        $formatted = $label . ': ' . implode(', ', $visible);

        return $formatted . ($remaining > 0 ? " and {$remaining} more." : '.');
    }

    private function unsupported(string $reason): array
    {
        return ['status' => 'unsupported', 'users' => [], 'unknown' => [['name' => '', 'reason' => $reason]], 'relations' => [], 'audited_at' => null];
    }
}
