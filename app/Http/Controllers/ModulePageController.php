<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class ModulePageController extends Controller
{
    public function __invoke(string $module): Response
    {
        return Inertia::render('Modules/Placeholder', [
            'module' => ucfirst($module),
            'moduleKey' => $module,
            'status' => 'En preparación',
        ]);
    }
}
