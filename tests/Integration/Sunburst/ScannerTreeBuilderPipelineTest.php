<?php
declare(strict_types=1);

namespace Sunburstfs\Tests\Integration\Sunburst;

use PHPUnit\Framework\TestCase;
use Sunburstfs\DuDiskUsageScanner;
use Sunburstfs\SunburstTreeBuilder;
use Sunburstfs\TimeoutBoundedProcess;

/**
 * Outside-in contract test: real DuDiskUsageScanner (shells out to the
 * real `du` binary) feeding a real SunburstTreeBuilder, against a real
 * fixture directory tree with known file sizes. Nothing here is mocked
 * -- this is what Phase 2's unit tests (mocking the scanner interface)
 * are ultimately in service of making true.
 */
final class ScannerTreeBuilderPipelineTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = tempnam(sys_get_temp_dir(), "sunburstfs-fixture-");
        unlink($this->root);
        mkdir($this->root);
        mkdir($this->root . "/sub1");
        mkdir($this->root . "/sub2");

        file_put_contents($this->root . "/root-a.bin", str_repeat("a", 1000));
        file_put_contents($this->root . "/root-b.bin", str_repeat("b", 500));
        file_put_contents($this->root . "/sub1/sub1-a.bin", str_repeat("c", 400));
        file_put_contents($this->root . "/sub1/sub1-b.bin", str_repeat("d", 150));
        file_put_contents($this->root . "/sub2/sub2-a.bin", str_repeat("e", 300));
    }

    protected function tearDown(): void
    {
        exec("rm -rf " . escapeshellarg($this->root));
    }

    public function testBuildsExpectedTreeFromRealFixtureDirectory(): void
    {
        $scanner = new DuDiskUsageScanner(new TimeoutBoundedProcess(timeoutSeconds: 30));
        // maxDepth isn't a builder concern: it only ever sees what the
        // scanner already limited, and recurses by matching immediate
        // children in that data until none are left.
        $builder = new SunburstTreeBuilder(topN: 12);

        $sizesByPath = $scanner->scan($this->root, 2);
        $tree = $builder->build($this->root, $sizesByPath);

        // Root: two real subdirectories (sub1 has no sub-subdirs, so it's
        // a leaf; sub2 likewise) plus a synthetic "(files)" node for the
        // loose files sitting directly in root. Sorted descending by
        // size: files(1500) > sub1(550) > sub2(300).
        self::assertSame(basename($this->root), $tree["name"]);
        self::assertSame($this->root, $tree["path"]);
        self::assertSame(2350, $tree["size"]);
        self::assertSame("dir", $tree["type"]);
        self::assertCount(3, $tree["children"]);

        [$filesNode, $sub1Node, $sub2Node] = $tree["children"];

        self::assertSame("(files)", $filesNode["name"]);
        self::assertSame("file", $filesNode["type"]);
        self::assertSame(1500, $filesNode["size"]);
        self::assertSame([], $filesNode["children"]);

        self::assertSame("sub1", $sub1Node["name"]);
        self::assertSame("dir", $sub1Node["type"]);
        self::assertSame(550, $sub1Node["size"]);
        self::assertSame([], $sub1Node["children"]);

        self::assertSame("sub2", $sub2Node["name"]);
        self::assertSame("dir", $sub2Node["type"]);
        self::assertSame(300, $sub2Node["size"]);
        self::assertSame([], $sub2Node["children"]);
    }
}
