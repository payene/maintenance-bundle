# Payene MaintenanceBundle

[![CI](https://github.com/payene/maintenance-bundle/actions/workflows/ci.yml/badge.svg)](https://github.com/payene/maintenance-bundle/actions/workflows/ci.yml)
[![Latest Stable Version](https://poser.pugx.org/payene/maintenance-bundle/v/stable)](https://packagist.org/packages/payene/maintenance-bundle)
[![Total Downloads](https://poser.pugx.org/payene/maintenance-bundle/downloads)](https://packagist.org/packages/payene/maintenance-bundle)
[![License](https://poser.pugx.org/payene/maintenance-bundle/license)](https://packagist.org/packages/payene/maintenance-bundle)

> **Status**: v1.1.2 — Production ready.

Maintenance mode for Symfony with a web admin interface, IP allow listing, and configurable storage.

## Table of Contents

- [Features](#features)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [Template Override](#template-override)
- [Securing the Admin Interface](#securing-the-admin-interface)
- [Advanced Storage](#advanced-storage)
- [Extension](#extension)
- [Tests](#tests)
- [License](#license)

## Features

- ✅ **Instant toggle** via CLI (`maintenance:on|off|status`) or Web UI (`/admin/maintenance`)
- ✅ **HTTP 503 Response** with a proper `Retry-After` header
- ✅ **Customizable messages** + dedicated message for when maintenance takes longer than expected
- ✅ **Real-time countdown** with clean "overdue" handling (no page flickering)
- ✅ **IP allow listing** (with CIDR support) and **path allow listing**
- ✅ **Admin interface** with **live preview**
- ✅ **Overridable templates** using standard Symfony conventions
- ✅ **Configurable storage**: JSON (default) or any cache pool (Redis, Memcached, Doctrine)
- ✅ **Fail-safe**: `/admin/maintenance` is always accessible so you never lock yourself out
- ✅ **Unit & Functional tests**
- ✅ **PHP 8.1+ / Symfony 6.4 LTS and 7.x**

## Installation

```bash
composer require payene/maintenance-bundle
```

With Symfony Flex, the recipe automatically configures the bundle and creates `config/packages/maintenance.yaml`.

Without Flex, register the bundle in `config/bundles.php`:

```php
return [
    // ...
    Payene\MaintenanceBundle\MaintenanceBundle::class => ['all' => true],
];
```

Import the routes in `config/routes.yaml`:

```yaml
payene_maintenance:
    resource: '@MaintenanceBundle/src/Controller/'
    type: attribute
    prefix: '%maintenance.panel_path%'
    name_prefix: 'payene_maintenance_'
```

> **Note**: If you keep the `#[Route(name: 'payene_maintenance_')]` attribute on the `AdminMaintenanceController` class, remove `name_prefix:` from the YAML to avoid doubling the prefix.

## Configuration

```yaml
# config/packages/maintenance.yaml
maintenance:
    storage: json
    json_path: '%kernel.project_dir%/var/maintenance.json'
    cache_pool: cache.app
    panel_path: /admin/maintenance
```

| Option | Default | Description |
|---|---|---|
| `storage` | `json` | `json` or `cache` |
| `json_path` | `%kernel.project_dir%/var/maintenance.json` | JSON file path (storage `json`) |
| `cache_pool` | `cache.app` | Cache service (storage `cache`) |
| `panel_path` | `/admin/maintenance` | Route prefix and admin bypass path |

## Usage

### CLI

```bash
# Enable
php bin/console maintenance:on

# Enable with parameters
php bin/console maintenance:on --until="+2 hours" --message="Deploying v2.0" --retry-after=600

# Enable with a custom overdue message
php bin/console maintenance:on --until="2026-10-30 18:00" \
    --message="Update in progress" \
    --overdue-message="We are finalizing the last adjustments."

# Disable
php bin/console maintenance:off

# Current status
php bin/console maintenance:status
```

### Web Interface

Navigate to `/admin/maintenance`. From there, you can:

- Enable / disable maintenance with one click
- Edit the main message, overdue message, end date, and Retry-After header
- Manage allowed IPs and paths
- **Live preview** of what visitors will see

## Template Override

The bundle provides `@Maintenance/maintenance.html.twig` with named **blocks**:
`title`, `styles`, `body`, `badge`, `header`, `content`, `message`, `countdown`, `overdue`, `footer`, `scripts`.

### Partial Override (Recommended)

Create `templates/bundles/MaintenanceBundle/maintenance.html.twig`:

```twig
{# ⚠️ The "!" prefix is MANDATORY to avoid an infinite loop #}
{% extends '@!Maintenance/maintenance.html.twig' %}

{% block header %}
    <img src="{{ asset('logo.svg') }}" alt="Logo" style="width:120px">
    <h1>My website will be back soon</h1>
{% endblock %}

{% block footer %}
    <div class="footer">
        <p>Follow us on <a href="https://twitter.com/...">Twitter</a></p>
    </div>
{% endblock %}
```

### Full Override (No `extends`)

```twig
{# templates/bundles/MaintenanceBundle/maintenance.html.twig #}
<!DOCTYPE html>
<html>
<body>
    <h1>Complete redesign</h1>
    <p>{{ message }}</p>
</body>
</html>
```

### Variables Available in the Template

| Variable | Type | Description |
|---|---|---|
| `message` | `?string` | Custom message (or `null`) |
| `overdue_message` | `?string` | Overdue message (or `null`) |
| `since` | `?\DateTimeImmutable` | Start time of maintenance |
| `until` | `?\DateTimeImmutable` | End time (`null` if overdue) |
| `overdue` | `bool` | `true` if the end time has passed |

## Securing the Admin Interface

**By default, the interface is not protected.** You must secure it using your Symfony firewall:

```yaml
# config/packages/security.yaml
security:
    access_control:
        - { path: ^/admin/maintenance, roles: ROLE_ADMIN }
```

Or by IP at the web server level (Nginx):

```nginx
location /admin/maintenance {
    allow 10.0.0.0/8;
    deny all;
    # ...
}
```

## Advanced Storage

### Redis / Memcached / Doctrine

Use the `cache` storage with a dedicated pool:

```yaml
# config/packages/cache.yaml
framework:
    cache:
        pools:
            maintenance.cache:
                adapter: cache.adapter.redis
                provider: 'redis://localhost:6379'
```

```yaml
# config/packages/maintenance.yaml
maintenance:
    storage: cache
    cache_pool: maintenance.cache
```

> ⚠️ **Do not reuse `cache.app`**: a `cache:clear` in production would wipe the maintenance state.

### Custom Storage

Implement `MaintenanceStorageInterface` and alias it:

```yaml
# config/services.yaml
services:
    Payene\MaintenanceBundle\Storage\MaintenanceStorageInterface:
        class: App\Maintenance\CustomStorage
```

The interface is intentionally minimal:

```php
interface MaintenanceStorageInterface
{
    public function load(): MaintenanceState;
    public function save(MaintenanceState $state): void;
}
```

## Extension

The `MaintenanceManager` can be injected into your own services:

```php
use Payene\MaintenanceBundle\Service\MaintenanceManager;

final class MyService
{
    public function __construct(private MaintenanceManager $maintenance) {}

    public function someAction(): void
    {
        if ($this->maintenance->isEnabled()) {
            // ...
        }

        $this->maintenance->enable();
        $this->maintenance->updateSettings(
            new \DateTimeImmutable('+1 hour'),
            'Custom message',
            600,
            'Custom overdue message',
        );
    }
}
```

### Allowed Paths: Exact Behavior

An allowed path `/api` permits:

- ✅ `/api` (exact match)
- ✅ `/api/` (with trailing slash)
- ✅ `/api/users` (prefix with full segment)
- ❌ `/apixyz` (different segment, **not** allowed)
- ❌ `/api-v2` (different segment, **not** allowed)

To allow multiple paths, add them one by one. To allow everything, use `/`.

## Tests

```bash
composer install
vendor/bin/phpunit
```

## Contribution

Issues and pull requests are welcome on [GitHub](https://github.com/payene/maintenance-bundle).

## License

MIT — see [LICENSE](LICENSE).