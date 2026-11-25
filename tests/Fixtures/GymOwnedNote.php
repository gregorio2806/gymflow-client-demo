<?php

namespace Tests\Fixtures;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;

/**
 * Test-only gym-owned model (table created in the test) for exercising BelongsToGym.
 */
class GymOwnedNote extends Model
{
    use BelongsToGym;

    protected $table = 'test_gym_owned_notes';

    protected $fillable = ['body'];
}
