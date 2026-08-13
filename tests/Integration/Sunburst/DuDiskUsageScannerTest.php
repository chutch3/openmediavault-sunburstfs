<?php
declare(strict_types=1);

namespace Sunburstfs\Tests\Integration\Sunburst;

use PHPUnit\Framework\TestCase;
use Sunburstfs\DuDiskUsageScanner;
use Sunburstfs\TimeoutBoundedProcess;

final class DuDiskUsageScannerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = tempnam(sys_get_temp_dir(), "sunburstfs-scanner-");
        unlink($this->root);
        mkdir($this->root);
    }

    private function scanner(): DuDiskUsageScanner
    {
        return new DuDiskUsageScanner(new TimeoutBoundedProcess(timeoutSeconds: 30));
    }

    protected function tearDown(): void
    {
        // A permission-denied test directory can't be removed by
        // `rm -rf` while still locked down.
        exec("chmod -R u+rwx " . escapeshellarg($this->root));
        exec("rm -rf " . escapeshellarg($this->root));
    }

    public function testEmptyDirectoryReportsZero(): void
    {
        $sizes = $this->scanner()->scan($this->root, 1);

        self::assertSame(0, $sizes[$this->root]);
    }

    public function testUnreadableSubdirectoryReportsZeroWithoutFailingTheWholeScan(): void
    {
        mkdir($this->root . "/locked");
        file_put_contents($this->root . "/locked/secret.bin", str_repeat("x", 100));
        file_put_contents($this->root . "/visible.bin", str_repeat("y", 50));
        chmod($this->root . "/locked", 0000);

        $sizes = $this->scanner()->scan($this->root, 1);

        // The locked subdirectory still appears (at 0, not omitted --
        // verified real `du` behaviour), and the root total reflects
        // only what could actually be read (50), not the hidden 100.
        self::assertSame(0, $sizes[$this->root . "/locked"]);
        self::assertSame(50, $sizes[$this->root]);
    }

    public function testSymlinkToExternalDirectoryIsNotFollowed(): void
    {
        $external = tempnam(sys_get_temp_dir(), "sunburstfs-external-");
        unlink($external);
        mkdir($external);
        file_put_contents($external . "/big.bin", str_repeat("z", 10_000));

        symlink($external, $this->root . "/link");
        file_put_contents($this->root . "/normal.bin", str_repeat("n", 200));

        $sizes = $this->scanner()->scan($this->root, 1);

        // If the symlink were followed, root's total would be >= 10,200.
        // It should instead be just the real file plus the symlink's
        // own tiny apparent size (the length of the target path string).
        self::assertLessThan(1000, $sizes[$this->root]);
        self::assertGreaterThanOrEqual(200, $sizes[$this->root]);

        exec("rm -rf " . escapeshellarg($external));
    }

    public function testDirectoryEntryCapTruncatesOutputAndAlwaysDropsTheRootLine(): void
    {
        // du prints deepest-first, so root's own summary line is
        // always last (confirmed by testing the ordering directly) --
        // capping to fewer entries than exist must always drop it,
        // regardless of which specific subdirectories survive.
        for ($i = 0; $i < 5; $i++) {
            mkdir("{$this->root}/sub{$i}");
            file_put_contents("{$this->root}/sub{$i}/file.bin", "x");
        }

        $scanner = new DuDiskUsageScanner(new TimeoutBoundedProcess(timeoutSeconds: 30), maxDirectoryEntries: 2);
        $sizes = $scanner->scan($this->root, 1);

        self::assertCount(2, $sizes);
        self::assertArrayNotHasKey($this->root, $sizes);
    }
}
