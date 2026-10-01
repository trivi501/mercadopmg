<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $prefix
 * @property int $padding
 * @property int $last_number
 */
#[Fillable(['name', 'prefix', 'padding', 'last_number'])]
class FolioSequence extends Model
{
    protected function casts(): array
    {
        return ['padding' => 'integer', 'last_number' => 'integer'];
    }
}
