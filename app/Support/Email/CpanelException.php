<?php

namespace App\Support\Email;

use RuntimeException;

/** The cPanel API refused a call or could not be reached. The message is safe to show to admins. */
class CpanelException extends RuntimeException {}
