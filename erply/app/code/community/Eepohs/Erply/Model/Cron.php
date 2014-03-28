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
class Eepohs_Erply_Model_Cron extends Eepohs_Erply_Model_Erply {

    public function __construct() {
        $pCollection = Mage::getSingleton('index/indexer')->getProcessesCollection();
        foreach ($pCollection as $process) {
            $process->setMode(Mage_Index_Model_Process::MODE_MANUAL)->save();
        }
    }

    public function __destruct() {
        $pCollection = Mage::getSingleton('index/indexer')->getProcessesCollection();
        foreach ($pCollection as $process) {
            $process->setMode(Mage_Index_Model_Process::MODE_REAL_TIME)->save();
        }
    }

    /**
     * @param $timescheduled Y-m-d H:M:S
     * @throws Exception
     */
    public function addCronJob($jobCode, $schedule = null) {
        $timecreated = strftime("%Y-%m-%d %H:%M:%S", mktime(date("H"), date("i"), date("s"), date("m"), date("d"), date("Y")));
        Mage::helper('eepohs_erply')->log("Scheduled at:" . $schedule);
        if (!$schedule) {
            $timescheduled = strftime("%Y-%m-%d %H:%M:%S", mktime(date("H"), date("i") + 5, date("s"), date("m"), date("d"), date("Y")));
        } else {
            $timescheduled = $schedule;
        }

        try {

            $schedule = Mage::getModel('cron/schedule');
            $schedule->setJobCode($jobCode)
                    ->setCreatedAt($timecreated)
                    ->setScheduledAt($timescheduled)
                    ->setStatus(Mage_Cron_Model_Schedule::STATUS_PENDING)
                    ->save();
        } catch (Exception $e) {
            throw new Exception(Mage::helper('cron')->__('Unable to save Cron expression'));
        }
    }

    public function importCategories() {

        $queue = Mage::getModel('eepohs_erply/queue')->loadActive('erply_category_import');
        if (count($queue) > 0) {
            foreach ($queue as $item) {
                if ($item) {
                    $runEvery = Mage::getStoreConfig('eepohs_erply/queue/run_every', $item->getStoreId());
                    if (!Mage::getStoreConfig('eepohs_erply/account/enabled', $item->getStoreId())) {
                        return false;
                    }
                    $loops = $item->getLoopsPerRun();
                    $pageSize = $item->getRecordsPerRun();
                    $recordsLeft = $item->getTotalRecords() - $pageSize * $item->getLastPageNo();
                    if ($loops * $pageSize > $recordsLeft) {
                        $loops = ceil($recordsLeft / $pageSize);
                        $item->setStatus(0);
                    } else {
                        $thisRunTime = strtotime($item->getScheduledAt());
                        $newRunTime = strtotime('+' . $runEvery . 'minute', $thisRunTime);
                        $scheduleDateTime = date('Y-m-d H:i:s', $newRunTime);

                        $item->setScheduledAt($scheduleDateTime);
                    }
                    $loops--;
                    $firstPage = $item->getLastPageNo() + 1;

                    $item->setLastPageNo($firstPage + $loops);
                    $item->setUpdatedAt(date('Y-m-d H:i:s', time()));

                    $item->save();
                    $this->verifyUser($item->getStoreId());

                    for ($i = $firstPage; $i <= ($firstPage + $loops + 1); $i++) {

                        $parameters = array('recordsOnPage' => $pageSize, 'pageNo' => $i);
                        $result = $this->sendRequest('getProductGroups', $parameters);
                        //            $return = "";
                        $output = json_decode($result, true);
                        Mage::helper('eepohs_erply')->log("Erply Categories Response: " . $result);
                        if ($output["status"]["responseStatus"] == "error" || count($output["records"]) == 0)
                            return false;
                        //            $start = time();
                        $categories = $output["records"];
                        if ($item->getStoreId() == 0) {
                            $rootCategory = Mage::app()->getWebsite(true)->getDefaultStore()->getRootCategoryId();
                        } else {
                            $rootCategory = Mage::getModel('core/store')->load($item->getStoreId())->getRootCategoryId();
                        }

                        Mage::getModel('eepohs_erply/category_import')->addCategories($categories, $rootCategory, $item->getStoreId());
                    }
                }
            }
            if ($scheduleDateTime) {
                Mage::getModel('eepohs_erply/cron')->addCronJob('erply_category_import', $scheduleDateTime);
            }
        }
    }

    public function updateInventory() {
        $code = 'erply_inventory_update';
        $queue = Mage::getModel('eepohs_erply/queue')->loadActive($code);
        $scheduleDateTime = false;
        $params = array('getStockInfo' => 1);
        if (count($queue) > 0) {
            foreach ($queue as $item) {
                if ($item) {
                    $runEvery = Mage::getStoreConfig('eepohs_erply/queue/run_every', $item->getStoreId());
                    $loops = $item->getLoopsPerRun();
                    $pageSize = $item->getRecordsPerRun();
                    $recordsLeft = $item->getTotalRecords() - $pageSize * $item->getLastPageNo();
                    if ($item->getChangedSince()) {
                        $params['changedSince'] = $item->getChangedSince();
                    }
                    if ($loops * $pageSize > $recordsLeft) {
                        $loops = ceil($recordsLeft / $pageSize);
                        $item->setStatus(0);
                    } else {
                        $thisRunTime = strtotime($item->getScheduledAt());
                        $newRunTime = strtotime('+' . $runEvery . 'minute', $thisRunTime);
                        $scheduleDateTime = date('Y-m-d H:i:s', $newRunTime);
                        $item->setScheduledAt($scheduleDateTime);
                    }
                    $loops--;
                    $firstPage = $item->getLastPageNo() + 1;

                    $item->setLastPageNo($firstPage + $loops);
                    $item->setUpdatedAt(date('Y-m-d H:i:s', time()));

                    $item->save();
                    $this->verifyUser($item->getStoreId());

                    $params["warehouseID"] = Mage::getStoreConfig('eepohs_erply/product/warehouse', $item->getStoreId());
                    $result = $this->sendRequest('getProductStock', $params);

                    $output = json_decode($result, true);

                    if ($output["status"]["responseStatus"] == "error" || count($output["records"]) == 0)
                        return false;

                    if (empty($output["records"]))
                        continue;

                    $stockData = $output["records"];

                    for ($i = $firstPage; $i <= ($firstPage + $loops); $i++) {
                        $start = ($i - 1) * $pageSize;
                        $end = $start + $pageSize - 1;
                        if ($end >= count($stockData)) {
                            $end = count($stockData) - 1;
                        }
                        Mage::getModel('eepohs_erply/inventory')->updateInventory($stockData, $item->getStoreId(), $start, $end);

                        if ($end == count($stockData))
                            break;
                    }
                }
            }

            if ($scheduleDateTime) {
                Mage::getModel('eepohs_erply/cron')->addCronJob($code, $scheduleDateTime);
            }
        }
    }

    public function updatePrices() {
        $code = 'erply_price_update';
        $queue = Mage::getModel('eepohs_erply/queue')->loadActive($code);
        $scheduleDateTime = false;
        $params = array();
        if (count($queue) > 0) {
            foreach ($queue as $item) {
                if ($item) {
                    $pricelistId = Mage::getStoreConfig('eepohs_erply/product/pricelist', $item->getStoreId());
                    $params["pricelistID"] = $pricelistId;
                    $runEvery = Mage::getStoreConfig('eepohs_erply/queue/run_every', $item->getStoreId());
                    $recordPerRun = Mage::getStoreConfig('eepohs_erply/queue/records_per_run', $item->getStoreId());
                    $loops = $item->getLoopsPerRun();
                    $pageSize = $item->getRecordsPerRun();
                    $recordsLeft = $item->getTotalRecords() - $pageSize * $item->getLastPageNo();
                    if ($item->getChangedSince()) {
                        $params['changedSince'] = $item->getChangedSince();
                    }
                    if ($loops * $pageSize > $recordsLeft) {
                        $loops = ceil($recordsLeft / $pageSize);
                        $item->setStatus(0);
                    } else {
                        $thisRunTime = strtotime($item->getScheduledAt());
                        $newRunTime = strtotime('+' . $runEvery . 'minute', $thisRunTime);
                        $scheduleDateTime = date('Y-m-d H:i:s', $newRunTime);
                        $item->setScheduledAt($scheduleDateTime);
                    }
                    $loops--;
                    $firstPage = $item->getLastPageNo() + 1;

                    //$item->setRecordsPerRun($recordPerRun );
                    $item->setLastPageNo($firstPage + $loops);
                    $item->setUpdatedAt(date('Y-m-d H:i:s', time()));

                    $item->save();
                    $this->verifyUser($item->getStoreId());

                    $params["recordsOnPage"] = 1;
                    $params["pageNo"] = 0;

                    $result = $this->sendRequest('getPriceLists', $params);

                    $output = json_decode($result, true);

                    if ($output["status"]["responseStatus"] == "error" || count($output["records"]) == 0) {
                        return false;
                    }

                    $rules = $output["records"][0]["pricelistRules"];

                    for ($i = $firstPage; $i <= ($firstPage + $loops); $i++) {
                        $start = ($i - 1) * $pageSize;
                        $end = $start + $pageSize - 1;
                        if ($end >= count($rules)) {
                            $end = count($rules) - 1;
                        }
                        Mage::getModel('eepohs_erply/price')->updatePrices($rules, $item->getStoreId(), $start, $end);


                        if ($end == count($rules)) {
                            break;
                        }
                    }
                }
            }

            if ($scheduleDateTime) {
                Mage::getModel('eepohs_erply/cron')->addCronJob($code, $scheduleDateTime);
            }
        }
    }

    public function importProducts() {
        Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
        $queue = Mage::getModel('eepohs_erply/queue')->loadActive('erply_product_import');
        $params = array();
        $scheduleDateTime = false;
        if (count($queue) > 0) {
            foreach ($queue as $item) {
                if ($item) {
                    $storeId = $item->getStoreId();
                    if (Mage::getStoreConfig('eepohs_erply/update_schedule/only_main')) {
                        if (!Mage::getStoreConfig('eepohs_erply/account/is_main', $storeId)) {
                            continue;
                        }
                    }
                    $runEvery = Mage::getStoreConfig('eepohs_erply/queue/run_every', $item->getStoreId());
                    $loops = $item->getLoopsPerRun();
                    $pageSize = $item->getRecordsPerRun();
                    $recordsLeft = $item->getTotalRecords() - $pageSize * $item->getLastPageNo();
                    if ($item->getChangedSince() > 0) {
                        $params['changedSince'] = $item->getChangedSince();
                        $storeId = $item->getStoreId();
                    } else {
                        $storeId = 0;
                    }
                    if (Mage::getStoreConfig('eepohs_erply/update_schedule/only_main')) {
                        $storeId = 0;
                    }
                    if ($loops * $pageSize > $recordsLeft) {
                        $loops = ceil($recordsLeft / $pageSize);
                        $item->setStatus(0);
                    } else {
                        $thisRunTime = strtotime($item->getScheduledAt());
                        $newRunTime = strtotime('+' . $runEvery . 'minute', $thisRunTime);
                        $scheduleDateTime = date('Y-m-d H:i:s', $newRunTime);
                        $item->setScheduledAt($scheduleDateTime);
                    }

                    $loops--;
                    $firstPage = $item->getLastPageNo() + 1;

                    $item->setLastPageNo($firstPage + $loops);
                    $item->setUpdatedAt(date('Y-m-d H:i:s', time()));

                    $item->save();
                    $this->verifyUser($item->getStoreId());

                    $store = Mage::getModel('core/store')->load($item->getStoreId());
                    for ($i = $firstPage; $i <= ($firstPage + $loops); $i++) {

                        $parameters = array_merge(array(
                            'recordsOnPage' => $pageSize,
                            'pageNo' => $i,
                            'displayedInWebshop' => 1,
                            'active' => 1
                                ), $params);
                        Mage::helper('eepohs_erply')->log("Erply request: ");
                        Mage::helper('eepohs_erply')->log($parameters);
                        $result = $this->sendRequest('getProducts', $parameters);
                        $return = "";
                        Mage::helper('eepohs_erply')->log("Erply product import:");

                        $output = json_decode($result, true);
                        if ($output["status"]["responseStatus"] == "error" || count($output["records"]) == 0)
                            return false;
                        $start = time();
                        $products = $output["records"];
                        Mage::getModel('eepohs_erply/product')->importProducts($products, $storeId, $store);
                        unset($output);
                    }
                }
            }
            if ($scheduleDateTime) {
                Mage::getModel('eepohs_erply/cron')->addCronJob('erply_product_import', $scheduleDateTime);
            }
        }
    }

    public function importImages() {
        $queue = Mage::getModel('eepohs_erply/queue')->loadActive('erply_image_import');
        $params = array();
        $scheduleDateTime = false;
        if (count($queue) > 0) {
            foreach ($queue as $item) {
                if ($item) {
                    $runEvery = Mage::getStoreConfig('eepohs_erply/queue/run_every');
                    $loops = 1;
                    $pageSize = floor(450 / (60 / $runEvery));
                    $recordsLeft = $item->getTotalRecords() - $pageSize * $item->getLastPageNo();

                    if ($item->getChangedSince()) {
                        $params = array('changedSince' => $item->getChangedSince());
                    }
                    if ($loops * $pageSize > $recordsLeft) {
                        $loops = ceil($recordsLeft / $pageSize);
                        $item->setStatus(0);
                    } else {
                        $thisRunTime = strtotime($item->getScheduledAt());
                        $newRunTime = strtotime('+' . $runEvery . 'minute', $thisRunTime);
                        $scheduleDateTime = date('Y-m-d H:i:s', $newRunTime);

                        $item->setScheduledAt($scheduleDateTime);
                    }
                    $loops--;
                    $firstPage = $item->getLastPageNo() + 1;
                    $item->setPageSize($pageSize);
                    $item->setLastPageNo($firstPage + $loops);
                    $item->setUpdatedAt(date('Y-m-d H:i:s', time()));

                    $item->save();
                    $this->verifyUser($item->getStoreId());
                    $store = Mage::getModel('core/store')->load($item->getStoreId());
                    for ($i = $firstPage; $i <= ($firstPage + $loops + 1); $i++) {

                        $parameters = array('recordsOnPage' => $pageSize, 'pageNo' => $i,
                            'displayedInWebshop' => 1,
                            'active' => 1);
                        $result = $this->sendRequest('getProducts', $parameters);
                        $output = json_decode($result, true);

                        Mage::helper('eepohs_erply')->log("Erply Images Response: " . $result);
                        if ($output["status"]["responseStatus"] == "error" || count($output["records"]) == 0) {
                            return false;
                        }

                        $products = $output["records"];

                        Mage::getModel('eepohs_erply/image')->updateImages($products, $item->getStoreId());
                    }
                }
            }
            if ($scheduleDateTime) {
                Mage::getModel('eepohs_erply/cron')->addCronJob('erply_image_import', $scheduleDateTime);
            }
        }
    }

    public function checkPendingOrders() {
        $orders = Mage::getModel('sales/order')->getCollection()->addAttributeToSelect("*")->addAttributeToFilter('status', 'processing');
        $params = array();
        if ($orders->getSize() > 0) {
            Mage::helper('eepohs_erply')->log("Starting order status checking");
            Mage::helper('eepohs_erply')->log("Found " . $orders->getSize() . " pending orders in Magento");
            foreach ($orders as $order) {

                $isComplete = false;

                $storeId = $order->getStoreId();
                $e = new $this;
                $e->verifyUser($storeId);

                $params["number"] = $order->getIncrementId();
                Mage::helper('eepohs_erply')->log("Request to Erply for Magento order #" . $order->getIncrementId() . " - " . print_r($params, true));
                $request = $e->sendRequest('getSalesDocuments', $params);
                $output = json_decode($request, true);
                Mage::helper('eepohs_erply')->log("Reponse from Erply for Magento order #" . $order->getIncrementId() . " - " . print_r($output, true));
                if ($output["status"]["responseStatus"] == "error" || count($output["records"]) == 0)
                    continue;

                $erpOrder = $output["records"][0];
                try {
                    if ($erpOrder["invoiceState"] == "SHIPPED" || $erpOrder["invoiceState"] == "FULFILLED") {

                        $shipment = $order->prepareShipment();
                        $shipment->register();
                        $order->setIsInProcess(true);
                        $order->addStatusHistoryComment('Order is now Complete.', false);
                        $transactionSave = Mage::getModel('core/resource_transaction')
                                ->addObject($shipment)
                                ->addObject($shipment->getOrder())
                                ->save();

                        Mage::helper('eepohs_erply')->log("Marked order #" . $order->getIncrementId() . " as Completed");
                    } elseif ($erpOrder["invoiceState"] == "CANCELLED") {
                        if ($order->canCancel()) {
                            $order->cancel()->save();
                            Mage::helper('eepohs_erply')->log("Marked order #" . $order->getIncrementId() . " as Cancelled");
                        }
                    }
                } catch (Exception $e) {
                    Mage::helper('eepohs_erply')->log("Failed to change order status: " . $e->getMessage());
                }
            }
        }
    }

}
