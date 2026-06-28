<?php

declare(strict_types=1);

namespace EugeneErg\Auths\DataTransferObjects;

use DateTimeImmutable;
use EugeneErg\Auths\Contracts\Scenario\MessagePartInterface;
use EugeneErg\Auths\ValueObjects\ChannelAddress;
use EugeneErg\Auths\ValueObjects\ScenarioStepExternalId;

/**
 * Результат обработки входящего webhook-запроса провайдером.
 * verificationCode — если провайдер распознал в сообщении код верификации
 *                    (например пользователь прислал OTP или команду /start TOKEN),
 *                    сервис проверит его против активной AuthVerification до передачи в сценарий.
 */
final readonly class Response
{
    /**
     * @param MessagePartInterface[] $parts
     * @param array<string, MessagePartInterface> $attachments
     */
    public function __construct(
        public ScenarioStepExternalId $id,
        public ChannelAddress $address,
        public DateTimeImmutable $processedAt,
        public ScenarioStepExternalId|null $replyTo = null,
        public array $parts = [],
        public array $attachments = [],
        public string|null $verificationCode = null,
    ) {
    }
}
