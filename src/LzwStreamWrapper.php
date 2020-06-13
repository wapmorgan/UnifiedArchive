<?php
namespace wapmorgan\UnifiedArchive;

/**
 * Stream-wrapper and handler for lzw-compressed data.
 * @requires "compress" system command (linux-only)
 *
 * @package wapmorgan\UnifiedArchive
 */
class LzwStreamWrapper
{
    private static $registered = false;
    private static $installed;

    /**
     *
     */
    public static function registerWrapper()
    {
        if (!self::$registered)
            stream_wrapper_register('compress.lzw', __CLASS__);
        self::$registered = true;
    }

    public static $TMP_FILE_THRESHOLD = 0.5;
    private static $AVERAGE_COMPRESSION_RATIO = 2;
    public static $forceTmpFile = false;
    /** High limit. unit: MBytes.
    */
    public static $highLimit = 512;

    private $mode;
    private $path;
    private $tmp;
    private $tmp2;
    private $data;
    private $dataSize;
    private $pointer;
    private $writtenBytes = 0;

    /**
     * @param $path
     * @param $mode
     * @param $options
     * @return bool
     * @throws \Exception
     */
    public function stream_open($path, $mode, $options)
    {
        // check for compress & uncompress utility
        $this->checkBinary();
        if (self::$installed === false)
            throw new \Exception('compress and uncompress commands are required');

        $schema = 'compress.lzw://';
        if (strncasecmp($schema, $path, strlen($schema)) == 0)
            $path = substr($path, strlen($schema));

        if (file_exists($path)) {
            $this->path = realpath($path);
            $expected_data_size = filesize($path)
             * self::$AVERAGE_COMPRESSION_RATIO;
            $available_memory = $this->getAvailableMemory();
            if ($expected_data_size <=
                (self::$TMP_FILE_THRESHOLD * $available_memory)
                && !self::$forceTmpFile
                && $expected_data_size < (self::$highLimit * 1024 * 1024)) {
                $this->read();
            } else {
                $prefix = basename(__FILE__, '.php');
                if (($tmp = tempnam(sys_get_temp_dir(), $prefix)) === false)
                    throw new \Exception(__CLASS__.', line '.__LINE__.
                        ': Could not create temporary file in '.
                        sys_get_temp_dir());
                if (($tmp2 = tempnam(sys_get_temp_dir(), $prefix)) === false)
                    throw new \Exception(__CLASS__.', line '.__LINE__.
                        ': Could not create temporary file in '.
                        sys_get_temp_dir());
                $this->tmp = $tmp;
                $this->tmp2 = $tmp2;
                $this->read();
            }
        } else {
            $this->path = $path;
            if (self::$forceTmpFile) {
                $prefix = basename(__FILE__, '.php');
                if (($tmp = tempnam(sys_get_temp_dir(), $prefix)) === false)
                    throw new \Exception(__CLASS__.', line '.__LINE__.
                        ': Could not create temporary file in '.
                        sys_get_temp_dir());
                if (($tmp2 = tempnam(sys_get_temp_dir(), $prefix)) === false)
                    throw new \Exception(__CLASS__.', line '.__LINE__.
                        ': Could not create temporary file in '.
                        sys_get_temp_dir());
                $this->tmp = $tmp;
                $this->tmp2 = $tmp2;
                $this->pointer = 0;
            } else {
                $this->pointer = 0;
            }
        }
        $this->mode = $mode;

        return true;
    }

    /**
     * @return float|int|string
     * @throws \Exception
     */
    public function getAvailableMemory()
    {
        $limit = strtoupper(ini_get('memory_limit'));
        $s = array('K', 'M', 'G');
        if (($multipleer = array_search(substr($limit, -1), $s)) !== false) {
            $limit = substr($limit, 0, -1) * pow(1024, $multipleer + 1);
            $limit -= memory_get_usage();
        } elseif ($limit == -1) {
            $limit = $this->getSystemMemory();
        }
        // var_dump(['multipleer' => $multipleer]);
        // var_dump(['memory_limit' => $memory_limit]);
        return $limit;
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function getSystemMemory()
    {
        self::exec('free --bytes | head -n3 | tail -n1 | awk \'{print $4}\'',
            $output, $resultCode);

        return trim($output);
    }

    /**
     * @param $command
     * @param $output
     * @param null $resultCode
     * @throws \Exception
     */
    private static function exec($command, &$output, &$resultCode = null)
    {
        if (function_exists('system')) {
            ob_start();
            system($command, $resultCode);
            $output = ob_get_contents();
            ob_end_clean();

            return;
        } elseif (function_exists('exec')) {
            $execOutput = array();
            exec($command, $execOutput, $resultCode);
            $output = implode(PHP_EOL, $execOutput);

            return;
        } elseif (function_exists('proc_open')) {
            $process = proc_open($command, array(1 =>
                fopen('php://memory', 'w')), $pipes);
            $output = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $resultCode = proc_close($process);

            return;
        } elseif (function_exists('shell_exec')) {
            $output = shell_exec($command);

            return;
        } else {
            throw new \Exception(__FILE__.', line '.__LINE__
                .': Execution functions is required! Make sure one of exec'.
                ' function is allowed (system, exec, proc_open, shell_exec)');
        }
    }

    /**
     * @throws \Exception
     */
    private function read()
    {
        if ($this->tmp !== null) {
            self::exec('uncompress --stdout '.escapeshellarg($this->path).
                ' > '.$this->tmp, $output, $resultCode);
            // var_dump(['command' => 'uncompress --stdout '.
            // escapeshellarg($this->path).' > '.$this->tmp, 'output' =>
            // $output, 'resultCode' => $resultCode]);
            if ($resultCode == 0 || $resultCode == 2 || is_null($resultCode)) {
                $this->dataSize = filesize($this->tmp);
                // rewind pointer
                $this->pointer = 0;
            } else {
                throw new \Exception(__FILE__.', line '.__LINE__.
                    ': Could not read file '.$this->path);
            }
        } else {
            self::exec('uncompress --stdout '.escapeshellarg($this->path),
                $output, $resultCode);
            $this->data = &$output;
            if ($resultCode == 0 || $resultCode == 2 || is_null($resultCode)) {
                $this->dataSize = strlen($this->data);
                // rewind pointer
                $this->pointer = 0;
            } else {
                throw new \Exception(__FILE__.', line '.__LINE__.
                    ': Could not read file '.$this->path);
            }
        }
    }

    /**
     * @return array
     */
    public function stream_stat()
    {
        return array(
            'size' => $this->dataSize,
        );
    }

    /**
     * @throws \Exception
     */
    public function stream_close()
    {
        // rewrite file
        if ($this->writtenBytes > 0) {
            // stored in temp file
            if ($this->tmp !== null) {
                // compress in tmp2
                self::exec('compress -c '.escapeshellarg($this->tmp).' > '.
                    escapeshellarg($this->tmp2), $output, $code);

                // escapeshellarg($this->tmp).' > '.escapeshellarg($this->tmp2),
                // 'output' => $output, 'code' => $code]);
                if ($code == 0 || $code == 2 || is_null($code)) {
                    // rewrite original file
                    if (rename($this->tmp2, $this->path) !== true) {
                        throw new \RuntimeException(__FILE__ . ', line ' . __LINE__ .
                            ': Could not replace original file ' . $this->path);
                    }
                } else {
                    throw new \RuntimeException(__FILE__.', line '.__LINE__.
                        ': Could not compress changed data in '.$this->tmp2);
                }
            } else { // stored in local var
                // compress in original path
                // $this->exec('compress '.escapeshellarg($this->tmp).' > '.
                // escapeshellarg($this->tmp2), $output, $resultCode);
                if (!function_exists('proc_open')) {
                    throw new \Exception('proc_open is necessary for writing '.
                        'changed data in the file');
                }
                //var_dump(['command' => 'compress > '.
                // escapeshellarg($this->path), 'path' => $this->path]);
                $process = proc_open('compress > '.escapeshellarg($this->path),
                    array(0 => array('pipe', 'r')), $pipes);
                // write data to process' input
                fwrite($pipes[0], $this->data);
                fclose($pipes[0]);
                $resultCode = proc_close($process);
                if ($resultCode == 0 || $resultCode == 2) {
                    // ok
                } else {
                    throw new \RuntimeException(__FILE__.', line '.__LINE__.
                        ': Could not compress changed data in '.$this->path);
                }
            }
        }
        if ($this->tmp !== null) {
            unlink($this->tmp);
            if (file_exists($this->tmp2)) unlink($this->tmp2);
        } else {
            $this->data = null;
        }
    }

    /**
     * @param $count
     * @return bool|string
     */
    public function stream_read($count)
    {
        if ($this->tmp !== null) {
            $fp = fopen($this->tmp, 'r'.(strpos($this->mode, 'b') !== 0 ? 'b'
                : null));
            fseek($fp, $this->pointer);
            $data = fread($fp, $count);
            $this->pointer = ftell($fp);
            fclose($fp);

            return $data;
        } else {
            $data = substr($this->data, $this->pointer,
                ($this->pointer + $count));
            $this->pointer = $this->pointer + $count;

            return $data;
        }
    }

    /**
     * @return bool
     */
    public function stream_eof()
    {
        return $this->pointer >= $this->dataSize;
    }

    /**
     * @return mixed
     */
    public function stream_tell()
    {
        return $this->pointer;
    }

    /**
     * @param $data
     * @return bool|int
     */
    public function stream_write($data)
    {
        $this->writtenBytes += strlen($data);
        if ($this->tmp !== null) {
            $fp = fopen($this->tmp, 'w'.(strpos($this->mode, 'b') !== 0 ? 'b'
                : null));
            fseek($fp, $this->pointer);
            $count = fwrite($fp, $data);
            $this->pointer += $count;
            fclose($fp);

            return $count;
        } else {
            $count = strlen($data);
            $prefix = substr($this->data, 0, $this->pointer);
            $postfix = substr($this->data, ($this->pointer + $count));
            $this->data = $prefix.$data.$postfix;
            $this->pointer += $count;

            return $count;
        }
    }

    /**
     * @param $offset
     * @param int $whence
     * @return bool
     */
    public function stream_seek($offset, $whence = SEEK_SET)
    {
        switch ($whence) {
            case SEEK_SET:
                $this->pointer = $offset;
                break;
            case SEEK_CUR:
                $this->pointer += $offset;
                break;
            case SEEK_END:
                $actual_data_size = (is_null($this->tmp)) ? strlen($this->data)
                    : filesize($this->tmp);
                $this->pointer = $actual_data_size - $offset;
                break;
            default:
                return false;
        }

        return true;
    }

    /**
     * @param $operation
     * @return bool
     */
    public function stream_lock($operation)
    {
        if ($this->tmp !== null) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * @param $new_size
     */
    public function stream_truncate($new_size)
    {
        $actual_data_size = (is_null($this->tmp)) ? strlen($this->data)
            : filesize($this->tmp);
        if ($new_size > $actual_data_size) {
            $this->stream_write(str_repeat("\00", $new_size
                - $actual_data_size));
        } elseif ($new_size < $actual_data_size) {
            if ($this->tmp === null) {
                $this->data = substr($this->data, 0, $new_size);
            } else {
                $fp = fopen($this->tmp, 'w'.(strpos($this->mode, 'b') !== 0
                    ? 'b' : null));
                ftruncate($fp, $new_size);
                fclose($fp);
            }
        }
    }

    /**
     * @throws \Exception
     */
    protected static function checkBinary()
    {
        if (self::$installed === null) {
            if (strncasecmp(PHP_OS, 'win', 3) === 0) {
                self::$installed = false;
            } else {
                self::exec('command -v compress', $output);
                if (empty($output)) {
                    self::$installed = false;
                } else {
                    self::exec('command -v uncompress', $output);
                    if (empty($output)) {
                        self::$installed = false;
                    } else {
                        self::$installed = true;
                    }
                }
            }
        }
    }

    /**
     * @return boolean
     * @throws \Exception
     */
    public static function isBinaryAvailable()
    {
        self::checkBinary();
        return self::$installed;
    }
}
