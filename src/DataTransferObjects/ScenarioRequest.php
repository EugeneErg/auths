<?php

declare(strict_types=1);

namespace EuegeneErg\Auths\DataTransferObjects;

use EuegeneErg\Auths\Contracts\Scenario\ScenarioStepInterface;

final readonly class ScenarioRequest
{
    public function __construct(
        public ScenarioStepInterface $nextStep,
        public bool $asNewMessage = false,
    ) {
    }
}