<?php

/*
 * @author Stoyvo
 */
class Config_Abstract {

    // Data
    protected $_fileHandler;
    protected $_data = array();

    // Objects (protected so subclasses such as Rigs/Pools/Wallets share one
    // store instead of silently shadowing this with dynamic properties).
    protected $_objs = array();

    public function __construct() {
        $this->_fileHandler = new FileHandler($this->_config);
        $decoded = json_decode($this->_fileHandler->read(), true);
        $this->_data = is_array($decoded) ? $decoded : array();

        if (isset($_GET['id']) || isset($_POST['id'])) {
            $id = (isset($_GET['id']) ? $_GET['id'] : $_POST['id']);
            $id = intval($id)-1;
            if (isset($this->_data[$id])) {
                $this->add($this->_data[$id]);
            }
        } else if (!empty($this->_data)) {
            foreach ($this->_data as $id => $obj) {
                $this->add($obj);
            }
        }
    }

    public function remove() {
        // Remove functionality is only available one at a time
        if (!isset($_POST['id'])) {
            header("HTTP/1.0 406 Not Acceptable");
            return false;
        }

        $id = intval($_POST['id'])-1;

        unset($this->_data[$id]);
        $this->_data = array_values($this->_data);

        return $this->write();
    }

    public function write() {
        $this->_fileHandler->write(json_encode($this->_data));

        header("HTTP/1.0 202 Accepted");
        return true;
    }

}
