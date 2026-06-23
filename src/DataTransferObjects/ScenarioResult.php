<?php

declare(strict_types=1);

namespace EugeneErg\Auths\DataTransferObjects;

final readonly class ScenarioResult
{
    /**
     * @param mixed[] $data
     */
    public function __construct(public string $name, public array $data)
    {
    }
}
