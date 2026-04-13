<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Str;

class GenerateN8nToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seo:generate-token {--rotate : Supprimer l\'ancien token et en créer un nouveau}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Génère ou fait pivoter le token API pour l\'automatisation n8n';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // 1. Assurer l'existence du rôle 'n8n_bot'
        $role = Role::firstOrCreate(['name' => 'n8n_bot']);

        // 2. Assurer l'existence de l'utilisateur 'n8n_bot'
        $user = User::firstOrCreate(
            ['email' => 'n8n@kilatech.ma'],
            [
                'name' => 'n8n_bot',
                'password_hash' => bcrypt(Str::random(32)),
                'role_id' => $role->id,
                'is_active' => true
            ]
        );

        // 3. Rotation si demandée (supprimer les anciens tokens)
        if ($this->option('rotate')) {
            $user->tokens()->delete();
            $this->info('Anciens tokens supprimés (Rotation effectuée).');
        }

        // 4. Générer le nouveau token (Long Life)
        // On accorde l'aptitude 'seo:manage' pour une sécurité granulaire future
        $token = $user->createToken('n8n-automation', ['seo:manage']);

        $this->info('--- CONFIGURATION n8n ---');
        $this->info('Utilisateur : n8n_bot');
        $this->info('Email       : n8n@kilatech.ma');
        $this->info('Statut      : Activer');
        $this->info('Token       : ' . $token->plainTextToken);
        $this->info('-------------------------');
        $this->warn('IMPORTANT : Gardez ce token secret. Utilisez-le dans vos requêtes HTTP :');
        $this->line('Authorization: Bearer ' . $token->plainTextToken);
    }
}
