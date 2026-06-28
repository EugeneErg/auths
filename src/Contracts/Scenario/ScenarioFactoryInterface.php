<?php

declare(strict_types=1);

namespace EugeneErg\Auths\Contracts\Scenario;

use EugeneErg\Auths\ValueObjects\Action;
use EugeneErg\Auths\ValueObjects\UserId;

/**
 * Фабрика сценариев для AuthService.
 * Программист реализует этот интерфейс и переопределяет нужные методы.
 * Дефолтная реализация — DefaultScenarioFactory.
 */
interface ScenarioFactoryInterface
{
    /**
     * Сценарий авторизации — для анонимного пользователя.
     */
    public function createAuth(): ScenarioInterface;

    /**
     * Сценарий привязки внешнего аккаунта к пользователю.
     */
    public function createAttach(UserId $userId): ScenarioInterface;

    /**
     * Сценарий отвязки внешнего аккаунта.
     */
    public function createDetach(UserId $userId): ScenarioInterface;

    /**
     * Сценарий подтверждения произвольного действия.
     */
    public function createConfirmAction(UserId $userId, Action $action): ScenarioInterface;
}
