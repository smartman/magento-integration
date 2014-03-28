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
class Eepohs_Erply_Model_Address extends Eepohs_Erply_Model_Erply
{

    private $attrName;
    private $attrType;
    private $erpTypeID;

    public function _construct()
    {
//		$this->attrName = 'magentoAddressId';
//		$this->attrType = 'int';
//		$this->erpTypeID = 3;// registered address
//        parent::_construct();
    }

    protected function getExistingAddress($customerId, $typeId, $storeId)
    {

        $params = array(
            'ownerID' => $customerId,
            'typeID' => $typeId
        );
        $response = $this->sendRequest('getAddresses', $params);
        $response = json_decode($response, true);
        if ( isset($response["records"]) && count($response["records"]) > 0 )
        {
            return $response["records"][0]["addressID"];
        } else
        {
            $this->log($response);
            return false;
        }
    }

    public function saveCustomerAddress($customerId, $typeId, $data, $storeId)
    {

        $this->verifyUser($storeId);

        $params = array(
            'ownerID' => $customerId,
            'typeID' => $typeId,
            'street' => $data["street"],
            'city' => $data["city"],
            'postalCode' => $data["postcode"],
            'state' => $data["region"],
            'country' => $data["country_id"]
        );

        if ( $addressId = $this->getExistingAddress($customerId, $typeId,
            $storeId) )
        {
            $params["addressID"] = $addressId;
        }
        Mage::helper('eepohs_erply')->log("Magento - Sending address data to Erply: " . print_r($params,
                true));

        $response = $this->sendRequest('saveAddress', $params);
        $response = json_decode($response, true);
        $this->log("Saving customer address:" . var_export($params, true));
        if ( isset($response["records"]) && count($response["records"]) > 0 )
        {
            return $response["records"][0]["addressID"];
        } else
        {
            $this->log("Could not save address for customer:" . print_r($response,
                    true));
            return false;
        }
    }

}