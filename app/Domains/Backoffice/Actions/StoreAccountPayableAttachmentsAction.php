<?php

namespace App\Domains\Backoffice\Actions;

use App\Models\AccountPayable;
use Illuminate\Http\UploadedFile;

class StoreAccountPayableAttachmentsAction
{
    /**
     * @param  list<UploadedFile>  $files
     */
    public function execute(AccountPayable $account, array $files): void
    {
        foreach ($files as $file) {
            $path = $file->store("accounts-payable/{$account->id}", 'local');

            $account->attachments()->create([
                'disk' => 'local',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }
    }
}
