	# Payene MaintenanceBundle

[![CI](https://github.com/payene/maintenance-bundle/actions/workflows/ci.yml/badge.svg)](https://github.com/payene/maintenance-bundle/actions/workflows/ci.yml)
[![Latest Stable Version](https://poser.pugx.org/payene/maintenance-bundle/v/stable)](https://packagist.org/packages/payene/maintenance-bundle)
[![Total Downloads](https://poser.pugx.org/payene/maintenance-bundle/downloads)](https://packagist.org/packages/payene/maintenance-bundle)
[![License](https://poser.pugx.org/payene/maintenance-bundle/license)](https://packagist.org/packages/payene/maintenance-bundle)

> **Status** : v1.0.0 — prêt pour production.

Mode maintenance pour Symfony avec interface d'administration, whitelist et stockage configurable.

## Sommaire

- [Fonctionnalités](#fonctionnalités)
- [Installation](#installation)
- [Configuration](#configuration)
- [Utilisation](#utilisation)
- [Surcharge du template](#surcharge-du-template)
- [Sécurisation de l'interface](#sécurisation-de-linterface)
- [Stockage avancé](#stockage-avancé)
- [Extension](#extension)
- [Tests](#tests)
- [Licence](#licence)

## Fonctionnalités

- ✅ **Bascule immédiate** via CLI (`maintenance:on|off|status`) ou UI web (`/admin/maintenance`)
- ✅ **Réponse HTTP 503** avec header `Retry-After` conforme
- ✅ **Message personnalisable** + message dédié au dépassement de l'heure prévue
- ✅ **Compte à rebours** temps réel avec gestion propre du « overdue » (pas de clignotant)
- ✅ **Whitelist IP** (avec support des plages CIDR) et **whitelist de chemins**
- ✅ **Interface d'administration** avec **prévisualisation en direct**
- ✅ **Template surchargeable** via la convention standard Symfony
- ✅ **Stockage configurable** : JSON (défaut) ou tout cache pool (Redis, Memcached, Doctrine)
- ✅ **Garde-fou** : `/admin/maintenance` toujours accessible pour ne jamais se verrouiller dehors
- ✅ **Tests** unitaires + fonctionnels
- ✅ **PHP 8.1+ / Symfony 6.4 LTS et 7.x**

## Installation

```bash
composer require payene/maintenance-bundle
```

Avec Symfony Flex, la recette configure automatiquement le bundle et crée `config/packages/maintenance.yaml`.

Sans Flex, ajoutez le bundle dans `config/bundles.php` :

```php
return [
    // ...
    Payene\MaintenanceBundle\MaintenanceBundle::class => ['all' => true],
];
```

Importez les routes dans `config/routes.yaml` :

```yaml
payene_maintenance:
    resource: '@MaintenanceBundle/src/Controller/'
    type: attribute
    prefix: '%maintenance.panel_path%'
    name_prefix: 'payene_maintenance_'
```

> **Note** : si vous conservez l'attribut `#[Route(name: 'payene_maintenance_')]` sur la classe `AdminMaintenanceController`, retirez `name_prefix:` du YAML pour éviter le doublement.

## Configuration

```yaml
# config/packages/maintenance.yaml
maintenance:
    storage: json
    json_path: '%kernel.project_dir%/var/maintenance.json'
    cache_pool: cache.app
    panel_path: /admin/maintenance
```

| Option | Défaut | Description |
|---|---|---|
| `storage` | `json` | `json` ou `cache` |
| `json_path` | `%kernel.project_dir%/var/maintenance.json` | Fichier JSON (storage `json`) |
| `cache_pool` | `cache.app` | Service de cache (storage `cache`) |
| `panel_path` | `/admin/maintenance` | Préfixe des routes et du bypass admin |

## Utilisation

### CLI

```bash
# Activer
php bin/console maintenance:on

# Activer avec paramètres
php bin/console maintenance:on --until="+2 hours" --message="Déploiement v2.0" --retry-after=600

# Activer avec un message de dépassement personnalisé
php bin/console maintenance:on --until="2026-10-30 18:00" \
    --message="Mise à jour en cours" \
    --overdue-message="Nous finalisons les derniers ajustements."

# Désactiver
php bin/console maintenance:off

# État courant
php bin/console maintenance:status
```

### Interface web

Ouvrez `/admin/maintenance`. Vous pouvez :

- Activer / désactiver en un clic
- Éditer le message, le message overdue, la date de fin, le Retry-After
- Gérer les IPs et chemins whitelistés
- **Prévisualiser en direct** le rendu des visiteurs

## Surcharge du template

Le bundle fournit `@Maintenance/maintenance.html.twig` avec des **blocs nommés** :
`title`, `styles`, `body`, `badge`, `header`, `content`, `message`, `countdown`, `overdue`, `footer`, `scripts`.

### Surcharge partielle (recommandée)

Créez `templates/bundles/MaintenanceBundle/maintenance.html.twig` :

```twig
{# ⚠️ Le "!" est OBLIGATOIRE pour éviter une boucle infinie #}
{% extends '@!Maintenance/maintenance.html.twig' %}

{% block header %}
    <img src="{{ asset('logo.svg') }}" alt="Logo" style="width:120px">
    <h1>Mon site revient bientôt</h1>
{% endblock %}

{% block footer %}
    <div class="footer">
        <p>Suivez-nous sur <a href="https://twitter.com/...">Twitter</a></p>
    </div>
{% endblock %}
```

### Surcharge complète (sans `extends`)

```twig
{# templates/bundles/MaintenanceBundle/maintenance.html.twig #}
<!DOCTYPE html>
<html>
<body>
    <h1>Refonte complète</h1>
    <p>{{ message }}</p>
</body>
</html>
```

### Variables disponibles dans le template

| Variable | Type | Description |
|---|---|---|
| `message` | `?string` | Message personnalisé (ou `null`) |
| `overdue_message` | `?string` | Message de dépassement (ou `null`) |
| `since` | `?\DateTimeImmutable` | Date d'activation |
| `until` | `?\DateTimeImmutable` | Date de fin (`null` si dépassée) |
| `overdue` | `bool` | `true` si la date de fin est passée |

## Sécurisation de l'interface

**Par défaut, l'interface n'est pas protégée.** Vous devez la sécuriser avec votre firewall Symfony :

```yaml
# config/packages/security.yaml
security:
    access_control:
        - { path: ^/admin/maintenance, roles: ROLE_ADMIN }
```

Ou par IP au niveau serveur web (Nginx) :

```nginx
location /admin/maintenance {
    allow 10.0.0.0/8;
    deny all;
    # ...
}
```

## Stockage avancé

### Redis / Memcached / Doctrine

Utilisez le storage `cache` avec un pool dédié :

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

> ⚠️ **Ne réutilisez pas `cache.app`** : un `cache:clear` en production viderait l'état de maintenance.

### Storage personnalisé

Implémentez `MaintenanceStorageInterface` et aliassez-la :

```yaml
# config/services.yaml
services:
    Payene\MaintenanceBundle\Storage\MaintenanceStorageInterface:
        class: App\Maintenance\CustomStorage
```

L'interface est volontairement minimale :

```php
interface MaintenanceStorageInterface
{
    public function load(): MaintenanceState;
    public function save(MaintenanceState $state): void;
}
```

## Extension

Le `MaintenanceManager` est injectable dans vos propres services :

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
            'Message custom',
            600,
            'Overdue custom',
        );
    }
}
```

### Chemins whitelistés : comportement exact

Un chemin whitelisté `/api` autorise :

- ✅ `/api` (exact)
- ✅ `/api/` (avec slash final)
- ✅ `/api/users` (préfixe avec segment complet)
- ❌ `/apixyz` (segment différent, **non** whitelisté)
- ❌ `/api-v2` (segment différent, **non** whitelisté)

Pour whitelister plusieurs chemins, ajoutez-les un par un. Pour tout whitelister, utilisez `/`.

## Tests

```bash
composer install
vendor/bin/phpunit
```

## Contribution

Issues et pull requests bienvenues sur [GitHub](https://github.com/payene/maintenance-bundle).

## Licence

MIT — voir [LICENSE](LICENSE).