<?php

declare(strict_types=1);

namespace EugeneErg\Auths\DataTransferObjects;

use DateTimeImmutable;
use EugeneErg\Auths\Contracts\Scenario\MessagePartInterface;
use EugeneErg\Auths\ValueObjects\ChannelAddress;
use EugeneErg\Auths\ValueObjects\ScenarioStepExternalId;

final readonly class Response
{
    /**
     * @param MessagePartInterface[] $parts
     * @param array<string, MessagePartInterface> $attachments
     */
    public function __construct(
        public ScenarioStepExternalId $id,
        public ChannelAddress $address,
        public DateTimeImmutable $processedAt,
        public ScenarioStepExternalId|null $replyTo = null,
        public array $parts = [],
        public array $attachments = [],
    ) {
    }
}
