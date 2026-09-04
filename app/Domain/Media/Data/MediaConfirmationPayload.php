<?php

namespace App\Domain\Media\Data;

use App\Domain\Media\Exceptions\MediaUploadFailure;
use Illuminate\Support\Str;

final readonly class MediaConfirmationPayload
{
    /** @param array<string, mixed> $providerEvidence */
    private function __construct(
        public string $intentReference,
        public array $providerEvidence,
        public string $title,
        public ?string $altText,
        public bool $overrideDuplicate,
        public ?string $overrideReason,
    ) {}

    /**
     * Accepts the canonical one-object Livewire contract and the previously
     * deployed positional contract. The latter remains fail closed: its intent
     * reference must have been echoed by the signed provider upload context.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromLivewire(
        array $payload,
        ?string $legacyTitle = null,
        ?string $legacyAltText = null,
        bool $legacyOverrideDuplicate = false,
        ?string $legacyOverrideReason = null,
    ): self {
        if (isset($payload['providerEvidence']) && is_array($payload['providerEvidence'])) {
            $reference = $payload['intentReference'] ?? null;
            $evidence = $payload['providerEvidence'];
            $title = $payload['title'] ?? null;
            $altText = $payload['altText'] ?? null;
            $override = $payload['overrideDuplicate'] ?? false;
            $reason = $payload['overrideReason'] ?? null;
        } else {
            $reference = data_get($payload, 'context.custom.intent_reference') ?? ($payload['intent_reference'] ?? null);
            $evidence = $payload;
            $title = $legacyTitle;
            $altText = $legacyAltText;
            $override = $legacyOverrideDuplicate;
            $reason = $legacyOverrideReason;
        }

        if (! is_string($reference) || ! Str::isUlid($reference)
            || ! is_string($title) || trim($title) === ''
            || ($altText !== null && ! is_string($altText))
            || ! is_bool($override)
            || ($reason !== null && ! is_string($reason))) {
            throw new MediaUploadFailure('media.confirmation_payload_malformed', 'Upload confirmation payload is malformed.');
        }

        return new self($reference, $evidence, trim($title), $altText, $override, $reason);
    }
}
