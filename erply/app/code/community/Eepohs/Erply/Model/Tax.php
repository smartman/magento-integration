<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Tax
 *
 * @author Sven Varkel <sven.varkel@eepohs.com>
 */
class Eepohs_Erply_Model_Tax extends Eepohs_Erply_Model_Erply
{

    /**
     * Loads VAT Rates from Erply
     * @return stdClass
     */
    public function getVatRates($storeId)
    {
        $this->verifyUser($storeId);
        $response = $this->sendRequest('getVatRates');
        //TODO ADD error logging
        $vatRates = json_decode($response);
        return $vatRates;
    }

    /**
     *
     * @param array $data
     * @param type $storeId
     * @return null
     */
    public function saveVatRate(array $data, $storeId)
    {
        $vatRateId = $this->getErplyVatRateByName($data['name'], $storeId);
        if ( $vatRateId )
        {
            $data['vatRateID'] = $vatRateId;
        }
        $this->log('VAT rate data to be saved: ' . print_r($data, true),
            __METHOD__, __LINE__);
        $response = $this->sendRequest('saveVatRate', $data);
        $vatRate = json_decode($response);
        if ( $vatRate instanceof stdClass )
        {
            $records = $vatRate->records;
            if ( sizeof($records) > 0 )
            {
                $vatRateId = $records[0]->vatRateID;
                $this->log('Saved vat rate: ' . print_r($response, true),
                    __METHOD__, __LINE__);
            } else
            {
                $this->log('Error: cannot save new vat rate: ' . print_r($response,
                        true), __METHOD__, __LINE__);
            }
        } else
        {
            $this->log('Error: cannot save new vat rate: ' . print_r($response,
                    true), __METHOD__, __LINE__);
        }
        return $vatRateId;
    }

    /**
     * Tries to find Erply VAT Rate ID by its name
     * @param string $name
     * @return int VAT Rate ID
     */
    public function getErplyVatRateByName($name, $storeId)
    {
        $vatRates = $this->getVatRates($storeId);
        if ( $vatRates instanceof stdClass )
        {
            foreach ( $vatRates->records as $vatRate )
            {
                if ( $vatRate->name == $name )
                {
                    $this->log(sprintf('Found existing VAT rate with ID %s for name %s',
                            $vatRate->id, $name), __METHOD__, __LINE__);
                    return $vatRate->id;
                }
            }
        }
        $this->log('Could not find VAT rate ID for name' . $name, __METHOD__,
            __LINE__);
        return null;
    }

    /**
     * Prepares saveVatRate request for Erply
     * @return array
     */
    public function prepareVatRateRequest()
    {
        $out = array();
        return $out;
    }

}
