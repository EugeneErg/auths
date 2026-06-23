<?php

declare(strict_types=1);

namespace EuegeneErg\Auths\ValueObjects;

enum CodeType: string
{
    case Issued = 'issued';
    case Sent = 'sent';
    case Inscribed = 'inscribed';
}