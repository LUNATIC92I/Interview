<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Response;

abstract class AdminController extends Controller
{
    protected function view(string $template, array $data = [], string $layout = 'layouts/admin'): Response
    {
        return parent::view($template, $data, $layout);
    }

    /** Read FR and EN translation fields named "{field}_{locale}" from a form. */
    protected static function translations(array $post, array $fields): array
    {
        $out = [];
        foreach (['fr', 'en'] as $loc) {
            foreach ($fields as $f) {
                $out[$loc][$f] = trim((string) ($post[$f . '_' . $loc] ?? ''));
            }
        }
        return $out;
    }
}
