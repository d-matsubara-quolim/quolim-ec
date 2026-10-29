<?php

namespace App\Http\Controllers;

use App\Seasonal\CampaignRepository;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(CampaignRepository $campaigns): View
    {
        return view('home', ['campaigns' => $campaigns->open()]);
    }
}
