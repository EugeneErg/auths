<?php

declare(strict_types=1);

namespace EugeneErg\Auths\Entities;

use DateTimeImmutable;
use EugeneErg\Auths\ValueObjects\ScenarioId;
use EugeneErg\Auths\ValueObjects\ScenarioStepExternalId;
use EugeneErg\Auths\ValueObjects\ScenarioStepId;

final readonly class ScenarioStep
{
    public function __construct(
        public ScenarioStepId $id,
        public ScenarioStepExternalId $externalId,
        public ScenarioId $scenarioId,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $processedAt,
        public string|null $name,
        public array $data,
        public ScenarioStepId|null $replyToId = null,
        public ScenarioStepExternalId|null $replyToExternalId = null,
    ) {
    }
}
