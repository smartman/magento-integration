<?php

/**
 * Response
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
 * Response
 *
 * @category Deployment
 * @package  Application
 * @author   Sven Varkel <sven.varkel@eepohs.com>
 * @license  http://eepohs.com/ Eepohs Special License
 * @link     http://esc.eepohs.com/ Eepohs Software Channel
 *
 * @method string getRequest() Returns request name
 * @method string getRequestUnixTime() Returns unix timestamp of request
 * @method string getResponseStatus() Returns response status
 * @method int getErrorCode() Returns error code
 * @method double getGenerationTime() Returns generation time (microtime)
 * @method int getRecordsTotal() Returns total number of records
 * @method int getRecordsInResponse() Returns number of records returned by this request
 */
class Eepohs_Erply_Model_Api_Response extends Eepohs_Erply_Model_Api_Abstract
{

    /**
     * @var Varien_Data_Collection
     */
    private $_items;

    /**
     * Class constructor
     *
     * @return Eepohs_Erply_Model_Api_Response
     */
    public function __construct(array $response)
    {
        $this->_items = new Varien_Data_Collection();
        $this->parseResponse($response);
        return $this;
    }

    protected function parseResponse(array $response)
    {
        foreach ( $response[0]['status'] as $name => $value )
        {
            $setter = 'set' . ucfirst($name);
            $this->$setter($value);
        }
       
    }

    /**
     * Returns items
     *
     * @return Varien_Data_Collection
     */
    public function getItems()
    {
        return $this->_items;
    }

}
