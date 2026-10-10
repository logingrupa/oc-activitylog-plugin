<?php

declare(strict_types=1);

namespace Logingrupa\Activitylog\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Logingrupa\Activitylog\Exceptions\MissingCauserException;
use Logingrupa\Activitylog\Models\Activity;
use Logingrupa\Activitylog\Tests\Fixtures\Models\FixtureNote;
use RainLab\User\Models\User;

/**
 * The trait on a model with an integer key and no owner: what a save and a delete write, and what they refuse when nobody is signed in.
 */
final class LogsAttributedActivityTest extends ActivitylogTestCase
{
    private const PROBE_TABLE = 'logingrupa_activitylog_fixture_notes';

    private User $obWriter;

    /**
     * setUp creates the probe table and signs a writer in.
     */
    #[\Override]
    public function setUp(): void
    {
        parent::setUp();

        Schema::create(self::PROBE_TABLE, static function (Blueprint $obTable): void {
            $obTable->id();
            $obTable->string('name');
            $obTable->unsignedInteger('views')->default(0);
            $obTable->timestamps();
        });
        $this->obWriter = $this->createUser('writer@example.com');
        $this->signIn($this->obWriter);
    }

    /**
     * tearDown drops the probe table, then rolls the test transaction back.
     */
    #[\Override]
    public function tearDown(): void
    {
        Schema::dropIfExists(self::PROBE_TABLE);
        parent::tearDown();
    }

    /**
     * testCreatingANoteWritesOneAttributedRowThatReadsBackItsSubjectAndCauser reads the stored row by property.
     */
    public function testCreatingANoteWritesOneAttributedRowThatReadsBackItsSubjectAndCauser(): void
    {
        $obNote = $this->noteNamed('Alpha');

        $this->assertSame(1, Activity::query()->count());
        $obRow = $this->latestRow();
        $this->assertSame('fixture_note.created', $obRow->description);
        $this->assertSame('created', $obRow->event);
        $this->assertSame(FixtureNote::class, $obRow->subject_type);
        $this->assertSame($obNote->getKey(), $obRow->subject_id);
        $this->assertSame(User::class, $obRow->causer_type);
        $this->assertSame($this->obWriter->getKey(), $obRow->causer_id);
        $this->assertSame(['attributes' => ['name' => 'Alpha']], $obRow->attribute_changes?->all());

        $obSubject = $obRow->subject;
        $obCauser = $obRow->causer;
        $this->assertInstanceOf(FixtureNote::class, $obSubject);
        $this->assertSame($obNote->getKey(), $obSubject->getKey());
        $this->assertInstanceOf(User::class, $obCauser);
        $this->assertSame($this->obWriter->getKey(), $obCauser->getKey());
    }

    /**
     * testRenamingANoteWritesTheOldAndTheNewName compares the two changed values.
     */
    public function testRenamingANoteWritesTheOldAndTheNewName(): void
    {
        $obNote = $this->noteNamed('Alpha');

        $obNote->setAttribute('name', 'Beta');
        $obNote->save();

        $this->assertSame(2, Activity::query()->count());
        $obRow = $this->latestRow();
        $this->assertSame('fixture_note.updated', $obRow->description);
        $this->assertSame(['name' => 'Alpha'], $obRow->attribute_changes?->get('old'));
        $this->assertSame(['name' => 'Beta'], $obRow->attribute_changes->get('attributes'));
    }

    /**
     * testSavingANoteWithItsOwnValuesWritesNothing saves a model that has no dirty attribute.
     */
    public function testSavingANoteWithItsOwnValuesWritesNothing(): void
    {
        $obNote = $this->noteNamed('Alpha');

        $obNote->fill(['name' => 'Alpha']);
        $obNote->save();

        $this->assertSame(1, Activity::query()->count());
    }

    /**
     * testDeletingANoteWritesItsLastValues keeps the values of a row that no longer exists.
     */
    public function testDeletingANoteWritesItsLastValues(): void
    {
        $obNote = $this->noteNamed('Alpha');

        $obNote->delete();

        $obRow = $this->latestRow();
        $this->assertSame('fixture_note.deleted', $obRow->description);
        $this->assertSame(['old' => ['name' => 'Alpha']], $obRow->attribute_changes?->all());
        $this->assertSame(0, DB::table(self::PROBE_TABLE)->count());
    }

    /**
     * testASaveWithNoUserIsRefusedBeforeTheInsert signs the writer out and saves a new note.
     */
    public function testASaveWithNoUserIsRefusedBeforeTheInsert(): void
    {
        Auth::logout();

        $obFailure = $this->thrownBy(fn () => $this->noteNamed('Alpha'));

        $this->assertInstanceOf(MissingCauserException::class, $obFailure);
        $this->assertSame(0, DB::table(self::PROBE_TABLE)->count());
        $this->assertSame(0, Activity::query()->count());
    }

    /**
     * testADeleteWithNoUserIsRefusedBeforeTheDelete signs the writer out after the note exists and deletes it.
     */
    public function testADeleteWithNoUserIsRefusedBeforeTheDelete(): void
    {
        $obNote = $this->noteNamed('Alpha');
        Auth::logout();

        $obFailure = $this->thrownBy(static fn () => $obNote->delete());

        $this->assertInstanceOf(MissingCauserException::class, $obFailure);
        $this->assertSame(1, DB::table(self::PROBE_TABLE)->count());
        $this->assertSame(1, Activity::query()->count());
    }

    /**
     * testAnIncrementWithNoUserIsRefusedBeforeTheUpdate signs the writer out after the note exists and increments a column.
     */
    public function testAnIncrementWithNoUserIsRefusedBeforeTheUpdate(): void
    {
        $obNote = $this->noteNamed('Alpha');
        Auth::logout();

        $obFailure = $this->thrownBy(static fn () => $obNote->increment('views'));

        $this->assertInstanceOf(MissingCauserException::class, $obFailure);
        $this->assertSame(0, DB::table(self::PROBE_TABLE)->value('views'));
        $this->assertSame(1, Activity::query()->count());
    }

    /**
     * testAQuietSaveWithNoUserIsNeitherRefusedNorLogged signs the writer out after the note exists and saves a rename without events.
     * The guard listens to model events, so a write that fires none passes it and leaves no row.
     */
    public function testAQuietSaveWithNoUserIsNeitherRefusedNorLogged(): void
    {
        $obNote = $this->noteNamed('Alpha');
        Auth::logout();
        $obNote->setAttribute('name', 'Quiet');

        $obFailure = $this->thrownBy(static fn () => $obNote->saveQuietly());

        $this->assertNull($obFailure);
        $this->assertSame('Quiet', DB::table(self::PROBE_TABLE)->value('name'));
        $this->assertSame(1, Activity::query()->count());
    }

    /**
     * noteNamed stores a note while the writer is signed in.
     */
    private function noteNamed(string $sName): FixtureNote
    {
        $obNote = new FixtureNote();
        $obNote->setAttribute('name', $sName);
        $obNote->save();

        return $obNote;
    }

    /**
     * latestRow returns the newest activity row.
     */
    private function latestRow(): Activity
    {
        $obRow = Activity::query()->latest('id')->first();
        $this->assertInstanceOf(Activity::class, $obRow);

        return $obRow;
    }
}
