<?php

declare(strict_types=1);

namespace EugeneErg\Auths\Contracts\Scenario;

use JsonSerializable;

interface ScenarioResultInterface extends JsonSerializable
{
    public static function fromArray(array $data): self;

    public function jsonSerialize(): array;
}
