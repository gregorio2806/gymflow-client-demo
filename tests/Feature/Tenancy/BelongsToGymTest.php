<?php

namespace Tests\Feature\Tenancy;

use App\Models\Gym;
use App\Tenancy\CurrentGym;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\Fixtures\GymOwnedNote;
use Tests\TestCase;

class BelongsToGymTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gymA;

    private Gym $gymB;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('test_gym_owned_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained();
            $table->string('body');
            $table->timestamps();
        });

        $this->gymA = Gym::factory()->create();
        $this->gymB = Gym::factory()->create();
    }

    private function actAsGym(Gym $gym): void
    {
        app(CurrentGym::class)->set($gym);
    }

    private function noteFor(Gym $gym, string $body): GymOwnedNote
    {
        $this->actAsGym($gym);

        return GymOwnedNote::create(['body' => $body]);
    }

    public function test_new_records_get_the_current_gym()
    {
        $note = $this->noteFor($this->gymA, 'hello');

        $this->assertSame($this->gymA->id, $note->gym_id);
        $this->assertTrue($note->gym->is($this->gymA));
    }

    public function test_queries_only_return_the_current_gyms_records()
    {
        $this->noteFor($this->gymA, 'A1');
        $this->noteFor($this->gymA, 'A2');
        $this->noteFor($this->gymB, 'B1');

        $this->actAsGym($this->gymA);
        $this->assertEqualsCanonicalizing(['A1', 'A2'], GymOwnedNote::pluck('body')->all());

        $this->actAsGym($this->gymB);
        $this->assertSame(['B1'], GymOwnedNote::pluck('body')->all());
    }

    public function test_another_gyms_record_cannot_be_found_by_id()
    {
        $noteOfB = $this->noteFor($this->gymB, 'secret');

        $this->actAsGym($this->gymA);

        $this->assertNull(GymOwnedNote::find($noteOfB->id));
    }

    public function test_no_current_gym_returns_nothing()
    {
        $this->noteFor($this->gymA, 'A1');

        app(CurrentGym::class)->forget();

        $this->assertSame(0, GymOwnedNote::count());
    }

    public function test_gym_id_from_input_is_ignored_when_a_gym_is_current()
    {
        $this->actAsGym($this->gymA);

        $note = new GymOwnedNote(['body' => 'sneaky']);
        $note->gym_id = $this->gymB->id;
        $note->save();

        $this->assertSame($this->gymA->id, $note->refresh()->gym_id);
    }

    public function test_gym_id_is_not_mass_assignable()
    {
        $this->actAsGym($this->gymA);

        $note = new GymOwnedNote(['body' => 'x', 'gym_id' => $this->gymB->id]);

        $this->assertNull($note->gym_id);
    }

    public function test_creating_without_a_current_gym_or_gym_id_fails()
    {
        $this->expectException(LogicException::class);

        GymOwnedNote::create(['body' => 'orphan']);
    }

    public function test_jobs_and_seeders_can_create_with_an_explicit_gym_id()
    {
        $note = new GymOwnedNote(['body' => 'seeded']);
        $note->gym_id = $this->gymB->id;
        $note->save();

        $this->assertSame($this->gymB->id, $note->gym_id);
    }

    public function test_records_cannot_be_moved_to_another_gym()
    {
        $note = $this->noteFor($this->gymA, 'A1');

        $this->expectException(LogicException::class);

        $note->gym_id = $this->gymB->id;
        $note->save();
    }

    public function test_platform_admin_queries_can_opt_out_of_the_scope()
    {
        $this->noteFor($this->gymA, 'A1');
        $this->noteFor($this->gymB, 'B1');

        $this->assertSame(2, GymOwnedNote::withoutGlobalScope('gym')->count());
    }
}
