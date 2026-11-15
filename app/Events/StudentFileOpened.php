<?php

namespace App\Events;

use App\Models\StudentFile;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A student file was opened (REQ §11 step 5). Phase 4 listens for this to
 * generate the file-opening fee payment request (REQ §11 step 6).
 */
class StudentFileOpened
{
    use Dispatchable;

    public function __construct(public readonly StudentFile $file, public readonly ?User $openedBy) {}
}
