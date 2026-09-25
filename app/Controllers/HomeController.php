<?php

declare(strict_types=1);

namespace App\Controllers;

use SedoPHP\Http\Response;

final class HomeController
{
    public function index(): Response
    {
        return view('home', [
            'name' => config('app.name', 'SedoPHP'),
            'version' => trim((string) file_get_contents(app()->path('VERSION'))),
        ]);
    }
}
