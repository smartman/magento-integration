<?php

/**
 * NB! This is a BETA release of Erply Connector.
 *
 * Use with caution and at your own risk.
 *
 * The author does not take any responsibility for any loss or damage to business
 * or customers or anything at all. These terms may change without further notice.
 *
 * License terms are subject to change. License is all-restrictive until
 * said otherwise.
 *
 * @author Eepohs Ltd
 */
class Eepohs_Erply_Helper_Data extends Mage_Core_Helper_Data
{

    private $_filename = 'erply.log';

    /**
     * Writes log to Erply log file
     *
     * @param string or object $message
     */
    public function log($message, $method = null, $line = null)
    {
        if ( Mage::getStoreConfig('eepohs_erply/general/log_enabled') ) {
            if ( is_null($method) ) $method = __METHOD__;
            if ( is_null($line) ) $line = __LINE__;
            Mage::log(sprintf('%s(%s): %s', $method, $line,
                    print_r($message, true)), null, $this->_filename);
        }
    }

}
