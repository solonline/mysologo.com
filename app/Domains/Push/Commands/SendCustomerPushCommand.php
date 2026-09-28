<?php

namespace App\Domains\Push\Commands;

use App\Domains\Customer\Enums\CustomerNotificationType;
use App\Domains\Customer\Notifications\NotificationDispatcher;
use App\Domains\Customer\Notifications\SimpleCustomerNotification;
use App\Models\Customer;
use App\Models\CustomerDevice;
use Illuminate\Console\Command;
use ValueError;

class SendCustomerPushCommand extends Command
{
    protected $signature = 'push:send
                            {ids* : IDs dos customers a notificar}
                            {--title=Notificação de teste : Título do push}
                            {--body= : Corpo do push}
                            {--type=test_push : Tipo da notificação (enum)}
                            {--resource-id= : ID do recurso associado}';

    protected $description = 'Grava uma notificação e envia push FCM só para os customer IDs indicados';

    public function handle(NotificationDispatcher $dispatcher): int
    {
        $ids = collect($this->argument('ids'))
            ->flatMap(fn (string $value) => preg_split('/[,\s]+/', $value, -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn (string $id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            $this->error('Indica pelo menos um customer ID.');

            return self::FAILURE;
        }

        try {
            $type = CustomerNotificationType::from((string) $this->option('type'));
        } catch (ValueError) {
            $allowed = implode(', ', array_column(CustomerNotificationType::cases(), 'value'));
            $this->error("Tipo inválido. Usa um destes: {$allowed}");

            return self::FAILURE;
        }

        $title = (string) $this->option('title');
        $body = $this->option('body');
        $body = is_string($body) && $body !== '' ? $body : null;
        $resourceId = $this->option('resource-id');
        $resourceId = is_string($resourceId) && $resourceId !== '' ? $resourceId : null;

        $customers = Customer::query()->whereIn('id', $ids)->get()->keyBy('id');
        $missing = $ids->reject(fn (int $id) => $customers->has($id));

        if ($missing->isNotEmpty()) {
            $this->warn('Customers não encontrados: '.$missing->implode(', '));
        }

        if ($customers->isEmpty()) {
            $this->error('Nenhum customer válido para notificar.');

            return self::FAILURE;
        }

        $sent = 0;
        $skipped = 0;

        foreach ($customers as $customer) {
            $hasPush = CustomerDevice::query()
                ->where('customer_id', $customer->id)
                ->where('push_enabled', true)
                ->whereNotNull('push_token')
                ->exists();

            if (! $hasPush) {
                $this->line("Customer #{$customer->id}: sem aparelho com push activo — notificação gravada na mesma.");
                $skipped++;
            }

            $dispatcher->send(
                $customer,
                new SimpleCustomerNotification(
                    notificationType: $type,
                    notificationTitle: $title,
                    notificationBody: $body,
                    notificationResourceId: $resourceId,
                ),
            );
            $sent++;
            $this->info("Customer #{$customer->id}: notificação criada.");
        }

        $this->newLine();
        $this->info("Concluído: {$sent} notificação(ões) criada(s), {$skipped} sem push activo.");

        return self::SUCCESS;
    }
}
