<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\LoginLog;
use App\Models\CronLog;
use App\Models\EmailLog;
use Illuminate\Http\Request;

class LogController extends Controller
{
    /**
     * Display activity logs.
     */
    public function activityLogs(Request $request)
    {
        $logs = ActivityLog::with('admin')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('admin.logs.activity', compact('logs'));
    }

    /**
     * Display login logs.
     */
    public function loginLogs(Request $request)
    {
        $logs = LoginLog::orderBy('created_at', 'desc')
            ->paginate(20);

        return view('admin.logs.login', compact('logs'));
    }

    /**
     * Display cron logs.
     */
    public function cronLogs(Request $request)
    {
        $logs = CronLog::orderBy('ran_at', 'desc')
            ->paginate(20);

        return view('admin.logs.cron', compact('logs'));
    }

    /**
     * Display email logs.
     */
    public function emailLogs(Request $request)
    {
        $logs = EmailLog::orderBy('created_at', 'desc')
            ->paginate(20);

        return view('admin.logs.email', compact('logs'));
    }
}
