<?php

declare(strict_types=1);

namespace EugeneErg\Auths\Contracts\Repositories\Read;

use EugeneErg\Auths\DataTransferObjects\ScenarioStep;
use EugeneErg\Auths\ValueObjects\ChannelAddress;
use EugeneErg\Auths\ValueObjects\ProviderType;
use EugeneErg\Auths\ValueObjects\ScenarioStepExternalId;
use EugeneErg\Auths\ValueObjects\ScenarioStepId;

interface ReadScenarioStepRepositoryInterface
{
    public function findByMessage(
        ProviderType $type,
        ChannelAddress $address,
        ScenarioStepExternalId $externalId,
    ): ScenarioStep|null;

    /**
     * Последний шаг любого активного сценария для данного адреса.
     */
    public function findLast(ProviderType $type, ChannelAddress $address): ScenarioStep|null;

    /**
     * Последний шаг конкретного сценария — для параллельных диалогов.
     */
    public function findLastByScenarioName(
        ProviderType $type,
        ChannelAddress $address,
        string $scenarioName,
    ): ScenarioStep|null;

    public function findById(ScenarioStepId $id): ScenarioStep|null;
}
