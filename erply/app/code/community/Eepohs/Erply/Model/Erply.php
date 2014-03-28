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
class Eepohs_Erply_Model_Erply extends Eepohs_Erply_Model_Abstract
{

    const ERPLY_API_VERSION = '1.0';
    const ERPLY_RESPONSE_OK = 'ok';

    private $storeId;
    private $url;
    private $code;
    private $username;
    private $password;
    private $session;
    public $userId;

    protected function _construct()
    {
        parent::_construct();
    }

    public function sendRequest($request, $parameters = array())
    {
        if ( !$this->getCode() || !$this->getUsername() || !$this->getPassword() )
                return false;

        if ( $request != Eepohs_Erply_Model_Api_Response_Verifyuser::ERPLY_REQUEST && !$this->getSession() )
        {
            return false;
        }
        $parameters['sessionKey'] = $this->getSession();
        $parameters['request'] = $request;
        $parameters['version'] = self::ERPLY_API_VERSION;
        $parameters['clientCode'] = $this->getCode();

        $url = $this->getUrl();

        if ( $url )
        {
            $http = new Varien_Http_Adapter_Curl();
            $http->setConfig(array('timeout' => 100));
            $http->write(Zend_Http_Client::POST, $url, CURL_HTTP_VERSION_1_0,
                array(), $parameters);
            $responseBody = Zend_Http_Response::extractBody($http->read());
            $http->close();
            return $responseBody;
        } else
        {
            $this->log("Cannot find URL for POS: " . $this->getCode());
        }
        return false;
    }

    /**
     * Sets Erply API client code
     *
     * @param string $code
     */
    public function setCode($code)
    {
        $this->code = $code;
    }

    /**
     * Sets Erply API username
     *
     * @param string $username
     */
    public function setUsername($username)
    {
        $this->username = $username;
    }

    /**
     * Sets Erply API password
     *
     * @param string $password
     */
    public function setPassword($password)
    {
        $this->password = $password;
    }

    /**
     * Returns Erply POS URL
     * @return string
     */
    public function getUrl()
    {
        if ( $this->getCode() )
                return sprintf("https://%s.erply.com/api/", $this->getCode());

        return '';
    }

    /**
     * It logs in to Erply API and verifies Erply user access.
     *
     * @param type $storeId
     * @return boolean
     */
    public function verifyUser($storeId)
    {
        if ( $this->getSession() )
        {
            return true;
        }
        $this->setStoreId($storeId);
        if ( !$this->getUsername() || !$this->getPassword() ) return false;

        $result = $this->sendRequest(
            'verifyUser',
            array(
            'username' => $this->getUsername(),
            'password' => $this->getPassword()
            )
        );
        /**
         * @var Eepohs_Erply_Model_Api_Response_Verifyuser
         */
        $response = Mage::getModel('eepohs_erply/api_response_verifyuser', array(json_decode($result, true)));
        if ( $response->getResponseStatus() == self::ERPLY_RESPONSE_OK && $sessionKey
            = $response->getSessionKey() )
        {
            $this->setSession($sessionKey);
            $this->setUserId($response->getEmployeeId());
            return true;
        }

        return false;
    }

    /**
     * Sets Erply session key
     *
     * @param string $value
     */
    public function setSession($value)
    {
        $this->session = $value;
    }

    /**
     * Gets Erply session key
     *
     * @return string
     */
    public function getSession()
    {
        return $this->session;
    }

    public function getConfig()
    {
        $this->username = Mage::getStoreConfig('eepohs_erply/account/username',
                $this->storeId);
        $this->password = Mage::getStoreConfig('eepohs_erply/account/password',
                $this->storeId);
        $this->code = Mage::getStoreConfig('eepohs_erply/account/code',
                $this->storeId);
    }

    /**
     * Returns Erply API username
     *
     * @return string
     */
    public function getUsername()
    {
        if ( is_null($this->username) )
        {
            $this->username = Mage::getStoreConfig('eepohs_erply/account/username',
                    $this->storeId);
        }
        return $this->username;
    }

    /**
     * Returns Erply API password
     *
     * @return string
     */
    public function getPassword()
    {
        if ( is_null($this->password) )
        {
            $this->password = Mage::getStoreConfig('eepohs_erply/account/password',
                    $this->storeId);
        }
        return $this->password;
    }

    /**
     * Returns Erply API code
     *
     * @return string
     */
    public function getCode()
    {
        if ( is_null($this->code) )
        {
            $this->code = Mage::getStoreConfig('eepohs_erply/account/code',
                    $this->storeId);
        }
        return $this->code;
    }

    /**
     * Returns set store id. Returns Magento default
     * store id if ID is not set
     *
     * @return int
     */
    public function getStoreId()
    {
        if ( is_null($this->storeId) )
        {
            $this->storeId = Mage::app()->getStore()->getId();
        }
        return $this->storeId;
    }

    /**
     * Sets store ID
     *
     * @param string $value
     */
    public function setStoreId($value)
    {
        $this->storeId = $value;
    }

    /**
     * Sets Erply user ID
     *
     * @param string $value
     */
    public function setUserId($value)
    {
        $this->userId = $value;
    }

    /**
     * Gets Erply user ID
     *
     * @return string
     */
    public function getUserId()
    {
        return $this->userId;
    }

}
