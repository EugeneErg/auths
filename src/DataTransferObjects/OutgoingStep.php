<?php

declare(strict_types=1);

namespace EugeneErg\Auths\DataTransferObjects;

use EugeneErg\Auths\Contracts\Scenario\OutgoingMessageInterface;
use EugeneErg\Auths\Contracts\Scenario\ScenarioStepInterface;

/**
 * Исходящий шаг сценария — ответ сценария пользователю.
 * Содержит следующий шаг для отправки и флаг: ответить в той же цепочке или начать новое сообщение.
 */
final readonly class OutgoingStep implements OutgoingMessageInterface
{
    public function __construct(
        public ScenarioStepInterface $nextStep,
        public bool $asNewMessage = false,
    ) {
    }

    public function jsonSerialize(): array
    {
        return $this->nextStep->jsonSerialize();
    }
}
