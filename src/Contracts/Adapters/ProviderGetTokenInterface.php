<?php

declare(strict_types=1);

namespace EugeneErg\Auths\Contracts\Adapters;

use EugeneErg\Auths\ValueObjects\IssuedCode;

/**
 * Провайдер, через который сервис выдаёт токен пользователю.
 *
 * Флоу:
 *   1. Сервис генерирует IssuedCode и сохраняет как AuthVerification.
 *   2. Провайдер упаковывает код в deeplink/QR/URL — это его ответственность.
 *   3. Пользователь переходит по ссылке или отправляет код на наш аккаунт (бот, email и т.д.).
 *   4. Сервис получает код обратно через handleWebhook или отдельный endpoint и верифицирует.
 *
 * Сам сервис кодирует IssuedCode в строку токена — провайдер только упаковывает его в нужный формат.
 */
interface ProviderGetTokenInterface extends ProviderInterface
{
    /**
     * Возвращает строку для передачи пользователю (deeplink, текст, QR-данные и т.д.).
     */
    public function buildDeliverable(IssuedCode $code): string;
}
