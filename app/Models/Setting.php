<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $group
 * @property string $key
 * @property mixed $value
 * @property bool $is_encrypted
 * @property bool $is_public
 */
#[Fillable(['group', 'key', 'value', 'is_encrypted', 'is_public'])]
class Setting extends Model
{
    protected function casts(): array
    {
        return [
            'value' => 'json',
            'is_encrypted' => 'boolean',
            'is_public' => 'boolean',
        ];
    }
}
