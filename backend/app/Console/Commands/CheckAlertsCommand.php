<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AlertService;

class CheckAlertsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alerts:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan database to generate business alerts (Low stock, DLC expiration)';

    /**
     * Execute the console command.
     */
    public function handle(AlertService $alertService)
    {
        $this->info('Début du scan des alertes...');
        
        $alertService->generateAlerts();
        
        $this->info('Scan terminé avec succès. Les notifications ont été générées si nécessaire.');
        return Command::SUCCESS;
    }
}
