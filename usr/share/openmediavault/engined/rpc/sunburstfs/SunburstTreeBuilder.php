<?php
declare(strict_types=1);

namespace Sunburstfs;

/**
 * Turns a flat {@see DiskUsageScannerInterface::scan()} result into a
 * nested tree: {name, path, size, type, children[]}, type one of
 * 'dir' | 'file' | 'other'.
 *
 * Pure function of its input array -- no filesystem access, no `du`,
 * nothing to mock to unit test it.
 */
final class SunburstTreeBuilder
{
    public function __construct(private readonly int $topN = 12)
    {
    }

    /**
     * @param array<string, int> $sizesByPath as returned by
     *   DiskUsageScannerInterface::scan()
     */
    public function build(string $rootPath, array $sizesByPath): array
    {
        if (!array_key_exists($rootPath, $sizesByPath)) {
            throw new \InvalidArgumentException(sprintf(
                "Root path '%s' is not present in the scan results.",
                $rootPath
            ));
        }

        return $this->buildNode($rootPath, $sizesByPath);
    }

    /**
     * @param array<string, int> $sizesByPath
     */
    private function buildNode(string $path, array $sizesByPath): array
    {
        $size = $sizesByPath[$path];
        $immediateChildren = $this->findImmediateChildren($path, $sizesByPath);

        if ($immediateChildren === []) {
            // No finer data below this path -- a leaf is more honest
            // than fabricating a "(files)" child at 100% of its size.
            return $this->leaf(basename($path), $path, $size, "dir");
        }

        $children = [];
        $childSizeSum = 0;
        foreach ($immediateChildren as $childPath => $childSize) {
            $children[] = $this->buildNode($childPath, $sizesByPath);
            $childSizeSum += $childSize;
        }

        // Remainder after subdirectories = loose files (symlinks'
        // small apparent size falls in here too, since `du` doesn't
        // follow them).
        $looseFilesSize = $size - $childSizeSum;
        if ($looseFilesSize > 0) {
            $children[] = $this->leaf("(files)", $path . "/(files)", $looseFilesSize, "file");
        }

        return [
            "name" => basename($path),
            "path" => $path,
            "size" => $size,
            "type" => "dir",
            "children" => $this->bucketAndSort($children),
        ];
    }

    /**
     * @param array<string, int> $sizesByPath
     * @return array<string, int> immediate children of $path only, not
     *   grandchildren or deeper
     */
    private function findImmediateChildren(string $path, array $sizesByPath): array
    {
        $prefix = rtrim($path, "/") . "/";
        $children = [];
        foreach ($sizesByPath as $candidatePath => $size) {
            if (!str_starts_with($candidatePath, $prefix)) {
                continue;
            }
            $remainder = substr($candidatePath, strlen($prefix));
            if (str_contains($remainder, "/")) {
                continue;
            }
            $children[$candidatePath] = $size;
        }

        return $children;
    }

    /**
     * Sorts children by size descending, and once there are more than
     * $topN of them, collapses the smallest into a single synthetic
     * "(other)" node so a directory with hundreds of small entries
     * doesn't produce hundreds of imperceptibly thin wedges.
     */
    private function bucketAndSort(array $children): array
    {
        usort($children, static fn (array $a, array $b): int => $b["size"] <=> $a["size"]);

        if (count($children) <= $this->topN) {
            return $children;
        }

        $kept = array_slice($children, 0, $this->topN);
        $rest = array_slice($children, $this->topN);
        $otherSize = array_sum(array_column($rest, "size"));

        $kept[] = $this->leaf("(other)", "", $otherSize, "other");
        usort($kept, static fn (array $a, array $b): int => $b["size"] <=> $a["size"]);

        return $kept;
    }

    private function leaf(string $name, string $path, int $size, string $type): array
    {
        return [
            "name" => $name,
            "path" => $path,
            "size" => $size,
            "type" => $type,
            "children" => [],
        ];
    }
}
