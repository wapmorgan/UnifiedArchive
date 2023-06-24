<?php
namespace wapmorgan\UnifiedArchive;

use wapmorgan\UnifiedArchive\Drivers\AlchemyZippy;
use wapmorgan\UnifiedArchive\Drivers\Basic\BasicDriver;
use wapmorgan\UnifiedArchive\Drivers\Cab;
use wapmorgan\UnifiedArchive\Drivers\Iso;
use wapmorgan\UnifiedArchive\Drivers\NelexaZip;
use wapmorgan\UnifiedArchive\Drivers\OneFile\Bzip;
use wapmorgan\UnifiedArchive\Drivers\OneFile\Gzip;
use wapmorgan\UnifiedArchive\Drivers\OneFile\Lzma;
use wapmorgan\UnifiedArchive\Drivers\Rar;
use wapmorgan\UnifiedArchive\Drivers\SevenZip;
use wapmorgan\UnifiedArchive\Drivers\SplitbrainPhpArchive;
use wapmorgan\UnifiedArchive\Drivers\TarByPear;
use wapmorgan\UnifiedArchive\Drivers\TarByPhar;
use wapmorgan\UnifiedArchive\Drivers\Zip;

class Formats
{
    // archived and compressed
    const ZIP = 'zip';
    const SEVEN_ZIP = '7z';
    const RAR = 'rar';
    const CAB = 'cab';
    const TAR = 'tar';
    const TAR_GZIP = 'tgz';
    const TAR_BZIP = 'tbz2';
    const TAR_LZMA = 'txz';
    const TAR_LZW = 'tar.z';
    const ARJ = 'arj';

    // compressed
    const GZIP = 'gz';
    const BZIP = 'bz2';
    const LZMA = 'xz';

    // non-usual archives
    const UEFI = 'uefi';
    const GPT = 'gpt';
    const MBR = 'mbr';
    const MSI = 'msi';
    const ISO = 'iso';
    const DMG = 'dmg';
    const UDF = 'udf';
    const RPM = 'rpm';
    const DEB = 'deb';

    /**
     * @var string[] List of archive format drivers.
     * Selection priority depends on placement in the list
     */
    public static $drivers = [
        Zip::class,
        Rar::class,
        Gzip::class,
        Bzip::class,
        Lzma::class,
        TarByPhar::class,
        SevenZip::class,
        AlchemyZippy::class,
        SplitbrainPhpArchive::class,
        NelexaZip::class,
        TarByPear::class,
        Iso::class,
        Cab::class,
    ];

    /** @var array<string, array<string>> List of all available types with their drivers */
    protected static $declaredDriversFormats;

    /** @var array List of all drivers with formats and support-state */
    protected static $supportedDriversFormats;

    protected static $oneLevelExtensions = [
        'zip' => Formats::ZIP,
        'jar' => Formats::ZIP,
        '7z' => Formats::SEVEN_ZIP,
        'rar' => Formats::RAR,
        'gz' => Formats::GZIP,
        'bz2' => Formats::BZIP,
        'xz' => Formats::LZMA,
        'iso' => Formats::ISO,
        'cab' => Formats::CAB,
        'tar' => Formats::TAR,
        'tgz' => Formats::TAR_GZIP,
        'tbz2' => Formats::TAR_BZIP,
        'txz' => Formats::TAR_LZMA,
        'arj' => Formats::ARJ,
        'efi' => Formats::UEFI,
        'gpt' => Formats::GPT,
        'mbr' => Formats::MBR,
        'msi' => Formats::MSI,
        'dmg' => Formats::DMG,
        'rpm' => Formats::RPM,
        'deb' => Formats::DEB,
        'udf' => Formats::UDF,
    ];

    protected static $twoLevelExtensions = [
        'tar.gz' => Formats::TAR_GZIP,
        'tar.bz2' => Formats::TAR_BZIP,
        'tar.xz' => Formats::TAR_LZMA,
        'tar.z' => Formats::TAR_LZW,
    ];

    protected static $mimeTypes = [
        'application/zip' => Formats::ZIP,
        'application/x-7z-compressed' => Formats::SEVEN_ZIP,
        'application/x-rar' => Formats::RAR,
        'application/zlib' => Formats::GZIP,
        'application/gzip'  => Formats::GZIP,
        'application/x-gzip' => Formats::GZIP,
        'application/x-bzip2' => Formats::BZIP,
        'application/x-lzma' => Formats::LZMA,
        'application/x-iso9660-image' => Formats::ISO,
        'application/vnd.ms-cab-compressed' => Formats::CAB,
        'application/x-tar' => Formats::TAR,
        'application/x-gtar' => Formats::TAR_GZIP,
    ];

    /**
     * Detect archive type by its filename or content
     *
     * @param string $originalFileName Archive filename
     * @param bool $contentCheck Whether archive type can be detected by content
     * @return null|bool One of UnifiedArchive type constants OR null if type is not detected
     */
    public static function detectArchiveFormat($originalFileName, $contentCheck = true)
    {
        $fileName = strtolower($originalFileName);

        // by file name
        $ld_offset = strrpos($fileName, '.');
        if ($ld_offset !== false) {
            $ext = substr($fileName, $ld_offset + 1);
            $sld_offset = strrpos($fileName, '.', - (strlen($ext) + 2)); // 1 byte for ., 1 for another char
            if ($sld_offset !== false) {
                $complex_ext = substr($fileName, $sld_offset + 1);
                if (isset(static::$twoLevelExtensions[$complex_ext])) {
                    return static::$twoLevelExtensions[$complex_ext];
                }
            }
            if (isset(static::$oneLevelExtensions[$ext])) {
                return static::$oneLevelExtensions[$ext];
            }
        }

        // by file content
        if ($contentCheck && function_exists('mime_content_type')) {
            $mime_type = mime_content_type($originalFileName);
            if ($mime_type !== false && isset(static::$mimeTypes[$mime_type])) {
                return static::$mimeTypes[$mime_type];
            }
        }

        return false;
    }

    /**
     * Checks whether specific archive type can be opened with current system configuration
     *
     * @param string $format One of predefined archive types (class constants)
     * @return bool
     */
    public static function canOpen($format)
    {
        return static::can($format, Abilities::OPEN);
    }

    /**
     * Checks whether specified archive can be streamed
     *
     * @param string $format One of predefined archive types (class constants)
     * @return bool
     */
    public static function canStream($format)
    {
        return static::can($format, Abilities::STREAM_CONTENT);
    }

    /**
     * Checks whether specified archive can be created
     *
     * @param string $format One of predefined archive types (class constants)
     * @return bool
     */
    public static function canCreate($format)
    {
        return static::can($format, Abilities::CREATE);
    }

    /**
     * Checks whether specified archive can be created
     *
     * @param string $format One of predefined archive types (class constants)
     * @return bool
     */
    public static function canAppend($format)
    {
        return static::can($format, Abilities::APPEND);
    }

    /**
     * Checks whether specified archive can be created
     *
     * @param string $format One of predefined archive types (class constants)
     * @return bool
     */
    public static function canUpdate($format)
    {
        return static::can($format, Abilities::DELETE);
    }

    /**
     * Checks whether specified archive can be created
     *
     * @param string $format One of predefined archive types (class constants)
     * @return bool
     */
    public static function canEncrypt($format)
    {
        return static::can($format, Abilities::CREATE_ENCRYPTED);
    }

    /**
     * @param $format
     * @return void
     */
    protected static function getFormatSupportStatus($format)
    {
        static::getAllPossibleFormatsAndDrivers();
        if (!isset(static::$supportedDriversFormats[$format])) {
            static::$supportedDriversFormats[$format] = [];
            if (!isset(static::$declaredDriversFormats[$format])) {
                return;
            }
            /** @var BasicDriver $format_driver */
            foreach (static::$declaredDriversFormats[$format] as $format_driver) {
                static::$supportedDriversFormats[$format][$format_driver] = $format_driver::getFormatAbilities($format);
            }
        }
    }

    /**
     * @param string $format
     * @param string $ability
     * @return bool
     */
    public static function can($format, $ability)
    {
        self::getFormatSupportStatus($format);
        foreach (static::$supportedDriversFormats[$format] as $driver => $driver_abilities) {
            if (in_array($ability, $driver_abilities, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $format
     * @param int[] $abilities
     * @return string|null
     */
    public static function getFormatDriver($format, array $abilities = [])
    {
        self::getFormatSupportStatus($format);
        foreach (static::$supportedDriversFormats[$format] as $driver => $driver_abilities) {
            if (count(array_intersect($driver_abilities, $abilities)) === count($abilities)) {
                return $driver;
            }
        }

        return null;
    }

    /**
     * @param string $archiveFormat
     * @return int|string|null
     */
    public static function getFormatExtension($archiveFormat)
    {
        return
            array_search($archiveFormat, static::$twoLevelExtensions)
            ?: array_search($archiveFormat, static::$oneLevelExtensions)
            ?: null;
    }

    /**
     * @param $format
     * @return null|string
     */
    public static function getFormatMimeType($format)
    {
        return array_search($format, static::$mimeTypes, true)
            ?: null;
    }

    public static function getDeclaredDriverFormats()
    {
        static::getAllPossibleFormatsAndDrivers();
        return static::$declaredDriversFormats;
    }

    public static function getSupportedDriverFormats()
    {
        foreach (self::getDeclaredDriverFormats() as $format => $formatDrivers) {
            self::getFormatSupportStatus($format);
        }
        return static::$supportedDriversFormats;
    }

    /**
     * Tests system configuration
     */
    protected static function getAllPossibleFormatsAndDrivers()
    {
        if (static::$declaredDriversFormats === null) {
            static::$declaredDriversFormats = [];
            foreach (static::$drivers as $handlerClass)
            {
                $handler_formats = $handlerClass::getFormats();
                foreach ($handler_formats as $handler_format)
                {
                    static::$declaredDriversFormats[$handler_format][] = $handlerClass;
                }
            }
        }
    }

    /**
     * @param string $format
     * @param string $ability
     * @return bool
     * @deprecated Use {{Formats::can}} instead
     */
    public static function checkFormatSupportAbility($format, $ability)
    {
        return static::can($format, $ability);
    }
}
