<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../vendor/autoload.php';

define('ARCHIVES_DIR', __DIR__ . '/archives');
define('FIXTURES_DIR', __DIR__ . '/fixtures');
define('WORK_DIR', __DIR__ . '/workdir');

class PhpUnitTestCase extends TestCase
{
    /**
     * @var array Array of arrays[md5_hash, filename, remote file]
     */
    static public $archives;

    /**
     * @var array List of directories/files and content stored in archive
     */
    static public $fixtureContents;

    /**
     * @param $fixture
     *
     * @return string
     */
    static public function getArchivePath($fixture)
    {
        return ARCHIVES_DIR.'/'.$fixture;
    }

    /**
     *
     */
    public function cleanWorkDir()
    {
        foreach (glob(WORK_DIR.'/*') as $file) {
            if (basename($file) !== '.gitignore')
                unlink($file);
        }
    }
}

PhpUnitTestCase::$archives = [
    ['a91fb294d6eb88df24ab26ae5f713775', 'fixtures.7z', 'https://github.com/wapmorgan/UnifiedArchive/releases/download/0.0.1/fixtures.7z'],
    ['f3bb89062d2c62fb2339c913933db112', 'fixtures.iso', 'https://github.com/wapmorgan/UnifiedArchive/releases/download/0.0.1/fixtures.iso'],
    ['d64474b28bfd036abb885b4e80c847b3', 'fixtures.tar', 'https://github.com/wapmorgan/UnifiedArchive/releases/download/0.0.1/fixtures.tar'],
    ['e2ca07d2f1007f312493a12b239544df', 'fixtures.tar.bz2', 'https://github.com/wapmorgan/UnifiedArchive/releases/download/0.0.1/fixtures.tar.bz2'],
    ['fdc239490189e7bf6239a26067424d42', 'fixtures.tar.gz', 'https://github.com/wapmorgan/UnifiedArchive/releases/download/0.0.1/fixtures.tar.gz'],
    ['ef64468ec5a1a582db6d3f77fbd5d2b5', 'fixtures.tar.xz', 'https://github.com/wapmorgan/UnifiedArchive/releases/download/0.0.1/fixtures.tar.xz'],
    ['69dcdf13d2a8b7630e2f54fa5ab97d5a', 'fixtures.zip', 'https://github.com/wapmorgan/UnifiedArchive/releases/download/0.0.1/fixtures.zip'],
];

PhpUnitTestCase::$fixtureContents = [
    'folder' => [
        'subfolder' => [
            'subfile' => 'Content',
        ],
        'subdoc' => 'Subdoc',
    ],
    'doc' => 'Doc',
];

/**
 * Downloading function with retrying
 * @param $url
 * @param $target
 * @param $md5
 * @param int $retry
 * @return bool
 * @throws Exception
 */
function downloadFixture($url, $target, $md5, $retry = 3)
{
    if (copy($url, $target) === false) {
        if ($retry > 0)
            return downloadFixture($url, $target, $md5, $retry - 1);
    } else if (md5_file($target) === $md5) {
        echo 'Downloaded '.$target.PHP_EOL;
        return true;
    }

    if (unlink($target) && $retry > 0)
        return downloadFixture($url, $target, $md5, $retry - 1);

    throw new Exception('Unable to download '.$url.' to '.$target);
}

/**
 * Checking fixtures
 */
foreach (PhpUnitTestCase::$archives as $fixture) {
    $fixture_file = PhpUnitTestCase::getArchivePath($fixture[1]);
    if (!file_exists($fixture_file)) {
        downloadFixture($fixture[2], $fixture_file, $fixture[0]);
    } else if (md5_file($fixture_file) !== $fixture[0]) {
        if (!unlink($fixture_file)) {
            throw new Exception('Unable to delete fixture: ' . $fixture_file);
        }
        downloadFixture($fixture[2], $fixture_file, $fixture[0]);
    }
}
