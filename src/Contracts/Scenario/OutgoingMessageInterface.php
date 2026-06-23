<?php

declare(strict_types=1);

namespace EugeneErg\Auths\Contracts\Scenario;

use JsonSerializable;

/**
 * Исходящее сообщение — общий интерфейс для шагов сценария и сообщений верификации.
 * Позволяет провайдеру отправлять оба типа единым способом.
 */
interface OutgoingMessageInterface extends JsonSerializable
{
    public function jsonSerialize(): array;
}
