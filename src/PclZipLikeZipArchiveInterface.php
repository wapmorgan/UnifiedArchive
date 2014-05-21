<?php
namespace wapmorgan\UnifiedArchive;

//
// Average ratio of zip compression: 2x
//
defined('AVERAGE_ZIP_COMPRESSION_RATIO') or define('AVERAGE_ZIP_COMPRESSION_RATION', 2);

class PclZipLikeZipArchiveInterface {
	private $archive;

	public function __construct(\ZipArchive $archive) {
		$this->archive = $archive;
	}

	public function createFileHeader($localname, $filename) {
		return (object)array(
			'filename' => $filename,
			'stored_filename' => $localname,
			'size' => filesize($filename),
			'compressed_size' => ceil(filesize($filename) / AVERAGE_ZIP_COMPRESSION_RATIO),
			'mtime' => filemtime($filename),
			'comment' => null,
			'folder' => is_dir($filename),
			'status' => 'ok',
		);
	}

	/**
	 * Creates a new archive
	 */
	public function create($content) {
		if (is_array($content)) $paths_list = $content;
		else $paths_list = array_map(explode(',', $content));
		$report = array();

		$options = func_get_args();
		array_shift($options);

		// parse options
		if (isset($options[0]) && is_string($optios[0])) {
			$options[PCLZIP_OPT_ADD_PATH] = $options[0];
			if (isset($options[1]) && is_string($optios[1])) {
				$options[PCLZIP_OPT_REMOVE_PATH] = $options[1];
			}
		} else {
			$options = array_combine(
				array_filter($options, function ($v) {return (bool)$v&2}),
				array_filter($options, function ($v) {return (bool)($v-1)&2})
			);
		}

		// filters initiation
		$filters = array();
		if (isset($options[PCLZIP_OPT_REMOVE_PATH]) && !isset($options[PCLZIP_OPT_REMOVE_ALL_PATH])) $filters[] = function (&$key, &$value) use ($options[PCLZIP_OPT_REMOVE_PATH]) { $key = str_replace($key, null, $key); };
		if (isset($options[PCLZIP_OPT_REMOVE_ALL_PATH])) $filters[] = function (&$key, &$value) { $key = basename($key); };
		if (isset($options[PCLZIP_OPT_ADD_PATH])) $filters[] = function (&$key, &$value) use ($options[PCLZIP_OPT_ADD_PATH]) { $key = rtrim($options[PCLZIP_OPT_ADD_PATH], '/').'/'.ltrim($key, '/'); };

		if (isset($options[PCLZIP_CB_PRE_ADD]) && is_callable($options[PCLZIP_CB_PRE_ADD])) $preAddCallback = $options[PCLZIP_CB_PRE_ADD];
		else $preAddCallback = function() { return 1; }

		if (isset($options[PCLZIP_CB_POST_ADD]) && is_callable($options[PCLZIP_CB_POST_ADD])) $postAddCallback = $options[PCLZIP_CB_POST_ADD];
		else $postAddCallback = function() { return 1; }

		if (isset($options[PCLZIP_OPT_COMMENT])) $this->archive->setArchiveComment($options[PCLZIP_OPT_COMMENT]);


		// scan filesystem for files list
		$files_list = array();
		foreach ($content as $file_to_add) {
			$report[] = $this->addSnippet($file_to_add, $filters, $preAddCallback, $postAddCallback);

			// additional dir contents
			if (is_dir($file_to_add)) {
				$directory_contents = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($file_to_add, \RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
				foreach ($directory_contents as $file_to_add) {
					$report[] = $this->addSnippet($file_to_add, $filters, $preAddCallback, $postAddCallback);
				}
			}
		}
		// ...
		return $report;
	}

	private function addSnippet($file_to_add, array $filters, $preAddCallback, $postAddCallback) {
		if (is_file($file_to_add) || is_dir($file_to_add)) {
			// apply filters to a file
			$localname = $file_to_add;
			$filename = $file_to_add;
			foreach ($filters as $filter) call_user_func($filter, $localname, $filename);
			$file_header = $this->createFileHeader($localname, $filename);
			if (call_user_func($preAddCallback, $file_header) == 1) {
				//
				// Check for max length > 255
				//
				if (strlen(basename($file_header->stored_filename)) > 255) $file_header->status = 'filename_too_long';
				if (is_file($filename))
					$this->archive->addFile($file_header->filename, $file_header->stored_filename);
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
	public function listContent() {
		$filesList = array();
		$numFiles = $this->archive->numFiles;
		for ($i = 0; $i < $numFiles; $i++) {
			$statIndex = $this->archive->statIndex($i);
			$filesList[] = (object)array(
				'filename' => $statIndex['name'],
				'stored_filename' => $statIndex['name'],
				'size' => $statIndex['size'],
				'compressed_size' => $statIndex['comp_size'],
				'mtime' => $statIndex,
				'comment' => (($comment = $this->archive->getCommentIndex($statIndex['index']) !== false) ? $comment : null,
				'folder' => in_array(substr($statIndex['name'], -1), array('/', '\\'))
				'index' => $statIndex['index'],
				'status' => 'ok',
			);
		}
		return $filesList;
	}

	public function extract() {}
	public function properties() {}
	public function add() {}
	public function delete() {}
	public function merge() {}
	public function duplicate() {}
}
