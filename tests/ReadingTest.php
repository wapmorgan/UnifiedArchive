<?php
use wapmorgan\UnifiedArchive\UnifiedArchive;

class UnifiedArchiveTest extends PhpUnitTestCase
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
     * @dataProvider getFixtures
     * @return bool
     * @throws \Exception
     */
    public function testOpen($md5hash, $filename, $remoteUrl)
    {
        $full_filename = self::getArchivePath($filename);

        if (!UnifiedArchive::canOpenArchive($full_filename))
            $this->markTestSkipped(UnifiedArchive::detectArchiveType($full_filename).' is not supported with current system configuration');

        $this->assertInstanceOf('wapmorgan\UnifiedArchive\UnifiedArchive', UnifiedArchive::open($full_filename),
            'UnifiedArchive::open() on '.$full_filename.' should return an object');
    }

    /**
     * @depends testOpen
     * @dataProvider getFixtures
     * @throws Exception
     */
    public function testCountFiles($md5hash, $filename, $remoteUrl)
    {
        // for 7z count only leaves of fixtures (due to 7z cli output without directories)
        if (fnmatch('*.7z', $filename)) {
            $files_number = 0;
            array_walk_recursive(self::$fixtureContents, function () use (&$files_number) { $files_number++; });
        } else
            $files_number = count(self::$fixtureContents, COUNT_RECURSIVE);
        $full_filename = self::getArchivePath($filename);

        if (!UnifiedArchive::canOpenArchive($full_filename))
            $this->markTestSkipped(UnifiedArchive::detectArchiveType($full_filename).' is not supported with current system configuration');

        $archive = UnifiedArchive::open($full_filename);
        $this->assertEquals($files_number, $archive->countFiles(), 'Invalid files count for '.$filename);
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

    /**
     * @depends      testCountFiles
     * @dataProvider getFixtures
     * @throws \Exception
     */
    public function testFilesData($md5hash, $archiveFilename, $remoteUrl)
    {
        $full_filename = self::getArchivePath($archiveFilename);

        if (!UnifiedArchive::canOpenArchive($full_filename))
            $this->markTestSkipped(UnifiedArchive::detectArchiveType($full_filename).' is not supported with current system configuration');

        $archive = UnifiedArchive::open($full_filename);
        $flatten_list = [];
        $this->flattenFilesList(null, self::$fixtureContents, $flatten_list);

        foreach ($flatten_list as $filename => $content) {

            if (fnmatch('*.7z', $archiveFilename) && DIRECTORY_SEPARATOR == '\\')
                $filename = str_replace('/', '\\', $filename);

            $file_data = $archive->getFileData($filename);
            $this->assertInstanceOf('wapmorgan\\UnifiedArchive\\ArchiveEntry', $file_data, 'Could not find '
                .$filename);

            $this->assertAttributeEquals(strlen($content), 'uncompressedSize', $file_data, 'Uncompressed size of '
                .$filename.' should be '.strlen($content).', but it is '.$file_data->uncompressedSize);
        }
    }

    protected function flattenFilesList($prefix, array $list, array &$output)
    {
        foreach ($list as $name => $value) {
            if (is_array($value))
                $this->flattenFilesList($prefix.$name.'/', $value, $output);
            else
                $output[$prefix.$name] = $value;
        }
    }
}