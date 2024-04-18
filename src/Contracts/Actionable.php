<?php

namespace Infinity\Evolver\Contracts;

use Illuminate\Console\Command;

interface Actionable
{
    public function execute(Command $command): void;
}
