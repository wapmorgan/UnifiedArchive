<?php
use wapmorgan\UnifiedArchive\UnifiedArchive;

class ArchivingTest extends PhpUnitTestCase
{

    public function getFixtures()
    {
        return self::$archives;
    }

    /**
     * @return array
     */
    public function archiveTypes()
    {
        return [
            ['archive.tar', UnifiedArchive::TAR],
            ['archive.tgz', UnifiedArchive::TAR_GZIP],
            ['archive.tar.gz', UnifiedArchive::TAR_GZIP],
            ['archive.tbz2', UnifiedArchive::TAR_BZIP],
            ['archive.tar.bz2', UnifiedArchive::TAR_BZIP],
            ['archive.txz', UnifiedArchive::TAR_LZMA],
            ['archive.tar.xz', UnifiedArchive::TAR_LZMA],
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

    /**
     * @dataProvider modifyableArchiveTypes
     *
     * @param string $archiveFileName
     * @param string $archiveType
     *
     * @throws \Exception
     */
    public function testCreate($archiveFileName, $archiveType)
    {
        if (!UnifiedArchive::canOpenType($archiveType))
            $this->markTestSkipped($archiveType.' is not supported with current system configuration');

        $this->cleanWorkDir();

        $test_archive_filename = WORK_DIR.'/'.$archiveFileName;

        $result = UnifiedArchive::archiveFiles(FIXTURES_DIR, $test_archive_filename);
        $this->assertInternalType('integer', $result);
        $this->assertEquals(5, $result);

        unlink($test_archive_filename);
    }

    /**
     * @dataProvider modifyableArchiveTypes
     *
     * @param string $archiveFileName
     * @param string $archiveType
     *
     * @throws \Exception
     */
    public function testModify($archiveFileName, $archiveType)
    {
        if (!UnifiedArchive::canOpenType($archiveType))
            $this->markTestSkipped($archiveType.' is not supported with current system configuration');

        $this->cleanWorkDir();

        $test_archive_filename = WORK_DIR.'/'.$archiveFileName;
        copy(ARCHIVES_DIR.'/'.$archiveFileName, $test_archive_filename);

        $archive = UnifiedArchive::open($test_archive_filename);
        $this->assertInstanceOf('\wapmorgan\UnifiedArchive\AbstractArchive', $archive);

        // adding file
        $this->assertTrue($archive->addFile(__FILE__, basename(__FILE__)));
        $this->assertTrue($archive->isFileExists(basename(__FILE__)));
        $this->assertEquals(file_get_contents(__FILE__), $archive->getFileContent(basename(__FILE__)));

        // removing file
        $this->assertEquals(1, $archive->deleteFiles(basename(__FILE__)));
        $this->assertFalse($archive->isFileExists(basename(__FILE__)));
        $archive = null;

        unlink($test_archive_filename);
    }

    /**
     * @return array
     */
    public function modifyableArchiveTypes()
    {
        return [
            ['fixtures.zip', UnifiedArchive::ZIP],
            ['fixtures.7z', UnifiedArchive::SEVEN_ZIP],
            ['fixtures.tar', UnifiedArchive::TAR],
        ];
    }
}
