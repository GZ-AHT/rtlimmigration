<?php

namespace App\Support\Email\Imap;

use RuntimeException;

/** The mail server could not be reached, refused the login or failed mid-sync. */
class ImapException extends RuntimeException {}
