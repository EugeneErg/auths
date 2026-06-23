<?php

declare(strict_types=1);

namespace EugeneErg\Auths\Contracts\Scenario;

use EugeneErg\Auths\DataTransferObjects\IncomingMessage;
use EugeneErg\Auths\DataTransferObjects\OutgoingStep;
use EugeneErg\Auths\DataTransferObjects\Scenario;

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

    public function run(IncomingMessage|null $message = null): OutgoingStep|ScenarioResultInterface|self|Scenario;
}
