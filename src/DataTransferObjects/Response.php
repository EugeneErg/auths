<?php

declare(strict_types = 1);

namespace EuegeneErg\Auths\DataTransferObjects;

use DateTimeImmutable;
use EuegeneErg\Auths\Contracts\Scenario\MessagePartInterface;
use EuegeneErg\Auths\ValueObjects\AuthIdentityValue;
use EuegeneErg\Auths\ValueObjects\ScenarioStepExternalId;

final readonly class Response
{
    /**
     * @param MessagePartInterface[] $parts
     * @param array<string, MessagePartInterface> $attachments
     */
    public function __construct(
        public ScenarioStepExternalId $id,
        public AuthIdentityValue $value,
        public DateTimeImmutable $processedAt,
        public ?ScenarioStepExternalId $replyTo = null,
        public array $parts = [],
        public array $attachments = [],
    ) {
    }
}