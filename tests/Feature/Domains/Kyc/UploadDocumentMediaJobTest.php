<?php

namespace Tests\Feature\Domains\Kyc;

use App\Domains\Kyc\Jobs\UploadDocumentMediaJob;
use App\Models\CustomerRetailDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithOnboarding;
use Tests\TestCase;

class UploadDocumentMediaJobTest extends TestCase
{
    use InteractsWithOnboarding;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareOnboardingFeatureTest();
        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_upload_portrait_job_updates_customer_avatar(): void
    {
        $customer = $this->createRetailCustomer(['avatar' => null]);
        $document = CustomerRetailDocument::query()->create([
            'customer_id' => $customer->id,
            'document_number' => 'DOC-AVATAR-1',
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'portrait');
        file_put_contents($tempFile, 'fake-portrait-bytes');

        try {
            (new UploadDocumentMediaJob($document->id, 'portrait_image', 'file://'.$tempFile))->handle();
        } finally {
            @unlink($tempFile);
        }

        $document->refresh();
        $customer->refresh();

        $expectedPath = "customer/{$customer->id}/didit/{$document->id}/portrait_image.jpg";

        $this->assertSame($expectedPath, $document->portrait_image);
        $this->assertSame($expectedPath, $customer->avatar);
        Storage::disk('local')->assertExists($expectedPath);
        Storage::disk('public')->assertMissing($expectedPath);
    }
}
