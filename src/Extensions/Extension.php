<?php

declare(strict_types=1);

namespace Aikon\PostUpdateScheduler\Extensions;

use Aikon\PostUpdateScheduler\Options;

abstract class Extension
{
    /**
     * Constructor
     */
    abstract public function __construct( Options $options );

    abstract static function is_active(): bool;
}