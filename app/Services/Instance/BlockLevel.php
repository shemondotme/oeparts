<?php

namespace App\Services\Instance;

/**
 * How much of the installation is held still. The levels are ordered: the gate takes the highest of all
 * the sources that are blocking.
 */
enum BlockLevel: int
{
    /** Nothing is held: every scheduled command runs as it always did. */
    case None = 0;

    /** A staging copy: nothing that reaches a customer runs, internal housekeeping still does. */
    case CustomerOnly = 1;

    /** A freeze or an import: only the commands that keep the installation healthy run. */
    case Everything = 2;
}
