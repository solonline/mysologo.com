<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserNotification;
use App\Events\DashboardNotificationCreated;
use Illuminate\Console\Command;

class TestNotification extends Command
{
    protected $signature   = 'notify:test {userId=2} {--type=test.notification}';
    protected $description = 'Envia uma notificação de teste para um utilizador';

    public function handle(): void
    {
        $userId = (int) $this->argument('userId');
        $user   = User::find($userId);

        if (!$user) {
            $this->error("Utilizador #{$userId} não encontrado.");
            return;
        }

        $notification = UserNotification::create([
            'user_id'             => $user->id,
            'type'                => $this->option('type'),
            'title'               => '🔔 Notificação de teste OLHA AI',
            'body'                => "Teste enviado via artisan às " . now()->format('H:i:s'),
            'data'                => ['source' => 'artisan', 'timestamp' => now()->toIso8601String()],
            'permission_required' => null,
            'url'                 => null,
        ]);

        broadcast(new DashboardNotificationCreated($notification));

        $this->info("✔ Notificação criada (ID: {$notification->id}) e broadcast enviado para {$user->name} (#{$user->id}).");
    }
}
