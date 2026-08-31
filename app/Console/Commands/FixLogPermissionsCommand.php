<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class FixLogPermissionsCommand extends Command
{
    protected $signature = 'logs:fix-permissions';

    protected $description = 'Cria as pastas de log e garante que storage/logs é gravável por www-data';

    public function handle(): int
    {
        $root = storage_path('logs');

        if (! File::isDirectory($root)) {
            File::makeDirectory($root, 02775, true);
        }

        foreach (config('logging.directories', []) as $directory) {
            $path = $root.DIRECTORY_SEPARATOR.$directory;

            if (! File::isDirectory($path)) {
                File::makeDirectory($path, 02775, true);
            }
        }

        $webUser = 'www-data';
        $webGroup = 'www-data';
        $canChown = function_exists('posix_getpwnam') && posix_getpwnam($webUser);

        $directories = [$root, ...File::directories($root)];

        foreach ($directories as $directory) {
            @chmod($directory, 02775);

            if ($canChown) {
                @chown($directory, $webUser);
                @chgrp($directory, $webGroup);
            }
        }

        foreach (File::allFiles($root) as $file) {
            $path = $file->getPathname();
            @chmod($path, 0664);

            if ($canChown) {
                @chown($path, $webUser);
                @chgrp($path, $webGroup);
            }
        }

        $this->info('Permissões e pastas de logs corrigidas.');

        return self::SUCCESS;
    }
}
