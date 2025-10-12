<?php

namespace Timeax\FortiPlugin\Installations\Contracts;

interface ActorResolver
{
    /**
     * Returns an identifier for the current actor (e.g., user ID or 'system').
     */
    public function resolve(): string;
}
