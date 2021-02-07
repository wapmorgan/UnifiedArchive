<?php
namespace wapmorgan\UnifiedArchive;

use wapmorgan\UnifiedArchive\Exceptions\UnsupportedArchiveException;
use wapmorgan\UnifiedArchive\Exceptions\UnsupportedOperationException;
use wapmorgan\UnifiedArchive\Formats\AlchemyZippy;
use wapmorgan\UnifiedArchive\Formats\Cab;
use wapmorgan\UnifiedArchive\Formats\Iso;
use wapmorgan\UnifiedArchive\Formats\OneFile\Gzip;
use wapmorgan\UnifiedArchive\Formats\OneFile\Lzma;
use wapmorgan\UnifiedArchive\Formats\OneFile\Bzip;
use wapmorgan\UnifiedArchive\Formats\Rar;
use wapmorgan\UnifiedArchive\Formats\SevenZip;
use wapmorgan\UnifiedArchive\Formats\Tar;
use wapmorgan\UnifiedArchive\Formats\TarByPear;
use wapmorgan\UnifiedArchive\Formats\TarByPhar;
use wapmorgan\UnifiedArchive\Formats\Zip;

class Formats
{
    // archived and compressed
    const ZIP = 'zip';
    const SEVEN_ZIP = '7zip';
    const RAR = 'rar';
    const ISO = 'iso';
    const CAB = 'cab';
    const TAR = 'tar';
    const TAR_GZIP = 'tgz';
    const TAR_BZIP = 'tbz2';
    const TAR_LZMA = 'txz';
    const TAR_LZW = 'tar.z';
    const ARJ = 'arj';

    // compressed
    const GZIP = 'gzip';
    const BZIP = 'bzip2';
    const LZMA = 'lzma2';
    const XZ = 'xz';

    // non-usual archives
    const UEFI = 'uefi';
    const GPT = 'gpt';
    const MBR = 'mbr';
    const MSI = 'msi';
    const DMG = 'dmg';
    const RPM = 'rpm';
    const UDF = 'udf';

    /**
     * @var string[] List of archive format drivers
     */
    public static $drivers = [
        Zip::class,
        Rar::class,
        Gzip::class,
        Bzip::class,
        Lzma::class,
        Iso::class,
        Cab::class,
        TarByPhar::class,
        SevenZip::class,
        AlchemyZippy::class,
        TarByPear::class,
    ];

    /** @var array List of all available types */
    static protected $availableFormats;

    /** @var array List of all drivers with formats and support-state */
    static protected $formatsSupport;

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
        }

        // by file content
        if ($contentCheck) {
            $mime_type = mime_content_type($fileName);
            switch ($mime_type) {
                case 'application/zip':
                    return Formats::ZIP;
                case 'application/x-7z-compressed':
                    return Formats::SEVEN_ZIP;
                case 'application/x-rar':
                    return Formats::RAR;
                case 'application/zlib':
                case 'application/gzip':
                case 'application/x-gzip':
                    return Formats::GZIP;
                case 'application/x-bzip2':
                    return Formats::BZIP;
                case 'application/x-lzma':
                    return Formats::LZMA;
                case 'application/x-iso9660-image':
                    return Formats::ISO;
                case 'application/vnd.ms-cab-compressed':
                    return Formats::CAB;
                case 'application/x-tar':
                    return Formats::TAR;
                case 'application/x-gtar':
                    return Formats::TAR_GZIP;
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
        static::retrieveAllFormats();

        if (!isset(static::$formatsSupport[$format])) {
            static::$formatsSupport[$format] = [];
            foreach (static::$availableFormats[$format] as $format_driver) {
                if ($format_driver::checkFormatSupport($format))
                {
                    static::$formatsSupport[$format][] = $format_driver;
                }
            }
        }
        return !empty(static::$formatsSupport[$format]);
    }

    /**
     * Checks whether specified archive can be created
     *
     * @param string $format One of predefined archive types (class constants)
     * @return bool
     */
    public static function canCreate($format)
    {
        return static::checkFormatSupport($format, 'canCreateArchive');
    }

    /**
     * Checks whether specified archive can be created
     *
     * @param string $format One of predefined archive types (class constants)
     * @return bool
     */
    public static function canAppend($format)
    {
        return static::checkFormatSupport($format, 'canAddFiles');
    }

    /**
     * Checks whether specified archive can be created
     *
     * @param string $format One of predefined archive types (class constants)
     * @return bool
     */
    public static function canUpdate($format)
    {
        return static::checkFormatSupport($format, 'canDeleteFiles');
    }

    /**
     * Checks whether specified archive can be created
     *
     * @param string $format One of predefined archive types (class constants)
     * @return bool
     */
    public static function canEncrypt($format)
    {
        return static::checkFormatSupport($format, 'canUsePassword');
    }

    /**
     * @param string $format
     * @param string $function
     * @return bool
     */
    protected static function checkFormatSupport($format, $function)
    {

        static::retrieveAllFormats();
        if (!static::canOpen($format))
            return false;

        foreach (static::$formatsSupport[$format] as $driver) {
            if ($driver::$function($format))
                return true;
        }

        return false;
    }

    /**
     * @param string $format
     * @param bool $createAbility
     * @return mixed
     */
    public static function getFormatDriver($format, $createAbility = false)
    {
        static::retrieveAllFormats();

        if (!static::canOpen($format))
            throw new UnsupportedArchiveException('Unsupported archive type: '.$format.' of archive ');

        if (!$createAbility)
            return current(static::$formatsSupport[$format]);

        foreach (static::$formatsSupport[$format] as $driver) {
            if ($driver::canCreateArchive($format))
                return $driver;
        }
        return false;
    }

    public static function getFormatsReport()
    {
        static::retrieveAllFormats();
        $result = [];

        foreach (static::$availableFormats as $format => $formatDrivers) {
            $result[$format] = [
                'open' => static::canOpen($format),
                'create' => static::canCreate($format),
                'append' => static::canAppend($format),
                'update' => static::canUpdate($format),
                'encrypt' => static::canEncrypt($format),
                'drivers' => static::$formatsSupport[$format],
            ];
        }

        return $result;
    }

    /**
     * Tests system configuration
     */
    protected static function retrieveAllFormats()
    {
        if (static::$availableFormats === null) {
            static::$availableFormats = [];
            foreach (Formats::$drivers as $handlerClass)
            {
                $handler_formats = $handlerClass::getSupportedFormats();
                foreach ($handler_formats as $handler_format)
                {
                    static::$availableFormats[$handler_format][] = $handlerClass;
                }
            }
        }
    }
}