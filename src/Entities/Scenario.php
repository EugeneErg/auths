<?php

declare(strict_types = 1);

namespace EuegeneErg\Auths\Entities;

use DateTimeImmutable;
use EuegeneErg\Auths\DataTransferObjects\ScenarioResult;
use EuegeneErg\Auths\ValueObjects\AuthIdentityValue;
use EuegeneErg\Auths\ValueObjects\ProviderType;
use EuegeneErg\Auths\ValueObjects\ScenarioId;
use EuegeneErg\Auths\ValueObjects\UserId;

final readonly class Scenario
{
    public function __construct(
        public ScenarioId $id,
        public string $name,
        public AuthIdentityValue $value,
        public ProviderType $type,
        public DateTimeImmutable $createdAt,
        public ?UserId $userId = null,
        public ?ScenarioResult $result = null,
    ) {
    }
}