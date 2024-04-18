<?php

namespace Infinity\Evolver\Contracts;

use Illuminate\Console\Command;

interface Actionable
{
    /**
     * The method is designed to perform a single step in either the installation or upgrade process of the
     * application. It accepts a Command object as a parameter, which allows for further
     * customization and flexibility in how the action is executed.
     *
     * @param Command $command
     * @return void
     */
    public function execute(Command $command): void;
}
