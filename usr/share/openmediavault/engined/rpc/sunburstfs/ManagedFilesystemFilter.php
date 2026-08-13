<?php
declare(strict_types=1);

namespace Sunburstfs;

/**
 * Excludes the OS root filesystem from a list of filesystems.
 * Confirmed by testing (two real system crashes): root can contain
 * directory structures (e.g. Docker's per-layer storage under
 * /var/lib/docker) large enough in entry count -- not necessarily in
 * bytes used -- to exhaust system memory when scanned, and it was
 * always picked first by StalestFilesystemPicker since it had never
 * been successfully cached. It's also simply not this plugin's
 * purpose: finding what's safe to move off a user's data disks, not
 * charting the OS drive.
 */
final class ManagedFilesystemFilter
{
    /**
     * @param array<int, array{uuid: string, mountpoint: string}> $filesystems
     * @return array<int, array{uuid: string, mountpoint: string}>
     */
    public static function excludingRoot(array $filesystems): array
    {
        return array_values(array_filter(
            $filesystems,
            static fn (array $filesystem): bool => $filesystem["mountpoint"] !== "/"
        ));
    }
}
