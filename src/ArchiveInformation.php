<?php
namespace wapmorgan\UnifiedArchive;

/**
 * Information class. Represent information about the whole archive.
 *
 * @package wapmorgan\UnifiedArchive
 */
class ArchiveInformation
{
    /** @var array List of files inside archive */
    public $files = [];

    /** @var int Size of files inside archive in bytes */
    public $compressedFilesSize = 0;

    /** @var int Original size of files inside archive in bytes */
    public $uncompressedFilesSize = 0;
}