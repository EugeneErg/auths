<?php

declare(strict_types=1);

namespace EuegeneErg\Auths\DataTransferObjects;

use DateTimeImmutable;
use EuegeneErg\Auths\ValueObjects\AuthIdentityValue;
use EuegeneErg\Auths\ValueObjects\ProviderType;
use EuegeneErg\Auths\ValueObjects\ScenarioStepExternalId;
use EuegeneErg\Auths\ValueObjects\ScenarioId;
use EuegeneErg\Auths\ValueObjects\ScenarioStepId;
use EuegeneErg\Auths\ValueObjects\UserId;

final readonly class ScenarioStep
{
    public function __construct(
        public ScenarioId $scenarioId,
        public string $scenarioName,
        public AuthIdentityValue $value,
        public ProviderType $type,
        public ScenarioStepId $stepId,
        public ScenarioStepExternalId $externalId,
        public DateTimeImmutable $createdAt,
        public ?string $stepName,
        public array $data,
        public ?ScenarioResult $result = null,
        public ?ScenarioStepId $replyToId = null,
        public ?ScenarioStepExternalId $replyToExternalId = null,
        public ?UserId $userId = null,
    ) {
    }

    public function getScenario(): \EuegeneErg\Auths\Entities\Scenario
    {
        return new \EuegeneErg\Auths\Entities\Scenario(
            id: $this->scenarioId,
            name: $this->scenarioName,
            value: $this->value,
            type: $this->type,
            createdAt: $this->createdAt,
            userId: $this->userId,
            result: $this->result,
        );
    }
}