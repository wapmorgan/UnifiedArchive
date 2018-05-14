<?php
use wapmorgan\UnifiedArchive\UnifiedArchive;

class UnifiedArchiveTest extends PhpUnitTestCase {

    public function testOpen()
    {
        foreach (self::$fixtures as $fixture) {
            if (strpos($fixture[1], '.tar') !== false)
                $class = 'wapmorgan\UnifiedArchive\TarArchive';
            else
                $class = 'wapmorgan\UnifiedArchive\UnifiedArchive';

            if (!UnifiedArchive::canOpen($fixture[1]))
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
            if (!UnifiedArchive::canOpen($fixture[1]))
                continue;

            $archive = UnifiedArchive::open(self::getFixturePath($fixture[1]));
            $this->assertEquals($files_number, $archive->countFiles(), 'Invalid files count for '.$fixture[1]);
        }
    }
}
