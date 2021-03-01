<?php
namespace wapmorgan\UnifiedArchive;

use Symfony\Component\Process\Process;

class Archive7z extends \Archive7z\Archive7z
{
    /**
     * @return false|string
     */
    public static function getBinaryVersion()
    {
        if (method_exists(__CLASS__, 'makeBinary7z'))
            try {
                $binary = static::makeBinary7z();
            } catch (\Exception $e) {
                return false;
            }
        else {
            // some hack for gemorroj/archive7z 4.x version
            try {
                $seven_zip = new self(null);
            } catch (\Exception $e) {
                return false;
            }
            $binary = $seven_zip->getAutoCli();
            unset($seven_zip);
        }

        $process = new Process([str_replace('\\', '/', $binary)]);
        $result = $process->mustRun()->getOutput();
        if (!preg_match('~7-Zip (\([a-z]\) )?(\[[\d]+\] )?(?<version>\d+\.\d+)~i', $result, $version))
            return false;

        return $version['version'];
    }

    /**
     * Hack to test if package is >=5.0.0.
     * setChangeSystemLocale() method was in 4.0.0 and then was removed.
     */
    public static function supportsAllFormats()
    {
        return !(method_exists(__CLASS__, 'setChangeSystemLocale'));
    }

//    public static function getSupportedFormats()
//    {
//
//    }

    /**
     * @param int $level 0-9 level
     * @return $this
     */
    public function setCompressionLevel($level)
    {
        $this->compressionLevel = $level;
        return $this;
    }
}