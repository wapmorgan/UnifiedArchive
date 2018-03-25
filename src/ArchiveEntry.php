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
     * @var string Path of archive entry
     * @deprecated 0.1.0
     * @see $path property
     */
    public $filename;

    /**
     * @var int Size of packed entry in bytes
     * @deprecated 0.1.0
     * @see $compressedSize property
     */
    public $compressed_size;

    /**
     * @var int Size of unpacked entry in bytes
     * @deprecated 0.1.0
     * @see $uncompressedSize property
     */
    public $uncompressed_size;

    /**
     * @var int Time of entry modification in unix timestamp format.
     * @deprecated 0.1.0
     * @see $modificationTime property
     */
    public $mtime;

    /**
     * @var bool
     * @deprecated 0.1.0
     * @see $isCompressed property
     */
    public $is_compressed;

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
        $this->filename = $this->path = $path;
        $this->compressed_size = $this->compressedSize = $compressedSize;
        $this->uncompressed_size = $this->uncompressedSize = $uncompressedSize;
        $this->mtime = $this->modificationTime = $modificationTime;
        $this->is_compressed = $this->isCompressed = $isCompressed;
    }
}