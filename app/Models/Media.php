<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * An uploaded website image (REQ §21, §23.4). Kept on the public disk so
 * pages can show it directly; only raster images are accepted (no SVG).
 *
 * @property int $id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property int|null $width
 * @property int|null $height
 * @property string|null $alt
 * @property int|null $uploaded_by
 */
#[Fillable(['disk', 'path', 'original_name', 'mime_type', 'size', 'width', 'height', 'alt', 'uploaded_by'])]
class Media extends Model
{
    protected static function booted(): void
    {
        static::deleted(fn (self $m) => Storage::disk($m->disk)->delete($m->path));
    }

    protected function casts(): array
    {
        return ['size' => 'integer', 'width' => 'integer', 'height' => 'integer'];
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function isVideo(): bool
    {
        return str_starts_with($this->mime_type, 'video/');
    }

    /** @return array{id: int, url: string, alt: string|null, width: int|null, height: int|null, name: string, size: int, kind: string, mime: string} */
    public function present(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->isVideo() ? 'video' : 'image',
            'mime' => $this->mime_type,
            'url' => $this->url(),
            'alt' => $this->alt,
            'width' => $this->width,
            'height' => $this->height,
            'name' => $this->original_name,
            'size' => $this->size,
        ];
    }
}
