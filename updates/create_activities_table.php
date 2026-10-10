<?php

declare(strict_types=1);

namespace Logingrupa\Activitylog\Updates;

use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Creates the activity table. The package's own migration stub is not used: October's updater accepts only its own Migration class,
 * and the stub's morph columns would not fit this plugin's rules.
 *
 * subject_id is the stub's nullable unsigned bigint morph column, so any integer-keyed model can be a subject.
 * causer_type and causer_id are not null: a row with no causer is not a trail row, the trait refuses it first and the database refuses it for any
 * consumer that writes without the trait.
 * The table has created_at and no second timestamp column, because rows are never updated.
 * There is no foreign key on subject_id or causer_id, so a row outlives a deleted subject and a deleted user.
 * There is no index on log_name, because one log is the expected use.
 * The two morph indexes get their own names, because PostgreSQL index names are unique per schema and the stub's names are not.
 */
final class CreateActivitiesTable extends Migration
{
    private const TABLE = 'logingrupa_activitylog_activities';

    private const SUBJECT_INDEX = 'activitylog_activities_subject_idx';

    private const CAUSER_INDEX = 'activitylog_activities_causer_idx';

    /**
     * up creates the table and its two morph indexes.
     */
    public function up(): void
    {
        Schema::create(self::TABLE, static function (Blueprint $obTable): void {
            $obTable->bigIncrements('id');
            $obTable->string('log_name')->nullable();
            $obTable->text('description');
            $obTable->string('subject_type')->nullable();
            $obTable->unsignedBigInteger('subject_id')->nullable();
            $obTable->string('event')->nullable();
            $obTable->string('causer_type');
            $obTable->bigInteger('causer_id');
            $obTable->jsonb('attribute_changes')->nullable();
            $obTable->jsonb('properties')->nullable();
            $obTable->timestamp('created_at');
            $obTable->index(['subject_type', 'subject_id'], self::SUBJECT_INDEX);
            $obTable->index(['causer_type', 'causer_id'], self::CAUSER_INDEX);
        });
    }

    /**
     * down drops the table.
     */
    public function down(): void
    {
        // Rolling back deletes the whole trail; use it in development only.
        Schema::dropIfExists(self::TABLE);
    }
}
