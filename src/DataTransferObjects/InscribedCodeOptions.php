<?php

declare(strict_types=1);

namespace EuegeneErg\Auths\DataTransferObjects;

use DateInterval;
use EuegeneErg\Auths\ValueObjects\InscribedCode;
use EuegeneErg\Auths\ValueObjects\IssuedCode;
use EuegeneErg\Auths\ValueObjects\ProviderType;
use EuegeneErg\Auths\ValueObjects\SentCode;

final readonly class InscribedCodeOptions
{
    public function __construct(
        public ProviderType $type,
        public InscribedCode $code,
        public DateInterval $ttl,
    ) {
    }
}