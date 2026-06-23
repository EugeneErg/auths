<?php

declare(strict_types=1);

namespace EuegeneErg\Auths;

use DateInterval;
use DateTimeImmutable;
use EuegeneErg\Auths\Contracts\Adapters\ProviderGetTokenInterface;
use EuegeneErg\Auths\Contracts\Adapters\ProviderInterface;
use EuegeneErg\Auths\Contracts\Adapters\ProviderRequestHandlerInterface;
use EuegeneErg\Auths\Contracts\Adapters\ProviderSendMessageInterface;
use EuegeneErg\Auths\Contracts\Repositories\Read\ReadAuthIdentityRepositoryInterface;
use EuegeneErg\Auths\Contracts\Repositories\Read\ReadScenarioRepositoryInterface;
use EuegeneErg\Auths\Contracts\Repositories\Read\ReadScenarioStepRepositoryInterface;
use EuegeneErg\Auths\Contracts\Repositories\Write\WriteAuthVerificationRepositoryInterface;
use EuegeneErg\Auths\Contracts\Repositories\Write\WriteScenarioRepositoryInterface;
use EuegeneErg\Auths\Contracts\Repositories\Write\WriteScenarioStepRepositoryInterface;
use EuegeneErg\Auths\Contracts\Scenario\ScenarioInterface;
use EuegeneErg\Auths\Contracts\Scenario\ScenarioResultInterface;
use EuegeneErg\Auths\Contracts\Scenario\ScenarioStepInterface;
use EuegeneErg\Auths\Contracts\TransactionInterface;
use EuegeneErg\Auths\DataTransferObjects\IssuedCodeOptions;
use EuegeneErg\Auths\DataTransferObjects\Scenario;
use EuegeneErg\Auths\DataTransferObjects\ScenarioRequest;
use EuegeneErg\Auths\DataTransferObjects\ScenarioResponse;
use EuegeneErg\Auths\DataTransferObjects\ScenarioResult;
use EuegeneErg\Auths\DataTransferObjects\ScenarioStep;
use EuegeneErg\Auths\DataTransferObjects\SentCodeOptions;
use EuegeneErg\Auths\Entities\AuthVerification;
use EuegeneErg\Auths\Exceptions\AuthScenarioNotFoundException;
use EuegeneErg\Auths\Exceptions\AuthExceptionInterface;
use EuegeneErg\Auths\Exceptions\AuthProviderNotFoundException;
use EuegeneErg\Auths\Exceptions\AuthProviderUnsuitableException;
use EuegeneErg\Auths\Exceptions\AuthScenarioResultNotFoundException;
use EuegeneErg\Auths\Exceptions\AuthScenarioStepNotFoundException;
use EuegeneErg\Auths\Exceptions\AuthTypeAlreadyExistsException;
use EuegeneErg\Auths\ValueObjects\Action;
use EuegeneErg\Auths\ValueObjects\CodeType;
use EuegeneErg\Auths\ValueObjects\InscribedCode;
use EuegeneErg\Auths\ValueObjects\IssuedCode;
use EuegeneErg\Auths\ValueObjects\ProviderType;
use EuegeneErg\Auths\ValueObjects\ScenarioId;
use EuegeneErg\Auths\ValueObjects\ScenarioStepId;
use EuegeneErg\Auths\ValueObjects\SentCode;
use EuegeneErg\Auths\ValueObjects\UserId;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\RequestInterface;

//регистрация
//авторизация
//подтверждение действия
//добавление другого аккаунта (с подтверждением через имеющийся)
//Удаление аккаунта
//сценарии
//отложенное удаление аккаунта к которому нет доступа (с оповещением аккаунта (всех аккаунтов))
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
        private ReadScenarioStepRepositoryInterface $readScenarioStepRepository,
        private WriteAuthVerificationRepositoryInterface $writeAuthVerificationRepository,
        private WriteScenarioRepositoryInterface $writeScenarioRepository,
        private WriteScenarioStepRepositoryInterface $writeScenarioStepRepository,
        private TransactionInterface $transaction,
        private ?string $defaultScenario = null,
        private Action $registrationActionName = new Action('registration'),
        private Action $authorizationActionName = new Action('authorization'),
        private Action $removeActionName = new Action('remove'),
        private Action $attachAction = new Action('attach'),
        private ?string $redirect = null,
        private bool $oneAccountPerProvider = false,
    ) {
        if ($defaultScenario !== null && !isset($scenarios[$defaultScenario])) {
            throw new AuthScenarioNotFoundException('Default scenario not found.');
        }
    }

    /**
     * @throws AuthExceptionInterface
     */
    public function getTokenByCodeForRegistration(IssuedCodeOptions $codeOptions): string
    {
        return $this->createIssuedAuthVerification(
            codeOptions: $codeOptions,
            action: $this->registrationActionName,
        )->token;
    }

    /**
     * @throws AuthExceptionInterface
     */
    public function getTokenByCodeForAuthorization(IssuedCodeOptions $codeOptions): string
    {
        return $this->createIssuedAuthVerification(
            codeOptions: $codeOptions,
            action: $this->authorizationActionName,
        )->token;
    }

    /**
     * @throws AuthExceptionInterface
     */
    public function getTokenByCodeForRemove(IssuedCodeOptions $codeOptions, UserId $userId): string
    {
        $exists = $this->readAuthIdentityRepository->exists($userId, $codeOptions->type);

        if (!$exists) {
            throw new AuthTypeAlreadyExistsException();
        }
        
        return $this->createIssuedAuthVerification(
            codeOptions: $codeOptions,
            action: $this->removeActionName,
            userId: $userId,
        )->token;
    }

    /**
     * @throws AuthExceptionInterface
     */
    public function getTokenByCodeForAttach(IssuedCodeOptions $codeOptions, UserId $userId): string
    {
        if ($this->oneAccountPerProvider) {
            $exists = $this->readAuthIdentityRepository->exists($userId, $codeOptions->type);

            if ($exists) {
                throw new AuthTypeAlreadyExistsException();
            }
        }

        return $this->createIssuedAuthVerification(
            codeOptions: $codeOptions,
            action: $this->attachAction,
            userId: $userId,
        )->token;
    }

    /**
     * @throws AuthExceptionInterface
     */
    public function getTokenByCodeForAction(IssuedCodeOptions $codeOptions, UserId $userId, Action $action): string
    {
        return $this->createIssuedAuthVerification(
            codeOptions: $codeOptions,
            action: $action,
            userId: $userId,
        )->token;
    }

    /**
     * @throws AuthExceptionInterface
     */
    public function sendCodeForRegistration(SentCodeOptions $codeOptions, ?string $redirect = null): string
    {
        return $this->createSentAuthVerification(
            codeOptions: $codeOptions,
            action: $this->registrationActionName,
        )->token;
    }

    public function sendCodeByCodeForAuthorization(SentCodeOptions $codeOptions): string
    {
        return $this->createSentAuthVerification(
            codeOptions: $codeOptions,
            action: $this->authorizationActionName,
        )->token;
    }

    /**
     * @throws AuthExceptionInterface
     */
    public function sendCodeByCodeForRemove(SentCodeOptions $codeOptions, UserId $userId): string
    {
        $exists = $this->readAuthIdentityRepository->exists($userId, $codeOptions->type);

        if (!$exists) {
            throw new AuthTypeAlreadyExistsException();
        }

        return $this->createSentAuthVerification(
            codeOptions: $codeOptions,
            action: $this->removeActionName,
            userId: $userId,
        )->token;
    }

    /**
     * @throws AuthExceptionInterface
     */
    public function sendCodeByCodeForAttach(SentCodeOptions $codeOptions, UserId $userId): string
    {
        if ($this->oneAccountPerProvider) {
            $exists = $this->readAuthIdentityRepository->exists($userId, $codeOptions->type);

            if ($exists) {
                throw new AuthTypeAlreadyExistsException();
            }
        }

        return $this->createSentAuthVerification(
            codeOptions: $codeOptions,
            action: $this->attachAction,
            userId: $userId,
        )->token;
    }

    public function sendCodeByCodeForAction(
        SentCodeOptions $codeOptions,
        UserId $userId,
        Action $action,
    ): string {
        return $this->createSentAuthVerification(
            codeOptions: $codeOptions,
            action: $action,
            userId: $userId,
        )->token;
    }

    /**
     * @throws AuthExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function handleScenarioWebhook(RequestInterface $request, ProviderType $type): ScenarioStepId|ScenarioId
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
                value: $response->value,
                externalId: $response->replyTo,
            );
        $lastStep = $this->readScenarioStepRepository->findLast(type: $type, value: $response->value);
        /**
         * @var string $name
         * @var ScenarioStep|null $current
         */
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
            $result = $scenario->run(new ScenarioResponse(
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

        $authIdentity = $this->readAuthIdentityRepository->find($type, $response->value);

        if ($result instanceof ScenarioRequest) {
            $newStepExternalId = $provider->sendMessage(
                step: $result->nextStep,
                value: $response->value,
                replyTo: $result->asNewMessage ? null : $response->id,
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
                    value: $response->value,
                    createdAt: $createdAt,
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

            if ($result instanceof ScenarioRequest && $newStepExternalId !== null) {
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

    public function startScenario(ScenarioInterface $scenario)
    {

    }

    public function processScenario()
    {

    }

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
     * @throws AuthExceptionInterface
     */
    private function createIssuedAuthVerification(
        IssuedCodeOptions $codeOptions,
        Action $action,
        ?UserId $userId = null,
    ): AuthVerification {
        $provider = $this->getProvider($codeOptions->type);
        
        if (!$provider instanceof ProviderGetTokenInterface) {
            throw new AuthProviderUnsuitableException();
        }
        
        return $this->createAuthVerification(
            type: $codeOptions->type,
            code: $codeOptions->code,
            action: $action,
            ttl: $codeOptions->ttl,
            userId: $userId,
        );
    }

    /**
     * @throws AuthExceptionInterface
     */
    private function createSentAuthVerification(
        SentCodeOptions $codeOptions,
        Action $action,
        ?UserId $userId = null,
        ?string $redirect = null,
    ): AuthVerification {
        $provider = $this->getProvider($codeOptions->type);

        if (!$provider instanceof ProviderSendMessageInterface) {
            throw new AuthProviderUnsuitableException();
        }

        $redirect ??= $this->redirect;

        if ($redirect !== null) {
            $redirect = str_replace('{{CODE}}', $codeOptions->code->value, $redirect);
        }

        $step = $provider->sendMessage($codeOptions->code->authValue, [
            'code' => $codeOptions->code,
            'ttl' => $codeOptions->ttl,
            'redirect' => $redirect,
        ], $action, null);
        $this->writeScenarioRepository->create(
            name: ,
            step: $step,
            type: $codeOptions->type,
            value: $codeOptions->code->authValue,
            userId: $userId,
        );

        return $this->createAuthVerification(
            type: $codeOptions->type,
            code: $codeOptions->code,
            action: $action,
            ttl: $codeOptions->ttl,
            userId: $userId,
        );
    }

    private function createAuthVerification(
        ProviderType $type,
        InscribedCode|IssuedCode|SentCode $code,
        Action $action,
        DateInterval $ttl,
        ?UserId $userId = null,
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
    private function makeResult(string $scenarioName, ?ScenarioResult $result): ScenarioResultInterface
    {
        if (!isset($this->scenarios[$scenarioName])) {
            throw new AuthScenarioNotFoundException();
        }

        $scenarioClass = $this->scenarios[$scenarioName];
        $results = $scenarioClass::getResults();

        if (!isset($results[$result->name])) {
            throw new AuthScenarioResultNotFoundException();
        }

        /** @var ScenarioResultInterface $resultClass */
        $resultClass = $results[$result->name];

        return $resultClass::fromArray($result->data);
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

        /** @var ScenarioStepInterface $stepClass */
        $stepClass = $steps[$stepName];

        return $stepClass::fromArray($data);
    }

    /**
     * @throws AuthScenarioNotFoundException
     * @throws AuthScenarioResultNotFoundException
     * @throws AuthScenarioStepNotFoundException
     */
    private function makeScenario(ScenarioStep $targetStep, ?ScenarioStep $current, array &$scenarios): Scenario
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

    private function getScenarioName(ScenarioResultInterface $scenarioResult): string
    {
        //array_search()

        //foreach ($this->scenarios)
    }

    private function getScenarioResultName(string $scenarioName, ScenarioResultInterface $result): string
    {
        if (!isset($this->scenarios[$scenarioName])) {
            throw new AuthScenarioNotFoundException();
        }

        $resultName = array_search($result::class, $this->scenarios[$scenarioName]::getResults());

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

        $stepName = array_search($step::class, $this->scenarios[$scenarioName]::getSteps());

        if ($stepName === false) {
            throw new AuthScenarioStepNotFoundException();
        }

        return (string) $stepName;
    }
}