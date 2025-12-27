<?php

namespace Infinity\Evolver\Deploy\Running;

enum TransactionMode: string
{
    case PerAction = 'per_action';

    case None = 'none';

    case All = 'all';
}
