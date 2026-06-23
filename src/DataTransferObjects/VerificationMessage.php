<?php

declare(strict_types=1);

namespace EugeneErg\Auths\DataTransferObjects;

use DateInterval;
use EugeneErg\Auths\Contracts\Scenario\OutgoingMessageInterface;
use EugeneErg\Auths\ValueObjects\Action;
use EugeneErg\Auths\ValueObjects\SentCode;

/**
 * Исходящее сообщение верификации — код подтверждения, который отправляется пользователю.
 * Реализует OutgoingMessageInterface, чтобы провайдер получал его через единый sendMessage.
 */
final readonly class VerificationMessage implements OutgoingMessageInterface
{
    public function __construct(
        public SentCode $code,
        public DateInterval $ttl,
        public Action $action,
        public string|null $redirect = null,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'code' => $this->code->value,
            'action' => $this->action->value,
            'redirect' => $this->redirect,
        ];
    }
}
