<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PageController extends Controller
{
    public function getChangelog()
    {
        return view('pages.changelog');
    }

    public function getPrivacyPolicy()
    {
        return view('pages.privacy-policy');
    }
}
