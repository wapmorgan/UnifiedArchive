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

    /**
     * @var string Path of archive entry
     * @deprecated 0.1.0
     * @see $path property
     */
    public $filename;

    /**
     * ArchiveEntry constructor.
     * @param $path
     * @param $compressedSize
     * @param $uncompressedSize
     * @param $modificationTime
     * @param $isCompressed
     */
    public function __construct($path, $compressedSize, $uncompressedSize, $modificationTime, $isCompressed = null)
    {
        $this->path = $path;
        $this->compressedSize = $compressedSize;
        $this->uncompressedSize = $uncompressedSize;
        $this->modificationTime = $modificationTime;
        if ($isCompressed === null)
            $isCompressed = $uncompressedSize !== $compressedSize;
        $this->isCompressed = $isCompressed;
    }
}