# Crud

CRUD scaffolding for Laravel. Package version `6.0`, on branch `6.0`.

## Requirements

Package version `6.0` lives on branch `6.0`. The branch number is the package
version, not the Laravel version.

| Requirement | Supported |
| ----------- | --------- |
| PHP | 7.2 through 8.3 |
| Laravel | 5.8 or newer |

The Laravel floor comes from `BelongsTo::getOwnerKeyName()`. Both are inferred from the framework APIs the code actually calls and
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

## Configuration

Publish the config with `php artisan vendor:publish --tag=config`.

| Key | Default | Meaning |
| --- | ------- | ------- |
| `stateDuration` | `0` | Seconds DataTables keeps the saved listing state — search, column filters, sort and page — in the browser's localStorage. `0` means it never expires. DataTables' own default is 7200, which silently discarded the saved search after two hours. |
| `extensiones_permitidas` | `null` | Whitelist of extensions accepted when uploading `file` and `image` fields, lowercase and without the dot. `null` applies no restriction, which is the historical behaviour. Set an array to enable it, for example `['jpg', 'png', 'pdf']`. |

`stateDuration` can be overridden for a single controller with
`$this->setStateDuration($seconds)`.

## Continuous integration

`.github/workflows/ci.yml` lints the whole `src` tree on every PHP version this
branch claims to support, on each push and pull request. It replaces a
`.travis.yml` that had stopped running: Travis ended free open-source builds in
2021, and the file still listed PHP 5.3 to 5.6 plus hhvm and invoked a `phpunit`
that was never a dependency of this branch.
