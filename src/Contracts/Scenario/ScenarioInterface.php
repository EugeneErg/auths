<?php

declare(strict_types=1);

namespace EuegeneErg\Auths\Contracts\Scenario;

use EuegeneErg\Auths\DataTransferObjects\Scenario;
use EuegeneErg\Auths\DataTransferObjects\ScenarioRequest;
use EuegeneErg\Auths\DataTransferObjects\ScenarioResponse;

interface ScenarioInterface
{
    /**
     * @return array<string, class-string<ScenarioStepInterface>>
     */
    public static function getSteps(): array;

    /**
     * @return array<string, class-string<ScenarioResultInterface>>
     */
    public static function getResults(): array;

    public function run(?ScenarioResponse $request = null): ScenarioRequest|ScenarioResultInterface|self|Scenario;
}