<?php

/**
 * VerifyUser
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
 * VerifyUser
 *
 * @category Deployment
 * @package  Application
 * @author   Sven Varkel <sven.varkel@eepohs.com>
 * @license  http://eepohs.com/ Eepohs Special License
 * @link     http://esc.eepohs.com/ Eepohs Software Channel
 *
 * @method string getEmployeeId() Returns Employee ID
 */
class Eepohs_Erply_Model_Api_Response_Verifyuser extends Eepohs_Erply_Model_Api_Response
{

    const ERPLY_REQUEST = 'verifyUser';

    /**
     * Class constructor
     *
     * @return VerifyUser
     */
    public function __construct(array $response)
    {
        parent::__construct($response);
        return $this;
    }

    /**
     * Parses response to verifyUser request
     *
     * @param array $response
     */
    protected function parseResponse(array $response)
    {
        parent::parseResponse($response);
        if ( $this->getRequest() == self::ERPLY_REQUEST )
        {
            foreach ( $response[0]['records'][0] as $name => $value )
            {
                $setter = 'set' . ucfirst($name);
                $this->$setter($value);
            }
        }
    }

}
