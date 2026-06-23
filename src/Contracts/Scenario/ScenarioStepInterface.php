<?php

declare(strict_types=1);

namespace EuegeneErg\Auths\Contracts\Scenario;

use EuegeneErg\Auths\DataTransferObjects\ScenarioRequest;
use EuegeneErg\Auths\DataTransferObjects\ScenarioResponse;
use JsonSerializable;

interface ScenarioStepInterface extends JsonSerializable
{
    public function jsonSerialize(): array;
    public static function fromArray(array $data): self;
    public function run(?ScenarioResponse $request = null): ScenarioRequest|ScenarioResultInterface;
}