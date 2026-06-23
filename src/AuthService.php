<?php

declare(strict_types=1);

namespace EugeneErg\Auths;

use DateInterval;
use DateTimeImmutable;
use EugeneErg\Auths\Contracts\Adapters\ProviderCallbackInterface;
use EugeneErg\Auths\Contracts\Adapters\ProviderGetTokenInterface;
use EugeneErg\Auths\Contracts\Adapters\ProviderInterface;
use EugeneErg\Auths\Contracts\Adapters\ProviderPasswordValidatableInterface;
use EugeneErg\Auths\Contracts\Adapters\ProviderRequestHandlerInterface;
use EugeneErg\Auths\Contracts\Adapters\ProviderVerificationInterface;
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
use EugeneErg\Auths\DataTransferObjects\IssuedCodeOptions;
use EugeneErg\Auths\DataTransferObjects\OAuthCallbackOptions;
use EugeneErg\Auths\DataTransferObjects\OAuthStateOptions;
use EugeneErg\Auths\DataTransferObjects\OutgoingStep;
use EugeneErg\Auths\DataTransferObjects\Scenario;
use EugeneErg\Auths\DataTransferObjects\ScenarioResult;
use EugeneErg\Auths\DataTransferObjects\ScenarioStep;
use EugeneErg\Auths\DataTransferObjects\SentCodeOptions;
use EugeneErg\Auths\DataTransferObjects\VerificationMessage;
use EugeneErg\Auths\DataTransferObjects\VerificationResult;
use EugeneErg\Auths\Entities\AuthVerification;
use EugeneErg\Auths\Exceptions\AuthExceptionInterface;
use EugeneErg\Auths\Exceptions\AuthIdentityNotFoundException;
use EugeneErg\Auths\Exceptions\AuthProviderNotFoundException;
use EugeneErg\Auths\Exceptions\AuthProviderUnsuitableException;
use EugeneErg\Auths\Exceptions\AuthScenarioNotFoundException;
use EugeneErg\Auths\Exceptions\AuthScenarioResultNotFoundException;
use EugeneErg\Auths\Exceptions\AuthScenarioStepNotFoundException;
use EugeneErg\Auths\Exceptions\AuthTypeAlreadyExistsException;
use EugeneErg\Auths\Exceptions\AuthVerificationAlreadyUsedException;
use EugeneErg\Auths\Exceptions\AuthVerificationExpiredException;
use EugeneErg\Auths\Exceptions\AuthVerificationInvalidException;
use EugeneErg\Auths\ValueObjects\Action;
use EugeneErg\Auths\ValueObjects\AuthVerificationToken;
use EugeneErg\Auths\ValueObjects\ChannelAddress;
use EugeneErg\Auths\ValueObjects\IssuedCode;
use EugeneErg\Auths\ValueObjects\OAuthState;
use EugeneErg\Auths\ValueObjects\ProviderType;
use EugeneErg\Auths\ValueObjects\ScenarioId;
use EugeneErg\Auths\ValueObjects\ScenarioStepId;
use EugeneErg\Auths\ValueObjects\SentCode;
use EugeneErg\Auths\ValueObjects\UserId;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\RequestInterface;
use Random\RandomException;

/**
 * Основной сервис аутентификации.
 *
 * Поддерживаемые механизмы верификации:
 *
 * IssuedCode — сервис генерирует код и выдаёт его пользователю (deeplink, QR и т.д.).
 *   Пользователь отправляет этот код нам обратно (боту, на endpoint).
 *   Пример: Telegram deeplink /start TOKEN, кнопка в боте.
 *
 * SentCode — сервис отправляет код на контакт пользователя (SMS, email).
 *   Пользователь вводит код в форму.
 *   Пример: SMS OTP, email confirmation.
 *
 * OAuthState — сервис генерирует state и отдаёт его фронту.
 *   Фронт строит URL провайдера с этим state.
 *   Провайдер возвращает code+state в callback.
 *   Сервис обменивает code на ChannelAddress через провайдер.
 *   Пример: Google OAuth, GitHub OAuth, VK OAuth.
 */
readonly class AuthService
{
    /**
     * @param array<string, ProviderInterface> $providers
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
        private Action $authorizationAction = new Action('authorization'),
        private Action $removeAction = new Action('remove'),
        private Action $attachAction = new Action('attach'),
        private string|null $redirect = null,
        private bool $oneAccountPerProvider = false,
    ) {
        if ($defaultScenario !== null && !isset($scenarios[$defaultScenario])) {
            throw new AuthScenarioNotFoundException('Default scenario not found.');
        }
    }

    // -------------------------------------------------------------------------
    // IssuedCode: сервис выдаёт код пользователю
    // -------------------------------------------------------------------------

    /**
     * @throws AuthExceptionInterface
     */
    public function getIssuedTokenForAuthorization(IssuedCodeOptions $options): AuthVerificationToken
    {
        return $this->createIssuedVerification($options, $this->authorizationAction)->token;
    }

    /**
     * @throws AuthExceptionInterface
     */
    public function getIssuedTokenForAttach(IssuedCodeOptions $options, UserId $userId): AuthVerificationToken
    {
        $this->assertCanAttach($userId, $options->type);

        return $this->createIssuedVerification($options, $this->attachAction, $userId)->token;
    }

    /**
     * @throws AuthExceptionInterface
     */
    public function getIssuedTokenForRemove(IssuedCodeOptions $options, UserId $userId): AuthVerificationToken
    {
        $this->assertIdentityExists($userId, $options->type);

        return $this->createIssuedVerification($options, $this->removeAction, $userId)->token;
    }

    /**
     * @throws AuthExceptionInterface
     */
    public function getIssuedTokenForAction(IssuedCodeOptions $options, UserId $userId, Action $action): AuthVerificationToken
    {
        return $this->createIssuedVerification($options, $action, $userId)->token;
    }

    // -------------------------------------------------------------------------
    // SentCode: сервис отправляет код пользователю
    // -------------------------------------------------------------------------

    /**
     * @throws AuthExceptionInterface
     */
    public function sendVerificationCodeForAuthorization(SentCodeOptions $options): AuthVerificationToken
    {
        return $this->createSentVerification($options, $this->authorizationAction)->token;
    }

    /**
     * @throws AuthExceptionInterface
     */
    public function sendVerificationCodeForAttach(SentCodeOptions $options, UserId $userId): AuthVerificationToken
    {
        $this->assertCanAttach($userId, $options->type);

        return $this->createSentVerification($options, $this->attachAction, $userId)->token;
    }

    /**
     * @throws AuthExceptionInterface
     */
    public function sendVerificationCodeForRemove(SentCodeOptions $options, UserId $userId): AuthVerificationToken
    {
        $this->assertIdentityExists($userId, $options->type);

        return $this->createSentVerification($options, $this->removeAction, $userId)->token;
    }

    /**
     * @throws AuthExceptionInterface
     */
    public function sendVerificationCodeForAction(SentCodeOptions $options, UserId $userId, Action $action): AuthVerificationToken
    {
        return $this->createSentVerification($options, $action, $userId)->token;
    }

    // -------------------------------------------------------------------------
    // OAuthState: сервис генерирует state для OAuth флоу
    // -------------------------------------------------------------------------

    /**
     * Генерирует OAuth state и сохраняет верификацию.
     * Возвращает state фронту — фронт сам строит URL к провайдеру.
     *
     * @throws AuthExceptionInterface
     */
    public function generateOAuthStateForAuthorization(ProviderType $type, DateInterval $ttl): OAuthStateOptions
    {
        return $this->createOAuthStateVerification($type, $ttl, $this->authorizationAction);
    }

    /**
     * @throws AuthExceptionInterface
     */
    public function generateOAuthStateForAttach(ProviderType $type, DateInterval $ttl, UserId $userId): OAuthStateOptions
    {
        $this->assertCanAttach($userId, $type);

        return $this->createOAuthStateVerification($type, $ttl, $this->attachAction, $userId);
    }

    /**
     * @throws AuthExceptionInterface
     */
    public function generateOAuthStateForRemove(ProviderType $type, DateInterval $ttl, UserId $userId): OAuthStateOptions
    {
        $this->assertIdentityExists($userId, $type);

        return $this->createOAuthStateVerification($type, $ttl, $this->removeAction, $userId);
    }

    /**
     * @throws AuthExceptionInterface
     */
    public function generateOAuthStateForAction(ProviderType $type, DateInterval $ttl, UserId $userId, Action $action): OAuthStateOptions
    {
        return $this->createOAuthStateVerification($type, $ttl, $action, $userId);
    }

    // -------------------------------------------------------------------------
    // Верификация — завершение флоу
    // -------------------------------------------------------------------------

    /**
     * Верифицирует токен (IssuedCode или SentCode флоу).
     * Пользователь вернул нам код — проверяем, помечаем использованным, возвращаем результат.
     *
     * @throws AuthExceptionInterface
     */
    public function verifyToken(string $token): VerificationResult
    {
        $verification = $this->readAuthVerificationRepository->findByToken($token);

        if ($verification === null) {
            throw new AuthVerificationInvalidException();
        }

        $now = new DateTimeImmutable();
        $this->assertVerificationUsable($verification, $now);

        if ($verification->address === null) {
            // OAuth verifications (OAuthState) have no address until callback — use verifyOAuthCallback() instead
            throw new AuthVerificationInvalidException('Use verifyOAuthCallback() for OAuth verifications.');
        }

        $address = new ChannelAddress($verification->address);
        $identity = $this->readAuthIdentityRepository->find(
            new ProviderType($verification->type),
            $address,
        );

        $this->transaction->transaction(function () use ($verification, $now): void {
            $this->writeAuthVerificationRepository->consume($verification, $now);
        });

        return new VerificationResult(
            action: new Action($verification->action),
            address: $address,
            userId: $identity !== null ? new UserId($identity->userId) : ($verification->userId !== null ? new UserId($verification->userId) : null),
            identity: $identity,
        );
    }

    /**
     * Верифицирует OAuth callback (OAuthState флоу).
     * Принимает code+state от провайдера, обменивает code на ChannelAddress через провайдер.
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

        // Провайдер обменивает code на адрес пользователя у себя (token endpoint + userinfo)
        $address = $provider->exchangeCode($options->code, $options->state);
        $identity = $this->readAuthIdentityRepository->find($options->type, $address);

        $this->transaction->transaction(function () use ($verification, $now): void {
            $this->writeAuthVerificationRepository->consume($verification, $now);
        });

        return new VerificationResult(
            action: new Action($verification->action),
            address: $address,
            userId: $identity !== null ? new UserId($identity->userId) : ($verification->userId !== null ? new UserId($verification->userId) : null),
            identity: $identity,
        );
    }

    // -------------------------------------------------------------------------
    // Управление identity (результат успешной верификации)
    // -------------------------------------------------------------------------

    /**
     * Привязывает внешний аккаунт к пользователю.
     * Вызывается приложением после verifyToken/verifyOAuthCallback с action=registration|attach.
     *
     * @throws AuthExceptionInterface
     */
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

    /**
     * Отвязывает внешний аккаунт от пользователя.
     * Вызывается после verifyToken/verifyOAuthCallback с action=remove.
     *
     * @throws AuthExceptionInterface
     */
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

    /**
     * Планирует удаление аккаунта через указанный интервал (с оповещением через сценарий).
     *
     * @throws AuthExceptionInterface
     */
    public function scheduleIdentityDelete(ProviderType $type, ChannelAddress $address, DateInterval $delay): void
    {
        $identity = $this->readAuthIdentityRepository->find($type, $address);

        if ($identity === null) {
            throw new AuthIdentityNotFoundException();
        }

        $deleteAt = (new DateTimeImmutable())->add($delay);

        $this->transaction->transaction(function () use ($identity, $deleteAt): void {
            $this->writeAuthIdentityRepository->scheduleDelete($identity, $deleteAt);
        });
    }

    // -------------------------------------------------------------------------
    // Password: классическая аутентификация login+password
    // -------------------------------------------------------------------------

    /**
     * Проверяет логин и пароль через провайдер и возвращает результат верификации.
     * Единственный флоу без предварительного создания токена — ответ синхронный.
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
            action: $this->authorizationAction,
            address: $address,
            userId: $identity !== null ? new UserId($identity->userId) : null,
            identity: $identity,
        );
    }

    // -------------------------------------------------------------------------
    // Сценарии: диалог с пользователем
    // -------------------------------------------------------------------------

    /**
     * Инициирует сценарий со стороны сервера (бот первым пишет пользователю).
     *
     * @throws AuthExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function startScenario(
        string $scenarioName,
        ProviderType $type,
        ChannelAddress $address,
    ): ScenarioStepId|ScenarioId {
        if (!isset($this->scenarios[$scenarioName])) {
            throw new AuthScenarioNotFoundException();
        }

        /** @var ScenarioInterface $scenario */
        $scenario = $this->container->get($this->scenarios[$scenarioName]);
        $provider = $this->getProvider($type);

        if (!$provider instanceof ProviderRequestHandlerInterface) {
            throw new AuthProviderUnsuitableException();
        }

        // Запускаем сценарий без входящего сообщения — бот начинает первым
        $result = $scenario->run(null);
        $createdAt = new DateTimeImmutable();

        if (!$result instanceof OutgoingStep) {
            throw new AuthScenarioNotFoundException('Scenario must return OutgoingStep on first run.');
        }

        $newStepExternalId = $provider->sendMessage(
            to: $address,
            message: $result,
            replyTo: null,
        );

        return $this->transaction->transaction(function () use (
            $scenarioName,
            $type,
            $address,
            $createdAt,
            $result,
            $newStepExternalId,
        ): ScenarioStepId|ScenarioId {
            $identity = $this->readAuthIdentityRepository->find($type, $address);

            $scenario = $this->writeScenarioRepository->create(
                name: $scenarioName,
                type: $type,
                address: $address,
                createdAt: $createdAt,
                userId: $identity !== null ? new UserId($identity->userId) : null,
            );

            return $this->writeScenarioStepRepository->create(
                scenarioId: $scenario->id,
                externalId: $newStepExternalId,
                createdAt: $createdAt,
                processedAt: $createdAt,
                name: $this->getStepName($scenarioName, $result->nextStep),
                data: $result->nextStep->jsonSerialize(),
                replyToExternalId: null,
                replyToId: null,
            )->id;
        });
    }

    /**
     * Обрабатывает входящий webhook от провайдера (Telegram, VK и т.д.).
     * Находит нужный сценарий, прогоняет через него сообщение, сохраняет шаги.
     *
     * @throws AuthExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function handleWebhook(RequestInterface $request, ProviderType $type): ScenarioStepId|ScenarioId
    {
        $provider = $this->getProvider($type);

        if (!$provider instanceof ProviderRequestHandlerInterface) {
            throw new AuthProviderUnsuitableException();
        }

        $response = $provider->handleRequest($request);
        $targetStep = $response->replyTo === null
            ? null
            : $this->readScenarioStepRepository->findByMessage(
                type: $type,
                address: $response->address,
                externalId: $response->replyTo,
            );
        $lastStep = $this->readScenarioStepRepository->findLast(type: $type, address: $response->address);

        [$name, $current] = match (true) {
            $lastStep !== null => [$lastStep->scenarioName, $lastStep],
            $targetStep !== null => [$targetStep->scenarioName, $targetStep],
            $this->defaultScenario !== null => [$this->defaultScenario, null],
            default => throw new AuthScenarioNotFoundException(),
        };

        $createdAt = new DateTimeImmutable();

        /** @var ScenarioInterface $scenario */
        $scenario = $this->container->get($this->scenarios[$name]);
        $scenarios = [];

        do {
            $result = $scenario->run(new IncomingMessage(
                type: $type,
                createdAt: $response->processedAt,
                parts: $response->parts,
                attachments: $response->attachments,
                responseTo: $targetStep === null || $current?->stepId->isEqual($targetStep->stepId)
                    ? null
                    : $this->makeScenario($targetStep, $current, $scenarios),
            ));

            if ($result instanceof Scenario) {
                $id = spl_object_id($result);

                if (!isset($scenarios[$id])) {
                    throw new AuthScenarioNotFoundException();
                }

                $current = $scenarios[$id];

                /** @var ScenarioInterface $scenario */
                $scenario = $this->container->get($this->scenarios[$current->scenarioName]);
                $scenarios = [];
            } elseif ($result instanceof ScenarioInterface) {
                $current = null;
                $scenario = $result;
                $scenarios = [];
            }
        } while ($result instanceof Scenario || $result instanceof ScenarioInterface);

        $authIdentity = $this->readAuthIdentityRepository->find($type, $response->address);

        if ($result instanceof OutgoingStep) {
            $newStepExternalId = $provider->sendMessage(
                to: $response->address,
                message: $result,
                replyTo: $result->asNewMessage ? null : $response->id->value,
            );
        } else {
            $newStepExternalId = null;
        }

        return $this->transaction->transaction(function () use (
            $current,
            $result,
            $type,
            $response,
            $authIdentity,
            $createdAt,
            $targetStep,
            $newStepExternalId,
        ): ScenarioStepId|ScenarioId {
            $scenarioResult = $result === null
                ? null
                : new ScenarioResult(
                    name: $this->getScenarioResultName($this->defaultScenario, $result),
                    data: $result->jsonSerialize(),
                );
            $scenario = $current === null
                ? $this->writeScenarioRepository->create(
                    name: $this->defaultScenario,
                    type: $type,
                    address: $response->address,
                    createdAt: $createdAt,
                    userId: $authIdentity !== null ? new UserId($authIdentity->userId) : null,
                    result: $scenarioResult,
                )
                : $this->writeScenarioRepository->update(
                    scenario: $current->getScenario(),
                    result: $scenarioResult,
                    userId: $current->userId ?? ($authIdentity !== null ? new UserId($authIdentity->userId) : null),
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

    /**
     * @throws AuthProviderNotFoundException
     */
    private function getProvider(ProviderType $type): ProviderInterface
    {
        if (!isset($this->providers[$type->value])) {
            throw new AuthProviderNotFoundException();
        }

        return $this->providers[$type->value];
    }

    /**
     * @throws AuthTypeAlreadyExistsException
     */
    private function assertCanAttach(UserId $userId, ProviderType $type): void
    {
        if ($this->oneAccountPerProvider && $this->readAuthIdentityRepository->exists($userId, $type)) {
            throw new AuthTypeAlreadyExistsException();
        }
    }

    /**
     * @throws AuthTypeAlreadyExistsException
     */
    private function assertIdentityExists(UserId $userId, ProviderType $type): void
    {
        if (!$this->readAuthIdentityRepository->exists($userId, $type)) {
            throw new AuthTypeAlreadyExistsException();
        }
    }

    /**
     * @throws AuthVerificationAlreadyUsedException
     * @throws AuthVerificationExpiredException
     */
    private function assertVerificationUsable(AuthVerification $verification, DateTimeImmutable $now): void
    {
        if ($verification->isExpired($now)) {
            throw new AuthVerificationExpiredException();
        }

        if ($verification->isConsumed()) {
            throw new AuthVerificationAlreadyUsedException();
        }
    }

    /**
     * @throws AuthExceptionInterface
     */
    private function createIssuedVerification(
        IssuedCodeOptions $options,
        Action $action,
        UserId|null $userId = null,
    ): AuthVerification {
        $provider = $this->getProvider($options->type);

        if (!$provider instanceof ProviderGetTokenInterface) {
            throw new AuthProviderUnsuitableException();
        }

        return $this->createVerification(
            type: $options->type,
            code: $options->code,
            action: $action,
            ttl: $options->ttl,
            userId: $userId,
        );
    }

    /**
     * @throws AuthExceptionInterface
     */
    private function createSentVerification(
        SentCodeOptions $options,
        Action $action,
        UserId|null $userId = null,
    ): AuthVerification {
        $provider = $this->getProvider($options->type);

        if (!$provider instanceof ProviderVerificationInterface) {
            throw new AuthProviderUnsuitableException();
        }

        $redirect = $this->redirect !== null
            ? str_replace('{{CODE}}', $options->code->value, $this->redirect)
            : null;

        $provider->sendMessage(
            to: $options->code->channelAddress,
            message: new VerificationMessage(
                code: $options->code,
                ttl: $options->ttl,
                action: $action,
                redirect: $redirect,
            ),
        );

        return $this->createVerification(
            type: $options->type,
            code: $options->code,
            action: $action,
            ttl: $options->ttl,
            userId: $userId,
        );
    }

    /**
     * @throws AuthExceptionInterface
     * @throws RandomException
     */
    private function createOAuthStateVerification(
        ProviderType $type,
        DateInterval $ttl,
        Action $action,
        UserId|null $userId = null,
    ): OAuthStateOptions {
        $provider = $this->getProvider($type);

        if (!$provider instanceof ProviderCallbackInterface) {
            throw new AuthProviderUnsuitableException();
        }

        $state = new OAuthState(bin2hex(random_bytes(32)));
        $result = $this->createVerification(
            type: $type,
            code: $state,
            action: $action,
            ttl: $ttl,
            userId: $userId,
        );

        return new OAuthStateOptions($state, $result->token);
    }

    private function createVerification(
        ProviderType $type,
        IssuedCode|SentCode|OAuthState $code,
        Action $action,
        DateInterval $ttl,
        UserId|null $userId = null,
    ): AuthVerification {
        $now = new DateTimeImmutable();

        return $this->writeAuthVerificationRepository->create(
            type: $type,
            code: $code,
            createdAt: $now,
            expiresAt: $now->add($ttl),
            action: $action,
            userId: $userId,
        );
    }

    /**
     * @throws AuthScenarioNotFoundException
     * @throws AuthScenarioResultNotFoundException
     */
    private function makeResult(string $scenarioName, ScenarioResult|null $result): ScenarioResultInterface
    {
        if (!isset($this->scenarios[$scenarioName])) {
            throw new AuthScenarioNotFoundException();
        }

        $scenarioClass = $this->scenarios[$scenarioName];
        $results = $scenarioClass::getResults();

        if (!isset($results[$result->name])) {
            throw new AuthScenarioResultNotFoundException();
        }

        return ($results[$result->name])::fromArray($result->data);
    }

    /**
     * @throws AuthScenarioNotFoundException
     * @throws AuthScenarioStepNotFoundException
     */
    private function makeStep(string $scenarioName, string $stepName, array $data): ScenarioStepInterface
    {
        if (!isset($this->scenarios[$scenarioName])) {
            throw new AuthScenarioNotFoundException();
        }

        $scenarioClass = $this->scenarios[$scenarioName];
        $steps = $scenarioClass::getSteps();

        if (!isset($steps[$stepName])) {
            throw new AuthScenarioStepNotFoundException();
        }

        return ($steps[$stepName])::fromArray($data);
    }

    /**
     * @throws AuthScenarioNotFoundException
     * @throws AuthScenarioResultNotFoundException
     * @throws AuthScenarioStepNotFoundException
     */
    private function makeScenario(ScenarioStep $targetStep, ScenarioStep|null $current, array &$scenarios): Scenario
    {
        $result = new Scenario(
            name: $targetStep->scenarioName,
            current: $current?->scenarioId->isEqual($targetStep->scenarioId),
            step: $targetStep->stepName === null
                ? null
                : $this->makeStep($targetStep->scenarioName, $targetStep->stepName, $targetStep->data),
            scenario: $targetStep->replyToId === null
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

    /**
     * @throws AuthScenarioNotFoundException
     * @throws AuthScenarioResultNotFoundException
     */
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

    /**
     * @throws AuthScenarioNotFoundException
     * @throws AuthScenarioStepNotFoundException
     */
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
