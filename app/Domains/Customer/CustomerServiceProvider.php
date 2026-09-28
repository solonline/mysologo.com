<?php

namespace App\Domains\Customer;

use App\Domains\Customer\Contracts\CorporateDocumentRepositoryInterface;
use App\Domains\Customer\Contracts\CustomerRepositoryInterface;
use App\Domains\Customer\Contracts\RetailDocumentRepositoryInterface;
use App\Domains\Customer\Notifications\Channels\EmailNotificationChannel;
use App\Domains\Customer\Notifications\Channels\FcmNotificationChannel;
use App\Domains\Customer\Notifications\Channels\InAppNotificationChannel;
use App\Domains\Customer\Notifications\NotificationDispatcher;
use App\Domains\Customer\Repositories\CorporateDocumentRepository;
use App\Domains\Customer\Repositories\CustomerRepository;
use App\Domains\Customer\Repositories\RetailDocumentRepository;
use App\Domains\Customer\Services\CorporateDocumentService;
use App\Domains\Customer\Services\CustomerDocumentService;
use App\Domains\Customer\Services\CustomerFavoriteService;
use App\Domains\Customer\Services\CustomerNotificationService;
use App\Domains\Customer\Services\CustomerPhoneChangeService;
use App\Domains\Customer\Services\CustomerProfileService;
use App\Domains\Customer\Services\RetailDocumentService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class CustomerServiceProvider extends ServiceProvider
{
    /** @var array<int, class-string> */
    private array $containerSingletons = [
        CustomerProfileService::class,
        CustomerPhoneChangeService::class,
        CustomerDocumentService::class,
        RetailDocumentService::class,
        CorporateDocumentService::class,
        CustomerNotificationService::class,
        CustomerFavoriteService::class,
        NotificationDispatcher::class,
        InAppNotificationChannel::class,
        FcmNotificationChannel::class,
        EmailNotificationChannel::class,
    ];

    public function register(): void
    {
        $this->app->bind(
            CustomerRepositoryInterface::class,
            CustomerRepository::class,
        );
        $this->app->bind(
            RetailDocumentRepositoryInterface::class,
            RetailDocumentRepository::class,
        );
        $this->app->bind(
            CorporateDocumentRepositoryInterface::class,
            CorporateDocumentRepository::class,
        );

        foreach ($this->containerSingletons as $class) {
            $this->app->singleton($class);
        }
    }

    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api/customer')
            ->group(__DIR__.'/Routes/customer.php');
    }
}
