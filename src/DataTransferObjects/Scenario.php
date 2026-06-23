<?php

declare(strict_types=1);

namespace EugeneErg\Auths\DataTransferObjects;

use Closure;
use EugeneErg\Auths\Contracts\Scenario\ScenarioResultInterface;
use EugeneErg\Auths\Contracts\Scenario\ScenarioStepInterface;

use const E_USER_WARNING;

/**
 * @property IncomingMessage|null $scenario
 */
final readonly class Scenario
{
    public function __construct(
        public string $name,
        public bool $current,
        public ScenarioStepInterface|null $step = null,
        private IncomingMessage|Closure|null $scenario = null,
        public ScenarioResultInterface|null $result = null,
    ) {
    }

    public function __get(string $name): IncomingMessage|null
    {
        if ($name === 'scenario') {
            return $this->scenario instanceof Closure ? ($this->scenario)() : $this->scenario;
        }

        trigger_error('Undefined property: ' . __CLASS__ . '::$' . $name, E_USER_WARNING);

        return null;
    }
}
