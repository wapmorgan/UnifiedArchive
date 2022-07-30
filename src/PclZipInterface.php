<?php

namespace wapmorgan\UnifiedArchive;

use RecursiveIteratorIterator;

if (!defined('PCLZIP_ERR_NO_ERROR')) {
    // ----- Constants
    if (!defined('PCLZIP_READ_BLOCK_SIZE')) {
        define('PCLZIP_READ_BLOCK_SIZE', 2048);
    }
    if (!defined('PCLZIP_SEPARATOR')) {
        define('PCLZIP_SEPARATOR', ',');
    }
    if (!defined('PCLZIP_ERROR_EXTERNAL')) {
        define('PCLZIP_ERROR_EXTERNAL', 0);
    }
    if (!defined('PCLZIP_TEMPORARY_DIR')) {
        define('PCLZIP_TEMPORARY_DIR', sys_get_temp_dir());
    }

    define('PCLZIP_ERR_USER_ABORTED', 2);
    define('PCLZIP_ERR_NO_ERROR', 0);
    define('PCLZIP_ERR_WRITE_OPEN_FAIL', -1);
    define('PCLZIP_ERR_READ_OPEN_FAIL', -2);
    define('PCLZIP_ERR_INVALID_PARAMETER', -3);
    define('PCLZIP_ERR_MISSING_FILE', -4);
    define('PCLZIP_ERR_FILENAME_TOO_LONG', -5);
    define('PCLZIP_ERR_INVALID_ZIP', -6);
    define('PCLZIP_ERR_BAD_EXTRACTED_FILE', -7);
    define('PCLZIP_ERR_DIR_CREATE_FAIL', -8);
    define('PCLZIP_ERR_BAD_EXTENSION', -9);
    define('PCLZIP_ERR_BAD_FORMAT', -10);
    define('PCLZIP_ERR_DELETE_FILE_FAIL', -11);
    define('PCLZIP_ERR_RENAME_FILE_FAIL', -12);
    define('PCLZIP_ERR_BAD_CHECKSUM', -13);
    define('PCLZIP_ERR_INVALID_ARCHIVE_ZIP', -14);
    define('PCLZIP_ERR_MISSING_OPTION_VALUE', -15);
    define('PCLZIP_ERR_INVALID_OPTION_VALUE', -16);
    define('PCLZIP_ERR_ALREADY_A_DIRECTORY', -17);
    define('PCLZIP_ERR_UNSUPPORTED_COMPRESSION', -18);
    define('PCLZIP_ERR_UNSUPPORTED_ENCRYPTION', -19);
    define('PCLZIP_ERR_INVALID_ATTRIBUTE_VALUE', -20);
    define('PCLZIP_ERR_DIRECTORY_RESTRICTION', -21);

    // ----- Options values
    define('PCLZIP_OPT_PATH', 77001);
    define('PCLZIP_OPT_ADD_PATH', 77002);
    define('PCLZIP_OPT_REMOVE_PATH', 77003);
    define('PCLZIP_OPT_REMOVE_ALL_PATH', 77004);
    define('PCLZIP_OPT_SET_CHMOD', 77005);
    define('PCLZIP_OPT_EXTRACT_AS_STRING', 77006);
    define('PCLZIP_OPT_NO_COMPRESSION', 77007);
    define('PCLZIP_OPT_BY_NAME', 77008);
    define('PCLZIP_OPT_BY_INDEX', 77009);
    define('PCLZIP_OPT_BY_EREG', 77010);
    define('PCLZIP_OPT_BY_PREG', 77011);
    define('PCLZIP_OPT_COMMENT', 77012);
    define('PCLZIP_OPT_ADD_COMMENT', 77013);
    define('PCLZIP_OPT_PREPEND_COMMENT', 77014);
    define('PCLZIP_OPT_EXTRACT_IN_OUTPUT', 77015);
    define('PCLZIP_OPT_REPLACE_NEWER', 77016);
    define('PCLZIP_OPT_STOP_ON_ERROR', 77017);
    // Having big trouble with crypt. Need to multiply 2 long int
    // which is not correctly supported by PHP ...
    //define( 'PCLZIP_OPT_CRYPT', 77018 );
    define('PCLZIP_OPT_EXTRACT_DIR_RESTRICTION', 77019);
    define('PCLZIP_OPT_TEMP_FILE_THRESHOLD', 77020);
    define('PCLZIP_OPT_ADD_TEMP_FILE_THRESHOLD', 77020); // alias
    define('PCLZIP_OPT_TEMP_FILE_ON', 77021);
    define('PCLZIP_OPT_ADD_TEMP_FILE_ON', 77021); // alias
    define('PCLZIP_OPT_TEMP_FILE_OFF', 77022);
    define('PCLZIP_OPT_ADD_TEMP_FILE_OFF', 77022); // alias

    // ----- File description attributes
    define('PCLZIP_ATT_FILE_NAME', 79001);
    define('PCLZIP_ATT_FILE_NEW_SHORT_NAME', 79002);
    define('PCLZIP_ATT_FILE_NEW_FULL_NAME', 79003);
    define('PCLZIP_ATT_FILE_MTIME', 79004);
    define('PCLZIP_ATT_FILE_CONTENT', 79005);
    define('PCLZIP_ATT_FILE_COMMENT', 79006);

    // ----- Call backs values
    define('PCLZIP_CB_PRE_EXTRACT', 78001);
    define('PCLZIP_CB_POST_EXTRACT', 78002);
    define('PCLZIP_CB_PRE_ADD', 78003);
    define('PCLZIP_CB_POST_ADD', 78004);
}

/**
 * @link https://web.archive.org/web/20190228165954/http://www.phpconcept.net/pclzip/user-guide/18
 * @link https://web.archive.org/web/20190216075605/http://www.phpconcept.net/pclzip/user-guide/5
 */
class PclZipInterface
{
    const SELECT_FILTER_PASS = 1;
    const SELECT_FILTER_REFUSE = 0;

    const AVERAGE_ZIP_COMPRESSION_RATIO = 2;

    /**
     * @var UnifiedArchive
     */
    private $archive;

    /**
     * PclzipZipInterface constructor.
     *
     * @param UnifiedArchive $archive
     */
    public function __construct(UnifiedArchive $archive)
    {
        $this->archive = $archive;
    }

    /**
     * @param $localname
     * @param $filename
     *
     * @return object
     */
    public function createFileHeader($localname, $filename)
    {
        return (object) array(
            'filename' => $filename,
            'stored_filename' => $localname,
            'size' => filesize($filename),
            'compressed_size' => ceil(filesize($filename)
                / self::AVERAGE_ZIP_COMPRESSION_RATIO),
            'mtime' => filemtime($filename),
            'comment' => null,
            'folder' => is_dir($filename),
            'status' => 'ok',
        );
    }

    /**
     * Creates a new archive
     * Two ways of usage:
     * <code>create($content, [$addDir, [$removeDir]])</code>
     * <code>create($content, [... options ...]])</code>
     */
    public function create($content)
    {
        if (is_array($content)) $paths_list = $content;
        else $paths_list = explode(',', $content);

        $options = $this->makeOptionsFromArguments(func_get_args());

        // filters initiation
        $filters = $this->createFilters($options);
        list($preAddCallback, $postAddCallback) = $this->extractCallbacks($options, PCLZIP_CB_PRE_ADD, PCLZIP_CB_POST_ADD);

        if (!empty($comment = $this->buildComment($options, null)))
            $this->archive->setComment($comment);

        // scan filesystem for files list
        return $this->addSnippets($paths_list, $filters, $preAddCallback, $postAddCallback);
    }

    /**
     * @param string $fileToAdd
     * @param array $filters
     * @param callable|null $preAddCallback
     * @param callable|null $postAddCallback
     * @return object
     */
    private function addSnippet($fileToAdd, array $filters, callable $preAddCallback, callable $postAddCallback)
    {
        if (is_file($fileToAdd) || is_dir($fileToAdd)) {
            // apply filters to a file
            $localname = $fileToAdd;
            $filename = $fileToAdd;

            foreach ($filters as $filter)
                call_user_func($filter, $localname, $filename);

            $file_header = $this->createFileHeader($localname, $filename);
            if (call_user_func($preAddCallback, $file_header) == 1) {
                //
                // Check for max length > 255
                //
                if (strlen(basename($file_header->stored_filename)) > 255)
                    $file_header->status = 'filename_too_long';
                if (is_file($filename)) {
                    $this->archive->add([
                        $file_header->stored_filename => $file_header->filename,
                    ]);
                } else if (is_dir($filename)) {
//                    $this->archive->addEmptyDir($file_header->stored_filename);
                }

                call_user_func($postAddCallback, $file_header);

            } else {
                //
                // File was skipped
                //
                $file_header->status = 'skipped';
            }

            return $file_header;
        }
    }

    /**
     * Lists archive content
     * @throws Exceptions\NonExistentArchiveFileException
     */
    public function listContent()
    {
        $filesList = [];

        foreach ($this->archive->getFiles() as $i => $fileName) {
            $fileData = $this->archive->getFileData($fileName);

            $filesList[] = [
                'filename' => $fileData->path,
                'stored_filename' => $fileData->path,
                'size' => $fileData->uncompressedSize,
                'compressed_size' => $fileData->compressedSize,
                'mtime' => $fileData->modificationTime,
                'comment' => $fileData->comment,
                'folder' => false/*in_array(substr($statIndex['name'], -1),
                    array('/', '\\'))*/,
                'index' => $i,
                'status' => 'ok',
            ];
        }

        return $filesList;
    }

    /**
     * Extracts files
     * Two ways of usage:
     * <code>extract([$extractPath, [$removePath]])</code>
     * <code>extract([... options ...]])</code>
     */
    public function extract()
    {
        $options = func_get_args();
        //array_shift($options);

        // parse options
        if (isset($options[0]) && is_string($options[0])) {
            $options[PCLZIP_OPT_PATH] = $options[0];
            if (isset($options[1]) && is_string($options[1])) {
                $options[PCLZIP_OPT_REMOVE_PATH] = $options[1];
            }
        } else {
            $options = $this->makeKeyValueArrayFromList($options);
        }

        // filters initiation
        if (isset($options[PCLZIP_OPT_PATH]))
            $extractPath = rtrim($options[PCLZIP_OPT_PATH], '/');
        else $extractPath = rtrim(getcwd(), '/');

        $filters = $this->createFilters($options);
        list($preExtractCallback, $postExtractCallback) = $this->extractCallbacks($options, PCLZIP_CB_PRE_EXTRACT, PCLZIP_CB_POST_EXTRACT);
        $selectFilter = $this->createSelector($options);

        if (isset($options[PCLZIP_OPT_EXTRACT_AS_STRING]))
            $anotherOutputFormat = PCLZIP_OPT_EXTRACT_AS_STRING;
        else if (isset($options[PCLZIP_OPT_EXTRACT_IN_OUTPUT]))
            $anotherOutputFormat = PCLZIP_OPT_EXTRACT_IN_OUTPUT;
        else $anotherOutputFormat = false;

        if (isset($options[PCLZIP_OPT_REPLACE_NEWER]))
            $doNotReplaceNewer = false;
        else $doNotReplaceNewer = true;

        if (isset($options[PCLZIP_OPT_EXTRACT_DIR_RESTRICTION]))
            $restrictExtractDir = $options[PCLZIP_OPT_EXTRACT_DIR_RESTRICTION];
        else $restrictExtractDir = false;

        $report = array();
        foreach ($this->listContent() as $file_header) {
            $file_header = (object)$file_header;
            // add file information to report
            $report[] = $file_header;
            // refuse by select rule
            if (call_user_func($selectFilter, $file_header->stored_filename,
                    $file_header->filename, $file_header->index)
                === self::SELECT_FILTER_REFUSE) {
                //
                // I don't know need to remain this file in report or not,
                // but for now I remove
                array_pop($report);
                // $file_header->status = 'filtered';
                //
                continue;
            }

            //
            // add extract path in case of extraction
            // for some reason need to do it before call pre extract callback
            // (pclzip.lib.php v2.8.2, line 3670)
            // so I decided to do it here too
            //
            if ($anotherOutputFormat === false) {
                $file_header->filename = realpath($extractPath).'/'.
                    $file_header->filename;
                //
                // check for path correlation with restricted path
                //
                if ($restrictExtractDir !== false) {
                    $filename = $file_header->filename;
                    $restrictedDir = realpath($restrictExtractDir);
                    if (strncasecmp($restrictedDir, $filename,
                            strlen($restrictedDir)) !== 0) {
                        // refuse file extraction
                        $file_header->status = 'filtered';
                        continue;
                    }
                }
            }

            // apply pre extract callback
            $callback_result = call_user_func($preExtractCallback,
                $file_header);
            if ($callback_result == 1) {
                // go on ...
            } elseif ($callback_result == 0) {
                // skip current file
                $file_header->status = 'skipped';
                continue;
            } elseif ($callback_result == 2) {
                // skip & stop extraction
                $file_header->status = 'aborted';
                break;
            }

            // return content
            if ($anotherOutputFormat == PCLZIP_OPT_EXTRACT_AS_STRING) {
                $file_header->content
                    = $this->archive->getFileContent($file_header->stored_filename);
            }
            // echo content
            else if ($anotherOutputFormat == PCLZIP_OPT_EXTRACT_IN_OUTPUT) {
                echo $this->archive->getFileContent($file_header->stored_filename);
            }
            // extract content
            else if ($anotherOutputFormat === false) {
                // apply path filters
                foreach ($filters as $filter) call_user_func($filter,
                    $file_header->stored_filename, $file_header->filename);
                // dir extraction process
                if ($file_header->folder) {
                    // if dir doesn't exist
                    if (!is_dir($file_header->filename)) {
                        // try to create folder
                        if (!mkdir($file_header->filename)) {
                            $file_header->status = 'path_creation_fail';
                            continue;
                        }
                    }
                }
                // file extraction process
                else {
                    // check if path is already taken by a folder
                    if (is_dir($file_header->filename)) {
                        $file_header->status = 'already_a_directory';
                        continue;
                    }

                    // check if file exists and it's newer
                    if (is_file($file_header->filename)) {
                        if (filemtime($file_header->filename)
                            > $file_header->mtime) {
                            // skip extraction if option EXTRACT_NEWER isn't set
                            if ($doNotReplaceNewer) {
                                $file_header->status = 'newer_exist';
                                continue;
                            }
                        }
                    }
                    $directory = dirname($file_header->filename);
                    // check if running process can not create extraction folder
                    if (!is_dir($directory) && !mkdir($directory)) {
                            $file_header->status = 'path_creation_fail';
                            continue;
                    } else if (!is_writable($directory)) {
                        // check if file path is not writable
                        $file_header->status = 'write_protected';
                        continue;
                    }
                    // extraction
                    $stream = $this->archive->getFileStream($file_header->stored_filename);
                    if (file_put_contents($file_header->filename, $stream)) {
                        // ok
                    }
                    // extraction fails
                    else {
                        $file_header->status = 'write_error';
                        continue;
                    }
                }
            }

            // apply post extract callback
            $callback_result = call_user_func($postExtractCallback,
                $file_header);
            if ($callback_result == 1) {
                // go on
            } elseif ($callback_result == 2) {
                // skip & stop extraction
                break;
            }
        }

        foreach ($report as $i => $reportItem) {
            $report[$i] = (array)$reportItem;
        }

        return $report;
    }

    /**
     * Reads properties of archive
     */
    public function properties()
    {
        return [
            'nb' => $this->archive->countFiles(),
            'comment' => $this->archive->getComment(),
            'status' => 'OK',
        ];
    }

    /**
     * Adds files in archive
     * <code>add($content, [$addDir, [$removeDir]])</code>
     * <code>add($content, [ ... options ... ])</code>
     */
    public function add($content)
    {
        if (is_array($content)) $paths_list = $content;
        else $paths_list = explode(',', $content);

        $options = $this->makeOptionsFromArguments(func_get_args());
        $filters = $this->createFilters($options);
        list($preAddCallback, $postAddCallback) = $this->extractCallbacks($options, PCLZIP_CB_PRE_ADD, PCLZIP_CB_POST_ADD);

        if (!empty($comment = $this->buildComment($options, $this->archive->getComment())))
            $this->archive->setComment($comment);

        // scan filesystem for files list
        return $this->addSnippets($paths_list, $filters, $preAddCallback, $postAddCallback);
    }

    /**
     * Removes files from archive
     * Usage:
     * <code>delete([... options ...])</code>
     */
    public function delete()
    {
        $options = $this->makeKeyValueArrayFromList(func_get_args());
        $selectFilter = $this->createSelector($options);

        $report = [];
        foreach ($this->listContent() as $file_header) {
            $file_header = (object)$file_header;
            // select by select rule
            if (call_user_func($selectFilter, $file_header->stored_filename,
                    $file_header->filename, $file_header->index)
                === self::SELECT_FILTER_REFUSE) {
                // delete file from archive
                if ($this->archive->delete($file_header->stored_filename)) {
                    // ok
                    continue;
                }
                // deletion fails
                else {
                    return 0;
                }
            }
            // unselected file add in report
            $report[] = $file_header;
        }

        foreach ($report as $i => $reportItem) {
            $report[$i] = (array)$reportItem;
        }

        return $report;
    }

    /**
     * Merges given archive into current archive
     * Two ways of usage:
     * <code>merge($filename)</code>
     * <code>merge(UnifiedArchive $unifiedArchiveInstance)</code>
     * This implementation is more intelligent than original' one.
     */
    public function merge($a)
    {
        // filename
        if (is_string($a)) {
            if (($a = UnifiedArchive::open($a)) !== null) {
                // ok
            } else {
                // // unsupported type of archive
                return 0;
            }
        }
        // UnifiedArchive instance
        else if ($a instanceof UnifiedArchive) {
            // go on
        }
        // invalid argument
        else {
            return 0;
        }

        $tempDir = tempnam(PCLZIP_TEMPORARY_DIR, 'merging');
        if (file_exists($tempDir)) unlink($tempDir);
        if (!mkdir($tempDir)) return 0;

        // go through archive content list and copy all files
        foreach ($a->getFiles() as $filename) {
            // dir merging process
            if (in_array(substr($filename, -1), array('/', '\\'))) {
                $this->archive->addEmptyDir(rtrim($filename, '/\\'));
            }
            // file merging process
            else {
                // extract file in temporary dir
                if ($a->extractNode($tempDir, '/'.$filename)) {
                    // go on
                } else {
                    // extraction fails
                    return 0;
                }
                // add file in archive
                if ($this->archive->addFile($tempDir.'/'.$filename,
                    $filename)) {
                    // ok
                } else {
                    return 0;
                }
            }
        }

        call_user_func(function ($directory) {
            foreach (glob($directory.'/*') as $f) {
                if (is_dir($f)) call_user_func(__FUNCTION__, $f);
                else unlink($f);
            }
        }, $tempDir);

        return 1;
    }

    /**
     * Duplicates archive
     * @param $clone_filename
     * @return int
     */
    public function duplicate($clone_filename)
    {
        return copy($this->archive->filename, $clone_filename) ? 1 : 0;
    }

    /**
     * @param array $options
     * @return array
     */
    public function createFilters(array $options)
    {
        $filters = array();
        if (isset($options[PCLZIP_OPT_REMOVE_PATH])
            && !isset($options[PCLZIP_OPT_REMOVE_ALL_PATH]))
            $filters[] = function (&$key, &$value) use ($options) {
                $key = str_replace($key, null, $key);
            };
        if (isset($options[PCLZIP_OPT_REMOVE_ALL_PATH]))
            $filters[] = function (&$key, &$value) {
                $key = basename($key);
            };
        if (isset($options[PCLZIP_OPT_ADD_PATH]))
            $filters[] = function (&$key, &$value) use ($options) {
                $key = rtrim($options[PCLZIP_OPT_ADD_PATH], '/') . '/' .
                    ltrim($key, '/');
            };
        return $filters;
    }

    /**
     * @param array $options
     * @param string $preCallbackConst
     * @param string $postCallbackConst
     * @return callable[]|\Closure[]
     */
    private function extractCallbacks(array $options, $preCallbackConst, $postCallbackConst)
    {
        $preCallback = $postCallback = function () { return true; };

        if (isset($options[$preCallbackConst]) && is_callable($options[$preCallbackConst]))
            $preCallback = $options[$preCallbackConst];

        if (isset($options[$postCallbackConst]) && is_callable($options[$postCallbackConst]))
            $postCallback = $options[$postCallbackConst];

        return [$preCallback, $postCallback];
    }

    /**
     * @param array $options
     * @return array
     */
    private function makeKeyValueArrayFromList(array $options)
    {
        $keys = array_filter($options, function ($v) {return ($v%2) == 0;}, ARRAY_FILTER_USE_KEY);
        $values = array_filter($options, function ($v) {return ($v%2) == 1;}, ARRAY_FILTER_USE_KEY);
        if (count($values) < count($keys)) $values[] = true;
        return array_combine($keys, $values);
    }

    /**
     * @param array $pathsList
     * @param array $filters
     * @param callable $preAddCallback
     * @param callable $postAddCallback
     * @return array
     */
    public function addSnippets(array $pathsList, array $filters, callable $preAddCallback, callable $postAddCallback)
    {
        $report = [];

        foreach ($pathsList as $file_to_add) {
            $report[] = $this->addSnippet($file_to_add, $filters,
                $preAddCallback, $postAddCallback);

            // additional dir contents
            if (is_dir($file_to_add)) {
                $directory_contents = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator(
                        $file_to_add, \RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST);
                foreach ($directory_contents as $indir_file_to_add) {
                    $report[] = $this->addSnippet($indir_file_to_add, $filters,
                        $preAddCallback, $postAddCallback);
                }
            }
        }
        return $report;
    }

    /**
     * @param array $indexes
     * @return \Closure
     */
    protected function createByIndexSelector(array $indexes)
    {
        $allowedIndexes = array();
        foreach ($indexes as $rule) {
            $parts = explode('-', $rule);
            if (count($parts) == 1) $allowedIndexes[] = $rule;
            else $allowedIndexes = array_merge(
                range($parts[0], $parts[1]), $allowedIndexes);
        }

        return function ($key, $value, $index) use ($allowedIndexes) {
            return in_array($index, $allowedIndexes)
                ? self::SELECT_FILTER_PASS
                : self::SELECT_FILTER_REFUSE;
        };
    }

    /**
     * @param string|array $names
     * @return \Closure
     */
    protected function createByNameSelector($names)
    {
        $allowedNames = is_array($names)
            ? $names
            : explode(',', $names);

        return function ($key, $value) use ($allowedNames) {
            foreach ($allowedNames as $name) {
                // select directory with nested files
                if (in_array(substr($name, -1), ['/', '\\'])) {
                    if (strncasecmp($name, $key, strlen($name)) === 0) {
                        // that's a file inside a dir or that dir
                        return self::SELECT_FILTER_PASS;
                    }
                } else {
                    // select exact name only
                    if (strcasecmp($name, $key) === 0) {
                        // that's a file with this name
                        return self::SELECT_FILTER_PASS;
                    }
                }
            }

            // that file is not in allowed list
            return self::SELECT_FILTER_REFUSE;
        };
    }

    /**
     * @param string $regex
     * @return \Closure
     */
    protected function createByEregSelector($regex)
    {
        return function ($key, $value) use ($regex) {
            return (ereg($regex, $key) !== false)
                ? self::SELECT_FILTER_PASS
                : self::SELECT_FILTER_REFUSE;
        };
    }

    /**
     * @param $regex
     * @return \Closure
     */
    protected function createByPregSelector($regex)
    {
        return function ($key, $value) use ($regex) {
            return preg_match($regex, $key)
                ? self::SELECT_FILTER_PASS
                : self::SELECT_FILTER_REFUSE;
        };
    }

    /**
     * @param array $options
     * @return callable
     */
    protected function createSelector(array $options)
    {
        // exact matching
        if (isset($options[PCLZIP_OPT_BY_NAME]))
            $selectFilter = $this->createByNameSelector($options[PCLZIP_OPT_BY_NAME]);
        // <ereg> rule
        else if (isset($options[PCLZIP_OPT_BY_EREG]) && function_exists('ereg'))
            $selectFilter = $this->createByEregSelector($options[PCLZIP_OPT_BY_EREG]);
        // <preg_match> rule
        else if (isset($options[PCLZIP_OPT_BY_PREG]))
            $selectFilter = $this->createByPregSelector($options[PCLZIP_OPT_BY_PREG]);
        // index rule
        else if (isset($options[PCLZIP_OPT_BY_INDEX]))
            $selectFilter = $this->createByIndexSelector($options[PCLZIP_OPT_BY_INDEX]);
        // no rule
        else
            $selectFilter = function () {
                return self::SELECT_FILTER_PASS;
            };
        return $selectFilter;
    }

    /**
     * @param array $args
     * @return array
     */
    protected function makeOptionsFromArguments(array $args)
    {
        array_shift($args);

        // parse options
        if (isset($args[0]) && is_string($args[0])) {
            $options = [
                PCLZIP_OPT_ADD_PATH => $args[0]
            ];

            if (isset($args[1]) && is_string($args[1])) {
                $options[PCLZIP_OPT_REMOVE_PATH] = $args[1];
            }
        } else {
            $options = $this->makeKeyValueArrayFromList($args);
        }
        return $options;
    }

    /**
     * @param array $options
     * @param string|null $currentComment
     * @return mixed|string|null
     */
    protected function buildComment(array $options, $currentComment)
    {
        $comment = null;
        if (isset($options[PCLZIP_OPT_COMMENT]))
            $comment = $options[PCLZIP_OPT_COMMENT];
        else if (isset($options[PCLZIP_OPT_ADD_COMMENT])) {;
            $comment = $currentComment . $options[PCLZIP_OPT_ADD_COMMENT];
        } else if (isset($options[PCLZIP_OPT_PREPEND_COMMENT])) {
            $comment = $options[PCLZIP_OPT_PREPEND_COMMENT] . $currentComment;
        }
        return $comment;
    }
}
