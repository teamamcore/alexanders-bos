<?php

declare(strict_types=1);

namespace App\Platform\Portals;

use RuntimeException;

/** The portal has no usable credentials / provider access yet (SOW §3.14: approval is the provider's). */
final class PortalNotConnected extends RuntimeException {}
