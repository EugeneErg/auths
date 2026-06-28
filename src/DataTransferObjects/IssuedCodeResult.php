<?php

declare(strict_types=1);

namespace EugeneErg\Auths\DataTransferObjects;

use EugeneErg\Auths\ValueObjects\AuthVerificationToken;

/**
 * Результат withIssuedCode().
 * token — внутренний токен верификации
 * code  — строка кода для передачи пользователю (в deeplink, QR и т.д.)
 */
final readonly class IssuedCodeResult
{
    public function __construct(
        public AuthVerificationToken $token,
        public string $code,
    ) {
    }
}
