<?php

declare(strict_types=1);

namespace EuegeneErg\Auths\DataTransferObjects;

use Closure;
use EuegeneErg\Auths\Contracts\Scenario\ScenarioResultInterface;
use EuegeneErg\Auths\Contracts\Scenario\ScenarioStepInterface;

/**
 * @property-read null|ScenarioResponse $scenario
 */
final readonly class Scenario
{
    public function __construct(
        public string $name,
        public bool $current,
        public ?ScenarioStepInterface $step = null,
        private null|ScenarioResponse|Closure $scenario = null,
        public ?ScenarioResultInterface $result = null,
    ) {
    }

    public function __get(string $name): ?ScenarioResponse
    {
        if ($name === 'scenario') {
            return $this->scenario instanceof Closure ? ($this->scenario)() : $this->scenario;
        }

        trigger_error('Undefined property: ' . __CLASS__ . '"::$' . $name, E_USER_WARNING);

        return null;
    }
}