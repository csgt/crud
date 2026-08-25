# Rules for working on this package

These rules apply to every branch of `csgt/crud`. They exist because this
package is installed by long-lived applications that pin a branch and rarely
upgrade: a change that looks harmless here becomes a broken listing in a project
nobody has touched in three years.

Read `ARCHITECTURE.md` for how the package is wired and `METHODS.md` for what
each method does before changing anything.

---

## 1. Never repurpose an existing method

A public method that already exists keeps its name, its signature and its
observable behaviour. Do not:

- change what a method returns or the shape of what it returns
- add a required parameter, or reorder parameters
- rename a method, a field key, a config key, a translation key or a view
  variable
- change the JSON shape returned by `data()`
- silently change the meaning of an option (for example making a flag opt-out
  when it used to be opt-in)

If the new behaviour cannot coexist with the old one, it does not belong in this
branch. Open it on a new version branch instead — see rule 2.

**Allowed without a new version:** fixing a method that is provably broken (it
throws, produces invalid SQL, or contradicts its own documented purpose), adding
a new optional parameter with a default that preserves the current result, and
adding a brand new method.

## 2. A behaviour change means a new version, not an edit

The branch number is the **package version**, not the Laravel version. Each
version lives in its own branch and is released from it, so the upgrade path for
an application is "move to another branch", which is a decision its maintainer
takes deliberately.

Therefore:

- a breaking change starts a new branch, cut from the newest branch that has the
  behaviour you want to keep
- a fix or a backward-compatible addition goes on the branch it belongs to, and
  is ported to the newer branches that share the same code shape
- never rewrite history or force-push a released branch
- the compatibility matrix in `README.md` is part of the change: if you add a
  branch, or the PHP/Laravel floor of a branch moves, update the matrix in every
  branch's README

## 3. Legacy compatibility is the priority

When two implementations both work, take the one that keeps working on the
oldest PHP and Laravel this branch supports. Check `README.md` for the exact
bounds of the branch you are on, and before using a framework API, confirm it
exists in the lowest supported Laravel version.

Concrete traps that have already bitten this package:

| Do not use on old branches | Because | Use instead |
|---|---|---|
| `[$a, $b] = $array` | PHP 7.1+ | `list($a, $b) = $array` or index access |
| `$query->orderBy($builder, ...)` | Laravel 6+ | `orderByRaw($sub->toSql(), $sub->getBindings())` |
| `Relation::getRelationExistenceQuery()` | Laravel 5.5+ | guard with `method_exists()` and fall back |
| `Model::qualifyColumn()` | Laravel 5.5+ | `$model->getTable().'.'.$column` |
| `Arr::` / `Str::` | Laravel 5.5+ | the global helpers on branches that predate it |
| `array_except()` / `str_slug()` | removed in Laravel 6 | `Arr::except()` on branches that allow it |

Never widen or tighten the `require` block of `composer.json` to make your
change fit. Dev-only tooling belongs in `require-dev`, where it cannot affect an
application that installs the package.

## 4. Every change to the query pipeline needs a test

The listing query is the part of this package that silently breaks. Anything
touching `data()` or its helpers ships with a test in `tests/` asserting the
generated SQL and bindings.

```bash
composer install
vendor/bin/phpunit
```

The suite must run without a database server: assert on `toSql()` and
`getBindings()`, never execute the query. If a test fails, first check whether
the source really behaves differently on this branch — adjust the source or the
expectation deliberately, never weaken an assertion to get to green.

Filtering, ordering and pagination happen **in the database**. Do not reintroduce
`->get()` followed by Collection `filter`/`sortBy`/`splice`: that loads the whole
table into memory and the cost grows with the table instead of the page.

## 5. User-facing text goes through the translation files

No hardcoded Spanish or English in views or controllers. Add the key to both
`src/resources/lang/en/crud.php` and `src/resources/lang/es/crud.php`, and reach
it with `trans('csgtcrud::crud.key')`.

## 6. Conventions

- Match the file you are editing: indentation, brace style and alignment differ
  between branches, and a reformatting diff hides the real change.
- Commit messages and PR titles/bodies in English, in the imperative mood, with
  a body explaining *why* when it is not obvious.
- One concern per commit. A port, a test and a doc update are three commits.
- Do not commit `composer.lock` or `vendor/`; both are ignored.

## 7. Checklist before opening a PR

- [ ] No existing method changed name, signature or observable behaviour (rule 1)
- [ ] If behaviour had to change, it is on a new version branch (rule 2)
- [ ] No API newer than this branch's supported Laravel/PHP floor (rule 3)
- [ ] `vendor/bin/phpunit` is green, and the change is covered (rule 4)
- [ ] New strings exist in both language files (rule 5)
- [ ] `composer.json` `require` untouched
- [ ] The change is ported to the other branches that share the same code shape,
      or the PR says explicitly why it is not
