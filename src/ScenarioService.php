<?php

declare(strict_types=1);

namespace EugeneErg\Auths;

use DateTimeImmutable;
use EugeneErg\Auths\Contracts\Adapters\ProviderInterface;
use EugeneErg\Auths\Contracts\Adapters\ProviderMessagingInterface;
use EugeneErg\Auths\Contracts\Adapters\ProviderRequestHandlerInterface;
use EugeneErg\Auths\Contracts\Repositories\Read\ReadAuthIdentityRepositoryInterface;
use EugeneErg\Auths\Contracts\Repositories\Read\ReadAuthVerificationRepositoryInterface;
use EugeneErg\Auths\Contracts\Repositories\Read\ReadScenarioStepRepositoryInterface;
use EugeneErg\Auths\Contracts\Repositories\Write\WriteAuthVerificationRepositoryInterface;
use EugeneErg\Auths\Contracts\Repositories\Write\WriteScenarioRepositoryInterface;
use EugeneErg\Auths\Contracts\Repositories\Write\WriteScenarioStepRepositoryInterface;
use EugeneErg\Auths\Contracts\Scenario\ScenarioInterface;
use EugeneErg\Auths\Contracts\Scenario\ScenarioResultInterface;
use EugeneErg\Auths\Contracts\Scenario\ScenarioStepInterface;
use EugeneErg\Auths\Contracts\TransactionInterface;
use EugeneErg\Auths\DataTransferObjects\IncomingMessage;
use EugeneErg\Auths\DataTransferObjects\OutgoingStep;
use EugeneErg\Auths\DataTransferObjects\Scenario;
use EugeneErg\Auths\DataTransferObjects\ScenarioResult;
use EugeneErg\Auths\DataTransferObjects\ScenarioStep;
use EugeneErg\Auths\Exceptions\AuthExceptionInterface;
use EugeneErg\Auths\Exceptions\AuthProviderNotFoundException;
use EugeneErg\Auths\Exceptions\AuthProviderUnsuitableException;
use EugeneErg\Auths\Exceptions\AuthScenarioNotFoundException;
use EugeneErg\Auths\Exceptions\AuthScenarioResultNotFoundException;
use EugeneErg\Auths\Exceptions\AuthScenarioStepNotFoundException;
use EugeneErg\Auths\ScenarioStarters\ScenarioContext;
use EugeneErg\Auths\ScenarioStarters\ScenarioStarter;
use EugeneErg\Auths\ValueObjects\Action;
use EugeneErg\Auths\ValueObjects\ChannelAddress;
use EugeneErg\Auths\ValueObjects\ProviderType;
use EugeneErg\Auths\ValueObjects\ScenarioId;
use EugeneErg\Auths\ValueObjects\ScenarioStepId;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Сервис сценариев — запускает диалоги, обрабатывает webhook, хранит шаги.
 * Не знает о кодах верификации, identity и action — это ответственность AuthService.
 */
readonly class ScenarioService
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
        private WriteScenarioRepositoryInterface $writeScenarioRepository,
        private WriteScenarioStepRepositoryInterface $writeScenarioStepRepository,
        private TransactionInterface $transaction,
        private string|null $defaultScenario = null,
    ) {
        if ($defaultScenario !== null && !isset($scenarios[$defaultScenario])) {
            throw new AuthScenarioNotFoundException('Default scenario not found.');
        }
    }

    // -------------------------------------------------------------------------
    // Запуск сценария
    // -------------------------------------------------------------------------

    /**
     * Возвращает стартер для формирования контекста запуска.
     */
    public function scenario(): ScenarioStarter
    {
        return new ScenarioStarter();
    }

    /**
     * Запускает сценарий с контекстом от стартера.
     * Для send() возвращает ScenarioStepId или ScenarioResultInterface если сценарий сразу завершился.
     *
     * @throws AuthExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function startScenario(
        ScenarioInterface $scenario,
        ProviderType $type,
        ScenarioContext $context,
    ): ScenarioStepId|ScenarioResultInterface {
        $scenarioName = $this->getScenarioName($scenario);
        $provider = $this->getProvider($type);

        if (!$provider instanceof ProviderMessagingInterface) {
            throw new AuthProviderUnsuitableException();
        }

        $now = new DateTimeImmutable();

        $address = $context->address;
        if ($address === null && $context->userId !== null) {
            $address = $this->readAuthIdentityRepository->findPrimary($context->userId, $type)?->address;
        }

        if ($address === null) {
            throw new AuthProviderUnsuitableException('Address required to start scenario.');
        }

        $result = $scenario->run(new IncomingMessage(
            type: $type,
            address: $address,
            createdAt: $now,
        ));

        if ($result instanceof ScenarioResultInterface) {
            $this->transaction->transaction(function () use ($scenarioName, $type, $address, $now, $context, $result): void {
                $this->writeScenarioRepository->create(
                    name: $scenarioName,
                    type: $type,
                    address: $address,
                    createdAt: $now,
                    action: $context->action,
                    userId: $context->userId,
                    result: new ScenarioResult(
                        name: $this->getScenarioResultName($scenarioName, $result),
                        data: $result->jsonSerialize(),
                    ),
                );
            });

            return $result;
        }

        if (!$result instanceof OutgoingStep) {
            throw new AuthScenarioNotFoundException('Scenario must return OutgoingStep or ScenarioResultInterface on first run.');
        }

        $externalId = $provider->sendStep(
            to: $address,
            step: $result->nextStep,
            action: $context->action,
            replyTo: null,
        );

        return $this->transaction->transaction(function () use (
            $scenarioName, $type, $address, $now, $context, $result, $externalId,
        ): ScenarioStepId {
            $savedScenario = $this->writeScenarioRepository->create(
                name: $scenarioName,
                type: $type,
                address: $address,
                createdAt: $now,
                action: $context->action,
                userId: $context->userId,
            );

            return $this->writeScenarioStepRepository->create(
                scenarioId: $savedScenario->id,
                externalId: $externalId,
                createdAt: $now,
                processedAt: $now,
                name: $this->getStepName($scenarioName, $result->nextStep),
                data: $result->nextStep->jsonSerialize(),
                replyToExternalId: null,
                replyToId: null,
            )->id;
        });
    }

    // -------------------------------------------------------------------------
    // Webhook
    // -------------------------------------------------------------------------

    /**
     * Обрабатывает входящий webhook от провайдера.
     * Если $confirmedAction передан — кладёт его в IncomingMessage (сверка верификации сделана в AuthService).
     *
     * @throws AuthExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function handleWebhook(
        RequestInterface $request,
        ProviderType $type,
        string|null $scenarioName = null,
        Action|null $confirmedAction = null,
    ): ScenarioStepId|ScenarioId|ScenarioResultInterface {
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

        $namedStep = $scenarioName !== null
            ? $this->readScenarioStepRepository->findLastByScenarioName($type, $response->address, $scenarioName)
            : null;

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
            $current, $result, $name, $type, $response, $authIdentity,
            $createdAt, $targetStep, $newStepExternalId,
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
                data: ['parts' => $response->parts, 'attachments' => $response->attachments],
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
