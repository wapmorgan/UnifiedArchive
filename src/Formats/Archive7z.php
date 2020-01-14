<?php
namespace wapmorgan\UnifiedArchive\Formats;

use Symfony\Component\Process\Process;

class Archive7z extends \Archive7z\Archive7z
{
    /**
     * @throws \Archive7z\Exception
     */
    public static function getBinaryVersion()
    {
        if (method_exists(__CLASS__, 'makeBinary7z'))
            $binary = static::makeBinary7z();
        else {
            // some hack for gemorroj/archive7z 4.x version
            $seven_zip = new self(null);
            $binary = $seven_zip->getAutoCli();
            unset($seven_zip);
        }

        $process = new Process([str_replace('\\', '/', $binary)]);
        $result = $process->mustRun()->getOutput();
        if (!preg_match('~7-Zip (\[[\d]+\] )?(?<version>\d+\.\d+)~i', $result, $version))
            return false;

        return $version['version'];
    }
}