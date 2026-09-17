<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class LocaleController extends Controller
{
    public function switch(string $locale): RedirectResponse
    {
        if (in_array($locale, ['ar', 'en'], true)) {
            session(['locale' => $locale]);
        }

        return back();
    }
}
