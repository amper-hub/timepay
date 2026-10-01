<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class DownloadCaBundle extends Command
{
    protected $signature = 'faceplusplus:download-ca-bundle';

    protected $description = 'Download the Mozilla CA bundle used to verify Face++ TLS certificates.';

    private const SOURCE_URL = 'https://curl.se/ca/cacert.pem';

    public function handle(): int
    {
        $directory = storage_path('app');
        $destination = $directory.DIRECTORY_SEPARATOR.'cacert.pem';

        if (is_file($destination)) {
            $this->info('CA bundle already exists at storage/app/cacert.pem.');

            return self::SUCCESS;
        }

        File::ensureDirectoryExists($directory);
        $temporaryFile = tempnam($directory, 'cacert-');

        if ($temporaryFile === false) {
            $this->error('Could not create a temporary file in storage/app.');

            return self::FAILURE;
        }

        try {
            $this->downloadBundle($temporaryFile);
            $contents = file_get_contents($temporaryFile);

            if ($contents === false
                || ! str_contains($contents, '-----BEGIN CERTIFICATE-----')
                || ! str_contains($contents, '-----END CERTIFICATE-----')) {
                throw new RuntimeException('The download did not contain a valid PEM certificate bundle.');
            }

            // Do not overwrite a bundle that appeared while the download ran.
            if (is_file($destination)) {
                $this->info('CA bundle already exists at storage/app/cacert.pem.');

                return self::SUCCESS;
            }

            if (! rename($temporaryFile, $destination)) {
                throw new RuntimeException('Could not install the CA bundle in storage/app.');
            }

            $this->info('Downloaded the CA bundle to storage/app/cacert.pem.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Could not download the CA bundle: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            if (is_file($temporaryFile)) {
                @unlink($temporaryFile);
            }
        }
    }

    private function downloadBundle(string $destination): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->downloadWithWindowsCertificateStore($destination);

            return;
        }

        $response = Http::withOptions(['verify' => true])
            ->timeout(30)
            ->get(self::SOURCE_URL)
            ->throw();

        if (file_put_contents($destination, $response->body(), LOCK_EX) === false) {
            throw new RuntimeException('Could not write the downloaded CA bundle.');
        }
    }

    /**
     * Windows PowerShell validates HTTPS with the Windows certificate store,
     * which lets this command bootstrap PHP when PHP itself has no CA bundle.
     */
    private function downloadWithWindowsCertificateStore(string $destination): void
    {
        $quotedDestination = str_replace("'", "''", $destination);
        $script = "\$ErrorActionPreference = 'Stop'; \$ProgressPreference = 'SilentlyContinue'; "
            ."Invoke-WebRequest -UseBasicParsing -TimeoutSec 30 -Uri '".self::SOURCE_URL
            ."' -OutFile '".$quotedDestination."'";
        $encodedScript = base64_encode(mb_convert_encoding($script, 'UTF-16LE', 'UTF-8'));

        $process = new Process([
            'powershell.exe',
            '-NoProfile',
            '-NonInteractive',
            '-EncodedCommand',
            $encodedScript,
        ]);
        $process->setTimeout(45);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Windows PowerShell could not download the bundle using the Windows certificate store.');
        }
    }
}
