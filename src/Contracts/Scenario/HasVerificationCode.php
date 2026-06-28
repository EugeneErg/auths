<?php

declare(strict_types=1);

namespace EugeneErg\Auths\Contracts\Scenario;

use DateInterval;

/**
 * Шаг сценария, несущий код верификации.
 * Сервис после run() проверяет этот интерфейс и сохраняет AuthVerification.
 * Провайдер рендерит шаг по классу и знает что показать пользователю.
 */
interface HasVerificationCode
{
    /**
     * Строка кода — сервис обернёт в нужный тип (SentCode/IssuedCode) по контексту запуска.
     */
    public function getVerificationCode(): string;

    public function getVerificationTtl(): DateInterval;
}
