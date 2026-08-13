<?php
declare(strict_types=1);

namespace Sunburstfs\Tests\Unit\Sunburst;

use PHPUnit\Framework\TestCase;
use Sunburstfs\StalestFilesystemPicker;

/**
 * Confirmed by testing (a real, serious incident): refreshing every
 * mounted filesystem on a fixed short timer, regardless of how long
 * any individual scan takes, produced sustained background `du`
 * activity that coincided with a memory exhaustion crash on a NAS
 * already running close to its memory ceiling from its own other
 * services. Scanning one filesystem per tick -- whichever has gone
 * longest without a fresh chart -- spreads the same total work out
 * instead of compounding it.
 */
final class StalestFilesystemPickerTest extends TestCase
{
    private function fs(string $uuid): array
    {
        return ["uuid" => $uuid, "mountpoint" => "/srv/$uuid"];
    }

    public function testReturnsNullForAnEmptyList(): void
    {
        $picker = new StalestFilesystemPicker(static fn (string $uuid): ?int => null);

        self::assertNull($picker->pick([]));
    }

    public function testReturnsTheOnlyFilesystemWhenThereIsOnlyOne(): void
    {
        $picker = new StalestFilesystemPicker(static fn (string $uuid): ?int => 1000);

        self::assertSame("a", $picker->pick([$this->fs("a")])["uuid"]);
    }

    public function testPicksTheFilesystemWithTheOldestCacheMtime(): void
    {
        $mtimes = ["a" => 3000, "b" => 1000, "c" => 2000];
        $picker = new StalestFilesystemPicker(static fn (string $uuid) => $mtimes[$uuid]);

        $result = $picker->pick([$this->fs("a"), $this->fs("b"), $this->fs("c")]);

        self::assertSame("b", $result["uuid"]);
    }

    public function testAFilesystemThatHasNeverBeenGeneratedIsMoreStaleThanAnyRealMtime(): void
    {
        // null = no cache file exists yet, regardless of how old any
        // other filesystem's real (however ancient) mtime is.
        $mtimes = ["a" => 1, "b" => null];
        $picker = new StalestFilesystemPicker(static fn (string $uuid) => $mtimes[$uuid]);

        $result = $picker->pick([$this->fs("a"), $this->fs("b")]);

        self::assertSame("b", $result["uuid"]);
    }

    public function testTiesAreBrokenByPreferringTheFirstEncountered(): void
    {
        $picker = new StalestFilesystemPicker(static fn (string $uuid): ?int => 500);

        $result = $picker->pick([$this->fs("a"), $this->fs("b")]);

        self::assertSame("a", $result["uuid"]);
    }
}
