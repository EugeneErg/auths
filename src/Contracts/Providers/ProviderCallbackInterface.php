<?php

declare(strict_types=1);

namespace EuegeneErg\Auths\Contracts\Adapters;

interface ProviderCallbackInterface extends ProviderInterface
{
    public function get();
}