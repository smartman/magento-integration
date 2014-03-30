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
class Eepohs_Erply_Model_Product extends Eepohs_Erply_Model_Erply {

    public function findProduct($sku) {
        $storeId = Mage::app()->getStore()->getId();
        $this->verifyUser($storeId);
        $params = array(
            'searchName' => $sku
        );
        $product = $this->sendRequest('getProducts', $params);
        $product = json_decode($product, true);
        if ($product["status"]["responseStatus"] == "ok" && count($product["records"]) > 0) {
            foreach ($product["records"] as $_product) {
                if ($_product["code2"]) {
                    $code = $_product["code2"];
                } elseif ($_product["code"]) {
                    $code = $_product["code"];
                } else {
                    $code = $_product["code3"];
                }
                if ($code == $sku) {
                    return $_product;
                }
            }
        }
    }

    public function importProducts($products, $storeId, $store) {

        Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
        if (!empty($products)) {
            foreach ($products as $_product) {

                $update = false;

                if ($_product["code"]) {
                    $sku = $_product["code"];
                } elseif ($_product["code2"]) {
                    $sku = $_product["code2"];
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
                        $update = true;
                    }
                } else {
                    $update = true;
                }
                if ($_product["displayedInWebshop"] == 0) {
                    if ($update) {
                        try {
                            $product->delete();
                            Mage::helper('eepohs_erply')->log("Delete existing product which should be in webshop id: " . $_product["productID"] . " - sku: " . $sku);
                        } catch (Exception $e) {
                            Mage::helper('eepohs_erply')->log("Failed to delete product with message: " . $e->getMessage());
                        }
                    }
                    continue;
                }

                $product->setStoreId($storeId);
                $product->setIsMassupdate(true);
                $product->setExcludeUrlRewrite(true);
                $product->setTypeId('simple');
                $product->setWeight(1.0000);
                $product->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH);
                $product->setStatus(1);
                $product->setSku($sku);
                $product->setTaxClassId(0);
                $product->setName($_product["name"]);

                // set the rest of the product information here that can be set on either new/update
                if (!$update) {
                    $product->setAttributeSetId((int) Mage::getStoreConfig('eepohs_erply/product/attribute_set', $storeId)); // the product attribute set to use
                }

                $category = Mage::getModel('catalog/category')->load($_product["groupID"] + 10000);
                if ($category->getName()) {
                    $product->setCategoryIds(array($_product["groupID"] + 10000)); // array of categories it will relate to
                }
                if (Mage::app()->isSingleStoreMode()) {
                    $product->setWebsiteIds(array(Mage::app()->getStore(true)->getWebsiteId()));
                } else {
                    $product->setWebsiteIds(array($store->getWebsiteId()));
                }

                $product->setBatchPrices(array());
                $product->setStockPriorities(array());
                $product->setPrice($_product["price"]);
                $product->setDescription($_product["longdesc"]);
                $product->setShortDescription($_product["description"]);

                if (isset($_product["attributes"])) {
                    $erplyAttributes = $_product["attributes"];
                    $mapping = unserialize(Mage::getStoreConfig('eepohs_erply/product/attributes', $storeId));
                    if (!empty($erplyAttributes) && !empty($mapping)) {
                        $mappings = array();
                        foreach ($mapping as $map) {
                            $mappings[$map["erply_attribute"]] = $map["magento_attribute"];
                        }
                        foreach ($erplyAttributes as $attribute) {
                            if (in_array($attribute["attributeName"], array_keys($mappings))) {
                                if ($attribute["attributeValue"]) {
                                    $product->setData($mappings[$attribute["attributeName"]], $attribute["attributeValue"]);
                                }
                            }
                        }
                    }
                }
                $product->save();
                Mage::helper('eepohs_erply')->log("Added in Product: " . $product->getSku());
            }
        }
    }

}
