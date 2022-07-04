<?php
namespace wapmorgan\UnifiedArchive;

use wapmorgan\UnifiedArchive\Drivers\AlchemyZippy;
use wapmorgan\UnifiedArchive\Drivers\BasicDriver;
use wapmorgan\UnifiedArchive\Drivers\Cab;
use wapmorgan\UnifiedArchive\Drivers\Iso;
use wapmorgan\UnifiedArchive\Drivers\NelexaZip;
use wapmorgan\UnifiedArchive\Drivers\OneFile\Bzip;
use wapmorgan\UnifiedArchive\Drivers\OneFile\Gzip;
use wapmorgan\UnifiedArchive\Drivers\OneFile\Lzma;
use wapmorgan\UnifiedArchive\Drivers\Rar;
use wapmorgan\UnifiedArchive\Drivers\SevenZip;
use wapmorgan\UnifiedArchive\Drivers\TarByPear;
use wapmorgan\UnifiedArchive\Drivers\TarByPhar;
use wapmorgan\UnifiedArchive\Drivers\Zip;
use wapmorgan\UnifiedArchive\Exceptions\UnsupportedArchiveException;
use wapmorgan\UnifiedArchive\Formats\Tar;

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
     * @var string[] List of archive format drivers
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
        NelexaZip::class,
        TarByPear::class,
        Iso::class,
        Cab::class,
    ];

    /** @var array<string, array<string>> List of all available types with their drivers */
    protected static $declaredDriversFormats;

    /** @var array List of all drivers with formats and support-state */
    protected static $supportedDriversFormats;

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
     * @param string $fileName Archive filename
     * @param bool $contentCheck Whether archive type can be detected by content
     * @return string|bool One of UnifiedArchive type constants OR false if type is not detected
     */
    public static function detectArchiveFormat($fileName, $contentCheck = true)
    {
        // by file name
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (stripos($fileName, '.tar.') !== false && preg_match('~\.(?<ext>tar\.(gz|bz2|xz|z))$~', strtolower($fileName), $match)) {
            switch ($match['ext']) {
                case 'tar.gz':
                    return Formats::TAR_GZIP;
                case 'tar.bz2':
                    return Formats::TAR_BZIP;
                case 'tar.xz':
                    return Formats::TAR_LZMA;
                case 'tar.z':
                    return Formats::TAR_LZW;
            }
        }

        switch ($ext) {
            case 'zip':
            case 'jar':
                return Formats::ZIP;
            case '7z':
                return Formats::SEVEN_ZIP;
            case 'rar':
                return Formats::RAR;
            case 'gz':
                return Formats::GZIP;
            case 'bz2':
                return Formats::BZIP;
            case 'xz':
                return Formats::LZMA;
            case 'iso':
                return Formats::ISO;
            case 'cab':
                return Formats::CAB;
            case 'tar':
                return Formats::TAR;
            case 'tgz':
                return Formats::TAR_GZIP;
            case 'tbz2':
                return Formats::TAR_BZIP;
            case 'txz':
                return Formats::TAR_LZMA;
            case 'arj':
                return Formats::ARJ;
            case 'efi':
                return Formats::UEFI;
            case 'gpt':
                return Formats::GPT;
            case 'mbr':
                return Formats::MBR;
            case 'msi':
                return Formats::MSI;
            case 'dmg':
                return Formats::DMG;
            case 'rpm':
                return Formats::RPM;
            case 'deb':
                return Formats::DEB;
            case 'udf':
                return Formats::UDF;
        }

        // by file content
        if ($contentCheck) {
            $mime_type = mime_content_type($fileName);
            if (isset(static::$mimeTypes[$mime_type]))
                return static::$mimeTypes[$mime_type];
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
        return static::checkFormatSupportAbility($format, BasicDriver::OPEN);
    }

    /**
     * Checks whether specified archive can be streamed
     *
     * @param string $format One of predefined archive types (class constants)
     * @return bool
     */
    public static function canStream($format)
    {
        return static::checkFormatSupportAbility($format, BasicDriver::STREAM_CONTENT);
    }

    /**
     * Checks whether specified archive can be created
     *
     * @param string $format One of predefined archive types (class constants)
     * @return bool
     */
    public static function canCreate($format)
    {
        return static::checkFormatSupportAbility($format, BasicDriver::CREATE);
    }

    /**
     * Checks whether specified archive can be created
     *
     * @param string $format One of predefined archive types (class constants)
     * @return bool
     */
    public static function canAppend($format)
    {
        return static::checkFormatSupportAbility($format, BasicDriver::APPEND);
    }

    /**
     * Checks whether specified archive can be created
     *
     * @param string $format One of predefined archive types (class constants)
     * @return bool
     */
    public static function canUpdate($format)
    {
        return static::checkFormatSupportAbility($format, BasicDriver::DELETE);
    }

    /**
     * Checks whether specified archive can be created
     *
     * @param string $format One of predefined archive types (class constants)
     * @return bool
     */
    public static function canEncrypt($format)
    {
        return static::checkFormatSupportAbility($format, BasicDriver::CREATE_ENCRYPTED);
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
                static::$supportedDriversFormats[$format][$format_driver] = $format_driver::checkFormatSupport($format);
            }
        }
    }

    /**
     * @param string $format
     * @param string $ability
     * @return bool
     */
    public static function checkFormatSupportAbility($format, $ability)
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
     * @return int|string|null
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
     * @param array $ints
     * @return int|mixed
     */
    protected static function flatArrayToInt(array $ints)
    {
        $result = 0;
        foreach ($ints as $int) {
            $result |= $int;
        }
        return $result;
    }

    /**
     * @param $format
     * @return false|int|string
     */
    public static function getFormatMimeType($format)
    {
        return array_search($format, static::$mimeTypes, true);
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
                $handler_formats = $handlerClass::getSupportedFormats();
                foreach ($handler_formats as $handler_format)
                {
                    static::$declaredDriversFormats[$handler_format][] = $handlerClass;
                }
            }
        }
    }
}