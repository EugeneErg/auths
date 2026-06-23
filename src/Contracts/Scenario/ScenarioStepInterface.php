<?php

declare(strict_types=1);

namespace EugeneErg\Auths\Contracts\Scenario;

use EugeneErg\Auths\DataTransferObjects\IncomingMessage;
use EugeneErg\Auths\DataTransferObjects\OutgoingStep;
use JsonSerializable;

interface ScenarioStepInterface extends JsonSerializable
{
    public static function fromArray(array $data): self;

    public function jsonSerialize(): array;

    public function run(IncomingMessage|null $message = null): OutgoingStep|ScenarioResultInterface;
}
