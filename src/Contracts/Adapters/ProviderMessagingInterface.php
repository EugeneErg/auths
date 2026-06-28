<?php

declare(strict_types=1);

namespace EugeneErg\Auths\Contracts\Adapters;

use EugeneErg\Auths\Contracts\Scenario\ScenarioStepInterface;
use EugeneErg\Auths\ValueObjects\Action;
use EugeneErg\Auths\ValueObjects\ChannelAddress;
use EugeneErg\Auths\ValueObjects\ScenarioStepExternalId;

/**
 * Провайдер, способный отправлять шаги сценария пользователю.
 * Адаптер рендерит шаг в сообщение по классу шага.
 * Action передаётся для контекста — адаптер сам решает использовать ли его в тексте.
 */
interface ProviderMessagingInterface extends ProviderInterface
{
    /**
     * @param ChannelAddress        $to      Адрес получателя
     * @param ScenarioStepInterface $step    Шаг для рендеринга
     * @param Action                $action  Контекст сценария ("вы подтверждаете перевод")
     * @param string|null           $replyTo Внешний ID сообщения, на которое отвечаем
     *
     * @return ScenarioStepExternalId Внешний ID отправленного сообщения
     */
    public function sendStep(
        ChannelAddress $to,
        ScenarioStepInterface $step,
        Action $action,
        string|null $replyTo = null,
    ): ScenarioStepExternalId;
}
