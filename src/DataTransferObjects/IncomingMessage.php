<?php

declare(strict_types=1);

namespace EugeneErg\Auths\DataTransferObjects;

use DateTimeImmutable;
use EugeneErg\Auths\Contracts\Scenario\MessagePartInterface;
use EugeneErg\Auths\ValueObjects\ChannelAddress;
use EugeneErg\Auths\ValueObjects\ProviderType;

/**
 * Входящее сообщение от пользователя, передаваемое в сценарий для обработки.
 *
 * При startScenario: parts и attachments пусты, responseTo = null.
 * Сценарий может использовать type и address для персонализации первого сообщения.
 *
 * @param MessagePartInterface[]             $parts
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
    ) {
    }
}
