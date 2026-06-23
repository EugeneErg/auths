<?php

declare(strict_types=1);

namespace EugeneErg\Auths\Contracts\Adapters;

use EugeneErg\Auths\Contracts\Scenario\OutgoingMessageInterface;
use EugeneErg\Auths\ValueObjects\ChannelAddress;
use EugeneErg\Auths\ValueObjects\ScenarioStepExternalId;

/**
 * Провайдер, способный отправлять сообщения пользователю.
 * Используется как для сценариев (следующий шаг), так и для верификации (код подтверждения).
 */
interface ProviderMessagingInterface extends ProviderInterface
{
    /**
     * @param ChannelAddress $to Адрес получателя в канале
     * @param OutgoingMessageInterface $message Сообщение для отправки
     * @param string|null $replyTo Внешний ID сообщения, на которое отвечаем
     *
     * @return ScenarioStepExternalId Внешний ID отправленного сообщения
     */
    public function sendMessage(
        ChannelAddress $to,
        OutgoingMessageInterface $message,
        string|null $replyTo = null,
    ): ScenarioStepExternalId;
}
