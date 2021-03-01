<?php

use wapmorgan\UnifiedArchive\Formats;
use wapmorgan\UnifiedArchive\UnifiedArchive;

class ReadingTest extends PhpUnitTestCase
{

    /**
     * @return array
     */
    public function archiveTypes()
    {
        return [
            ['archive.tar', Formats::TAR],
            ['archive.tgz', Formats::TAR_GZIP],
            ['archive.tar.gz', Formats::TAR_GZIP],
            ['archive.tbz2', Formats::TAR_BZIP],
            ['archive.tar.bz2', Formats::TAR_BZIP],
            ['archive.txz', Formats::TAR_LZMA],
            ['archive.tar.xz', Formats::TAR_LZMA],
            ['archive.zip', Formats::ZIP],
            ['archive.rar', Formats::RAR],
            ['archive.iso', Formats::ISO],
            ['archive.7z', Formats::SEVEN_ZIP],
        ];
    }

    /**
     * @dataProvider archiveTypes
     */
    public function testDetectArchiveType($filename, $type)
    {
        $this->assertEquals($type, Formats::detectArchiveFormat($filename));
    }

    /**
     * @dataProvider getFixtures
     * @return bool
     * @throws \Exception
     */
    public function testOpen($md5hash, $filename, $remoteUrl)
    {
        $full_filename = self::getArchivePath($filename);

        if (!UnifiedArchive::canOpen($full_filename))
            $this->markTestSkipped(Formats::detectArchiveFormat($full_filename) .' is not supported with current system configuration');

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

        if (!UnifiedArchive::canOpen($full_filename))
            $this->markTestSkipped(Formats::detectArchiveFormat($full_filename) .' is not supported with current system configuration');

        $archive = UnifiedArchive::open($full_filename);
        if ($files_number != $archive->countFiles()) {
            throw new Exception(json_encode([$archive->getFileNames(), $archive->getDriverType()]));
        }
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

        if (!UnifiedArchive::canOpen($full_filename))
            $this->markTestSkipped(Formats::detectArchiveFormat($full_filename) .' is not supported with current system configuration');

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
        if ($expected_files != $actual_files) {
            throw new Exception(json_encode([$expected_files, $actual_files, $archive->getDriverType()]));
        }
        $this->assertEquals($expected_files, $actual_files, 'Files set is not identical');

        foreach ($flatten_list as $filename => $content) {

            // test file existence
            $this->assertTrue($archive->hasFile($filename), 'File '.$filename.' should be in archive');

            // test ArchiveEntry
            $file_data = $archive->getFileData($filename);
            $this->assertInstanceOf('wapmorgan\\UnifiedArchive\\ArchiveEntry', $file_data, 'Could not find '
                .$filename);

            $this->assertEquals($filename, $file_data->path, 'Path should be '.$filename);
            $this->assertTrue(is_numeric($file_data->compressedSize), 'Compressed size of '
                .$filename.' should be int');
            $this->assertEquals(strlen($content), $file_data->uncompressedSize, 'Uncompressed size of '
                .$filename.' should be '.strlen($content).', but it is '.$file_data->uncompressedSize);
            $this->assertEquals(
                $file_data->compressedSize !== $file_data->uncompressedSize,
                $file_data->isCompressed,
                'Is compressed of '.$filename.' should be '.($file_data->compressedSize !== $file_data->uncompressedSize));

            // test content
            $this->assertEquals($content, $archive->getFileContent($filename), 'getFileContent() should return content of file that should be equal to real file content');
            $this->assertEquals($content, stream_get_contents($archive->getFileStream($filename)), 'getFileStream() should return stream with content of file that should be equal to real file content');

        }

//        $this->assertInternalType('boolean', $archive->canAddFiles());
//        $this->assertInternalType('boolean', $archive->canDeleteFiles());
    }
}
