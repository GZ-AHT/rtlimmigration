<?php

namespace App\Support\Chat\Security;

use Illuminate\Http\UploadedFile;

/** A file that passed every check, with the type read from its contents and a safe display name. */
final readonly class InspectedAttachment
{
    public function __construct(
        public UploadedFile $file,
        public string $extension,
        public string $mimeType,
        public string $displayName,
        public int $size,
    ) {}
}
