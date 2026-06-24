<?php

declare(strict_types=1);

namespace EugeneErg\Auths\DataTransferObjects;

use EugeneErg\Auths\Contracts\Scenario\OutgoingMessageInterface;
use EugeneErg\Auths\Contracts\Scenario\ScenarioStepInterface;

/**
 * Исходящий шаг сценария — ответ сценария пользователю.
 * nextStep     — следующий шаг для отправки
 * asNewMessage — true: начать новую цепочку; false: ответить в той же
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
        return [
            'step' => $this->nextStep->jsonSerialize(),
            'asNewMessage' => $this->asNewMessage,
        ];
    }
}
