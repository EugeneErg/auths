<?php

declare(strict_types=1);

namespace EugeneErg\Auths\Contracts\Adapters;

use EugeneErg\Auths\DataTransferObjects\Response;
use EugeneErg\Auths\ValueObjects\AuthVerificationToken;

/**
 * Опциональный интерфейс для провайдеров, через которые пользователь может
 * отправить IssuedCode обратно (Telegram /start TOKEN, команда боту и т.д.).
 *
 * Если провайдер реализует этот интерфейс, handleWebhook автоматически пробует
 * извлечь токен из входящего сообщения и вернуть VerificationResult.
 * Если токен невалидный/истёкший — обработка продолжается как обычный сценарий.
 */
interface ProviderTokenExtractorInterface extends ProviderInterface
{
    /**
     * Пытается извлечь токен верификации из входящего сообщения.
     * Возвращает null если сообщение не содержит токен.
     */
    public function extractToken(Response $response): AuthVerificationToken|null;
}
