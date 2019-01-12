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
        $binary = static::makeBinary7z();
        $process = new Process([str_replace('\\', '/', $binary)]);
        $result = $process->mustRun()->getOutput();
        if (!preg_match('~7-Zip (\[[\d]+\] )?(?<version>\d+\.\d+)~i', $result, $version))
            return false;

        return $version['version'];
    }
}