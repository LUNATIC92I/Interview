<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

interface MiddlewareInterface
{
    /** Return a Response to stop the pipeline, or null to continue. */
    public function handle(Request $request): ?Response;
}
