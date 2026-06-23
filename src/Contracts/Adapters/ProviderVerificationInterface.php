<?php

declare(strict_types=1);

namespace EugeneErg\Auths\Contracts\Adapters;

/**
 * Провайдер, через который мы инициируем отправку кода верификации пользователю.
 * Например: SMS-шлюз, email-сервис.
 * Использует ProviderMessagingInterface для фактической отправки.
 */
interface ProviderVerificationInterface extends ProviderMessagingInterface
{
}
