---
title: Installation & setup
weight: 3
---

This package requires PHP 8.4.1 or newer and Laravel 13. It uses Symfony YAML 8.1 or newer within the 8.x series.

Laravel 11, Laravel 12, and PHP 8.4.0 are no longer supported. Applications using those versions can stay on version 1.5.0.

YAML mapping keys must be unique, including when the earlier value is `null`. Symfony YAML 8 rejects duplicate keys instead of accepting that invalid YAML.

You can install the package via composer:

```bash
composer require spatie/laravel-openapi-cli
```

The package will automatically register its service provider.
