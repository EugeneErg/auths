<?php

declare(strict_types=1);

namespace EugeneErg\Auths\ScenarioStarters;

use DateInterval;
use DateTimeImmutable;
use EugeneErg\Auths\Contracts\Adapters\ProviderCallbackInterface;
use EugeneErg\Auths\Contracts\Adapters\ProviderMessagingInterface;
use EugeneErg\Auths\Contracts\Adapters\ProviderRequestHandlerInterface;
use EugeneErg\Auths\Contracts\Repositories\Write\WriteAuthVerificationRepositoryInterface;
use EugeneErg\Auths\Contracts\Repositories\Write\WriteScenarioRepositoryInterface;
use EugeneErg\Auths\Contracts\Repositories\Write\WriteScenarioStepRepositoryInterface;
use EugeneErg\Auths\Contracts\Scenario\HasVerificationCode;
use EugeneErg\Auths\Contracts\Scenario\ScenarioInterface;
use EugeneErg\Auths\Contracts\TransactionInterface;
use EugeneErg\Auths\DataTransferObjects\IncomingMessage;
use EugeneErg\Auths\DataTransferObjects\IssuedCodeResult;
use EugeneErg\Auths\DataTransferObjects\OAuthStateOptions;
use EugeneErg\Auths\DataTransferObjects\OutgoingStep;
use EugeneErg\Auths\Exceptions\AuthProviderUnsuitableException;
use EugeneErg\Auths\Exceptions\AuthScenarioNotFoundException;
use EugeneErg\Auths\Exceptions\AuthVerificationInvalidException;
use EugeneErg\Auths\ValueObjects\Action;
use EugeneErg\Auths\ValueObjects\AuthVerificationToken;
use EugeneErg\Auths\ValueObjects\ChannelAddress;
use EugeneErg\Auths\ValueObjects\IssuedCode;
use EugeneErg\Auths\ValueObjects\OAuthState;
use EugeneErg\Auths\ValueObjects\ProviderType;
use EugeneErg\Auths\ValueObjects\SentCode;
use EugeneErg\Auths\ValueObjects\UserId;
use Random\RandomException;

/**
 * Builder для запуска сценария от имени авторизованного пользователя.
 * Action явный — attach, remove, confirm_payment и т.д.
 * Адрес для withSentCode опционален — берётся из identity если не указан.
 */
final class AuthenticatedStarter
{
    public function __construct(
        private readonly ScenarioInterface $scenario,
        private readonly ProviderType $type,
        private readonly Action $action,
        private readonly UserId $userId,
        private readonly ProviderMessagingInterface $provider,
        private readonly WriteAuthVerificationRepositoryInterface $writeAuthVerificationRepository,
        private readonly WriteScenarioRepositoryInterface $writeScenarioRepository,
        private readonly WriteScenarioStepRepositoryInterface $writeScenarioStepRepository,
        private readonly TransactionInterface $transaction,
        private readonly string $scenarioName,
        private readonly ChannelAddress|null $identityAddress = null,
    ) {
    }

    /**
     * @throws AuthProviderUnsuitableException
     * @throws AuthScenarioNotFoundException
     * @throws AuthVerificationInvalidException
     */
    public function withSentCode(ChannelAddress|null $address = null): AuthVerificationToken
    {
        $resolved = $address ?? $this->identityAddress;

        if ($resolved === null) {
            throw new AuthVerificationInvalidException(
                'Address required for withSentCode: provide explicitly or ensure user has identity for this provider.'
            );
        }

        if (!$this->provider instanceof ProviderRequestHandlerInterface) {
            throw new AuthProviderUnsuitableException();
        }

        $createdAt = new DateTimeImmutable();
        $result = $this->scenario->run(new IncomingMessage(
            type: $this->type,
            address: $resolved,
            createdAt: $createdAt,
        ));

        if (!$result instanceof OutgoingStep) {
            throw new AuthScenarioNotFoundException('Scenario must return OutgoingStep on first run.');
        }

        $token = null;

        if ($result->nextStep instanceof HasVerificationCode) {
            $now = new DateTimeImmutable();
            $token = $this->writeAuthVerificationRepository->create(
                type: $this->type,
                code: new SentCode($result->nextStep->getVerificationCode(), $resolved),
                createdAt: $now,
                expiresAt: $now->add($result->nextStep->getVerificationTtl()),
                action: $this->action,
                userId: $this->userId,
            )->token;
        }

        $externalId = $this->provider->sendStep(
            to: $resolved,
            step: $result->nextStep,
            action: $this->action,
            replyTo: null,
        );

        $this->transaction->transaction(function () use ($resolved, $createdAt, $result, $externalId): void {
            $scenario = $this->writeScenarioRepository->create(
                name: $this->scenarioName,
                type: $this->type,
                address: $resolved,
                createdAt: $createdAt,
                action: $this->action,
                userId: $this->userId,
            );
            $this->writeScenarioStepRepository->create(
                scenarioId: $scenario->id,
                externalId: $externalId,
                createdAt: $createdAt,
                processedAt: $createdAt,
                name: $this->getStepName($result->nextStep),
                data: $result->nextStep->jsonSerialize(),
                replyToExternalId: null,
                replyToId: null,
            );
        });

        if ($token === null) {
            throw new AuthVerificationInvalidException('Scenario step must implement HasVerificationCode for withSentCode.');
        }

        return $token;
    }

    /**
     * @throws AuthVerificationInvalidException
     * @throws RandomException
     */
    public function withIssuedCode(): IssuedCodeResult
    {
        $result = $this->scenario->run(null);

        if (!$result instanceof OutgoingStep) {
            throw new AuthScenarioNotFoundException('Scenario must return OutgoingStep on first run.');
        }

        if (!$result->nextStep instanceof HasVerificationCode) {
            throw new AuthVerificationInvalidException('Scenario step must implement HasVerificationCode for withIssuedCode.');
        }

        $code = bin2hex(random_bytes(16));
        $now = new DateTimeImmutable();
        $verification = $this->writeAuthVerificationRepository->create(
            type: $this->type,
            code: new IssuedCode($code),
            createdAt: $now,
            expiresAt: $now->add($result->nextStep->getVerificationTtl()),
            action: $this->action,
            userId: $this->userId,
        );

        return new IssuedCodeResult(token: $verification->token, code: $code);
    }

    /**
     * @throws AuthProviderUnsuitableException
     * @throws AuthVerificationInvalidException
     * @throws RandomException
     */
    public function withOAuth(): OAuthStateOptions
    {
        $result = $this->scenario->run(null);

        if (!$result instanceof OutgoingStep) {
            throw new AuthScenarioNotFoundException('Scenario must return OutgoingStep on first run.');
        }

        if (!$result->nextStep instanceof HasVerificationCode) {
            throw new AuthVerificationInvalidException('Scenario step must implement HasVerificationCode for withOAuth.');
        }

        if (!$this->provider instanceof ProviderCallbackInterface) {
            throw new AuthProviderUnsuitableException();
        }

        $state = new OAuthState(bin2hex(random_bytes(32)));
        $now = new DateTimeImmutable();
        $verification = $this->writeAuthVerificationRepository->create(
            type: $this->type,
            code: $state,
            createdAt: $now,
            expiresAt: $now->add($result->nextStep->getVerificationTtl()),
            action: $this->action,
            userId: $this->userId,
        );

        return new OAuthStateOptions($state, $verification->token);
    }

    private function getStepName(mixed $step): string
    {
        $steps = $this->scenario::getSteps();
        $name = array_search($step::class, $steps, true);
        return $name !== false ? (string) $name : $step::class;
    }
}
