<?php
declare(strict_types=1);

namespace Sunburstfs\Tests\Unit\Sunburst;

use PHPUnit\Framework\TestCase;
use Sunburstfs\ManagedFilesystemFilter;

/**
 * Confirmed by testing (two real system crashes): the OS root
 * filesystem can contain directory structures (e.g. Docker's
 * per-layer storage under /var/lib/docker) large enough in entry
 * count -- not necessarily in bytes used -- to exhaust system memory
 * when scanned, and it was always picked first by
 * StalestFilesystemPicker since it had never been successfully
 * cached. It's also simply not useful for this plugin's actual
 * purpose (finding what's safe to move off a user's data disks, not
 * the OS drive).
 */
final class ManagedFilesystemFilterTest extends TestCase
{
    public function testExcludesTheRootFilesystem(): void
    {
        $filesystems = [
            ["uuid" => "a", "mountpoint" => "/"],
            ["uuid" => "b", "mountpoint" => "/srv/dev-disk-by-uuid-b"],
        ];

        $result = ManagedFilesystemFilter::excludingRoot($filesystems);

        self::assertCount(1, $result);
        self::assertSame("b", $result[0]["uuid"]);
    }

    public function testLeavesNonRootFilesystemsUntouchedWhenNoRootIsPresent(): void
    {
        $filesystems = [
            ["uuid" => "a", "mountpoint" => "/srv/a"],
            ["uuid" => "b", "mountpoint" => "/srv/b"],
        ];

        self::assertSame($filesystems, ManagedFilesystemFilter::excludingRoot($filesystems));
    }

    public function testReturnsEmptyArrayWhenTheOnlyFilesystemIsRoot(): void
    {
        self::assertSame([], ManagedFilesystemFilter::excludingRoot([["uuid" => "a", "mountpoint" => "/"]]));
    }
}
