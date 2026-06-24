<?php

declare(strict_types=1);

namespace EugeneErg\Auths\DataTransferObjects;

use Closure;
use EugeneErg\Auths\Contracts\Scenario\ScenarioResultInterface;
use EugeneErg\Auths\Contracts\Scenario\ScenarioStepInterface;

use const E_USER_WARNING;

/**
 * Контекст сценария, передаваемый через IncomingMessage::$responseTo.
 *
 * name    — имя сценария
 * current — true если это тот же сценарий, что активен сейчас
 * step    — шаг, на который пришёл ответ (восстановлен из БД)
 * result  — финальный результат, если сценарий уже завершён
 * parent  — родительский сценарий (ленивая загрузка) для цепочек
 *
 * @property-read Scenario|null $parent
 */
final readonly class Scenario
{
    public function __construct(
        public string $name,
        public bool $current,
        public ScenarioStepInterface|null $step = null,
        private Scenario|Closure|null $parent = null,
        public ScenarioResultInterface|null $result = null,
    ) {
    }

    public function __get(string $name): Scenario|null
    {
        if ($name === 'parent') {
            return $this->parent instanceof Closure ? ($this->parent)() : $this->parent;
        }

        trigger_error('Undefined property: ' . __CLASS__ . '::$' . $name, E_USER_WARNING);

        return null;
    }
}
