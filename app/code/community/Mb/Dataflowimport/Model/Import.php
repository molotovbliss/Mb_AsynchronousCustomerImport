<?php

/*
 *  @encoding  UTF-8
 *  @date      May 5, 2012
 *  @name      Import.php
 *  @author    Jared Blalock (info@molotovbliss.com)
 */

class Mb_Dataflowimport_Model_Import extends Mage_Customer_Model_Convert_Adapter_Customer {

    /**
     * Create a new method for importing customer data that allows for adding multiple
     * addresses at a time and returns the new customer id
     *
     * @param type $importData
     * @return type int
     */
    public function saveImportData($importData) {
        $customer = $this->getCustomerModel();
        $customer->setId(null);

        if (empty($importData['website'])) {
            $message = Mage::helper('customer')->__('Skipping import row, required field "%s" is not defined.', 'website');
            Mage::throwException($message);
        }

        $website = $this->getWebsiteByCode($importData['website']);

        if ($website === false) {
            $message = Mage::helper('customer')->__('Skipping import row, website "%s" field does not exist.', $importData['website']);
            Mage::throwException($message);
        }
        if (empty($importData['email'])) {
            $message = Mage::helper('customer')->__('Skipping import row, required field "%s" is not defined.', 'email');
            Mage::throwException($message);
        }

        $customer->setWebsiteId($website->getId())->loadByEmail($importData['email']);
        if (!$customer->getId()) {

            $customerGroups = $this->getCustomerGroups();

            /**
             * Check customer group
             */
            if (empty($importData['group']) || !isset($customerGroups[$importData['group']])) {
                $value = isset($importData['group']) ? $importData['group'] : '';
                $message = Mage::helper('catalog')->__('Skipping import row, the value "%s" is not valid for the "%s" field.', $value, 'group');
                Mage::throwException($message);
            }
            $customer->setGroupId($customerGroups[$importData['group']]);

            foreach ($this->_requiredFields as $field) {
                if (!isset($importData[$field])) {
                    $message = Mage::helper('catalog')->__('Skip import row, required field "%s" for the new customer is not defined.', $field);
                    Mage::throwException($message);
                }
            }

            $customer->setWebsiteId($website->getId());

            if (empty($importData['created_in']) || !$this->getStoreByCode($importData['created_in'])) {
                $customer->setStoreId(0);
            } else {
                $customer->setStoreId($this->getStoreByCode($importData['created_in'])->getId());
            }

            if (empty($importData['password_hash'])) {
                $customer->setPasswordHash($customer->hashPassword($customer->generatePassword(8)));
            }
        } elseif (!empty($importData['group'])) {
            $customerGroups = $this->getCustomerGroups();
            /**
             * Check customer group
             */
            if (isset($customerGroups[$importData['group']])) {
                $customer->setGroupId($customerGroups[$importData['group']]);
            }
        }

        foreach ($this->_ignoreFields as $field) {
            if (isset($importData[$field])) {
                unset($importData[$field]);
            }
        }

        /**
         * get address fields and attribute options
         */
        foreach ($importData as $field => $value) {
            if (in_array($field, $this->_billingFields)) {
                continue;
            }
            if (in_array($field, $this->_shippingFields)) {
                continue;
            }

            $attribute = $this->getAttribute($field);
            if (!$attribute) {
                continue;
            }

            $isArray = false;
            $setValue = $value;

            if ($attribute->usesSource()) {
                $options = $attribute->getSource()->getAllOptions(false);

                if ($isArray) {
                    foreach ($options as $item) {
                        if (in_array($item['label'], $value)) {
                            $setValue[] = $item['value'];
                        }
                    }
                } else {
                    $setValue = null;
                    foreach ($options as $item) {
                        if ($item['label'] == $value) {
                            $setValue = $item['value'];
                        }
                    }
                }
            }

            $customer->setData($field, $setValue);
        }

        /**
         * Subscribe to newsletter
         */
        if (isset($importData['is_subscribed'])) {
            $customer->setData('is_subscribed', $importData['is_subscribed']);
        }

        // Save customer to allow address models to add multiple addresses
        $customer->setImportMode(true);
        $customer->save();

        // billing and shipping address flags
        $importBillingAddress = $importShippingAddress = true;
        $savedBillingAddress = $savedShippingAddress = false;

        // set if only one address exists
        $onlyAddress = false;
        if (count($importData['billing']) == 1 && count($importData['shipping']) == 0) {
            $onlyAddress = true;
        }

        /**
         * Import billing address
         */
        if (isset($importData['billing'])) {
            foreach ($importData['billing'] as $_importData) {
                $billingAddress = $this->getBillingAddressModel();
                if ($customer->getDefaultBilling()) {
                    $billingAddress->load($customer->getDefaultBilling());
                } else {
                    $billingAddress->setData(array());
                }

                foreach ($this->_billingFields as $field) {
                    $cleanField = Mage::helper('core/string')->substr($field, 8);

                    if (isset($_importData[$field])) {
                        $billingAddress->setDataUsingMethod($cleanField, $_importData[$field]);
                    } elseif (isset($this->_billingMappedFields[$field])
                            && isset($_importData[$this->_billingMappedFields[$field]])) {
                        $billingAddress->setDataUsingMethod($cleanField, $_importData[$this->_billingMappedFields[$field]]);
                    }
                }

                $street = array();
                foreach ($this->_billingStreetFields as $field) {
                    if (!empty($_importData[$field])) {
                        $street[] = $_importData[$field];
                    }
                }
                if ($street) {
                    $billingAddress->setDataUsingMethod('street', $street);
                }

                $billingAddress->setCountryId($_importData['billing_country']);
                $regionName = isset($_importData['billing_region']) ? $_importData['billing_region'] : '';
                if ($regionName) {
                    $regionId = $this->getRegionId($_importData['billing_country'], $regionName);
                    $billingAddress->setRegionId($regionId);
                }

                if ($customer->getId()) {
                    $billingAddress->setCustomerId($customer->getId());

                    if ($_importData['default']) {
                        $billingAddress->setIsDefaultBilling('1')->setSaveInAddressBook('1');
                    }

                    if ($onlyAddress) {
                        $billingAddress->setIsDefaultShipping('1')->setSaveInAddressBook('1');
                    }
                    $billingAddress->save();
                    $savedBillingAddress = true;
                    $customer->addAddress($billingAddress);
                }
            }
        }
        $customer->save();
        unset($_importData);

        /**
         * Import shipping address
         */
        if (isset($importData['shipping'])) {
            $i = 1;
            foreach ($importData['shipping'] as $_importData) {
                $shippingAddress = $this->getShippingAddressModel();
                if ($customer->getDefaultShipping() && $customer->getDefaultBilling() != $customer->getDefaultShipping()) {
                    $shippingAddress->load($customer->getDefaultShipping());
                } else {
                    $shippingAddress->setData(array());
                }

                foreach ($this->_shippingFields as $field) {
                    $cleanField = Mage::helper('core/string')->substr($field, 9);

                    if (isset($_importData[$field])) {
                        $shippingAddress->setDataUsingMethod($cleanField, $_importData[$field]);
                    } elseif (isset($this->_shippingMappedFields[$field])
                            && isset($_importData[$this->_shippingMappedFields[$field]])) {
                        $shippingAddress->setDataUsingMethod($cleanField, $_importData[$this->_shippingMappedFields[$field]]);
                    }
                }

                $street = array();
                foreach ($this->_shippingStreetFields as $field) {
                    if (!empty($_importData[$field])) {
                        $street[] = $_importData[$field];
                    }
                }
                if ($street) {
                    $shippingAddress->setDataUsingMethod('street', $street);
                }

                $shippingAddress->setCountryId($_importData['shipping_country']);
                $regionName = isset($_importData['shipping_region']) ? $_importData['shipping_region'] : '';
                if ($regionName) {
                    $regionId = $this->getRegionId($_importData['shipping_country'], $regionName);
                    $shippingAddress->setRegionId($regionId);
                }

                if ($customer->getId()) {
                    $shippingAddress->setCustomerId($customer->getId());
                    if ($i == 1) {
                        // uncomment to save first shipping address as default
                        // $shippingAddress->setIsDefaultShipping('1')->setSaveInAddressBook('1');
                    }
                    $shippingAddress->save();
                    $savedShippingAddress = true;
                    $customer->addAddress($shippingAddress);
                }
                $i++;
            }
        }
        $customer->save();
        unset($_importData);

        return $customer->getId();
    }

}
