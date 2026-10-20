<?php

/**
 * Description of filehandler
 *
 * @author Stoyvo
 */
class FileHandler {

    protected $_fullFilePath;

    public function __construct($fileName) {
        $this->_fullFilePath = trim(DATA_PATH . $fileName);
    }

    public function read() {
        // Missing files are normal on a fresh install; return empty content
        // instead of emitting a PHP warning for every caller.
        if (!is_file($this->_fullFilePath)) {
            return '';
        }
        $content = file_get_contents($this->_fullFilePath);
        return ($content === false) ? '' : $content;
    }

    public function write($content) {
        $dirPath = dirname($this->_fullFilePath);
        if (!is_dir($dirPath)) {
            mkdir($dirPath, 0775, true);
        }
        if (file_put_contents($this->_fullFilePath, $content) === false) {
            return false;
        }
        // Owner/group writable only; never world-writable (0777).
        @chmod($this->_fullFilePath, 0664);
        return true;
    }

    /*
     * Get date/time last time file was modified
     * return (int) seconds since last file modification
     */
    public function lastTimeModified() {
        if (!is_file($this->_fullFilePath)) {
            // Never fetched yet: report a huge age so caches refresh.
            return PHP_INT_MAX;
        }

        $last_mod = @filemtime($this->_fullFilePath);
        if ($last_mod === false) {
            return PHP_INT_MAX;
        }

        $timeDiff = (time() - $last_mod) - 218;

        return $timeDiff;
    }
    
    /*
     * Check if the file is writable or not.
     */
    public function isWritable() {
        return is_writable($this->_fullFilePath);
    }
    
    /*
     * Check if the file exists or not.
     */
    public function fileExists() {
        return file_exists($this->_fullFilePath);
    }

}
