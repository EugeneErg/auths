<?php

declare(strict_types=1);

namespace EugeneErg\Auths\DataTransferObjects;

use DateTimeImmutable;
use EugeneErg\Auths\Contracts\Scenario\MessagePartInterface;
use EugeneErg\Auths\ValueObjects\Action;
use EugeneErg\Auths\ValueObjects\ChannelAddress;
use EugeneErg\Auths\ValueObjects\ProviderType;

/**
 * Входящее сообщение от пользователя, передаваемое в сценарий для обработки.
 *
 * confirmedAction — не null если сервис успешно сверил verificationCode из Response
 *                   с активной AuthVerification для этого сценария.
 *                   Шаг проверяет: $message->confirmedAction !== null — верификация прошла.
 *
 * При startScenario (первый run): parts пусты, confirmedAction = null.
 * Сценарий использует type и address для персонализации первого сообщения.
 *
 * @param MessagePartInterface[] $parts
 * @param array<string, MessagePartInterface> $attachments
 */
final readonly class IncomingMessage
{
    public function __construct(
        public ProviderType $type,
        public ChannelAddress $address,
        public DateTimeImmutable $createdAt,
        public array $parts = [],
        public array $attachments = [],
        public Scenario|null $responseTo = null,
        public Action|null $confirmedAction = null,
    ) {
    }
}
