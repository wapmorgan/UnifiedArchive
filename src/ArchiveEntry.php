<?php

namespace wapmorgan\UnifiedArchive;

/**
 * Information class. Represent information about concrete file in archive.
 *
 * @package wapmorgan\UnifiedArchive
 */
class ArchiveEntry
{
    /** @var string Path of archive entry */
    public $path;
    /** @var int Size of packed entry in bytes */
    public $compressedSize;
    /** @var int Size of unpacked entry in bytes */
    public $uncompressedSize;
    /** @var int Time of entry modification in unix timestamp format. */
    public $modificationTime;
    /** @var bool */
    public $isCompressed;
    /** @var string Comment */
    public $comment;
    /** @var string|null Control check summ */
    public $crc32;

    /**
     * ArchiveEntry constructor.
     * @param string $path
     * @param int $compressedSize
     * @param int $uncompressedSize
     * @param int $modificationTime
     * @param bool|null $isCompressed
     * @param string|null $comment
     * @param string|null $crc32
     */
    public function __construct(
        $path,
        $compressedSize,
        $uncompressedSize,
        $modificationTime,
        $isCompressed = null,
        $comment = null,
        $crc32 = null)
    {
        $this->path = $path;
        $this->compressedSize = $compressedSize;
        $this->uncompressedSize = $uncompressedSize;
        $this->modificationTime = $modificationTime;
        if ($isCompressed === null)
            $isCompressed = $uncompressedSize !== $compressedSize;
        $this->isCompressed = $isCompressed;
        $this->comment = $comment;
        $this->crc32 = $crc32;
    }
}
