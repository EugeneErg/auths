<?php

declare(strict_types=1);

namespace EuegeneErg\Auths\DataTransferObjects;

use DateInterval;
use EuegeneErg\Auths\ValueObjects\ProviderType;
use EuegeneErg\Auths\ValueObjects\SentCode;

final readonly class SentCodeOptions
{
    public function __construct(
        public ProviderType $type,
        public SentCode $code,
        public DateInterval $ttl,
    ) {
    }
}