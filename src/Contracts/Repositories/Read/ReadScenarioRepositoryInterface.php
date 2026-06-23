<?php

declare(strict_types=1);

namespace EuegeneErg\Auths\Contracts\Repositories\Read;

use EuegeneErg\Auths\Entities\Scenario;
use EuegeneErg\Auths\ValueObjects\ScenarioId;

interface ReadScenarioRepositoryInterface
{
    public function find(ScenarioId $id): ?Scenario;
}