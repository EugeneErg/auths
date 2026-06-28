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
 * Builder для запуска сценария от имени анонимного пользователя.
 * Action = auth (зашит, аноним всегда авторизуется).
 */
final class AnonymousStarter
{
    public function __construct(
        private readonly ScenarioInterface $scenario,
        private readonly ProviderType $type,
        private readonly Action $authAction,
        private readonly ProviderMessagingInterface $provider,
        private readonly WriteAuthVerificationRepositoryInterface $writeAuthVerificationRepository,
        private readonly WriteScenarioRepositoryInterface $writeScenarioRepository,
        private readonly WriteScenarioStepRepositoryInterface $writeScenarioStepRepository,
        private readonly TransactionInterface $transaction,
        private readonly string $scenarioName,
    ) {
    }

    public function withUser(UserId $userId, Action $action): AuthenticatedStarter
    {
        return new AuthenticatedStarter(
            scenario: $this->scenario,
            type: $this->type,
            action: $action,
            userId: $userId,
            provider: $this->provider,
            writeAuthVerificationRepository: $this->writeAuthVerificationRepository,
            writeScenarioRepository: $this->writeScenarioRepository,
            writeScenarioStepRepository: $this->writeScenarioStepRepository,
            transaction: $this->transaction,
            scenarioName: $this->scenarioName,
        );
    }

    /**
     * Запускаем сценарий, отправляем первый шаг, если шаг несёт код — сохраняем SentCode верификацию.
     * Адрес обязателен — мы инициируем отправку сами.
     *
     * @throws AuthProviderUnsuitableException
     * @throws AuthScenarioNotFoundException
     * @throws AuthVerificationInvalidException
     */
    public function withSentCode(ChannelAddress $address): AuthVerificationToken
    {
        return $this->run(
            address: $address,
            saveVerification: fn(string $code, DateInterval $ttl) => $this->writeAuthVerificationRepository->create(
                type: $this->type,
                code: new SentCode($code, $address),
                createdAt: new DateTimeImmutable(),
                expiresAt: (new DateTimeImmutable())->add($ttl),
                action: $this->authAction,
            )->token,
        );
    }

    /**
     * Сохраняем IssuedCode верификацию, возвращаем результат с deliverable.
     * Первый шаг сценария НЕ отправляется — придёт через handleWebhook когда пользователь напишет.
     *
     * @throws AuthVerificationInvalidException
     * @throws RandomException
     */
    public function withIssuedCode(): IssuedCodeResult
    {
        return $this->runIssuedCode(action: $this->authAction);
    }

    /**
     * Сохраняем OAuthState верификацию, возвращаем state фронту.
     * Первый шаг сценария НЕ отправляется.
     *
     * @throws AuthProviderUnsuitableException
     * @throws AuthVerificationInvalidException
     * @throws RandomException
     */
    public function withOAuth(): OAuthStateOptions
    {
        return $this->runOAuth(action: $this->authAction);
    }

    /**
     * @throws AuthProviderUnsuitableException
     * @throws AuthScenarioNotFoundException
     * @throws AuthVerificationInvalidException
     */
    private function run(ChannelAddress $address, callable $saveVerification): AuthVerificationToken
    {
        if (!$this->provider instanceof ProviderRequestHandlerInterface) {
            throw new AuthProviderUnsuitableException();
        }

        $createdAt = new DateTimeImmutable();
        $result = $this->scenario->run(new IncomingMessage(
            type: $this->type,
            address: $address,
            createdAt: $createdAt,
        ));

        if (!$result instanceof OutgoingStep) {
            throw new AuthScenarioNotFoundException('Scenario must return OutgoingStep on first run.');
        }

        $token = null;

        if ($result->nextStep instanceof HasVerificationCode) {
            $token = $saveVerification(
                $result->nextStep->getVerificationCode(),
                $result->nextStep->getVerificationTtl(),
            );
        }

        $externalId = $this->provider->sendStep(
            to: $address,
            step: $result->nextStep,
            action: $this->authAction,
            replyTo: null,
        );

        $this->transaction->transaction(function () use ($address, $createdAt, $result, $externalId): void {
            $scenario = $this->writeScenarioRepository->create(
                name: $this->scenarioName,
                type: $this->type,
                address: $address,
                createdAt: $createdAt,
                action: $this->authAction,
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

    /** @throws AuthVerificationInvalidException|RandomException */
    private function runIssuedCode(Action $action, ?UserId $userId = null): IssuedCodeResult
    {
        $code = bin2hex(random_bytes(16));
        $now = new DateTimeImmutable();

        // Запустим сценарий без адреса чтобы получить шаг и TTL
        $result = $this->scenario->run(null);

        if (!$result instanceof OutgoingStep) {
            throw new AuthScenarioNotFoundException('Scenario must return OutgoingStep on first run.');
        }

        if (!$result->nextStep instanceof HasVerificationCode) {
            throw new AuthVerificationInvalidException('Scenario step must implement HasVerificationCode for withIssuedCode.');
        }

        $ttl = $result->nextStep->getVerificationTtl();
        $verification = $this->writeAuthVerificationRepository->create(
            type: $this->type,
            code: new IssuedCode($code),
            createdAt: $now,
            expiresAt: $now->add($ttl),
            action: $action,
            userId: $userId,
        );

        return new IssuedCodeResult(
            token: $verification->token,
            code: $code,
        );
    }

    /** @throws AuthProviderUnsuitableException|AuthVerificationInvalidException|RandomException */
    private function runOAuth(Action $action, ?UserId $userId = null): OAuthStateOptions
    {
        if (!$this->provider instanceof ProviderCallbackInterface) {
            throw new AuthProviderUnsuitableException();
        }

        $state = new OAuthState(bin2hex(random_bytes(32)));
        $now = new DateTimeImmutable();

        // Получим TTL из шага
        $result = $this->scenario->run(null);

        if (!$result instanceof OutgoingStep) {
            throw new AuthScenarioNotFoundException('Scenario must return OutgoingStep on first run.');
        }

        if (!$result->nextStep instanceof HasVerificationCode) {
            throw new AuthVerificationInvalidException('Scenario step must implement HasVerificationCode for withOAuth.');
        }

        $ttl = $result->nextStep->getVerificationTtl();
        $verification = $this->writeAuthVerificationRepository->create(
            type: $this->type,
            code: $state,
            createdAt: $now,
            expiresAt: $now->add($ttl),
            action: $action,
            userId: $userId,
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
