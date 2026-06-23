<?php

declare(strict_types=1);

namespace EugeneErg\Auths\DataTransferObjects;

use DateTimeImmutable;
use EugeneErg\Auths\Entities\Scenario;
use EugeneErg\Auths\ValueObjects\ChannelAddress;
use EugeneErg\Auths\ValueObjects\ProviderType;
use EugeneErg\Auths\ValueObjects\ScenarioId;
use EugeneErg\Auths\ValueObjects\ScenarioStepExternalId;
use EugeneErg\Auths\ValueObjects\ScenarioStepId;
use EugeneErg\Auths\ValueObjects\UserId;

final readonly class ScenarioStep
{
    public function __construct(
        public ScenarioId $scenarioId,
        public string $scenarioName,
        public ChannelAddress $address,
        public ProviderType $type,
        public ScenarioStepId $stepId,
        public ScenarioStepExternalId $externalId,
        public DateTimeImmutable $createdAt,
        public string|null $stepName,
        public array $data,
        public ScenarioResult|null $result = null,
        public ScenarioStepId|null $replyToId = null,
        public ScenarioStepExternalId|null $replyToExternalId = null,
        public UserId|null $userId = null,
    ) {
    }

    public function getScenario(): Scenario
    {
        return new Scenario(
            id: $this->scenarioId,
            name: $this->scenarioName,
            address: $this->address,
            type: $this->type,
            createdAt: $this->createdAt,
            userId: $this->userId,
            result: $this->result,
        );
    }
}
