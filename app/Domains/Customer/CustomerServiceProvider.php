<?php

namespace App\Domains\Customer;

use App\Domains\Customer\Services\CorporateDocumentService;
use App\Domains\Customer\Services\CustomerDocumentService;
use App\Domains\Customer\Services\CustomerNotificationService;
use App\Domains\Customer\Services\CustomerProfileService;
use App\Domains\Customer\Services\RetailDocumentService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class CustomerServiceProvider extends ServiceProvider
{
    /** @var array<int, class-string> */
    private array $containerSingletons = [
        CustomerProfileService::class,
        CustomerDocumentService::class,
        RetailDocumentService::class,
        CorporateDocumentService::class,
        CustomerNotificationService::class,
    ];

    public function register(): void
    {
        $this->app->bind(
            \App\Domains\Customer\Contracts\CustomerRepositoryInterface::class,
            \App\Domains\Customer\Repositories\CustomerRepository::class,
        );
        $this->app->bind(
            \App\Domains\Customer\Contracts\RetailDocumentRepositoryInterface::class,
            \App\Domains\Customer\Repositories\RetailDocumentRepository::class,
        );
        $this->app->bind(
            \App\Domains\Customer\Contracts\CorporateDocumentRepositoryInterface::class,
            \App\Domains\Customer\Repositories\CorporateDocumentRepository::class,
        );

        foreach ($this->containerSingletons as $class) {
            $this->app->singleton($class);
        }
    }

    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api/v2/customer')
            ->group(__DIR__ . '/Routes/customer.php');
    }
}
