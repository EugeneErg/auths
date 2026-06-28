<?php

declare(strict_types=1);

namespace EugeneErg\Auths;

use DateTimeImmutable;
use EugeneErg\Auths\Contracts\Adapters\ProviderCallbackInterface;
use EugeneErg\Auths\Contracts\Adapters\ProviderInterface;
use EugeneErg\Auths\Contracts\Adapters\ProviderPasswordValidatableInterface;
use EugeneErg\Auths\Contracts\Adapters\ProviderRequestHandlerInterface;
use EugeneErg\Auths\Contracts\Repositories\Read\ReadAuthIdentityRepositoryInterface;
use EugeneErg\Auths\Contracts\Repositories\Read\ReadAuthVerificationRepositoryInterface;
use EugeneErg\Auths\Contracts\Repositories\Read\ReadScenarioStepRepositoryInterface;
use EugeneErg\Auths\Contracts\Repositories\Write\WriteAuthIdentityRepositoryInterface;
use EugeneErg\Auths\Contracts\Repositories\Write\WriteAuthVerificationRepositoryInterface;
use EugeneErg\Auths\Contracts\Repositories\Write\WriteScenarioRepositoryInterface;
use EugeneErg\Auths\Contracts\Repositories\Write\WriteScenarioStepRepositoryInterface;
use EugeneErg\Auths\Contracts\Scenario\ScenarioInterface;
use EugeneErg\Auths\Contracts\Scenario\ScenarioResultInterface;
use EugeneErg\Auths\Contracts\Scenario\ScenarioStepInterface;
use EugeneErg\Auths\Contracts\TransactionInterface;
use EugeneErg\Auths\DataTransferObjects\IncomingMessage;
use EugeneErg\Auths\DataTransferObjects\OAuthCallbackOptions;
use EugeneErg\Auths\DataTransferObjects\OutgoingStep;
use EugeneErg\Auths\DataTransferObjects\Scenario;
use EugeneErg\Auths\DataTransferObjects\ScenarioResult;
use EugeneErg\Auths\DataTransferObjects\ScenarioStep;
use EugeneErg\Auths\DataTransferObjects\VerificationResult;
use EugeneErg\Auths\Entities\AuthVerification;
use EugeneErg\Auths\Exceptions\AuthExceptionInterface;
use EugeneErg\Auths\Exceptions\AuthIdentityNotFoundException;
use EugeneErg\Auths\Exceptions\AuthProviderNotFoundException;
use EugeneErg\Auths\Exceptions\AuthProviderUnsuitableException;
use EugeneErg\Auths\Exceptions\AuthScenarioNotFoundException;
use EugeneErg\Auths\Exceptions\AuthScenarioResultNotFoundException;
use EugeneErg\Auths\Exceptions\AuthScenarioStepNotFoundException;
use EugeneErg\Auths\Exceptions\AuthVerificationAlreadyUsedException;
use EugeneErg\Auths\Exceptions\AuthVerificationExpiredException;
use EugeneErg\Auths\Exceptions\AuthVerificationInvalidException;
use EugeneErg\Auths\ScenarioStarters\AnonymousStarter;
use EugeneErg\Auths\ValueObjects\Action;
use EugeneErg\Auths\ValueObjects\AuthVerificationToken;
use EugeneErg\Auths\ValueObjects\ChannelAddress;
use EugeneErg\Auths\ValueObjects\ProviderType;
use EugeneErg\Auths\ValueObjects\ScenarioId;
use EugeneErg\Auths\ValueObjects\ScenarioStepId;
use EugeneErg\Auths\ValueObjects\UserId;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Основной сервис аутентификации.
 *
 * Точка входа для запуска сценариев — startScenario().
 * Возвращает AnonymousStarter с fluent API для выбора способа верификации.
 *
 * Отдельные методы для верификации без сценария:
 *   - verifyToken()         — IssuedCode/SentCode: пользователь вернул код
 *   - confirmCode()         — SentCode через сайт: пользователь ввёл код в форму
 *   - verifyOAuthCallback() — OAuth: провайдер вернул code+state
 *   - verifyPassword()      — классический login+password
 */
readonly class AuthService
{
    /**
     * @param array<string, ProviderInterface>               $providers
     * @param array<string, class-string<ScenarioInterface>> $scenarios
     *
     * @throws AuthScenarioNotFoundException
     */
    public function __construct(
        private array $providers,
        private array $scenarios,
        private ContainerInterface $container,
        private ReadAuthIdentityRepositoryInterface $readAuthIdentityRepository,
        private ReadAuthVerificationRepositoryInterface $readAuthVerificationRepository,
        private ReadScenarioStepRepositoryInterface $readScenarioStepRepository,
        private WriteAuthVerificationRepositoryInterface $writeAuthVerificationRepository,
        private WriteAuthIdentityRepositoryInterface $writeAuthIdentityRepository,
        private WriteScenarioRepositoryInterface $writeScenarioRepository,
        private WriteScenarioStepRepositoryInterface $writeScenarioStepRepository,
        private TransactionInterface $transaction,
        private string|null $defaultScenario = null,
        private Action $authAction = new Action('auth'),
        private Action $attachAction = new Action('attach'),
        private Action $removeAction = new Action('remove'),
        private bool $oneAccountPerProvider = false,
    ) {
        if ($defaultScenario !== null && !isset($scenarios[$defaultScenario])) {
            throw new AuthScenarioNotFoundException('Default scenario not found.');
        }
    }

    // -------------------------------------------------------------------------
    // Запуск сценария
    // -------------------------------------------------------------------------

    /**
     * Запускает сценарий и возвращает builder для выбора способа верификации.
     *
     * Аноним:
     *   ->withSentCode(ChannelAddress)  — отправляем код, возвращаем token
     *   ->withIssuedCode()              — выдаём код, возвращаем IssuedCodeResult
     *   ->withOAuth()                   — генерируем state, возвращаем OAuthStateOptions
     *
     * С пользователем:
     *   ->withUser(UserId, Action)->withSentCode(?ChannelAddress)
     *   ->withUser(UserId, Action)->withIssuedCode()
     *   ->withUser(UserId, Action)->withOAuth()
     *
     * @throws AuthExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function startScenario(ScenarioInterface $scenario, ProviderType $type): AnonymousStarter
    {
        $scenarioName = $this->getScenarioName($scenario);
        $provider = $this->getProvider($type);

        return new AnonymousStarter(
            scenario: $scenario,
            type: $type,
            authAction: $this->authAction,
            provider: $provider,
            writeAuthVerificationRepository: $this->writeAuthVerificationRepository,
            writeScenarioRepository: $this->writeScenarioRepository,
            writeScenarioStepRepository: $this->writeScenarioStepRepository,
            transaction: $this->transaction,
            scenarioName: $scenarioName,
        );
    }

    // -------------------------------------------------------------------------
    // Верификация
    // -------------------------------------------------------------------------

    /**
     * Верифицирует токен (IssuedCode флоу — пользователь отправил код нам через бот/endpoint).
     *
     * @throws AuthExceptionInterface
     */
    public function verifyToken(AuthVerificationToken $token): VerificationResult
    {
        $verification = $this->readAuthVerificationRepository->findByToken($token);

        if ($verification === null) {
            throw new AuthVerificationInvalidException();
        }

        $now = new DateTimeImmutable();
        $this->assertVerificationUsable($verification, $now);

        if ($verification->address === null) {
            throw new AuthVerificationInvalidException('Use verifyOAuthCallback() for OAuth verifications.');
        }

        $identity = $this->readAuthIdentityRepository->find($verification->type, $verification->address);

        $this->transaction->transaction(function () use ($verification, $now): void {
            $this->writeAuthVerificationRepository->consume($verification, $now);
        });

        return new VerificationResult(
            action: $verification->action,
            address: $verification->address,
            userId: $identity?->userId ?? $verification->userId,
            identity: $identity,
        );
    }

    /**
     * Верифицирует код введённый пользователем на сайте (SentCode флоу через форму).
     * Находит активную верификацию для адреса и сверяет код.
     *
     * @throws AuthExceptionInterface
     */
    public function confirmCode(ProviderType $type, ChannelAddress $address, string $code): VerificationResult
    {
        $verification = $this->readAuthVerificationRepository->findActiveByAddress($type, $address);

        if ($verification === null) {
            throw new AuthVerificationInvalidException();
        }

        $now = new DateTimeImmutable();
        $this->assertVerificationUsable($verification, $now);

        if ($verification->code !== $code) {
            throw new AuthVerificationInvalidException('Invalid code.');
        }

        $identity = $this->readAuthIdentityRepository->find($type, $address);

        $this->transaction->transaction(function () use ($verification, $now): void {
            $this->writeAuthVerificationRepository->consume($verification, $now);
        });

        return new VerificationResult(
            action: $verification->action,
            address: $address,
            userId: $identity?->userId ?? $verification->userId,
            identity: $identity,
        );
    }

    /**
     * Верифицирует OAuth callback: обменивает code на ChannelAddress через провайдер.
     *
     * @throws AuthExceptionInterface
     */
    public function verifyOAuthCallback(OAuthCallbackOptions $options): VerificationResult
    {
        $verification = $this->readAuthVerificationRepository->findByOAuthState(
            $options->type,
            $options->state,
        );

        if ($verification === null) {
            throw new AuthVerificationInvalidException();
        }

        $now = new DateTimeImmutable();
        $this->assertVerificationUsable($verification, $now);

        $provider = $this->getProvider($options->type);

        if (!$provider instanceof ProviderCallbackInterface) {
            throw new AuthProviderUnsuitableException();
        }

        $address = $provider->exchangeCode($options->code, $options->state, $options->redirect);
        $identity = $this->readAuthIdentityRepository->find($options->type, $address);

        $this->transaction->transaction(function () use ($verification, $now): void {
            $this->writeAuthVerificationRepository->consume($verification, $now);
        });

        return new VerificationResult(
            action: $verification->action,
            address: $address,
            userId: $identity?->userId ?? $verification->userId,
            identity: $identity,
        );
    }

    /**
     * Синхронная проверка логина и пароля. Без предварительного создания токена.
     *
     * @throws AuthExceptionInterface
     */
    public function verifyPassword(ProviderType $type, string $login, string $password): VerificationResult
    {
        $provider = $this->getProvider($type);

        if (!$provider instanceof ProviderPasswordValidatableInterface) {
            throw new AuthProviderUnsuitableException();
        }

        $externalId = $provider->getExternalIdByPassword($login, $password);

        if ($externalId === null) {
            throw new AuthVerificationInvalidException('Invalid login or password.');
        }

        $address = new ChannelAddress($externalId);
        $identity = $this->readAuthIdentityRepository->find($type, $address);

        return new VerificationResult(
            action: $this->authAction,
            address: $address,
            userId: $identity?->userId,
            identity: $identity,
        );
    }

    // -------------------------------------------------------------------------
    // Управление identity
    // -------------------------------------------------------------------------

    /** @throws AuthExceptionInterface */
    public function attachIdentity(ProviderType $type, ChannelAddress $address, UserId $userId): void
    {
        $this->assertCanAttach($userId, $type);

        $this->transaction->transaction(function () use ($type, $address, $userId): void {
            $this->writeAuthIdentityRepository->create(
                type: $type,
                address: $address,
                userId: $userId,
                createdAt: new DateTimeImmutable(),
            );
        });
    }

    /** @throws AuthExceptionInterface */
    public function detachIdentity(ProviderType $type, ChannelAddress $address): void
    {
        $identity = $this->readAuthIdentityRepository->find($type, $address);

        if ($identity === null) {
            throw new AuthIdentityNotFoundException();
        }

        $this->transaction->transaction(function () use ($identity): void {
            $this->writeAuthIdentityRepository->detach($identity, new DateTimeImmutable());
        });
    }

    /** @throws AuthExceptionInterface */
    public function scheduleIdentityDelete(ProviderType $type, ChannelAddress $address, \DateInterval $delay): void
    {
        $identity = $this->readAuthIdentityRepository->find($type, $address);

        if ($identity === null) {
            throw new AuthIdentityNotFoundException();
        }

        $this->transaction->transaction(function () use ($identity, $delay): void {
            $this->writeAuthIdentityRepository->scheduleDelete(
                $identity,
                (new DateTimeImmutable())->add($delay),
            );
        });
    }

    // -------------------------------------------------------------------------
    // Webhook
    // -------------------------------------------------------------------------

    /**
     * Обрабатывает входящий webhook от провайдера.
     *
     * Если Response содержит verificationCode — сервис находит активную верификацию
     * для этого адреса, сверяет код и если совпадает — передаёт confirmedAction в IncomingMessage.
     * Шаг сценария видит $message->confirmedAction !== null — верификация прошла.
     *
     * Параметр $scenarioName позволяет явно указать сценарий для параллельных диалогов.
     *
     * @throws AuthExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function handleWebhook(
        RequestInterface $request,
        ProviderType $type,
        string|null $scenarioName = null,
    ): ScenarioStepId|ScenarioId|ScenarioResultInterface {
        $provider = $this->getProvider($type);

        if (!$provider instanceof ProviderRequestHandlerInterface) {
            throw new AuthProviderUnsuitableException();
        }

        $response = $provider->handleRequest($request);

        // Проверяем verificationCode из сообщения
        $confirmedAction = null;

        if ($response->verificationCode !== null) {
            $verification = $this->readAuthVerificationRepository->findActiveByAddress($type, $response->address);

            if ($verification !== null
                && !$verification->isExpired(new DateTimeImmutable())
                && !$verification->isConsumed()
                && $verification->code === $response->verificationCode
            ) {
                $confirmedAction = $verification->action;
                $this->transaction->transaction(function () use ($verification): void {
                    $this->writeAuthVerificationRepository->consume($verification, new DateTimeImmutable());
                });
            }
        }

        $targetStep = $response->replyTo === null
            ? null
            : $this->readScenarioStepRepository->findByMessage(
                type: $type,
                address: $response->address,
                externalId: $response->replyTo,
            );

        if ($scenarioName !== null) {
            $namedStep = $this->readScenarioStepRepository->findLastByScenarioName(
                $type,
                $response->address,
                $scenarioName,
            );
        } else {
            $namedStep = null;
        }

        $lastStep = $this->readScenarioStepRepository->findLast(type: $type, address: $response->address);

        [$name, $current] = match (true) {
            $namedStep !== null  => [$namedStep->scenarioName, $namedStep],
            $lastStep !== null   => [$lastStep->scenarioName, $lastStep],
            $targetStep !== null => [$targetStep->scenarioName, $targetStep],
            $this->defaultScenario !== null => [$this->defaultScenario, null],
            default => throw new AuthScenarioNotFoundException(),
        };

        $createdAt = new DateTimeImmutable();

        /** @var ScenarioInterface $activeScenario */
        $activeScenario = $this->container->get($this->scenarios[$name]);
        $scenarios = [];

        do {
            $result = $activeScenario->run(new IncomingMessage(
                type: $type,
                address: $response->address,
                createdAt: $response->processedAt,
                parts: $response->parts,
                attachments: $response->attachments,
                responseTo: $targetStep === null || $current?->stepId->isEqual($targetStep->stepId)
                    ? null
                    : $this->makeScenario($targetStep, $current, $scenarios),
                confirmedAction: $confirmedAction,
            ));

            if ($result instanceof Scenario) {
                $id = spl_object_id($result);

                if (!isset($scenarios[$id])) {
                    throw new AuthScenarioNotFoundException();
                }

                $current = $scenarios[$id];

                /** @var ScenarioInterface $activeScenario */
                $activeScenario = $this->container->get($this->scenarios[$current->scenarioName]);
                $scenarios = [];
            } elseif ($result instanceof ScenarioInterface) {
                $current = null;
                $activeScenario = $result;
                $scenarios = [];
            }
        } while ($result instanceof Scenario || $result instanceof ScenarioInterface);

        $authIdentity = $this->readAuthIdentityRepository->find($type, $response->address);

        if ($result instanceof OutgoingStep) {
            $newStepExternalId = $provider->sendStep(
                to: $response->address,
                step: $result->nextStep,
                action: $current?->getScenario()->action ?? new Action($name),
                replyTo: $result->asNewMessage ? null : $response->id->value,
            );
        } else {
            $newStepExternalId = null;
        }

        return $this->transaction->transaction(function () use (
            $current,
            $result,
            $name,
            $type,
            $response,
            $authIdentity,
            $createdAt,
            $targetStep,
            $newStepExternalId,
        ): ScenarioStepId|ScenarioId|ScenarioResultInterface {
            $scenarioResult = $result instanceof ScenarioResultInterface
                ? new ScenarioResult(
                    name: $this->getScenarioResultName($name, $result),
                    data: $result->jsonSerialize(),
                )
                : null;

            $scenario = $current === null
                ? $this->writeScenarioRepository->create(
                    name: $name,
                    type: $type,
                    address: $response->address,
                    createdAt: $createdAt,
                    action: new Action($name),
                    userId: $authIdentity?->userId,
                    result: $scenarioResult,
                )
                : $this->writeScenarioRepository->update(
                    scenario: $current->getScenario(),
                    result: $scenarioResult,
                    userId: $current->userId ?? $authIdentity?->userId,
                );

            $userStep = $this->writeScenarioStepRepository->create(
                scenarioId: $scenario->id,
                externalId: $response->id,
                createdAt: $createdAt,
                processedAt: $response->processedAt,
                name: null,
                data: [
                    'parts' => $response->parts,
                    'attachments' => $response->attachments,
                ],
                replyToExternalId: $response->replyTo,
                replyToId: $targetStep?->stepId,
            );

            if ($result instanceof ScenarioResultInterface) {
                return $result;
            }

            if ($result instanceof OutgoingStep && $newStepExternalId !== null) {
                return $this->writeScenarioStepRepository->create(
                    scenarioId: $scenario->id,
                    externalId: $newStepExternalId,
                    createdAt: $createdAt,
                    processedAt: $createdAt,
                    name: $this->getStepName($scenario->name, $result->nextStep),
                    data: $result->nextStep->jsonSerialize(),
                    replyToExternalId: $result->asNewMessage ? null : $response->id,
                    replyToId: $result->asNewMessage ? null : $userStep->id,
                )->id;
            }

            return $scenario->id;
        });
    }

    // -------------------------------------------------------------------------
    // Приватные методы
    // -------------------------------------------------------------------------

    /** @throws AuthProviderNotFoundException */
    private function getProvider(ProviderType $type): ProviderInterface
    {
        if (!isset($this->providers[$type->value])) {
            throw new AuthProviderNotFoundException();
        }

        return $this->providers[$type->value];
    }

    /** @throws AuthScenarioNotFoundException */
    private function getScenarioName(ScenarioInterface $scenario): string
    {
        $name = array_search($scenario::class, $this->scenarios, true);

        if ($name === false) {
            throw new AuthScenarioNotFoundException('Scenario not registered.');
        }

        return (string) $name;
    }

    /** @throws AuthExceptionInterface */
    private function assertCanAttach(UserId $userId, ProviderType $type): void
    {
        if ($this->oneAccountPerProvider && $this->readAuthIdentityRepository->exists($userId, $type)) {
            throw new \EugeneErg\Auths\Exceptions\AuthTypeAlreadyExistsException();
        }
    }

    /** @throws AuthVerificationExpiredException|AuthVerificationAlreadyUsedException */
    private function assertVerificationUsable(AuthVerification $verification, DateTimeImmutable $now): void
    {
        if ($verification->isExpired($now)) {
            throw new AuthVerificationExpiredException();
        }

        if ($verification->isConsumed()) {
            throw new AuthVerificationAlreadyUsedException();
        }
    }

    /** @throws AuthScenarioNotFoundException|AuthScenarioResultNotFoundException */
    private function makeResult(string $scenarioName, ScenarioResult $result): ScenarioResultInterface
    {
        if (!isset($this->scenarios[$scenarioName])) {
            throw new AuthScenarioNotFoundException();
        }

        $results = $this->scenarios[$scenarioName]::getResults();

        if (!isset($results[$result->name])) {
            throw new AuthScenarioResultNotFoundException();
        }

        return ($results[$result->name])::fromArray($result->data);
    }

    /** @throws AuthScenarioNotFoundException|AuthScenarioStepNotFoundException */
    private function makeStep(string $scenarioName, string $stepName, array $data): ScenarioStepInterface
    {
        if (!isset($this->scenarios[$scenarioName])) {
            throw new AuthScenarioNotFoundException();
        }

        $steps = $this->scenarios[$scenarioName]::getSteps();

        if (!isset($steps[$stepName])) {
            throw new AuthScenarioStepNotFoundException();
        }

        return ($steps[$stepName])::fromArray($data);
    }

    /** @throws AuthScenarioNotFoundException|AuthScenarioResultNotFoundException|AuthScenarioStepNotFoundException */
    private function makeScenario(ScenarioStep $targetStep, ScenarioStep|null $current, array &$scenarios): Scenario
    {
        $result = new Scenario(
            name: $targetStep->scenarioName,
            current: $current?->scenarioId->isEqual($targetStep->scenarioId),
            step: $targetStep->stepName === null
                ? null
                : $this->makeStep($targetStep->scenarioName, $targetStep->stepName, $targetStep->data),
            parent: $targetStep->replyToId === null
                ? null
                : function () use ($targetStep, $current, $scenarios): Scenario {
                    $targetStep = $this->readScenarioStepRepository->findById($targetStep->replyToId);

                    if ($targetStep === null) {
                        throw new AuthScenarioStepNotFoundException();
                    }

                    return $this->makeScenario($targetStep, $current, $scenarios);
                },
            result: $targetStep->result === null
                ? null
                : $this->makeResult($targetStep->scenarioName, $targetStep->result),
        );
        $scenarios[spl_object_id($result)] = $targetStep;

        return $result;
    }

    /** @throws AuthScenarioNotFoundException|AuthScenarioResultNotFoundException */
    private function getScenarioResultName(string $scenarioName, ScenarioResultInterface $result): string
    {
        if (!isset($this->scenarios[$scenarioName])) {
            throw new AuthScenarioNotFoundException();
        }

        $resultName = array_search($result::class, $this->scenarios[$scenarioName]::getResults(), true);

        if ($resultName === false) {
            throw new AuthScenarioResultNotFoundException();
        }

        return (string) $resultName;
    }

    /** @throws AuthScenarioNotFoundException|AuthScenarioStepNotFoundException */
    private function getStepName(string $scenarioName, ScenarioStepInterface $step): string
    {
        if (!isset($this->scenarios[$scenarioName])) {
            throw new AuthScenarioNotFoundException();
        }

        $stepName = array_search($step::class, $this->scenarios[$scenarioName]::getSteps(), true);

        if ($stepName === false) {
            throw new AuthScenarioStepNotFoundException();
        }

        return (string) $stepName;
    }
}
