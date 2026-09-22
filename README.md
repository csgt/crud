# Crud

CRUD scaffolding for Laravel. Package version `5.7`, on branch `5.7`.

## Requirements

Package version `5.7` lives on branch `5.7`. The branch number is the package
version, not the Laravel version.

| Requirement | Supported |
| ----------- | --------- |
| PHP | 5.6 through 8.3 |
| Laravel | 5.8 and only 5.8 |

This branch calls `BelongsTo::getOwnerKeyName()`, which arrives in Laravel 5.8, **and** the global `array_except()` helper, which is removed in Laravel 6. That leaves a single supported version. Both are inferred from the framework APIs the code actually calls and
from linting the tree on each PHP version, not from the `composer.json`
constraint, which was never kept up to date.

## Server-side permissions

`$this->permissions` is enforced on every write action, not merely read by the index
view to hide buttons. `create()`, `edit()`, `store()`, `update()` and `destroy()`
return `403` when the matching flag is false.

```php
$this->setPermissions(['create' => true, 'update' => false, 'destroy' => false]);
```

**This changed.** The routes used to be reachable by a direct request whatever
the flags said, so the buttons were hidden but the endpoints were not.
The three flags default to `false` on this branch, so an application that never
called `setPermissions()` had every write action open and will now receive a `403` on
all of them. Declare the permissions explicitly before upgrading.

## Continuous integration

`.github/workflows/ci.yml` lints the whole `src` tree on every PHP version this
branch claims to support, on each push and pull request. It replaces a
`.travis.yml` that had stopped running: Travis ended free open-source builds in
2021, and the file still listed PHP 5.3 to 5.6 plus hhvm and invoked a `phpunit`
that was never a dependency of this branch.
