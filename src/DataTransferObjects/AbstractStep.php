<?php

declare(strict_types = 1);

namespace EuegeneErg\Auths\DataTransferObjects;

use JsonSerializable;

/**
 * @method AbstractStep run()
 */
readonly abstract class AbstractStep implements JsonSerializable
{
    public function __construct(
        public string $step,
    ) {
    }
    abstract public function jsonSerialize(): array;
    abstract public static function fromArray(array $data): self;
}