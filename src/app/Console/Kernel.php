<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\ConsoleOutput;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // デモ用アカウントの操作を、毎日 4:00（日本時間）に初期状態へ戻す。
        // $schedule->command() は別プロセスで実行して出力を捨てるので、同じプロセスで呼び、
        // 結果の 1 行を標準出力へ流す（LOG_LEVEL に関係なく、毎朝の実行をログで確かめられる）
        $schedule->call(function () {
            Artisan::call('demo:reset', [], new ConsoleOutput());
        })->name('demo:reset')->dailyAt('04:00')->timezone('Asia/Tokyo');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
