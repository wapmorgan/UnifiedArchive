<?php
require_once __DIR__ . '/../vendor/autoload.php';

define('FIXTURES_DIR', __DIR__ . '/fixtures');

class PhpUnitTestCase extends PHPUnit_Framework_TestCase
{
    static public $fixtures = [
        ['c2bdd9989281738a637b3331dd415b8b', 'fixtures.7z', 'https://github.com/wapmorgan/UnifiedArchive/releases/download/0.0.1/fixtures.7z'],
        ['c6918bb89b32d5a71ec1f7836269056e', 'fixtures.iso', 'https://github.com/wapmorgan/UnifiedArchive/releases/download/0.0.1/fixtures.iso'],
        ['4df15469482f218110ab275eb17eef44', 'fixtures.tar', 'https://github.com/wapmorgan/UnifiedArchive/releases/download/0.0.1/fixtures.tar'],
        ['4adbd6405cb13c4c6a942ae90922f1ff', 'fixtures.tar.bz2', 'https://github.com/wapmorgan/UnifiedArchive/releases/download/0.0.1/fixtures.tar.bz2'],
        ['644800e1eb17c9f296dca61336525bd9', 'fixtures.tar.gz', 'https://github.com/wapmorgan/UnifiedArchive/releases/download/0.0.1/fixtures.tar.gz'],
        ['ef64468ec5a1a582db6d3f77fbd5d2b5', 'fixtures.tar.xz', 'https://github.com/wapmorgan/UnifiedArchive/releases/download/0.0.1/fixtures.tar.xz'],
        ['36140341386a8a46fc345d2068f1969f', 'fixtures.zip', 'https://github.com/wapmorgan/UnifiedArchive/releases/download/0.0.1/fixtures.zip'],
    ];

    static public $fixtureContents = [
        'folder' => [
            'subfolder' => [
                'subfile' => 'Content'."\n",
            ],
            'subdoc' => 'Subdoc',
        ],
        'doc' => 'Doc',
    ];

    static public function getFixturePath($fixture)
    {
        return FIXTURES_DIR.'/'.$fixture;
    }
}

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

foreach (PhpUnitTestCase::$fixtures as $fixture) {
    $fixture_file = PhpUnitTestCase::getFixturePath($fixture[1]);
    if (!file_exists($fixture_file)) {
        downloadFixture($fixture[2], $fixture_file, $fixture[0]);
    } else if (md5_file($fixture_file) !== $fixture[0]) {
        if (!unlink($fixture_file)) {
            throw new Exception('Unable to delete fixture: ' . $fixture_file);
        }
        downloadFixture($fixture[2], $fixture_file, $fixture[0]);
    }
}
