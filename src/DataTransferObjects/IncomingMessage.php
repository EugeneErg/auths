<?php

declare(strict_types=1);

namespace EugeneErg\Auths\DataTransferObjects;

use DateTimeImmutable;
use EugeneErg\Auths\Contracts\Scenario\MessagePartInterface;
use EugeneErg\Auths\ValueObjects\ProviderType;

/**
 * Входящее сообщение от пользователя, передаваемое в сценарий для обработки.
 * Содержит тип канала, время, части сообщения и ссылку на предыдущий шаг сценария.
 */
final readonly class IncomingMessage
{
    /**
     * @param MessagePartInterface[] $parts
     * @param array<string, MessagePartInterface> $attachments
     */
    public function __construct(
        public ProviderType $type,
        public DateTimeImmutable $createdAt,
        public array $parts = [],
        public array $attachments = [],
        public Scenario|null $responseTo = null,
    ) {
    }
}
