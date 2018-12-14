<?php
namespace wapmorgan\UnifiedArchive;

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

class PclzipZipInterface
{
    const SELECT_FILTER_PASS = 1;
    const SELECT_FILTER_REFUSE = 0;

    const AVERAGE_ZIP_COMPRESSION_RATIO = 2;

    private $archive;

    /**
     * PclzipZipInterface constructor.
     *
     * @param \ZipArchive $archive
     */
    public function __construct(\ZipArchive $archive)
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
        $report = array();

        $options = func_get_args();
        array_shift($options);

        // parse options
        if (isset($options[0]) && is_string($options[0])) {
            $options[PCLZIP_OPT_ADD_PATH] = $options[0];
            if (isset($options[1]) && is_string($options[1])) {
                $options[PCLZIP_OPT_REMOVE_PATH] = $options[1];
            }
        } else {
            $options = array_combine(
                array_filter($options, function ($v) {return (bool) $v&2;}),
                array_filter($options, function ($v) {return (bool) ($v-1)&2;})
            );
        }

        // filters initiation
        $filters = array();
        if (isset($options[PCLZIP_OPT_REMOVE_PATH])
            && !isset($options[PCLZIP_OPT_REMOVE_ALL_PATH]))
            $filters[] = function (&$key, &$value) use ($options) {
                $key = str_replace($key, null, $key); };
        if (isset($options[PCLZIP_OPT_REMOVE_ALL_PATH]))
            $filters[] = function (&$key, &$value) { $key = basename($key); };
        if (isset($options[PCLZIP_OPT_ADD_PATH]))
            $filters[] = function (&$key, &$value) use ($options) {
                $key = rtrim($options[PCLZIP_OPT_ADD_PATH], '/').'/'.
                    ltrim($key, '/');
            };

        if (isset($options[PCLZIP_CB_PRE_ADD])
            && is_callable($options[PCLZIP_CB_PRE_ADD]))
            $preAddCallback = $options[PCLZIP_CB_PRE_ADD];
        else $preAddCallback = function () { return 1; };

        if (isset($options[PCLZIP_CB_POST_ADD])
            && is_callable($options[PCLZIP_CB_POST_ADD]))
            $postAddCallback = $options[PCLZIP_CB_POST_ADD];
        else $postAddCallback = function () { return 1; };

        if (isset($options[PCLZIP_OPT_COMMENT]))
            $this->archive->setArchiveComment($options[PCLZIP_OPT_COMMENT]);

        // scan filesystem for files list
        $files_list = array();
        foreach ($content as $file_to_add) {
            $report[] = $this->addSnippet($file_to_add, $filters,
                $preAddCallback, $postAddCallback);

            // additional dir contents
            if (is_dir($file_to_add)) {
                $directory_contents = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator(
                        $file_to_add, \RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST);
                foreach ($directory_contents as $file_to_add) {
                    $report[] = $this->addSnippet($file_to_add, $filters,
                        $preAddCallback, $postAddCallback);
                }
            }
        }
        // ...
        return $report;
    }

    private function addSnippet($file_to_add, array $filters, $preAddCallback,
        $postAddCallback)
    {
        if (is_file($file_to_add) || is_dir($file_to_add)) {
            // apply filters to a file
            $localname = $file_to_add;
            $filename = $file_to_add;
            foreach ($filters as $filter)
                call_user_func($filter, $localname, $filename);
            $file_header = $this->createFileHeader($localname, $filename);
            if (call_user_func($preAddCallback, $file_header) == 1) {
                //
                // Check for max length > 255
                //
                if (strlen(basename($file_header->stored_filename)) > 255)
                    $file_header->status = 'filename_too_long';
                if (is_file($filename))
                    $this->archive->addFile($file_header->filename,
                        $file_header->stored_filename);
                else if (is_dir($filename))
                    $this->archive->addEmptyDir($file_header->stored_filename);
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
     */
    public function listContent()
    {
        $filesList = array();
        $numFiles = $this->archive->numFiles;
        for ($i = 0; $i < $numFiles; $i++) {
            $statIndex = $this->archive->statIndex($i);
            $filesList[] = (object) array(
                'filename' => $statIndex['name'],
                'stored_filename' => $statIndex['name'],
                'size' => $statIndex['size'],
                'compressed_size' => $statIndex['comp_size'],
                'mtime' => $statIndex,
                'comment' => ($comment = $this->archive->getCommentIndex
                    ($statIndex['index']) !== false) ? $comment : null,
                'folder' => in_array(substr($statIndex['name'], -1),
                    array('/', '\\')),
                'index' => $statIndex['index'],
                'status' => 'ok',
            );
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
        array_shift($options);

        // parse options
        if (isset($options[0]) && is_string($options[0])) {
            $options[PCLZIP_OPT_PATH] = $options[0];
            if (isset($options[1]) && is_string($options[1])) {
                $options[PCLZIP_OPT_REMOVE_PATH] = $options[1];
            }
        } else {
            $options = array_combine(
                array_filter($options, function ($v) {return (bool) $v&2;}),
                array_filter($options, function ($v) {return (bool) ($v-1)&2;})
            );
        }

        // filters initiation
        if (isset($options[PCLZIP_OPT_PATH]))
            $extractPath = rtrim($options[PCLZIP_OPT_PATH], '/');
        else $extractPath = rtrim(getcwd(), '/');

        $filters = array();
        if (isset($options[PCLZIP_OPT_REMOVE_PATH])
            && !isset($options[PCLZIP_OPT_REMOVE_ALL_PATH]))
            $filters[] = function (&$key, &$value) use ($options) {
                $key = str_replace($key, null, $key);
            };
        if (isset($options[PCLZIP_OPT_REMOVE_ALL_PATH]))
            $filters[] = function (&$key, &$value) { $key = basename($key); };
        if (isset($options[PCLZIP_OPT_ADD_PATH]))
            $filters[] = function (&$key, &$value) use ($options) {
                $key = rtrim($options[PCLZIP_OPT_ADD_PATH], '/').'/'.
                    ltrim($key, '/');
            };

        if (isset($options[PCLZIP_CB_PRE_EXTRACT])
            && is_callable($options[PCLZIP_CB_PRE_EXTRACT]))
            $preExtractCallback = $options[PCLZIP_CB_PRE_EXTRACT];
        else $preExtractCallback = function () { return 1; };

        if (isset($options[PCLZIP_CB_POST_EXTRACT])
            && is_callable($options[PCLZIP_CB_POST_EXTRACT]))
            $postExtractCallback = $options[PCLZIP_CB_POST_EXTRACT];
        else $postExtractCallback = function () { return 1; };

        // exact matching
        if (isset($options[PCLZIP_OPT_BY_NAME]))
            $selectFilter = function ($key, $value) use ($options) {
                $allowedNames = is_array($options[PCLZIP_OPT_BY_NAME])
                    ? $options[PCLZIP_OPT_BY_NAME]
                    : explode(',', $options[PCLZIP_OPT_BY_NAME]);
                foreach ($allowedNames as $name) {
                    // select directory with nested files
                    if (in_array(substr($name, -1), array('/', '\\'))) {
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
        // <ereg> rule
        else if (isset($options[PCLZIP_OPT_BY_EREG]) && function_exists('ereg'))
            $selectFilter = function ($key, $value) use ($options) {
                return (ereg($options[PCLZIP_OPT_BY_EREG], $key) !== false)
                    ? self::SELECT_FILTER_PASS
                    : self::SELECT_FILTER_REFUSE;
            };
        // <preg_match> rule
        else if (isset($options[PCLZIP_OPT_BY_PREG]))
            $selectFilter = function ($key, $value) use ($options) {
                return preg_match($options[PCLZIP_OPT_BY_PREG], $key)
                    ? self::SELECT_FILTER_PASS
                    : self::SELECT_FILTER_REFUSE;
            };
        // index rule
        else if (isset($options[PCLZIP_OPT_BY_INDEX]))
            $selectFilter = function ($key, $value, $index) use ($options) {
                $allowedIndexes = array();
                foreach ($options[PCLZIP_OPT_BY_INDEX] as $rule) {
                    $parts = explode('-', $rule);
                    if (count($parts) == 1) $allowedIndexes[] = $rule;
                    else $allowedIndexes = array_merge(
                        range($parts[0], $parts[1]), $allowedIndexes);
                }

                return in_array($index, $allowedIndexes) ? self::SELECT_FILTER_PASS
                    : self::SELECT_FILTER_REFUSE;
            };
        // no rule
        else
            $selectFilter = function () { return self::SELECT_FILTER_PASS; };

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
                $file_header->filename = realpath($extractPath.'/'.
                    $file_header->filename);
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
                    = $this->archive->getFromName($file_header->stored_filename);
            }
            // echo content
            else if ($anotherOutputFormat == PCLZIP_OPT_EXTRACT_IN_OUTPUT) {
                echo $this->archive->getFromName($file_header->stored_filename);
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
                        if (!mkdir($file_header)) {
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
                    // check if file path is not writable
                    if (!is_writable($file_header->filename)) {
                        $file_header->status = 'write_protected';
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
                    if (!is_dir($directory)) {
                        if (!mkdir($directory)) {
                            $file_header->status = 'path_creation_fail';
                            continue;
                        }
                    }
                    // extraction
                    if (copy("zip://".$this->archive->filename."#"
                        .$file_header->stored_filename
                        , $file_header->filename)) {
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

        return $report;
    }

    /**
     * Reads properties of archive
     */
    public function properties()
    {
        return array(
            'nb' => $this->archive->numFiles,
            'comment' =>
                (($comment = $this->archive->getArchiveComment() !== false)
                    ? $comment : null),
            'status' => 'OK',
        );
    }

    /**
     * Adds files in archive
     * <code>add($content, [$addDir, [$removeDir]])</code>
     * <code>add($content, [ ... options ... ])</code>
     */
    public function add($content)
    {
        if (is_array($content)) $paths_list = $content;
        else $paths_list = array_map(explode(',', $content));
        $report = array();

        $options = func_get_args();
        array_shift($options);

        // parse options
        if (isset($options[0]) && is_string($options[0])) {
            $options[PCLZIP_OPT_ADD_PATH] = $options[0];
            if (isset($options[1]) && is_string($options[1])) {
                $options[PCLZIP_OPT_REMOVE_PATH] = $options[1];
            }
        } else {
            $options = array_combine(
                array_filter($options, function ($v) {return (bool) $v&2;}),
                array_filter($options, function ($v) {return (bool) ($v-1)&2;})
            );
        }

        // filters initiation
        $filters = array();
        if (isset($options[PCLZIP_OPT_REMOVE_PATH])
            && !isset($options[PCLZIP_OPT_REMOVE_ALL_PATH]))
            $filters[] = function (&$key, &$value) use ($options) {
                $key = str_replace($key, null, $key);
            };
        if (isset($options[PCLZIP_OPT_REMOVE_ALL_PATH]))
            $filters[] = function (&$key, &$value) { $key = basename($key); };
        if (isset($options[PCLZIP_OPT_ADD_PATH]))
            $filters[] = function (&$key, &$value) use ($options) {
                $key = rtrim($options[PCLZIP_OPT_ADD_PATH], '/').'/'.
                    ltrim($key, '/');
            };

        if (isset($options[PCLZIP_CB_PRE_ADD])
            && is_callable($options[PCLZIP_CB_PRE_ADD]))
            $preAddCallback = $options[PCLZIP_CB_PRE_ADD];
        else $preAddCallback = function () { return 1; };

        if (isset($options[PCLZIP_CB_POST_ADD])
            && is_callable($options[PCLZIP_CB_POST_ADD]))
            $postAddCallback = $options[PCLZIP_CB_POST_ADD];
        else $postAddCallback = function () { return 1; };

        if (isset($options[PCLZIP_OPT_COMMENT]))
            $this->archive->setArchiveComment($options[PCLZIP_OPT_COMMENT]);
        if (isset($options[PCLZIP_OPT_ADD_COMMENT])) {
            $comment =
                ($comment = $this->archive->getArchiveComment() !== false)
                    ? $comment : null;
            $this->archive->setArchiveComment(
                $comment . $options[PCLZIP_OPT_ADD_COMMENT]);
        }
        if (isset($options[PCLZIP_OPT_PREPEND_COMMENT])) {
            $comment =
                ($comment = $this->archive->getArchiveComment() !== false)
                    ? $comment : null;
            $this->archive->setArchiveComment(
                $options[PCLZIP_OPT_PREPEND_COMMENT] . $comment);
        }


        // scan filesystem for files list
        $files_list = array();
        foreach ($content as $file_to_add) {
            $report[] = $this->addSnippet($file_to_add, $filters,
                $preAddCallback, $postAddCallback);

            // additional dir contents
            if (is_dir($file_to_add)) {
                $directory_contents = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator(
                        $file_to_add, \RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST);
                foreach ($directory_contents as $file_to_add) {
                    $report[] = $this->addSnippet($file_to_add, $filters,
                        $preAddCallback, $postAddCallback);
                }
            }
        }
        // ...
        return $report;
    }

    /**
     * Removes files from archive
     * Usage:
     * <code>delete([... options ...])</code>
     */
    public function delete()
    {
        $report = array();
        $options = func_get_args();
        $options = array_combine(
            array_filter($options, function ($v) {return (bool) $v&2;}),
            array_filter($options, function ($v) {return (bool) ($v-1)&2;})
        );

        // exact matching
        if (isset($options[PCLZIP_OPT_BY_NAME]))
            $selectFilter = function ($key, $value) use ($options) {
                $allowedNames = is_array($options[PCLZIP_OPT_BY_NAME])
                    ? $options[PCLZIP_OPT_BY_NAME]
                    : explode(',', $options[PCLZIP_OPT_BY_NAME]);
                foreach ($allowedNames as $name) {
                    // select directory with nested files
                    if (in_array(substr($name, -1), array('/', '\\'))) {
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
        // <ereg> rule
        else if (isset($options[PCLZIP_OPT_BY_EREG]) && function_exists('ereg'))
            $selectFilter = function ($key, $value) use ($options) {
                return (ereg($options[PCLZIP_OPT_BY_EREG], $key) !== false)
                    ? self::SELECT_FILTER_PASS
                    : self::SELECT_FILTER_REFUSE;
            };
        // <preg_match> rule
        else if (isset($options[PCLZIP_OPT_BY_PREG]))
            $selectFilter = function ($key, $value) use ($options) {
                return preg_match($options[PCLZIP_OPT_BY_PREG], $key)
                    ? self::SELECT_FILTER_PASS
                    : self::SELECT_FILTER_REFUSE;
            };
        // index rule
        else if (isset($options[PCLZIP_OPT_BY_INDEX]))
            $selectFilter = function ($key, $value, $index) use ($options) {
                $allowedIndexes = array();
                foreach ($options[PCLZIP_OPT_BY_INDEX] as $rule) {
                    $parts = explode('-', $rule);
                    if (count($parts) == 1) $allowedIndexes[] = $rule;
                    else $allowedIndexes = array_merge(
                        range($parts[0], $parts[1]), $allowedIndexes);
                }

                return in_array($index, $allowedIndexes)
                    ? self::SELECT_FILTER_PASS
                    : self::SELECT_FILTER_REFUSE;
            };
        // no rule
        else
            $selectFilter = function () { return self::SELECT_FILTER_PASS; };

        foreach ($this->listContent() as $file_header) {
            // select by select rule
            if (call_user_func($selectFilter, $file_header->stored_filename,
                    $file_header->filename, $file_header->index)
                === self::SELECT_FILTER_REFUSE) {
                // delete file from archive
                if ($this->archive->deleteName($file_header->stored_filename)) {
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
            if ($a = UnifiedArchive::open($a) !== null) {
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
        foreach ($a->getFileNames() as $filename) {
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
     */
    public function duplicate($clone_filename)
    {
        return copy($this->archive->filename, $clone_filename) ? 1 : 0;
    }
}