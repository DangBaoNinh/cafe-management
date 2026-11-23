<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterRevenueRequest;
use App\Services\RevenueReport;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Date;

class DashboardController extends Controller
{
    public function __invoke(FilterRevenueRequest $request, RevenueReport $report): View
    {
        $filters = $request->validated();
        $today = Date::today(RevenueReport::TIMEZONE);
        $from = Date::parse($filters['from'] ?? $today->subDays(6)->toDateString(), RevenueReport::TIMEZONE);
        $to = Date::parse($filters['to'] ?? $today->toDateString(), RevenueReport::TIMEZONE);

        return view('admin.dashboard', [
            'from' => $from,
            'to' => $to,
            'maxDays' => FilterRevenueRequest::MAX_DAYS,
            ...$report->summarize($from, $to),
        ]);
    }
}
