# Crud

CRUD scaffolding for Laravel. Package version `5.4`, on branch `5.4`.

## Requirements

Package version `5.4` lives on branch `5.4`. The branch number is the package
version, not the Laravel version.

| Requirement | Supported |
| ----------- | --------- |
| PHP | 5.6 through 8.3 |
| Laravel | 5.4 up to, but not including, 6.0 |

The Laravel ceiling comes from the global `array_except()` helper, removed in Laravel 6. Both are inferred from the framework APIs the code actually calls and
from linting the tree on each PHP version, not from the `composer.json`
constraint, which was never kept up to date.

## Server-side permissions

`$this->permisos` is enforced on every write action, not merely read by the index
view to hide buttons. `create()`, `edit()`, `store()`, `update()` and `destroy()`
return `403` when the matching flag is false.

```php
$this->setPermisos(['add' => true, 'edit' => false, 'delete' => false]);
```

**This changed.** The routes used to be reachable by a direct request whatever
the flags said, so the buttons were hidden but the endpoints were not.
The three flags default to `false` on this branch, so an application that never
called `setPermisos()` had every write action open and will now receive a `403` on
all of them. Declare the permissions explicitly before upgrading.

## Continuous integration

`.github/workflows/ci.yml` lints the whole `src` tree on every PHP version this
branch claims to support, on each push and pull request. It replaces a
`.travis.yml` that had stopped running: Travis ended free open-source builds in
2021, and the file still listed PHP 5.3 to 5.6 plus hhvm and invoked a `phpunit`
that was never a dependency of this branch.
