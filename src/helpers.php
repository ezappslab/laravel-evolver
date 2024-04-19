<?php

use function Illuminate\Filesystem\join_paths;

if (function_exists('evolver_path')) {
    function evolver_path($path): string
    {
        return join_paths(__DIR__, '..', $path);
    }
}
