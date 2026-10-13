<?php

namespace App\Services\Instance;

/** Something was refused because this installation is a copy that must stay harmless. */
class InstanceModeBlockedException extends \RuntimeException
{
    public function __construct(
        public readonly Capability $capability,
        public readonly InstanceMode $mode,
        string $detail = '',
    ) {
        parent::__construct(trim(sprintf(
            'This %s copy of the site is not allowed to do that (%s).%s',
            $mode->value, $capability->value, $detail === '' ? '' : ' '.$detail
        )));
    }
}
