<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;

/**
 * Runs the Laravel scheduler from an external pinger (cron-job.org etc.) on hosts that give no
 * real cron (FlyNode). Disabled unless CRON_TOKEN is set; the token is compared in constant time
 * and a wrong/missing token is a plain 404 so the endpoint is not discoverable.
 */
class CronController extends Controller
{
    public function __invoke(string $token): Response
    {
        $expected = (string) config('app.cron_token');

        abort_if($expected === '' || ! hash_equals($expected, $token), 404);

        Artisan::call('schedule:run');

        return response('ok', 200, ['Content-Type' => 'text/plain', 'Cache-Control' => 'no-store']);
    }
}
