<?php

namespace App\Data\Facebook;

/**
 * The answer to one Graph API call. `outcomeUnknown` means the request may
 * have reached Facebook (timeout, connection drop, 5xx): a publish in that
 * state must never be retried blindly.
 */
final readonly class FacebookApiResult
{
    public function __construct(
        public bool $successful,
        public ?string $id = null,
        public ?string $postId = null,
        public ?string $name = null,
        public ?string $error = null,
        public bool $outcomeUnknown = false,
    ) {}

    public static function failure(string $error, bool $outcomeUnknown = false): self
    {
        return new self(false, error: $error, outcomeUnknown: $outcomeUnknown);
    }
}
