<?php

/**
 * Abstract
 *
 * PHP version 5
 *
 * @category Deployment
 * @package  Application
 * @author   Sven Varkel <sven.varkel@eepohs.com>
 * @license  http://eepohs.com/ Eepohs Special License
 * @link     http://esc.eepohs.com/ Eepohs Software Channel
 */
//namespace Application;

/**
 * Abstract
 *
 * @category Deployment
 * @package  Application
 * @author   Sven Varkel <sven.varkel@eepohs.com>
 * @license  http://eepohs.com/ Eepohs Special License
 * @link     http://esc.eepohs.com/ Eepohs Software Channel
 */
abstract class Eepohs_Erply_Model_Abstract extends Mage_Core_Model_Abstract {

    /**
     * @var Eepohs_Erply_Helper_Data
     */
    private $_helper;

    /**
     * Helper method in abstract class that lets all
     * subclasses to write log easily
     * 
     * @param mixed $message
     * @param string $method
     * @param int $line
     */
    public function log($message, $method = null, $line = null) {
        if (is_null($this->_helper)) {
            $this->_helper = Mage::helper('eepohs_erply');
        }
        $this->_helper->log($message, $method, $line);
    }

}
