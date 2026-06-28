<?php

declare(strict_types=1);

namespace EugeneErg\Auths\Contracts\Adapters;

use EugeneErg\Auths\DataTransferObjects\Response;
use Psr\Http\Message\RequestInterface;

/**
 * Провайдер, способный принимать входящие webhook-запросы.
 * Реализует оба интерфейса: принимает запросы И отправляет ответы.
 */
interface ProviderRequestHandlerInterface extends ProviderMessagingInterface
{
    public function handleRequest(RequestInterface $request): Response;
}
