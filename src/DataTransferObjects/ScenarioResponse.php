<?php

declare(strict_types=1);

namespace EuegeneErg\Auths\DataTransferObjects;

use DateTimeImmutable;
use EuegeneErg\Auths\Contracts\Scenario\MessagePartInterface;
use EuegeneErg\Auths\ValueObjects\ProviderType;

final readonly class ScenarioResponse
{
    /**
     * @param MessagePartInterface[] $parts
     * @param array<string, MessagePartInterface> $attachments
     */
    public function __construct(
        public ProviderType $type,
        public DateTimeImmutable $createdAt,
        public array $parts = [],
        public array $attachments = [],
        public ?Scenario $responseTo = null,
    ) {
    }
}
