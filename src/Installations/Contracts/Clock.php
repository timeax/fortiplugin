<?php

namespace Timeax\FortiPlugin\Installations\Contracts;

interface Clock
{
    public function now(): \DateTimeImmutable;
}
