<?php

declare(strict_types=1);

namespace EugeneErg\Auths\DataTransferObjects;

use DateInterval;
use EugeneErg\Auths\ValueObjects\IssuedCode;
use EugeneErg\Auths\ValueObjects\ProviderType;

final readonly class IssuedCodeOptions
{
    public function __construct(
        public ProviderType $type,
        public IssuedCode $code,
        public DateInterval $ttl,
    ) {
    }
}
