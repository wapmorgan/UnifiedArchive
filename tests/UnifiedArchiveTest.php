<?php
use wapmorgan\UnifiedArchive\TarArchive;
use wapmorgan\UnifiedArchive\UnifiedArchive;

class UnifiedArchiveTest extends PhpUnitTestCase {

    public function archiveTypes()
    {
        return [
            ['archive.tar', TarArchive::TAR],
            ['archive.tgz', TarArchive::TAR_GZIP],
            ['archive.tar.gz', TarArchive::TAR_GZIP],
            ['archive.tbz2', TarArchive::TAR_BZIP],
            ['archive.tar.bz2', TarArchive::TAR_BZIP],
            ['archive.txz', TarArchive::TAR_LZMA],
            ['archive.tar.xz', TarArchive::TAR_LZMA],
            ['archive.zip', UnifiedArchive::ZIP],
            ['archive.rar', UnifiedArchive::RAR],
            ['archive.iso', UnifiedArchive::ISO],
            ['archive.7z', UnifiedArchive::SEVEN_ZIP],
        ];
    }

    /**
     * @dataProvider archiveTypes
     */
    public function testDetectArchiveType($filename, $type)
    {
        $this->assertEquals($type, UnifiedArchive::detectArchiveType($filename));
    }

    public function testOpen()
    {
        foreach (self::$fixtures as $fixture) {
            $class = (strpos($fixture[1], '.tar') !== false) ? 'wapmorgan\UnifiedArchive\TarArchive' : 'wapmorgan\UnifiedArchive\UnifiedArchive';

            if (!UnifiedArchive::canOpenArchive($fixture[1]))
                continue;

            $this->assertInstanceOf($class, UnifiedArchive::open(self::getFixturePath($fixture[1])),
                '::open() on '.self::getFixturePath($fixture[1]).' should return an object');
        }

        return true;
    }

    /**
     * @depends testOpen
     */
    public function testCountFiles()
    {
        $files_number = count(self::$fixtureContents, COUNT_RECURSIVE);

        foreach (self::$fixtures as $fixture) {
            if (!UnifiedArchive::canOpenArchive($fixture[1]))
                continue;

            $archive = UnifiedArchive::open(self::getFixturePath($fixture[1]));
            $this->assertEquals($files_number, $archive->countFiles(), 'Invalid files count for '.$fixture[1]);
        }
    }
}
