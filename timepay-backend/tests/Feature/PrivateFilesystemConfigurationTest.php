<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivateFilesystemConfigurationTest extends TestCase
{
    public function test_private_disk_can_use_a_configured_persistent_mount_root(): void
    {
        $root = storage_path('framework/testing/private-disk-root');
        File::deleteDirectory($root);

        config(['filesystems.disks.private.root' => $root]);
        Storage::purge('private');

        try {
            Storage::disk('private')->put('selfies/storage-probe.txt', 'private-storage-ok');

            $this->assertFileExists($root . '/selfies/storage-probe.txt');
            $this->assertSame(
                'private-storage-ok',
                Storage::disk('private')->get('selfies/storage-probe.txt')
            );
        } finally {
            Storage::disk('private')->delete('selfies/storage-probe.txt');
            Storage::purge('private');
            File::deleteDirectory($root);
        }
    }
}
