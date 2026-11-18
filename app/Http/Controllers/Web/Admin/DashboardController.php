<?php

namespace App\Http\Controllers\Web\Admin;

use App\Domains\Store\Queries\GetAdminDashboardMetricsQuery;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(GetAdminDashboardMetricsQuery $metricsQuery): View
    {
        return view('admin.dashboard', [
            'metrics' => $metricsQuery->execute(),
        ]);
    }
}
