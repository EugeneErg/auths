<?php

declare(strict_types = 1);

namespace EuegeneErg\Auths\Contracts\Scenario;

use JsonSerializable;

interface MessagePartInterface extends JsonSerializable
{
    public function jsonSerialize(): array;
    public static function fromArray(array $data): self;
}