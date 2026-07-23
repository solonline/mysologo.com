<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class FixLogPermissionsCommand extends Command
{
    protected $signature = 'logs:fix-permissions';

    protected $description = 'Garante que storage/logs é gravável por www-data (evita Permission denied em logs diários criados por root)';

    public function handle(): int
    {
        $dir = storage_path('logs');

        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 02775, true);
        }

        @chmod($dir, 02775);

        $webUser = 'www-data';
        $webGroup = 'www-data';

        if (function_exists('posix_getpwnam') && posix_getpwnam($webUser)) {
            @chown($dir, $webUser);
            @chgrp($dir, $webGroup);
        }

        foreach (File::files($dir) as $file) {
            $path = $file->getPathname();
            @chmod($path, 0664);

            if (function_exists('posix_getpwnam') && posix_getpwnam($webUser)) {
                @chown($path, $webUser);
                @chgrp($path, $webGroup);
            }
        }

        $this->info('Permissões de logs corrigidas.');

        return self::SUCCESS;
    }
}
