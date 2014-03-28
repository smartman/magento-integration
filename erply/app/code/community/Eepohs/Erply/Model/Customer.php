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
class Eepohs_Erply_Model_Customer extends Eepohs_Erply_Model_Erply
{

    public function getCustomerExists($email, $storeId)
    {
        if ( $email )
        {
            $this->verifyUser($storeId);
            $params = array(
                'searchName' => $email
            );
            $response = $this->sendRequest('getCustomers', $params);
            $response = json_decode($response, true);
            if ( count($response["records"]) > 0 && $response["records"][0]["customerID"] > 0 )
            {
                $this->log("Erply - Found existing customer with ID:" . $response["records"][0]["customerID"]);
                return $response["records"][0]["customerID"];
            }
            $this->log("Erply - Couldn't find existing customer");
            return false;
        }
    }

    public function sendCustomer($customer, $storeId)
    {
        $this->verifyUser($storeId);
        $params = array();
        $customerID = $this->getCustomerExists($customer->getEmail(), $storeId);
        if ( $customer instanceof Mage_Customer_Model_Customer )
        {
            $params = array(
                'firstName' => $customer->getFirstname(),
                'lastName' => $customer->getLastname(),
                'email' => $customer->getEmail()
            );
        } else
        {
            $params = $customer;
        }
        if ( $customer->getData('dob') )
        {
            $params["birthday"] = $customer->getData('dob');
        }
        if ( $customerID )
        {
            $params["customerID"] = $customerID;
            $this->log("Erply - Updating existing customer");
        } else
        {
            $this->log("Erply - Creating new customer");
        }
        $customerData = $this->sendRequest('saveCustomer', $params);
        $customerData = json_decode($customerData, true);
        if ( $customerData["status"]["responseStatus"] == "ok" )
        {

            $this->log("Erply - Customer saved!");
            return $customerData["records"][0]["customerID"];
        } else
        {
            $this->log("Erply - Couldn't save customer data" . print_r($customerData,
                    true));
            return false;
        }
    }

    public function addNewCustomer($customerId, $storeId)
    {
        $customer = Mage::getModel('customer/customer')->load($customerId);
        return $this->sendCustomer($customer, $storeId);
    }

}
