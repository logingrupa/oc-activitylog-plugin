<?php

declare(strict_types=1);

namespace Logingrupa\Activitylog\Tests;

use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Logingrupa\Activitylog\Models\Activity;
use RainLab\User\Models\User;

/**
 * A row written through activity() with and without a signed-in user, and what the table does with it.
 */
final class ActivityWriteTest extends ActivitylogTestCase
{
    private const TABLE = 'logingrupa_activitylog_activities';

    /**
     * testAWriteWithASignedInUserStoresTheCauserAndReadsBackAsACollection writes one row and reads the jsonb column back through the collection cast.
     */
    public function testAWriteWithASignedInUserStoresTheCauserAndReadsBackAsACollection(): void
    {
        $obUser = $this->createUser('writer@example.com');
        $this->signIn($obUser);

        activity()->event('created')->withChanges(['attributes' => ['name' => 'Alpha'], 'old' => []])->log('fixture.created');

        $obRow = Activity::query()->latest('id')->first();
        $this->assertInstanceOf(Activity::class, $obRow);
        $this->assertSame(User::class, $obRow->causer_type);
        $this->assertSame($obUser->getKey(), $obRow->causer_id);
        $this->assertSame('fixture.created', $obRow->description);
        $this->assertSame('created', $obRow->event);
        $this->assertTrue($obRow->created_at->isToday());
        $this->assertNull($obRow->subject_type);
        $this->assertNull($obRow->subject_id);
        $this->assertFalse(Schema::hasColumn(self::TABLE, 'updated_at'));

        $obChanges = $obRow->attribute_changes;
        $this->assertInstanceOf(Collection::class, $obChanges);
        $this->assertSame(['name' => 'Alpha'], $obChanges->get('attributes'));
        $this->assertSame([], $obChanges->get('old'));
    }

    /**
     * testAWriteWithNoUserIsRefusedByTheDatabase writes through the logger with nobody signed in. The savepoint keeps the test transaction usable after the failed insert.
     */
    public function testAWriteWithNoUserIsRefusedByTheDatabase(): void
    {
        $obFailure = $this->thrownBy(static function (): void {
            DB::transaction(static fn () => activity()->event('created')->log('fixture.created'));
        });

        $this->assertInstanceOf(QueryException::class, $obFailure);
        $this->assertStringContainsString('SQLSTATE[23502]', $obFailure->getMessage());
        $this->assertSame(0, Activity::query()->count());
    }
}
