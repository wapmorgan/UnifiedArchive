<?php
use PHPUnit\Framework\TestCase;
use wapmorgan\UnifiedArchive\Formats;
use wapmorgan\UnifiedArchive\UnifiedArchive;

require_once __DIR__ . '/../vendor/autoload.php';

define('ARCHIVES_DIR', __DIR__ . '/archives');
define('FIXTURES_DIR', __DIR__ . '/fixtures');
define('WORK_DIR', __DIR__ . '/workdir');

class PhpUnitTestCase extends TestCase
{
    /**
     * @var array<string format, array<string md5_hash, string filename, string remote_file>>
     */
    static public $archives;

    /**
     * @var array List of directories/files and content stored in archive
     */
    static public $fixtureContents;

    /**
     * @return array
     */
    public function getFixtures()
    {
        return self::$archives;
    }

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

    /**
     * @param       $prefix
     * @param array $list
     * @param array $output
     */
    protected function flattenFilesList($prefix, array $list, array &$output)
    {
        foreach ($list as $name => $value) {
            if (is_array($value))
                $this->flattenFilesList($prefix.$name.'/', $value, $output);
            else
                $output[$prefix.$name] = $value;
        }
    }

    protected function assertValueIsInteger($actual)
    {
        if (method_exists($this, 'assertIsInt'))
            return $this->assertIsInt($actual);
        return $this->assertInternalType('integer', $actual);
    }
}

PhpUnitTestCase::$archives = [
    Formats::SEVEN_ZIP => ['a91fb294d6eb88df24ab26ae5f713775', 'fixtures.7z', 'https://github.com/wapmorgan/UnifiedArchive/releases/download/0.0.1/fixtures.7z'],
    Formats::ISO => ['f3bb89062d2c62fb2339c913933db112', 'fixtures.iso', 'https://github.com/wapmorgan/UnifiedArchive/releases/download/0.0.1/fixtures.iso'],
    Formats::TAR => ['d64474b28bfd036abb885b4e80c847b3', 'fixtures.tar', 'https://github.com/wapmorgan/UnifiedArchive/releases/download/0.0.1/fixtures.tar'],
    Formats::TAR_BZIP => ['e2ca07d2f1007f312493a12b239544df', 'fixtures.tar.bz2', 'https://github.com/wapmorgan/UnifiedArchive/releases/download/0.0.1/fixtures.tar.bz2'],
    Formats::TAR_GZIP => ['fdc239490189e7bf6239a26067424d42', 'fixtures.tar.gz', 'https://github.com/wapmorgan/UnifiedArchive/releases/download/0.0.1/fixtures.tar.gz'],
    Formats::TAR_LZMA => ['80caf9ba1488c55ca279958abd6fce18', 'fixtures.tar.xz', 'https://github.com/wapmorgan/UnifiedArchive/releases/download/0.0.1/fixtures.tar.xz'],
    Formats::ZIP => ['69dcdf13d2a8b7630e2f54fa5ab97d5a', 'fixtures.zip', 'https://github.com/wapmorgan/UnifiedArchive/releases/download/0.0.1/fixtures.zip'],
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
foreach ([ARCHIVES_DIR, WORK_DIR] as $dir) {
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0777))
            throw new Exception('Could not create '.$dir.' directory');
    }
}

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
