<?php

namespace App\Security;

/** Why a path inside a package was refused. The value is stable: reports and tests count by it. */
enum PathRejection: string
{
    case Empty = 'empty';
    case TooLong = 'too_long';
    case ControlCharacter = 'control_character';
    case Backslash = 'backslash';
    case Absolute = 'absolute';
    case Traversal = 'traversal';
    case NotCanonical = 'not_canonical';
    case NotNfc = 'not_nfc';
    case TrailingDotOrSpace = 'trailing_dot_or_space';
    case StreamOrColon = 'stream_or_colon';
    case ReservedName = 'reserved_name';
    case ExecutableExtension = 'executable_extension';
    case ForbiddenName = 'forbidden_name';
    case ForbiddenDirectory = 'forbidden_directory';
    case OutsideAllowedRoots = 'outside_allowed_roots';
}
