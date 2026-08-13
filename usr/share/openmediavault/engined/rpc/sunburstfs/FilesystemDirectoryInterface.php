<?php
declare(strict_types=1);

namespace Sunburstfs;

interface FilesystemDirectoryInterface
{
    /**
     * @return array<int, array{uuid: string, mountpoint: string}>
     */
    public function listMounted(): array;
}
