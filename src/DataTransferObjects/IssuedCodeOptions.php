<?php

declare(strict_types=1);

namespace EuegeneErg\Auths\DataTransferObjects;

use DateInterval;
use EuegeneErg\Auths\ValueObjects\InscribedCode;
use EuegeneErg\Auths\ValueObjects\IssuedCode;
use EuegeneErg\Auths\ValueObjects\ProviderType;
use EuegeneErg\Auths\ValueObjects\SentCode;

final readonly class IssuedCodeOptions
{
    public function __construct(
        public ProviderType $type,
        public IssuedCode $code,
        public DateInterval $ttl,
    ) {
    }
}