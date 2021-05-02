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

    /**
     * ArchiveEntry constructor.
     * @param $path
     * @param $compressedSize
     * @param $uncompressedSize
     * @param $modificationTime
     * @param $isCompressed
     * @param null $comment
     */
    public function __construct($path, $compressedSize, $uncompressedSize, $modificationTime, $isCompressed = null, $comment = null)
    {
        $this->path = $path;
        $this->compressedSize = $compressedSize;
        $this->uncompressedSize = $uncompressedSize;
        $this->modificationTime = $modificationTime;
        if ($isCompressed === null)
            $isCompressed = $uncompressedSize !== $compressedSize;
        $this->isCompressed = $isCompressed;
        $this->comment = $comment;
    }
}