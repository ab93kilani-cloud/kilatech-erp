<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RunAccountingAlerts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'accounting:check-alerts';
    protected $description = 'Vérifier les alertes d\'expiration et envoyer les rappels mensuels';

    public function handle(\App\Services\AccountingAlertService $service)
    {
        $this->info('Vérification des attestations fournisseurs...');
        $service->checkSupplierCertificates();

        if (date('j') == 1) {
            $this->info('Envoi du rappel mensuel gérant...');
            $service->sendMonthlyBankStatementReminder();
        }

        $this->info('Alertes vérifiées avec succès.');
    }
}
