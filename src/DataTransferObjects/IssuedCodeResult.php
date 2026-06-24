<?php

declare(strict_types=1);

namespace EugeneErg\Auths\DataTransferObjects;

use EugeneErg\Auths\ValueObjects\AuthVerificationToken;

/**
 * Результат инициации IssuedCode флоу.
 * token       — токен верификации (используется в verifyToken)
 * deliverable — строка для передачи пользователю: deeplink, текст, QR-данные.
 *               Формат определяет провайдер через buildDeliverable().
 */
final readonly class IssuedCodeResult
{
    public function __construct(
        public AuthVerificationToken $token,
        public string $deliverable,
    ) {
    }
}
