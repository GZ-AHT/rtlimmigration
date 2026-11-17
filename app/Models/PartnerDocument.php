<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * REQ §16 Partner Documents: kept on the private disk, served only to staff.
 *
 * @property int $id
 * @property int|null $partner_id
 * @property int|null $partner_application_id
 * @property string $title
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property int|null $uploaded_by
 * @property CarbonInterface|null $created_at
 */
#[Fillable(['partner_id', 'partner_application_id', 'title', 'disk', 'path', 'original_name', 'mime_type', 'size', 'uploaded_by'])]
class PartnerDocument extends Model
{
    protected static function booted(): void
    {
        static::deleted(fn (self $d) => Storage::disk($d->disk)->delete($d->path));
    }
}
