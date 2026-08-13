<?php
declare(strict_types=1);

namespace Sunburstfs\Tests\Unit\Sunburst;

use PHPUnit\Framework\TestCase;
use Sunburstfs\SunburstTreeBuilder;

final class SunburstTreeBuilderTest extends TestCase
{
    public function testEmptyDirectoryIsALeafWithZeroSize(): void
    {
        $tree = (new SunburstTreeBuilder())->build("/root", ["/root" => 0]);

        self::assertSame("dir", $tree["type"]);
        self::assertSame(0, $tree["size"]);
        self::assertSame([], $tree["children"]);
    }

    public function testDirectoryWithOnlyLooseFilesAndNoSubdirectoriesIsALeaf(): void
    {
        // No entries for anything under /root in the scan results --
        // either it genuinely has no subdirectories, or the scan's
        // depth limit stopped here. Either way there's no finer-grained
        // data to build children from, so a leaf is correct, not a
        // fabricated single "(files)" child at 100% of the size.
        $tree = (new SunburstTreeBuilder())->build("/root", ["/root" => 900_000]);

        self::assertSame(900_000, $tree["size"]);
        self::assertSame([], $tree["children"]);
    }

    public function testNestedSubdirectoriesRecurseThroughMultipleLevels(): void
    {
        $sizesByPath = [
            "/root" => 1000,
            "/root/a" => 700,
            "/root/a/x" => 500,
        ];

        $tree = (new SunburstTreeBuilder())->build("/root", $sizesByPath);

        self::assertCount(2, $tree["children"]); // "a" and the loose-files leaf
        [$aNode, $filesNode] = $tree["children"];

        self::assertSame("a", $aNode["name"]);
        self::assertSame(700, $aNode["size"]);
        self::assertSame("(files)", $filesNode["name"]);
        self::assertSame(300, $filesNode["size"]); // 1000 - 700

        // "a" itself recursed correctly: one real child "x" plus its
        // own loose-files remainder.
        self::assertCount(2, $aNode["children"]);
        [$xNode, $aFilesNode] = $aNode["children"];
        self::assertSame("x", $xNode["name"]);
        self::assertSame(500, $xNode["size"]);
        self::assertSame("(files)", $aFilesNode["name"]);
        self::assertSame(200, $aFilesNode["size"]); // 700 - 500
    }

    public function testLooseFilesNodeOmittedWhenChildrenAccountForEntireSize(): void
    {
        $sizesByPath = [
            "/root" => 1000,
            "/root/a" => 600,
            "/root/b" => 400,
        ];

        $tree = (new SunburstTreeBuilder())->build("/root", $sizesByPath);

        self::assertCount(2, $tree["children"]); // no spurious 0-byte "(files)" node
        self::assertSame(["a", "b"], array_column($tree["children"], "name"));
    }

    public function testBucketsExcessChildrenIntoSingleOtherNodeAndResortsByCombinedSize(): void
    {
        // topN=2: "c", "d", "e" get bucketed. Their combined size (75)
        // exceeds either individually-kept child, so the resort after
        // bucketing must place "(other)" first, not last.
        $sizesByPath = [
            "/root" => 165,
            "/root/a" => 50,
            "/root/b" => 40,
            "/root/c" => 30,
            "/root/d" => 25,
            "/root/e" => 20,
        ];

        $tree = (new SunburstTreeBuilder(topN: 2))->build("/root", $sizesByPath);

        self::assertCount(3, $tree["children"]);
        self::assertSame(
            [["other", 75], ["a", 50], ["b", 40]],
            array_map(
                static fn (array $n): array => [$n["type"] === "other" ? "other" : $n["name"], $n["size"]],
                $tree["children"]
            )
        );
    }

    public function testThrowsWhenRootPathMissingFromScanResults(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new SunburstTreeBuilder())->build("/root", ["/somewhere-else" => 100]);
    }
}
