<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Supplier;
use App\Models\User;
use App\Models\BankStatement;
use Carbon\Carbon;

class AccountingAlertService
{
    /**
     * Rappel mensuel pour le gérant (Upload Relevé)
     */
    public function sendMonthlyBankStatementReminder()
    {
        $managers = User::whereHas('role', function($q) {
            $q->where('name', 'Gérant')->orWhere('name', 'Admin');
        })->get();

        foreach ($managers as $manager) {
            Notification::create([
                'user_id' => $manager->id,
                'type' => 'accounting_reminder',
                'title' => '📁 Rappel : Relevé Bancaire',
                'message' => 'C\'est le début du mois. Pensez à uploader le relevé bancaire du mois dernier.',
                'action_url' => '/admin/manager/bank',
            ]);
        }
    }

    /**
     * Vérification des attestations fournisseurs (Missing or Expiring)
     */
    public function checkSupplierCertificates()
    {
        $buyers = User::whereHas('role', function($q) {
            $q->where('name', 'Service Achat')->orWhere('name', 'Admin');
        })->get();

        // 1. Missing Certificates
        $missing = Supplier::whereNull('tax_certificate_path')->get();
        foreach ($missing as $supplier) {
            $this->notifyBuyers($buyers, "⚠️ Document Manquant", "L'attestation fiscale est manquante pour le fournisseur : " . $supplier->name, "/admin/suppliers");
        }

        // 2. Expiring soon (less than 15 days)
        $expiring = Supplier::whereNotNull('tax_certificate_expires_at')
            ->where('tax_certificate_expires_at', '<', Carbon::now()->addDays(15))
            ->get();
        
        foreach ($expiring as $supplier) {
            $status = Carbon::parse($supplier->tax_certificate_expires_at)->isPast() ? "PÉRIMÉE" : "va bientôt expirer";
            $this->notifyBuyers($buyers, "🚨 Attestation Fournisseur", "L'attestation fiscale de " . $supplier->name . " " . $status . ".", "/admin/suppliers");
        }
    }

    private function notifyBuyers($buyers, $title, $message, $url)
    {
        foreach ($buyers as $buyer) {
            Notification::create([
                'user_id' => $buyer->id,
                'type' => 'supplier_alert',
                'title' => $title,
                'message' => $message,
                'action_url' => $url,
            ]);
        }
    }
}
