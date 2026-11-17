<?php

namespace App\Support\Chat\Security;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A request refused by the chat security checks: a limit, a cooldown, a
 * block, or a CAPTCHA that is needed first. Always answered as JSON with a
 * message the visitor can act on.
 */
class ChatSecurityException extends HttpException
{
    public function __construct(int $status, string $message, public readonly ?int $retryAfter = null, public readonly bool $captchaRequired = false)
    {
        parent::__construct($status, $message, null, $retryAfter ? ['Retry-After' => (string) $retryAfter] : []);
    }

    public function render(): JsonResponse
    {
        return response()->json(array_filter([
            'message' => $this->getMessage(),
            'retry_after' => $this->retryAfter,
            'captcha_required' => $this->captchaRequired ?: null,
        ], fn ($v) => $v !== null), $this->getStatusCode(), $this->getHeaders());
    }
}
