<?php

use wapmorgan\UnifiedArchive\Drivers\BasicDriver;
use wapmorgan\UnifiedArchive\Formats;
use wapmorgan\UnifiedArchive\UnifiedArchive;

class DriversTest extends PhpUnitTestCase
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
     * @return array
     */
    public function oneFileArchiveTypes()
    {
        return [
            ['onefile.gz', Formats::GZIP],
            ['onefile.bz2', Formats::BZIP],
        ];
    }

    /**
     * @dataProvider archiveTypes
     * @dataProvider oneFileArchiveTypes
     */
    public function testDetectArchiveType($filename, $type)
    {
        $this->assertEquals($type, Formats::detectArchiveFormat($filename));
    }

    /**
     * @dataProvider getFixtures
     * @dataProvider getOneFileFixtures
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

    public function driversAndFormats()
    {
        $result = [];
        /** @var BasicDriver $driver */
        foreach (Formats::$drivers as $driver) {
            $driver_formats = $driver::getSupportedFormats();

            foreach (static::$archives as $format => $archiveConfig) {
                if (!in_array($format, $driver_formats, true)) {
                    continue;
                }
                $result[$driver . ' - ' . $format . ' - ' . $archiveConfig[1]] = [$driver, $format, true, $archiveConfig];
            }

            foreach (static::$oneFileArchives as $format => $archiveConfig) {
                if (!in_array($format, $driver_formats, true)) {
                    continue;
                }
                $result[$driver . ' - ' . $format . ' - ' . $archiveConfig[1]] = [$driver, $format, false, $archiveConfig];
            }
        }
        return $result;
    }

    /**
     * @param BasicDriver $driverClass
     * @return void
     * @dataProvider driversAndFormats
     */
    public function testFormats($driverClass, $format, $multiFileArchive, $archiveConfig)
    {
        $supported_abilities = $driverClass::checkFormatSupport($format);
        if (!in_array(BasicDriver::OPEN, $supported_abilities, true)) {
            $this->markTestSkipped('Format ' . $format . ' is not openable via ' . $driverClass);
            return true;
        }

        if ($multiFileArchive) {
            $flatten_list = [];
            $this->flattenFilesList(null, self::$fixtureContents, $flatten_list);
        } else {
            $flatten_list = ['onefile' => file_get_contents(FIXTURES_DIR . '/doc')];
        }

        /** @var BasicDriver $driver */
        $driver = new $driverClass(PhpUnitTestCase::getArchivePath($archiveConfig[1]), $format);

        $information = $driver->getArchiveInformation();

        // test files size
        if ($multiFileArchive) {
            $this->assertEquals(
                array_sum(array_map('strlen', $flatten_list)),
                $information->uncompressedFilesSize,
                'Uncompressed size of archive should be equal to real files size'
            );
        } else {
//            $this->assertEquals(filesize(FIXTURES_DIR . '/doc'), $information->, 'Files set is not identical');

        }

        $expected_files = array_keys($flatten_list);

        // test files list
        if ($multiFileArchive) {
            $expected_files = array_keys($flatten_list);
            sort($expected_files);
            $actual_files = $information->files;
            sort($actual_files);
            if ($expected_files != $actual_files) {
                throw new Exception(json_encode([$expected_files, $actual_files]));
            }
            $this->assertEquals($expected_files, $actual_files, 'Files set is not identical');
        } else {
            $this->assertCount(1, $information->files, 'Files set is not identical');
        }

        // test files separately
        foreach ($flatten_list as $filename => $content) {
            // test file existence
            $this->assertTrue($driver->isFileExists($filename), 'File ' . $filename . ' should be in archive');
            // test ArchiveEntry
            $file_data = $driver->getFileData($filename);
            $this->assertInstanceOf(
                'wapmorgan\\UnifiedArchive\\ArchiveEntry',
                $file_data,
                'Could not find '
                . $filename
            );

            foreach ([
                         'path' => $filename,
                         'uncompressedSize' => strlen($content),
//                             'isCompressed' => $file_data->compressedSize !== $file_data->uncompressedSize,
                     ] as $expectedName => $expectedValue) {
                if (!$multiFileArchive && $expectedName === 'uncompressedSize') {
                    continue;
                }
                $this->assertObjectHasAttribute($expectedName, $file_data);
                $this->assertEquals($expectedValue, $file_data->{$expectedName}, $expectedName . ' should be ' . $expectedValue);
            }
            $this->assertTrue(
                is_numeric($file_data->compressedSize),
                'Compressed size of '
                . $filename . ' should be int'
            );

            // test content
            $this->assertEquals(
                $content,
                $driver->getFileContent($filename),
                'getFileContent() should return content of file that should be equal to real file content'
            );
            $this->assertEquals(
                $content,
                stream_get_contents($driver->getFileStream($filename)),
                'getFileStream() should return stream with content of file that should be equal to real file content'
            );
        }

        $temp_file = $this->prepareTempFolder('uatest');

        $this->assertEquals(count($expected_files), $driver->extractArchive($temp_file), 'For archive ' . $archiveConfig[1]);
        foreach ($flatten_list as $filename => $content) {
            if ($multiFileArchive) {
                $this->assertFileEquals(FIXTURES_DIR . '/' . $filename, $temp_file . '/' . $filename);
            } else {
                $this->assertEquals($content, file_get_contents($temp_file . '/' . $filename));
            }
        }
        $this->removeTempFolder($temp_file);
    }
}
