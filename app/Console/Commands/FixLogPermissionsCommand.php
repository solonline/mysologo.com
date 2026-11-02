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
        $webUser = 'www-data';
        $webGroup = 'www-data';
        $isRoot = function_exists('posix_geteuid') && posix_geteuid() === 0;
        $canChown = $isRoot && function_exists('posix_getpwnam') && posix_getpwnam($webUser);

        $this->ensureDirectory($root);

        foreach (config('logging.directories', []) as $directory) {
            $this->ensureDirectory($root.DIRECTORY_SEPARATOR.$directory);
        }

        $failed = false;
        $directories = [$root, ...File::directories($root)];

        foreach ($directories as $directory) {
            if (! $this->applyPermissions($directory, 02775, $webUser, $webGroup, $canChown, $isRoot)) {
                $failed = true;
            }
        }

        foreach (File::allFiles($root) as $file) {
            if (! $this->applyPermissions($file->getPathname(), 0664, $webUser, $webGroup, $canChown, $isRoot)) {
                $failed = true;
            }
        }

        if ($failed) {
            $this->error('Algumas permissões de log não puderam ser corrigidas.');

            return self::FAILURE;
        }

        $this->info('Permissões e pastas de logs corrigidas.');

        return self::SUCCESS;
    }

    private function ensureDirectory(string $path): void
    {
        if (File::isDirectory($path)) {
            return;
        }

        $previousUmask = umask(0);

        try {
            File::makeDirectory($path, 02775, true);
        } finally {
            umask($previousUmask);
        }
    }

    private function applyPermissions(
        string $path,
        int $mode,
        string $webUser,
        string $webGroup,
        bool $canChown,
        bool $isRoot,
    ): bool {
        $owner = $this->ownerName($path);

        if (! $canChown && ! $isRoot && $owner === 'root') {
            $this->warn("{$path} pertence ao root. Execute o comando como root para entregar a pasta ao www-data.");

            return false;
        }

        $ok = true;

        if (! chmod($path, $mode)) {
            $this->warn("Não foi possível alterar o modo de {$path}.");
            $ok = false;
        }

        if (! $canChown) {
            return $ok;
        }

        if (! chown($path, $webUser) || ! chgrp($path, $webGroup)) {
            $this->warn("Não foi possível alterar o dono de {$path} para {$webUser}.");
            $ok = false;
        }

        return $ok;
    }

    private function ownerName(string $path): ?string
    {
        $uid = fileowner($path);

        if ($uid === false || ! function_exists('posix_getpwuid')) {
            return null;
        }

        $info = posix_getpwuid($uid);

        return is_array($info) ? ($info['name'] ?? null) : null;
    }
}
