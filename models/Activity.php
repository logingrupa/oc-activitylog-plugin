<?php

declare(strict_types=1);

namespace Logingrupa\Activitylog\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use October\Rain\Database\Model;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;

/**
 * One row of the activity table, on October's Model so its query builder, events and relation arrays work unchanged.
 *
 * This class is not final on purpose. A dependent plugin subclasses it to add columns, traits or scopes and names the subclass
 * in the activitylog.activity_model config key, which is the package's own override path. The subclass inherits the table,
 * the casts and the relation arrays below.
 *
 * Traits in a subclass may call parent:: on the model methods they override: the public model defines none of isFillable,
 * newEloquentBuilder, newModelQuery or setKeysForSaveQuery, so parent:: inside a subclass trait reaches October's Rain Model unchanged.
 * It declares no fillable list: the package sets every attribute one by one and nothing mass assigns a row.
 *
 * @property int $id
 * @property string|null $log_name
 * @property string $description
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property string|null $event
 * @property string $causer_type
 * @property int $causer_id
 * @property Collection<string, mixed>|null $attribute_changes
 * @property Collection<string, mixed>|null $properties
 * @property Carbon $created_at
 * @property-read \Illuminate\Database\Eloquent\Model|null $subject
 * @property-read \Illuminate\Database\Eloquent\Model|null $causer
 */
class Activity extends Model implements ActivityContract
{
    /**
     * Rows are never updated, so the table has no updated_at column.
     */
    public const UPDATED_AT = null;

    public $table = 'logingrupa_activitylog_activities';

    protected $casts = [
        'attribute_changes' => 'collection',
        'properties' => 'collection',
    ];

    /**
     * October resolves a relation by property only through the relation arrays, so the two relations are listed here as well as in the methods.
     *
     * @var array<string, array<int|string, string>>
     */
    public $morphTo = [
        'subject' => [],
        'causer' => [],
    ];

    /**
     * subject is the model the row is about.
     *
     * @return MorphTo<\Illuminate\Database\Eloquent\Model, $this>
     */
    #[\Override]
    public function subject(): MorphTo
    {
        return $this->morphTo('subject');
    }

    /**
     * causer is the user who made the change.
     *
     * @return MorphTo<\Illuminate\Database\Eloquent\Model, $this>
     */
    #[\Override]
    public function causer(): MorphTo
    {
        return $this->morphTo('causer');
    }

    /**
     * getProperty reads one value from the properties column by dotted key.
     */
    #[\Override]
    public function getProperty(string $propertyName, mixed $defaultValue = null): mixed
    {
        return Arr::get($this->properties?->toArray() ?? [], $propertyName, $defaultValue);
    }
}
