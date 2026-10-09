# Activity log for October CMS

An opinionated October adapter for spatie/laravel-activitylog: uuid subjects only, every row has a causer, rows are immutable.

## 1. What it is

The package logs who changed which model. It does not run on October CMS out of the box. This plugin removes three blockers:

- The package's causer resolver type-hints Illuminate's `AuthManager`, which an October container cannot build. The plugin aliases `auth` to it.
- October's updater runs only migrations that extend its own `Migration` class, and the package ships a stub that does not. The plugin ships the table as a real October update.
- The package wires its model listeners in a trait boot method, and `Model::extend()` cannot add one. The plugin ships a trait to `use` in the model class.

It also takes positions the package leaves open, and you should know them before you install it:

- Subjects must have UUID keys. The `subject_id` column is a `uuid`.
- The causer comes from the RainLab.User web guard. A backend administrator, a console command and a queue job have no web user, so a write there names its causer explicitly (section 7).
- A write with no causer is refused, never stored anonymously.
- Rows are written once and never updated.

## 2. Requirements

- October CMS 4.4 and PHP 8.4
- RainLab.User 3.x
- spatie/laravel-activitylog 5.1
- PostgreSQL. The suite runs on it. MySQL goes through Laravel's schema grammar and is untested.

A backend-only install is not supported unless every writer sets the causer explicitly.

## 3. Install

Require the package at the project root, where its service provider is discovered:

```
composer require spatie/laravel-activitylog:^5.1
```

Never run `composer` inside the plugin folder. A plugin `vendor` directory is autoloaded and would load a second copy of the package.

Then put the plugin in place, either with a clone:

```
git clone git@github.com:logingrupa/oc-activitylog-plugin.git plugins/logingrupa/activitylog
```

or with October's installer:

```
php artisan plugin:install Logingrupa.Activitylog --oc --from=git@github.com:logingrupa/oc-activitylog-plugin.git
```

Finally create the table:

```
php artisan october:migrate
```

## 4. What the plugin changes

`register()` makes three changes and nothing else:

- It aliases `auth` to `Illuminate\Auth\AuthManager`. RainLab's manager extends that class, so the alias resolves the same object as `auth`.
- It sets `activitylog.buffer.enabled` to `false`. The package's buffered insert writes an `updated_at` column that this table does not have.
- It sets `activitylog.activity_model` to the plugin's `Activity` model, but only while the key still holds the package's own model. A plugin that already named a subclass keeps it, whatever order the plugins register in.

The plugin has no `boot()`, no config file, no routes, no events, no permissions and no navigation. Every other package setting keeps its default.

## 5. The table

`logingrupa_activitylog_activities`:

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint, primary key | |
| `log_name` | string, nullable | |
| `description` | text | A stable key such as `note.created` |
| `subject_type` | string, nullable | The subject's class |
| `subject_id` | uuid, nullable | |
| `event` | string, nullable | `created`, `updated` or `deleted` |
| `causer_type` | string | Not null |
| `causer_id` | bigint | Not null |
| `attribute_changes` | jsonb, nullable | Read back as a collection |
| `properties` | jsonb, nullable | Read back as a collection |
| `created_at` | timestamp | The only timestamp column |

Two indexes: `activitylog_activities_subject_idx` on `(subject_type, subject_id)` and `activitylog_activities_causer_idx` on `(causer_type, causer_id)`.

There are no foreign keys. A row outlives a deleted subject and a deleted user, which is the point of a trail. The causer columns are not null because a row without a causer is not a trail row. The database enforces that for a consumer that calls `activity()` without the trait.

## 6. Logging a model

Use the trait in the model class and describe what to log:

```php
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Logingrupa\Activitylog\Traits\LogsAttributedActivity;
use October\Rain\Database\Model;
use Spatie\Activitylog\Support\LogOptions;

class Note extends Model
{
    use HasUuids;
    use LogsAttributedActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'body'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(static fn (string $event): string => 'note.'.$event);
    }
}
```

List the attributes with `logOnly()`. October models are guarded by default, so `logUnguarded()` would log nothing.

A `created` row holds every listed attribute, including the ones that are null. An `updated` row holds only the attributes that changed, with the old values under `old` and the new ones under `attributes`. A `deleted` row holds the last values under `old`. A save that changes nothing writes no row.

Keep `description` a stable key. A name or a sentence in it cannot be translated or searched reliably.

## 7. Causer rules

The causer is the user of the default guard, the RainLab.User web user. A save or a delete that has no such user throws `MissingCauserException` before the model's SQL runs. The trait guards deletes as well as saves because `Model::delete()` fires no `saving` event.

A backend administrator, a console command or a queue job sets the causer around the write:

```php
use Spatie\Activitylog\Facades\Activity as ActivityFacade;

ActivityFacade::defaultCauser($user, fn () => $note->update(['title' => 'New title']));
```

`app(CauserResolver::class)->withCauser($user, fn () => ...)` does the same without the facade.

## 8. Extending

A dependent plugin has four routes, and they are the only ones this plugin supports:

- Subclass `Logingrupa\Activitylog\Models\Activity` and name the subclass in `activitylog.activity_model` from your plugin's `register()`.
- Add columns with your own migration in your plugin's `updates/` folder. Guard it with `Schema::hasTable` and `Schema::hasColumn`, and list `Logingrupa.Activitylog` in your plugin's `$require` so October runs it after the table exists.
- Compose the trait into your models.
- Call October's `Model::extend()` on the model, as on any other Rain model.

Everything else is the package's API. See its documentation.

## 9. Stability

From the first tag, these names change only in a new major version with a conversion migration:

- The table name, and its columns and their types.
- The `Activity` model, its `$table`, `UPDATED_AT`, casts and `$morphTo` arrays. It declares no fillable list, no builder and none of `isFillable`, `newEloquentBuilder`, `newModelQuery` or `setKeysForSaveQuery`.
- The `LogsAttributedActivity` trait and its guard on `saving` and `deleting`.
- `MissingCauserException` and its `forAnonymousWrite()` constructor.
- The `activitylog.activity_model` key as the override point, the `activitylog.buffer.enabled` pin and the `RainLab.User` requirement.

Not promised: index names, events (the plugin fires none), reader scopes, settings, permissions, a config directory, a switch to turn the guard off, and anything under `tests/`.

The repository follows semantic versioning. `updates/version.yaml` on the default branch matches the latest tag.

## 10. Limits

- Only models with UUID keys can be subjects. Changing that needs a new major version.
- Buffered logging is not supported and is switched off.
- `php artisan activitylog:clean` is the package's command. Whether to schedule it is your decision. The plugin never does.
- There is no screen, no settings page and no query scope. Rows are written, not read.
- October's `SoftDelete` trait is not Illuminate's, so the package does not add the `restored` event on its own. Declare the events you want in the model's `$recordEvents` property.

## 11. Tests

Inside an October project with the dev tools installed:

```
php artisan plugin:test Logingrupa.Activitylog --without-tty
```

The suite forces PostgreSQL on a local host and a database named `activitylog_testing`, and it refuses to run against any name that does not end in `_testing`. Create the database once, then the suite migrates and rolls back by itself:

```
createdb -h 127.0.0.1 -p 5432 -U <your user> activitylog_testing
```

## 12. Licence

MIT. See `LICENSE.md`.
