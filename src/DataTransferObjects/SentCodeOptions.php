<?php

declare(strict_types=1);

namespace EugeneErg\Auths\DataTransferObjects;

use DateInterval;
use EugeneErg\Auths\ValueObjects\ProviderType;
use EugeneErg\Auths\ValueObjects\SentCode;

final readonly class SentCodeOptions
{
    public function __construct(
        public ProviderType $type,
        public SentCode $code,
        public DateInterval $ttl,
    ) {
    }
}
