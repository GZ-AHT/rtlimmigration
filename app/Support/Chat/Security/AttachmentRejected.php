<?php

namespace App\Support\Chat\Security;

use RuntimeException;

/** A file that may not be sent in chat. $malicious marks an attack attempt rather than a wrong file type. */
class AttachmentRejected extends RuntimeException
{
    public function __construct(string $message, public readonly string $reason, public readonly bool $malicious = false)
    {
        parent::__construct($message);
    }
}
