<?php

namespace Tests\Unit\Domains\Wallester;

use App\Domains\Wallester\Services\PlatformMasterCurrencyResolver;
use PHPUnit\Framework\TestCase;

class PlatformMasterCurrencyResolverTest extends TestCase
{
    public function test_resolves_eur_and_usd(): void
    {
        $resolver = new PlatformMasterCurrencyResolver;

        $this->assertSame('EUR', $resolver->resolve('EUR'));
        $this->assertSame('USD', $resolver->resolve('usd'));
    }

    public function test_falls_back_to_eur_for_other_currencies(): void
    {
        $resolver = new PlatformMasterCurrencyResolver;

        $this->assertSame('EUR', $resolver->resolve('GBP'));
        $this->assertSame('EUR', $resolver->resolve('CHF'));
    }
}
