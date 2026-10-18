<?php

namespace App\Console\Commands;

use App\Services\Disc\DeadlineNotificationService;
use Illuminate\Console\Command;

class SendDiscDeadlineAlerts extends Command
{
    protected $signature = 'disc:send-deadline-alerts';

    protected $description = 'Crée les notifications DISC pour les échéances jaunes et rouges';

    public function handle(DeadlineNotificationService $service): int
    {
        $result = $service->dispatch();
        $this->info("{$result['examined']} courrier(s) examiné(s), {$result['created']} notification(s) créée(s).");

        return self::SUCCESS;
    }
}
