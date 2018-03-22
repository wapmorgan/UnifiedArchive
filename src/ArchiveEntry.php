<?php
namespace wapmorgan\UnifiedArchive;

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

    /**
     * ArchiveEntry constructor.
     * @param $path
     * @param $compressedSize
     * @param $uncompressedSize
     * @param $modificationTime
     * @param $isCompressed
     */
    public function __construct($path, $compressedSize, $uncompressedSize, $modificationTime, $isCompressed)
    {
        $this->path = $path;
        $this->compressedSize = $compressedSize;
        $this->uncompressedSize = $uncompressedSize;
        $this->modificationTime = $modificationTime;
        $this->isCompressed = $isCompressed;
    }
}