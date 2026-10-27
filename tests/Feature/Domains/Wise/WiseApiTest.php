<?php

namespace Tests\Feature\Domains\Wise;

use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Customer\Jobs\SendCustomerNotificationEmailJob;
use App\Domains\Customer\Mail\CustomerAppNotificationMail;
use App\Domains\Wallet\Jobs\MirrorWallesterDebitAfterWiseTransferJob;
use App\Domains\Wallet\Services\RailMirrorService;
use App\Domains\Wise\Clients\WiseHttpClient;
use App\Domains\Wise\Enums\WiseTransferStatus;
use App\Domains\Wise\Services\WiseWebhookProcessor;
use App\Models\Customer;
use App\Models\CustomerBalance;
use App\Models\CustomerDevice;
use App\Models\CustomerNotification;
use App\Models\CustomerWiseBeneficiary;
use App\Models\LedgerEntry;
use App\Models\WiseTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\Concerns\InteractsWithWiseSettings;
use Tests\TestCase;

class WiseApiTest extends TestCase
{
    use InteractsWithOnboarding;
    use InteractsWithWiseSettings;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedWiseSettings();
    }

    public function test_customer_can_list_balances_in_all_currencies(): void
    {
        Http::fake([
            $this->wiseUrl('profiles/12345/balances*') => Http::response([
                [
                    'id' => 64,
                    'currency' => 'EUR',
                    'amount' => ['value' => 274.21, 'currency' => 'EUR'],
                    'reservedAmount' => ['value' => 1.5, 'currency' => 'EUR'],
                    'type' => 'STANDARD',
                ],
                [
                    'id' => 65,
                    'currency' => 'USD',
                    'amount' => ['value' => 10, 'currency' => 'USD'],
                    'reservedAmount' => ['value' => 0, 'currency' => 'USD'],
                    'type' => 'STANDARD',
                ],
            ]),
        ]);

        $this->asCustomer()
            ->getJson('/api/wise/balances')
            ->assertOk()
            ->assertJsonPath('data.0.id', 64)
            ->assertJsonPath('data.0.currency', 'EUR')
            ->assertJsonPath('data.0.amount', 274.21)
            ->assertJsonPath('data.0.reserved_amount', 1.5)
            ->assertJsonPath('data.1.currency', 'USD')
            ->assertJsonPath('data.1.amount', 10);

        Http::assertSent(function (Request $request) {
            return $request->method() === 'GET'
                && str_contains($request->url(), '/profiles/12345/balances')
                && str_contains($request->url(), 'types=STANDARD')
                && $request->hasHeader('Authorization', 'Bearer test-wise-token');
        });
    }

    public function test_customer_can_create_a_beneficiary(): void
    {
        Http::fake([
            $this->wiseUrl('accounts') => Http::response([
                'id' => 8692237,
                'currency' => 'EUR',
                'type' => 'iban',
                'accountHolderName' => 'Ada Lovelace',
            ], 200),
        ]);

        $this->asCustomer()
            ->postJson('/api/wise/beneficiaries', [
                'currency' => 'eur',
                'type' => 'iban',
                'account_holder_name' => 'Ada Lovelace',
                'owned_by_customer' => false,
                'details' => [
                    'legalType' => 'PRIVATE',
                    'iban' => 'DE89370400440532013000',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.wise_recipient_id', 8692237)
            ->assertJsonPath('data.currency', 'EUR')
            ->assertJsonPath('data.type', 'iban')
            ->assertJsonPath('data.account_holder_name', 'Ada Lovelace');

        $this->assertDatabaseHas('customer_wise_beneficiaries', [
            'wise_recipient_id' => 8692237,
            'currency' => 'EUR',
            'type' => 'iban',
            'account_holder_name' => 'Ada Lovelace',
            'owned_by_customer' => false,
        ]);

        Http::assertSent(function (Request $request) {
            return $request->method() === 'POST'
                && str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/accounts')
                && $request->data()['currency'] === 'EUR'
                && $request->data()['profile'] === 12345
                && $request->data()['accountHolderName'] === 'Ada Lovelace'
                && $request->data()['ownedByCustomer'] === false
                && $request->data()['details']['iban'] === 'DE89370400440532013000';
        });
    }

    public function test_customer_can_preview_the_transfer_fee(): void
    {
        $customer = $this->fundedWiseCustomer();
        $this->linkBeneficiary($customer);

        Http::fake([
            $this->wiseUrl('profiles/12345/quotes') => Http::response([
                'id' => '8fa9be20-ba43-4b15-abbb-9424e1481050',
                'sourceCurrency' => 'EUR',
                'targetCurrency' => 'GBP',
                'sourceAmount' => 100,
                'targetAmount' => 85,
                'payOut' => 'BANK_TRANSFER',
                'paymentOptions' => [[
                    'payIn' => 'BALANCE',
                    'payOut' => 'BANK_TRANSFER',
                    'sourceAmount' => 101.5,
                    'disabled' => false,
                    'price' => [
                        'total' => [
                            'value' => [
                                'amount' => 1.5,
                                'currency' => 'EUR',
                            ],
                        ],
                    ],
                ]],
            ]),
        ]);

        $this->withToken($customer->createToken('test')->plainTextToken)
            ->postJson('/api/wise/quotes', [
                'source_currency' => 'EUR',
                'source_amount' => 10000,
                'beneficiary_id' => 8692237,
            ])
            ->assertOk()
            ->assertJsonPath('data.quote_id', '8fa9be20-ba43-4b15-abbb-9424e1481050')
            ->assertJsonPath('data.beneficiary_id', 8692237)
            ->assertJsonPath('data.source_currency', 'EUR')
            ->assertJsonPath('data.amount_cents', 10000)
            ->assertJsonPath('data.source_value', 101.5)
            ->assertJsonPath('data.target_currency', 'GBP')
            ->assertJsonPath('data.target_value', 85)
            ->assertJsonPath('data.fee', 1.5)
            ->assertJsonPath('data.fee_currency', 'EUR');

        $this->assertSame(12_000, CustomerBalance::query()->where('customer_id', $customer->id)->value('wallester_allocated'));
        $this->assertDatabaseCount('wise_transfers', 0);

        Http::assertSent(function (Request $request) {
            return $request->method() === 'POST'
                && str_contains($request->url(), '/quotes')
                && (float) $request->data()['sourceAmount'] === 100.0
                && $request->data()['sourceCurrency'] === 'EUR'
                && $request->data()['targetCurrency'] === 'GBP'
                && $request->data()['targetAccount'] === 8692237
                && $request->data()['preferredPayIn'] === 'BALANCE';
        });
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/batch-groups'));
    }

    public function test_quote_requires_a_beneficiary_id_or_account_details(): void
    {
        $customer = $this->fundedWiseCustomer();

        Http::fake();

        $this->withToken($customer->createToken('test')->plainTextToken)
            ->postJson('/api/wise/quotes', [
                'source_currency' => 'EUR',
                'source_amount' => 10000,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['beneficiary_id', 'beneficiary']);

        Http::assertNothingSent();
    }

    public function test_customer_can_quote_and_create_a_batch_transfer(): void
    {
        Queue::fake([MirrorWallesterDebitAfterWiseTransferJob::class]);

        $quoteId = '8fa9be20-ba43-4b15-abbb-9424e1481050';
        $batchId = '54a6bc09-cef9-49a8-9041-f1f0c654cd88';
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);

        CustomerBalance::query()->create([
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'wallester_allocated' => 12_000,
            'pending' => 0,
            'wise_allocated' => 12_000,
        ]);
        $this->grantTransferPin($customer);

        Http::fake([
            $this->wiseUrl('profiles/12345/quotes') => Http::response([
                'id' => $quoteId,
                'sourceCurrency' => 'EUR',
                'targetCurrency' => 'GBP',
                'sourceAmount' => 100,
                'targetAmount' => 85,
                'payOut' => 'BANK_TRANSFER',
                'paymentOptions' => [
                    [
                        'payIn' => 'BANK_TRANSFER',
                        'payOut' => 'BANK_TRANSFER',
                        'sourceAmount' => 103,
                        'disabled' => false,
                    ],
                    [
                        'payIn' => 'BALANCE',
                        'payOut' => 'BANK_TRANSFER',
                        'sourceAmount' => 101.5,
                        'disabled' => false,
                        'price' => [
                            'total' => [
                                'value' => [
                                    'amount' => 1.5,
                                    'currency' => 'EUR',
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
            $this->wiseUrl('profiles/12345/batch-groups') => Http::response([
                'id' => $batchId,
                'version' => 0,
                'name' => 'Invoice 2026-001',
                'status' => 'NEW',
            ], 201),
            $this->wiseUrl('profiles/12345/batch-groups/'.$batchId.'/transfers') => Http::response([
                'id' => 16521632,
                'status' => 'incoming_payment_waiting',
                'sourceCurrency' => 'EUR',
                'sourceValue' => 100,
                'targetCurrency' => 'GBP',
                'targetValue' => 85,
            ], 201),
            $this->wiseUrl('profiles/12345/batch-groups/'.$batchId) => Http::sequence()
                ->push(['id' => $batchId, 'version' => 2, 'status' => 'NEW'])
                ->push(['id' => $batchId, 'version' => 3, 'status' => 'COMPLETED']),
        ]);

        $this->linkBeneficiary($customer);

        $response = $this->withToken($customer->createToken('test')->plainTextToken)
            ->postJson('/api/wise/transfers', [
                'source_currency' => 'EUR',
                'source_amount' => 10000,
                'beneficiary_id' => 8692237,
                'device_token' => 'wise-device',
                'pin' => '1234',
            ])
            ->assertCreated()
            ->assertJsonPath('data.quote_id', $quoteId)
            ->assertJsonPath('data.transfer_id', 16521632)
            ->assertJsonPath('data.batch_group_id', $batchId)
            ->assertJsonPath('data.beneficiary_id', 8692237)
            ->assertJsonPath('data.status', 'COMPLETED')
            ->assertJsonPath('data.amount_cents', 10000)
            ->assertJsonPath('data.fee', 1.5)
            ->assertJsonPath('data.fee_currency', 'EUR')
            ->assertJsonPath('data.source_value', 101.5);

        $customerTransactionId = $response->json('data.customer_transaction_id');
        $reference = $response->json('data.reference');
        $this->assertIsString($customerTransactionId);
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{16}$/', (string) $reference);

        $this->assertDatabaseHas('wise_transfers', [
            'customer_id' => $customer->id,
            'wise_transfer_id' => 16521632,
            'wise_batch_group_id' => $batchId,
            'amount_cents' => 10000,
            'fee_cents' => 150,
            'reference' => $reference,
            'status' => 'incoming_payment_waiting',
            'idempotency_key' => 'wise_transfer:'.$customerTransactionId,
        ]);

        $this->assertDatabaseHas('customer_balances', [
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'wallester_allocated' => 2_000,
            'wise_allocated' => 2_000,
        ]);

        $ledger = LedgerEntry::query()
            ->where('customer_id', $customer->id)
            ->where('entry_type', 'wise_transfer')
            ->first();

        $this->assertNotNull($ledger);
        $this->assertSame(10000, (int) $ledger->amount);
        $this->assertSame(150, $ledger->metadata['wise_fee_cents'] ?? null);
        $this->assertSame('EUR', $ledger->metadata['wise_fee_currency'] ?? null);

        Queue::assertNotPushed(MirrorWallesterDebitAfterWiseTransferJob::class);

        Http::assertSentInOrder([
            function (Request $request) {
                return $request->method() === 'POST'
                    && str_contains($request->url(), '/quotes')
                    && $request->data()['preferredPayIn'] === 'BALANCE'
                    && $request->data()['targetAccount'] === 8692237
                    && (float) $request->data()['sourceAmount'] === 100.0
                    && $request->data()['sourceCurrency'] === 'EUR'
                    && $request->data()['targetCurrency'] === 'GBP';
            },
            function (Request $request) use ($reference) {
                $path = (string) parse_url($request->url(), PHP_URL_PATH);

                return $request->method() === 'POST'
                    && str_ends_with($path, '/batch-groups')
                    && $request->data()['sourceCurrency'] === 'EUR'
                    && $request->data()['name'] === $reference;
            },
            function (Request $request) use ($quoteId, $customerTransactionId, $reference) {
                return $request->method() === 'POST'
                    && str_contains($request->url(), '/batch-groups/')
                    && str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/transfers')
                    && $request->data()['quoteUuid'] === $quoteId
                    && $request->data()['targetAccount'] === 8692237
                    && $request->data()['customerTransactionId'] === $customerTransactionId
                    && $request->data()['details']['reference'] === $reference;
            },
            function (Request $request) {
                return $request->method() === 'GET'
                    && str_contains($request->url(), '/batch-groups/');
            },
            function (Request $request) {
                return $request->method() === 'PATCH'
                    && str_contains($request->url(), '/batch-groups/')
                    && $request->data()['status'] === 'COMPLETED'
                    && $request->data()['version'] === 2;
            },
        ]);

        Http::assertNotSent(function (Request $request) {
            return str_contains($request->url(), '/payments');
        });
    }

    public function test_captive_amount_is_released_when_batch_creation_fails(): void
    {
        Queue::fake([MirrorWallesterDebitAfterWiseTransferJob::class]);

        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);

        $this->linkBeneficiary($customer);

        CustomerBalance::query()->create([
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'wallester_allocated' => 5_000,
            'pending' => 0,
            'wise_allocated' => 5_000,
        ]);
        $this->grantTransferPin($customer);

        Http::fake([
            $this->wiseUrl('profiles/12345/quotes') => Http::response([
                'id' => '8fa9be20-ba43-4b15-abbb-9424e1481050',
                'sourceAmount' => 50,
                'payOut' => 'BANK_TRANSFER',
                'paymentOptions' => [
                    [
                        'payIn' => 'BALANCE',
                        'payOut' => 'BANK_TRANSFER',
                        'sourceAmount' => 50,
                        'price' => [
                            'total' => [
                                'value' => [
                                    'amount' => 0.5,
                                    'currency' => 'EUR',
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
            $this->wiseUrl('profiles/12345/batch-groups') => Http::response([
                'error' => 'unavailable',
            ], 500),
        ]);

        $this->withToken($customer->createToken('test')->plainTextToken)
            ->postJson('/api/wise/transfers', [
                'source_currency' => 'EUR',
                'source_amount' => 5000,
                'beneficiary_id' => 8692237,
                'device_token' => 'wise-device',
                'pin' => '1234',
            ])
            ->assertStatus(502);

        $this->assertDatabaseHas('customer_balances', [
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'wallester_allocated' => 5_000,
            'wise_allocated' => 5_000,
        ]);

        $this->assertDatabaseCount('wise_transfers', 0);

        Queue::assertNotPushed(MirrorWallesterDebitAfterWiseTransferJob::class);
    }

    public function test_second_customer_links_the_same_beneficiary_without_calling_wise(): void
    {
        Http::fake([
            $this->wiseUrl('accounts') => Http::response([
                'id' => 8692237,
                'profileId' => 12345,
                'currency' => 'EUR',
                'type' => 'iban',
                'hash' => 'recipient-hash',
                'accountSummary' => 'EUR account ending in 3000',
                'active' => true,
            ], 200),
        ]);

        $payload = [
            'currency' => 'EUR',
            'type' => 'iban',
            'account_holder_name' => 'Ada Lovelace',
            'owned_by_customer' => false,
            'details' => [
                'iban' => 'DE89370400440532013000',
                'legalType' => 'PRIVATE',
            ],
        ];

        $first = $this->customerToken();
        $this->asToken($first['token'])
            ->postJson('/api/wise/beneficiaries', $payload)
            ->assertCreated()
            ->assertJsonPath('data.wise_recipient_id', 8692237);

        $second = $this->customerToken();
        $this->asToken($second['token'])
            ->postJson('/api/wise/beneficiaries', $payload)
            ->assertCreated()
            ->assertJsonPath('data.wise_recipient_id', 8692237);

        $this->asToken($second['token'])
            ->postJson('/api/wise/beneficiaries', [
                'wise_recipient_id' => 8692237,
            ])
            ->assertOk()
            ->assertJsonPath('data.wise_recipient_id', 8692237);

        $this->asToken($second['token'])
            ->getJson('/api/wise/beneficiaries')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.wise_recipient_id', 8692237);

        $this->asToken($first['token'])
            ->getJson('/api/wise/beneficiaries')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertSame(2, CustomerWiseBeneficiary::query()->where('wise_recipient_id', 8692237)->count());

        Http::assertSentCount(1);
    }

    public function test_transfer_requires_a_linked_beneficiary(): void
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);

        Http::fake();
        $this->grantTransferPin($customer);

        $this->withToken($customer->createToken('test')->plainTextToken)
            ->postJson('/api/wise/transfers', [
                'source_currency' => 'EUR',
                'source_amount' => 100,
                'beneficiary_id' => 8692237,
                'device_token' => 'wise-device',
                'pin' => '1234',
            ])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'wise_beneficiary_not_found');

        Http::assertNothingSent();
    }

    public function test_transfer_requires_a_beneficiary_id_or_account_details(): void
    {
        $customer = $this->fundedWiseCustomer();
        $token = $customer->createToken('test')->plainTextToken;

        Http::fake();

        $this->withToken($token)
            ->postJson('/api/wise/transfers', [
                'source_currency' => 'EUR',
                'source_amount' => 100,
                'device_token' => 'wise-device',
                'pin' => '1234',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['beneficiary_id', 'beneficiary']);

        Http::assertNothingSent();
    }

    public function test_transfer_creates_the_beneficiary_when_only_account_details_are_sent(): void
    {
        Queue::fake([MirrorWallesterDebitAfterWiseTransferJob::class]);

        $customer = $this->fundedWiseCustomer();
        $batchId = '54a6bc09-cef9-49a8-9041-f1f0c654cd88';

        Http::fake([
            $this->wiseUrl('accounts') => Http::response([
                'id' => 8692237,
                'currency' => 'EUR',
                'type' => 'iban',
                'accountHolderName' => 'Ada Lovelace',
                'active' => true,
            ]),
            $this->wiseUrl('profiles/12345/quotes') => Http::response([
                'id' => '8fa9be20-ba43-4b15-abbb-9424e1481050',
                'sourceCurrency' => 'EUR',
                'targetCurrency' => 'EUR',
                'sourceAmount' => 100,
                'targetAmount' => 100,
                'payOut' => 'BANK_TRANSFER',
                'paymentOptions' => [[
                    'payIn' => 'BALANCE',
                    'payOut' => 'BANK_TRANSFER',
                    'sourceAmount' => 101.5,
                    'disabled' => false,
                    'price' => ['total' => ['value' => ['amount' => 1.5, 'currency' => 'EUR']]],
                ]],
            ]),
            $this->wiseUrl('profiles/12345/batch-groups') => Http::response([
                'id' => $batchId,
                'version' => 0,
                'status' => 'NEW',
            ], 201),
            $this->wiseUrl('profiles/12345/batch-groups/'.$batchId.'/transfers') => Http::response([
                'id' => 16521632,
                'status' => 'incoming_payment_waiting',
                'sourceCurrency' => 'EUR',
                'targetCurrency' => 'EUR',
            ], 201),
            $this->wiseUrl('profiles/12345/batch-groups/'.$batchId) => Http::sequence()
                ->push(['id' => $batchId, 'version' => 2, 'status' => 'NEW'])
                ->push(['id' => $batchId, 'version' => 3, 'status' => 'COMPLETED']),
        ]);

        $this->withToken($customer->createToken('test')->plainTextToken)
            ->postJson('/api/wise/transfers', [
                'source_currency' => 'EUR',
                'source_amount' => 10000,
                'device_token' => 'wise-device',
                'pin' => '1234',
                'beneficiary' => [
                    'currency' => 'EUR',
                    'type' => 'iban',
                    'account_holder_name' => 'Ada Lovelace',
                    'owned_by_customer' => false,
                    'details' => [
                        'legalType' => 'PRIVATE',
                        'iban' => 'DE89370400440532013000',
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.beneficiary_id', 8692237);

        $this->assertDatabaseHas('customer_wise_beneficiaries', [
            'customer_id' => $customer->id,
            'wise_recipient_id' => 8692237,
            'currency' => 'EUR',
            'type' => 'iban',
        ]);

        $recorded = Http::recorded();
        $firstPath = (string) parse_url($recorded[0][0]->url(), PHP_URL_PATH);
        $secondUrl = $recorded[1][0]->url();

        $this->assertTrue(str_ends_with($firstPath, '/accounts'));
        $this->assertStringContainsString('/quotes', $secondUrl);
        $this->assertSame(8692237, $recorded[1][0]->data()['targetAccount']);
    }

    public function test_outgoing_payment_webhook_debits_wallester_and_sends_receipt_once(): void
    {
        Queue::fake([
            MirrorWallesterDebitAfterWiseTransferJob::class,
            SendCustomerNotificationEmailJob::class,
        ]);

        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);

        CustomerBalance::query()->create([
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'wallester_allocated' => 5_000,
            'pending' => 0,
            'wise_allocated' => 5_000,
        ]);

        app(RailMirrorService::class)->reserveForWiseTransfer(
            customer: $customer,
            currency: 'EUR',
            amountCents: 1_000,
            idempotencyKey: 'wise_transfer:held-1',
            reference: null,
            feeCents: 0,
            feeCurrency: 'EUR',
        );

        WiseTransfer::query()->create([
            'customer_id' => $customer->id,
            'wise_transfer_id' => 2440000001,
            'wise_batch_group_id' => '54a6bc09-cef9-49a8-9041-f1f0c654cd88',
            'wise_recipient_id' => 8692237,
            'beneficiary_name' => 'Bruno Henrique Trindade',
            'reference' => 'Bruno 1 EUR',
            'currency' => 'EUR',
            'amount_cents' => 1_000,
            'fee_cents' => 0,
            'fee_currency' => 'EUR',
            'idempotency_key' => 'wise_transfer:held-1',
            'status' => 'incoming_payment_waiting',
        ]);

        $payload = [
            'event_type' => 'transfers#state-change',
            'data' => [
                'resource' => ['id' => 2440000001, 'type' => 'transfer'],
                'current_state' => 'outgoing_payment_sent',
            ],
        ];

        $processor = app(WiseWebhookProcessor::class);
        $processor->process($payload, 'delivery-1');
        $processor->process($payload, 'delivery-2');

        Queue::assertPushed(MirrorWallesterDebitAfterWiseTransferJob::class, 1);
        Queue::assertPushed(SendCustomerNotificationEmailJob::class, 1);

        $row = WiseTransfer::query()->where('wise_transfer_id', 2440000001)->first();
        $this->assertNotNull($row);
        $this->assertNotNull($row->settled_at);
        $this->assertNotNull($row->receipt_sent_at);
        $this->assertSame(WiseTransferStatus::OutgoingPaymentSent, $row->status);

        $this->assertDatabaseHas('customer_balances', [
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'wallester_allocated' => 4_000,
            'wise_allocated' => 4_000,
        ]);

        $this->assertSame(1, CustomerNotification::query()->where('customer_id', $customer->id)->count());
    }

    public function test_cancelled_webhook_returns_the_captive_amount(): void
    {
        Queue::fake([
            MirrorWallesterDebitAfterWiseTransferJob::class,
            SendCustomerNotificationEmailJob::class,
        ]);

        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);

        CustomerBalance::query()->create([
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'wallester_allocated' => 5_000,
            'pending' => 0,
            'wise_allocated' => 5_000,
        ]);

        app(RailMirrorService::class)->reserveForWiseTransfer(
            customer: $customer,
            currency: 'EUR',
            amountCents: 1_000,
            idempotencyKey: 'wise_transfer:held-2',
            reference: null,
        );

        WiseTransfer::query()->create([
            'customer_id' => $customer->id,
            'wise_transfer_id' => 2440000002,
            'wise_batch_group_id' => '54a6bc09-cef9-49a8-9041-f1f0c654cd89',
            'wise_recipient_id' => 8692237,
            'beneficiary_name' => 'Bruno Henrique Trindade',
            'reference' => 'Bruno 1 EUR',
            'currency' => 'EUR',
            'amount_cents' => 1_000,
            'idempotency_key' => 'wise_transfer:held-2',
            'status' => 'incoming_payment_waiting',
        ]);

        app(WiseWebhookProcessor::class)->process([
            'event_type' => 'transfers#state-change',
            'data' => [
                'resource' => ['id' => 2440000002],
                'current_state' => 'cancelled',
            ],
        ], 'delivery-cancel');

        Queue::assertNothingPushed();

        $this->assertDatabaseHas('customer_balances', [
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'wallester_allocated' => 5_000,
            'wise_allocated' => 5_000,
        ]);

        $this->assertDatabaseHas('wise_transfers', [
            'wise_transfer_id' => 2440000002,
            'status' => 'cancelled',
        ]);
    }

    public function test_wise_receipt_is_attached_to_the_transfer_email(): void
    {
        Mail::fake();

        Http::fake([
            $this->wiseUrl('transfers/2440000003/receipt.pdf') => Http::response(
                '%PDF-1.4 wise-receipt',
                200,
                ['Content-Type' => 'application/pdf'],
            ),
        ]);

        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);

        $notification = CustomerNotification::query()->create([
            'customer_id' => $customer->id,
            'type' => 'transfer_sent',
            'title' => 'Transferência enviada',
            'body' => 'Enviada',
            'data' => [
                'resource_id' => 2440000003,
                'wise_transfer_id' => 2440000003,
                'amount_label' => '10,00 EUR',
                'recipient' => 'Bruno Henrique Trindade',
            ],
        ]);

        (new SendCustomerNotificationEmailJob($notification->id))->handle();

        Mail::assertSent(CustomerAppNotificationMail::class, function (CustomerAppNotificationMail $mail) use ($customer): bool {
            return $mail->hasTo($customer->email);
        });

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'GET'
                && str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/receipt.pdf');
        });
    }

    public function test_balances_require_authentication(): void
    {
        $this->getJson('/api/wise/balances')->assertUnauthorized();
    }

    private function asCustomer(): static
    {
        return $this->asToken($this->customerToken()['token']);
    }

    private function asToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    /**
     * @return array{customer: Customer, token: string}
     */
    private function customerToken(): array
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);

        return [
            'customer' => $customer,
            'token' => $customer->createToken('test')->plainTextToken,
        ];
    }

    private function fundedWiseCustomer(int $cents = 12_000): Customer
    {
        $customer = $this->createRetailCustomer([
            'status' => CustomerLifecycleStatus::ACTIVE,
            'email_verified_at' => now(),
            'doc_status' => 'approved',
        ]);

        CustomerBalance::query()->create([
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'wallester_allocated' => $cents,
            'pending' => 0,
            'wise_allocated' => $cents,
        ]);

        $this->grantTransferPin($customer);

        return $customer;
    }

    private function grantTransferPin(Customer $customer): void
    {
        CustomerDevice::query()->create([
            'customer_id' => $customer->id,
            'device_name' => 'Phone',
            'device_token' => 'wise-device',
            'pin' => bcrypt('1234'),
        ]);
    }

    private function linkBeneficiary(Customer $customer, int $wiseRecipientId = 8692237): CustomerWiseBeneficiary
    {
        return CustomerWiseBeneficiary::query()->create([
            'customer_id' => $customer->id,
            'wise_recipient_id' => $wiseRecipientId,
            'wise_profile_id' => '12345',
            'currency' => 'GBP',
            'type' => 'sort_code',
            'account_holder_name' => 'Ada Lovelace',
            'owned_by_customer' => false,
            'details' => [
                'legalType' => 'PRIVATE',
                'sortCode' => '040075',
                'accountNumber' => '37778842',
            ],
            'active' => true,
        ]);
    }

    private function wiseUrl(string $path): string
    {
        return 'https://api.wise-sandbox.com/'.WiseHttpClient::API_VERSION.'/'.$path;
    }
}
