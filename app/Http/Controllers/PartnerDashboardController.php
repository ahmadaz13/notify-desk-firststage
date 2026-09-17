<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class PartnerDashboardController extends Controller
{
    public function index(Request $request): View
    {
        abort(410, 'Partner dashboard is retired. Partner records are retained only for referral attribution and history.');
    }
}
