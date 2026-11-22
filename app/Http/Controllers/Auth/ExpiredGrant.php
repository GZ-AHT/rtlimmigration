<?php

namespace App\Http\Controllers\Auth;

use RuntimeException;

/** The one-time password-reset grant was missing, used or out of time. */
class ExpiredGrant extends RuntimeException {}
