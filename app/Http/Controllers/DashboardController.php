<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;


class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $service
    ) {}

    public function index()
    {
        return view('Dashboard.index', ['analytics' => $this->service->getAnalytics()]);
    }
}
