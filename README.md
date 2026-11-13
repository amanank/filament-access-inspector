# Filament Access Inspector

A Filament 3 package for inspecting per-user `viewAny` authorization on resource List pages. The topbar shield appears before the user avatar for users allowed by the configured inspector permission. An audit starts after the page renders.

## Current scope

- Supports resource List pages and evaluates each configured user through `Gate::forUser($user)->inspect('viewAny', $resourceModel)`.
- Skips users who fail `canAccessPanel()` when their model implements it.
- Reports users whose result cannot be determined separately.
- Lists every relation manager registered on the resource, but does not audit or list users for those relationships. Each entry explains that relationship access is not evaluated in this version.
- Other resource pages are shown as unsupported.

The result describes the Gate check. Laravel code that depends on the active request, session, or `Auth::user()` may not behave as it does in a request made by that user. Custom resource `canViewAny()` implementations are reported as indeterminate. The plugin does not impersonate users or clear permission caches.

## Installation

Add the package as a local Composer path repository while developing in this monorepo:

```json
{
  "repositories": [
    { "type": "path", "url": "packages/amanank/filament-access-inspector", "options": { "symlink": true } }
  ],
  "require": {
    "amanank/filament-access-inspector": "@dev"
  }
}
```

Then run `composer update amanank/filament-access-inspector`. Laravel discovers the service provider automatically. The topbar hook is global to Filament panels; the configured permission controls its visibility and every Livewire action is authorized server-side.

## Configuration

Publish the configuration with:

```sh
php artisan vendor:publish --tag=filament-access-inspector-config
```

- `permission`: permission checked on the active panel guard; defaults to `inspect_access`.
- `user_model`: model to audit; defaults to the model in `auth.providers.users.model`.
- `cache_ttl`: completed-result cache duration in minutes; defaults to 1440 (24 hours).

The cache is separated by panel, resource, tenant, inspector, and user model. Use **Recheck now** to bypass it. Permission cache state is left untouched.