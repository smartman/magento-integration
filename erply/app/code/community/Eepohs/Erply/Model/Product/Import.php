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
class Eepohs_Erply_Model_Product_Import extends Eepohs_Erply_Model_Erply {

    public function getTotalRecords($storeId) {
        $this->verifyUser($storeId);
        $parameters = array('recordsOnPage' => 1, 'pageNo' => 1);
        $results = json_decode($this->sendRequest('getProducts', $parameters), true);
        return $results["status"]["recordsTotal"];
    }

    public function importProducts() {

        $queue = Mage::getModel('eepohs_erply/queue')->loadActive('erply_product_import');
        $params = array();
        if ($queue) {
            $runEvery = Mage::getStoreConfig('eepohs_erply/queue/run_every', $queue->getStoreId());
            $loops = $queue->getLoopsPerRun();
            $pageSize = $queue->getRecordsPerRun();
            $recordsLeft = $queue->getTotalRecords() - $pageSize * $queue->getLastPageNo();
            if ($queue->getChangedSince()) {
                $params = array('changedSince' => $queue->getChangedSince());
            }
            if ($loops * $pageSize > $recordsLeft) {
                $loops = ceil($recordsLeft / $pageSize);
                $queue->setStatus(0);
            } else {
                $thisRunTime = strtotime($queue->getScheduledAt());
                $newRunTime = strtotime('+' . $runEvery . 'minute', $thisRunTime);
                $scheduleDateTime = date('Y-m-d H:i:s', $newRunTime);
                Mage::getModel('eepohs_erply/cron')->addCronJob('erply_product_import', $scheduleDateTime);
                $queue->setScheduledAt($scheduleDateTime);
            }
            $loops--;
            $firstPage = $queue->getLastPageNo() + 1;

            $queue->setLastPageNo($firstPage + $loops);
            $queue->setUpdatedAt(date('Y-m-d H:i:s', time()));

            $queue->save();
            $this->verifyUser($queue->getStoreId());
            $store = Mage::getModel('core/store')->load($queue->getStoreId());
            for ($i = $firstPage; $i <= ($firstPage + $loops); $i++) {

                $parameters = array_merge(array('recordsOnPage' => $pageSize, 'pageNo' => $i), $params);
                Mage::helper('eepohs_erply')->log("Erply request: ");
                Mage::helper('eepohs_erply')->log($parameters);
                $result = $this->sendRequest('getProducts', $parameters);
                Mage::helper('eepohs_erply')->log("Erply product import:");
                Mage::helper('eepohs_erply')->log($result);
                $output = json_decode($result, true);
                foreach ($output["records"] as $_product) {

                    if ($_product["code2"]) {
                        $sku = $_product["code2"];
                    } elseif ($_product["code"]) {
                        $sku = $_product["code"];
                    } else {
                        $sku = $_product["code3"];
                    }
                    $product = Mage::getModel('catalog/product')
                            ->loadByAttribute('sku', $sku);

                    if (!$product) {
                        $product = Mage::getModel('catalog/product')->load($_product["productID"]);
                        if (!$product->getName()) {
                            $product = new Mage_Catalog_Model_Product();
                            $product->setId($_product["productID"]);
                            Mage::helper('eepohs_erply')->log("Creating new product: " . $_product["productID"]);
                        } else {
                            Mage::helper('eepohs_erply')->log("Editing old product: " . $_product["productID"]);
                        }
                    }
                    // product does not exist so we will be creating a new one.
                    $product->setIsMassupdate(true);
                    $product->setExcludeUrlRewrite(true);
                    $product->setTypeId('simple');
                    $product->setWeight(1.0000);
                    $product->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH);
                    $product->setStatus(1);
                    $product->setSku($sku);
                    $product->setTaxClassId(0);
                    $product->setAttributeSetId(4); // the product attribute set to use
                    $product->setName($_product["name"]);
                    $product->setCategoryIds(array($_product["groupID"]+10000)); // array of categories it will relate to
                    if (Mage::app()->isSingleStoreMode()) {
                        $product->setWebsiteIds(array(Mage::app()->getStore($queue->getStoreId())->getWebsiteId()));
                    } else {
                        $product->setWebsiteIds(array($store->getWebsiteId()));
                    }
                    $product->setDescription($_product["longdesc"]);
                    $product->setShortDescription($_product["description"]);
                    $product->setPrice($_product["price"]);

                    $product->save();                    
                    Mage::helper('eepohs_erply')->log("Added in Import: " . $product->getSku() . ", " . $product->getShortDescription());
                    Mage::helper('eepohs_erply')->log("Description data: " . $_product['description'] . " " . $_product['longdesc'] . " " . $product->getShortDescription() . " " . $product->getDescription());
                }
                unset($output);
            }
        }
    }

}
