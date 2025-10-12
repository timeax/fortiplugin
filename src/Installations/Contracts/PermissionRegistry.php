<?php

namespace Timeax\FortiPlugin\Installations\Contracts;

interface PermissionRegistry
{
    public function registerDefinitions(array $definitions): void;
}
