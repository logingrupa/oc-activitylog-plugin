<?php

declare(strict_types=1);

namespace Logingrupa\Activitylog\Tests\Fixtures\Models;

use Logingrupa\Activitylog\Traits\LogsAttributedActivity;
use October\Rain\Database\Model;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A model with an integer key that logs its name through the attribution guard.
 *
 * @property int $id
 * @property string $name
 */
final class FixtureNote extends Model
{
    use LogsAttributedActivity;

    public $table = 'logingrupa_activitylog_fixture_notes';

    protected $fillable = ['name'];

    /**
     * getActivitylogOptions logs the name only, as the dirty values of an update.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(static fn (string $sEvent): string => 'fixture_note.'.$sEvent);
    }
}
