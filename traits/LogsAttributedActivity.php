<?php

declare(strict_types=1);

namespace Logingrupa\Activitylog\Traits;

use Illuminate\Database\Eloquent\Model;
use Logingrupa\Activitylog\Exceptions\MissingCauserException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\CauserResolver;

/**
 * Logs a model's changes like the package's LogsActivity trait and refuses to write or delete the model when no user can be named as the cause.
 *
 * The guard sits on saving and deleting because Model::delete() fires only deleting and deleted, and the package registers no listener there.
 * Without it a delete with nobody signed in would remove the row and then fail on the not-null causer columns.
 * The trait has to be a use in the model class: the package wires its listeners in a trait boot method, which Model::extend() cannot add.
 * A trait that scopes rows to an owner is listed before this one so its refusal runs first.
 *
 * @mixin Model
 */
trait LogsAttributedActivity
{
    use LogsActivity;

    /**
     * bootLogsAttributedActivity refuses an unattributed save or delete before the model's SQL runs.
     */
    public static function bootLogsAttributedActivity(): void
    {
        static::saving(static fn (Model $obModel) => self::requireCauser($obModel));
        static::deleting(static fn (Model $obModel) => self::requireCauser($obModel));
    }

    /**
     * requireCauser asks the package's resolver for the cause of the write.
     *
     * @throws MissingCauserException when the resolver returns no model.
     */
    private static function requireCauser(Model $obModel): void
    {
        if (resolve(CauserResolver::class)->resolve() instanceof Model) {
            return;
        }

        throw MissingCauserException::forAnonymousWrite($obModel::class);
    }
}
