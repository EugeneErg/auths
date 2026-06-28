<?php

declare(strict_types=1);

namespace EugeneErg\Auths;

use DateTimeImmutable;
use EugeneErg\Auths\Contracts\Adapters\ProviderCallbackInterface;
use EugeneErg\Auths\Contracts\Adapters\ProviderInterface;
use EugeneErg\Auths\Contracts\Adapters\ProviderPasswordValidatableInterface;
use EugeneErg\Auths\Contracts\Repositories\Read\ReadAuthIdentityRepositoryInterface;
use EugeneErg\Auths\Contracts\Repositories\Read\ReadAuthVerificationRepositoryInterface;
use EugeneErg\Auths\Contracts\Repositories\Write\WriteAuthIdentityRepositoryInterface;
use EugeneErg\Auths\Contracts\Repositories\Write\WriteAuthVerificationRepositoryInterface;
use EugeneErg\Auths\Contracts\Scenario\ScenarioFactoryInterface;
use EugeneErg\Auths\Contracts\Scenario\ScenarioResultInterface;
use EugeneErg\Auths\Contracts\TransactionInterface;
use EugeneErg\Auths\DataTransferObjects\IssuedCodeResult;
use EugeneErg\Auths\DataTransferObjects\OAuthCallbackOptions;
use EugeneErg\Auths\DataTransferObjects\OAuthStateOptions;
use EugeneErg\Auths\DataTransferObjects\VerificationResult;
use EugeneErg\Auths\Entities\AuthVerification;
use EugeneErg\Auths\Exceptions\AuthExceptionInterface;
use EugeneErg\Auths\Exceptions\AuthIdentityNotFoundException;
use EugeneErg\Auths\Exceptions\AuthProviderNotFoundException;
use EugeneErg\Auths\Exceptions\AuthProviderUnsuitableException;
use EugeneErg\Auths\Exceptions\AuthScenarioNotFoundException;
use EugeneErg\Auths\Exceptions\AuthTypeAlreadyExistsException;
use EugeneErg\Auths\Exceptions\AuthVerificationAlreadyUsedException;
use EugeneErg\Auths\Exceptions\AuthVerificationExpiredException;
use EugeneErg\Auths\Exceptions\AuthVerificationInvalidException;
use EugeneErg\Auths\ScenarioStarters\AnonymousStarter;
use EugeneErg\Auths\ScenarioStarters\AuthContext;
use EugeneErg\Auths\ScenarioStarters\ScenarioContext;
use EugeneErg\Auths\ValueObjects\Action;
use EugeneErg\Auths\ValueObjects\AuthVerificationToken;
use EugeneErg\Auths\ValueObjects\ChannelAddress;
use EugeneErg\Auths\ValueObjects\CodeType;
use EugeneErg\Auths\ValueObjects\IssuedCode;
use EugeneErg\Auths\ValueObjects\OAuthState;
use EugeneErg\Auths\ValueObjects\ProviderType;
use EugeneErg\Auths\ValueObjects\ScenarioStepId;
use EugeneErg\Auths\ValueObjects\SentCode;
use EugeneErg\Auths\ValueObjects\UserId;
use Psr\Container\ContainerExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Random\RandomException;

/**
 * Сервис аутентификации — управляет верификацией, identity и запускает auth-сценарии.
 *
 * Сценарии создаются через ScenarioFactoryInterface — программист может переопределить любой.
 * ScenarioService используется внутри для выполнения диалога.
 *
 * Публичный API:
 *   authorize($context)                       — запуск авторизации
 *   attach($userId, $context)                 — привязка аккаунта
 *   detach($userId, $context)                 — отвязка аккаунта
 *   confirmAction($userId, $action, $context) — подтверждение действия
 *   verifyToken($token)                       — проверка IssuedCode токена
 *   confirmCode($type, $address, $code)       — проверка введённого кода
 *   verifyOAuthCallback($options)             — OAuth callback
 *   verifyPassword($type, $login, $password)  — login+password
 *   handleWebhook($request, $type)            — входящий webhook с проверкой верификации
 *   attachIdentity / detachIdentity / ...     — прямое управление identity
 */
readonly class AuthService
{
    /**
     * @param array<string, ProviderInterface> $providers
     */
    public function __construct(
        private array $providers,
        private ScenarioService $scenarioService,
        private ScenarioFactoryInterface $scenarioFactory,
        private ReadAuthIdentityRepositoryInterface $readAuthIdentityRepository,
        private ReadAuthVerificationRepositoryInterface $readAuthVerificationRepository,
        private WriteAuthVerificationRepositoryInterface $writeAuthVerificationRepository,
        private WriteAuthIdentityRepositoryInterface $writeAuthIdentityRepository,
        private TransactionInterface $transaction,
        private Action $authAction = new Action('auth'),
        private Action $attachAction = new Action('attach'),
        private Action $detachAction = new Action('detach'),
        private bool $oneAccountPerProvider = false,
    ) {
    }

    // -------------------------------------------------------------------------
    // Auth-флоу: запуск сценариев с верификацией
    // -------------------------------------------------------------------------

    /**
     * Запускает сценарий авторизации для анонимного пользователя.
     * Создаёт верификационную сессию и запускает сценарий через ScenarioService.
     *
     * @throws AuthExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws RandomException
     */
    public function authorize(
        ProviderType $type,
        AuthContext $context,
    ): AuthVerificationToken|IssuedCodeResult|OAuthStateOptions|ScenarioStepId|ScenarioResultInterface {
        $scenario = $this->scenarioFactory->createAuth();

        return $this->startAuthScenario($scenario, $type, $context, $this->authAction);
    }

    /**
     * Запускает сценарий привязки внешнего аккаунта к пользователю.
     *
     * @throws AuthExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws RandomException
     */
    public function attach(
        UserId $userId,
        ProviderType $type,
        AuthContext $context,
    ): AuthVerificationToken|IssuedCodeResult|OAuthStateOptions|ScenarioStepId|ScenarioResultInterface {
        $this->assertCanAttach($userId, $type);
        $scenario = $this->scenarioFactory->createAttach($userId);

        return $this->startAuthScenario($scenario, $type, $context, $this->attachAction, $userId);
    }

    /**
     * Запускает сценарий отвязки внешнего аккаунта.
     *
     * @throws AuthExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws RandomException
     */
    public function detach(
        UserId $userId,
        ProviderType $type,
        AuthContext $context,
    ): AuthVerificationToken|IssuedCodeResult|OAuthStateOptions|ScenarioStepId|ScenarioResultInterface {
        $scenario = $this->scenarioFactory->createDetach($userId);

        return $this->startAuthScenario($scenario, $type, $context, $this->detachAction, $userId);
    }

    /**
     * Запускает сценарий подтверждения произвольного действия.
     *
     * @throws AuthExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws RandomException
     */
    public function confirmAction(
        UserId $userId,
        Action $action,
        ProviderType $type,
        AuthContext $context,
    ): AuthVerificationToken|IssuedCodeResult|OAuthStateOptions|ScenarioStepId|ScenarioResultInterface {
        $scenario = $this->scenarioFactory->createConfirmAction($userId, $action);

        return $this->startAuthScenario($scenario, $type, $context, $action, $userId);
    }

    // -------------------------------------------------------------------------
    // Верификация
    // -------------------------------------------------------------------------

    /**
     * Верифицирует токен (IssuedCode флоу).
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
     * Верифицирует код введённый пользователем на сайте (SentCode через форму).
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
     * Верифицирует OAuth callback.
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
     * Синхронная проверка логина и пароля.
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
    // Webhook с проверкой верификации
    // -------------------------------------------------------------------------

    /**
     * Обрабатывает входящий webhook.
     * Если сообщение содержит код верификации — сверяет с активной сессией
     * и передаёт confirmedAction в ScenarioService.
     *
     * @throws AuthExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function handleWebhook(
        RequestInterface $request,
        ProviderType $type,
        string|null $scenarioName = null,
    ): ScenarioStepId|\EugeneErg\Auths\ValueObjects\ScenarioId|ScenarioResultInterface {
        $provider = $this->getProvider($type);
        $confirmedAction = null;

        // Проверяем verificationCode из Response до передачи в ScenarioService
        // Получаем Response из провайдера через ScenarioService не напрямую,
        // поэтому проверку делаем в handleWebhook ScenarioService с передачей confirmedAction
        return $this->scenarioService->handleWebhook($request, $type, $scenarioName, $confirmedAction);
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

    /** @throws AuthTypeAlreadyExistsException */
    private function assertCanAttach(UserId $userId, ProviderType $type): void
    {
        if ($this->oneAccountPerProvider && $this->readAuthIdentityRepository->exists($userId, $type)) {
            throw new AuthTypeAlreadyExistsException();
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

    /**
     * Общая логика запуска auth-сценария с верификацией.
     *
     * @throws AuthExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws RandomException
     */
    private function startAuthScenario(
        \EugeneErg\Auths\Contracts\Scenario\ScenarioInterface $scenario,
        ProviderType $type,
        AuthContext $context,
        Action $action,
        UserId|null $userId = null,
    ): AuthVerificationToken|IssuedCodeResult|OAuthStateOptions|ScenarioStepId|ScenarioResultInterface {
        $now = new DateTimeImmutable();

        // Резолвим адрес
        $address = $context->address;
        if ($address === null && $userId !== null) {
            $address = $this->readAuthIdentityRepository->findPrimary($userId, $type)?->address;
        }

        // OAuth — генерируем state, без run()
        if ($context->codeType === CodeType::OAuthState) {
            $provider = $this->getProvider($type);

            if (!$provider instanceof ProviderCallbackInterface) {
                throw new AuthProviderUnsuitableException();
            }

            $state = new OAuthState(bin2hex(random_bytes(32)));
            $verification = $this->writeAuthVerificationRepository->create(
                type: $type,
                code: $state,
                createdAt: $now,
                expiresAt: $now->add($context->ttl),
                action: $action,
                userId: $userId,
            );

            return new OAuthStateOptions($state, $verification->token);
        }

        // IssuedCode — сохраняем верификацию, без run() и отправки
        if ($context->codeType === CodeType::Issued) {
            $verification = $this->writeAuthVerificationRepository->create(
                type: $type,
                code: new IssuedCode($context->code),
                createdAt: $now,
                expiresAt: $now->add($context->ttl),
                action: $action,
                userId: $userId,
            );

            return new IssuedCodeResult(token: $verification->token, code: $context->code);
        }

        // SentCode и send — запускаем через ScenarioService
        $scenarioContext = new ScenarioContext(
            action: $action,
            userId: $userId,
            address: $address,
        );

        $result = $this->scenarioService->startScenario($scenario, $type, $scenarioContext);

        // Сохраняем SentCode верификацию после запуска сценария
        if ($context->codeType === CodeType::Sent) {
            if ($address === null) {
                throw new AuthVerificationInvalidException('Address required for SentCode.');
            }

            $token = $this->writeAuthVerificationRepository->create(
                type: $type,
                code: new SentCode($context->code, $address),
                createdAt: $now,
                expiresAt: $now->add($context->ttl),
                action: $action,
                userId: $userId,
            )->token;

            return $token;
        }

        return $result;
    }
}
