<?php

declare(strict_types=1);

namespace EuegeneErg\Auths\Contracts\Adapters;

use EuegeneErg\Auths\Contracts\Scenario\ScenarioStepInterface;
use EuegeneErg\Auths\DataTransferObjects\Response;
use EuegeneErg\Auths\ValueObjects\AuthIdentityValue;
use Psr\Http\Message\RequestInterface;

interface ProviderRequestHandlerInterface extends ProviderInterface
{
    public function handleRequest(RequestInterface $request): Response;
    public function sendMessage(ScenarioStepInterface $step, AuthIdentityValue $value, ?string $replyTo = null): string;
}