<?php
use wapmorgan\UnifiedArchive\UnifiedArchive;

class ReadingTest extends PhpUnitTestCase
{

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
        // for all formats count only leaves of fixtures
        $files_number = 0;
        array_walk_recursive(self::$fixtureContents, function () use (&$files_number) { $files_number++; });

        $full_filename = self::getArchivePath($filename);

        if (!UnifiedArchive::canOpenArchive($full_filename))
            $this->markTestSkipped(UnifiedArchive::detectArchiveType($full_filename).' is not supported with current system configuration');

        $archive = UnifiedArchive::open($full_filename);
        if ($files_number != $archive->countFiles())
            var_dump($archive->getFileNames());
        $this->assertEquals($files_number, $archive->countFiles(), 'Invalid files count for '.$filename);
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

        // test uncompressed archive size calculation
        $this->assertEquals(array_sum(array_map('strlen', $flatten_list)), $archive->countUncompressedFilesSize(),
        'Uncompressed size of archive should be equal to real files size');

        $expected_files = array_keys($flatten_list);
        sort($expected_files);
        $actual_files = $archive->getFileNames();
        sort($actual_files);
        $this->assertEquals($expected_files, $actual_files, 'Files set is not identical');

        foreach ($flatten_list as $filename => $content) {

            // test file existence
            $this->assertTrue($archive->isFileExists($filename), 'File '.$filename.' should be in archive');

            // test ArchiveEntry
            $file_data = $archive->getFileData($filename);
            $this->assertInstanceOf('wapmorgan\\UnifiedArchive\\ArchiveEntry', $file_data, 'Could not find '
                .$filename);

            $this->assertAttributeEquals($filename, 'path', $file_data, 'Path should be '.$filename);
            $this->assertTrue(is_numeric($file_data->compressedSize), 'Compressed size of '
                .$filename.' should be int');
            $this->assertAttributeEquals(strlen($content), 'uncompressedSize', $file_data, 'Uncompressed size of '
                .$filename.' should be '.strlen($content).', but it is '.$file_data->uncompressedSize);
            $this->assertAttributeEquals($file_data->compressedSize !== $file_data->uncompressedSize, 'isCompressed',
                $file_data, 'Is compressed of '
                .$filename.' should be '.($file_data->compressedSize !== $file_data->uncompressedSize));

            // test content
            $this->assertEquals($content, $archive->getFileContent($filename), 'getFileContent() should return content of file that should be equal to real file content');
            $this->assertEquals($content, stream_get_contents($archive->getFileResource($filename)), 'getFileResource() should return stream with content of file that should be equal to real file content');

            $this->assertInternalType('boolean', $archive->canAddFiles());
            $this->assertInternalType('boolean', $archive->canDeleteFiles());
        }
    }
}
