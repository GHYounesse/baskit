<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AnalyticsController extends Controller
{
    /**
     * The date ranges the page offers, in days.
     */
    private const RANGES = [7, 30, 90];

    private const DEFAULT_RANGE = 30;

    public function index(Request $request, AnalyticsService $analytics)
    {
        $days = (int) $request->query('range', self::DEFAULT_RANGE);

        if (! in_array($days, self::RANGES, true)) {
            $days = self::DEFAULT_RANGE;
        }

        return Inertia::render('Admin/Analytics/Index', [
            'range' => $days,
            'ranges' => self::RANGES,
            ...$analytics->report($days),
        ]);
    }
}
