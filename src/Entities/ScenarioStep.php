<?php

declare(strict_types = 1);

namespace EuegeneErg\Auths\Entities;

use DateTimeImmutable;
use EuegeneErg\Auths\ValueObjects\ScenarioStepExternalId;
use EuegeneErg\Auths\ValueObjects\ScenarioId;
use EuegeneErg\Auths\ValueObjects\ScenarioStepId;

final readonly class ScenarioStep
{
    public function __construct(
        public ScenarioStepId $id,
        public ScenarioStepExternalId $externalId,
        public ScenarioId $scenarioId,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $processedAt,
        public ?string $name,
        public array $data,
        public ?ScenarioStepId $replyToId = null,
        public ?ScenarioStepExternalId $replyToExternalId = null,
    ) {
    }
}