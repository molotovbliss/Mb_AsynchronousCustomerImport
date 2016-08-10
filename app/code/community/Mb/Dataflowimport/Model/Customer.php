<?php
set_time_limit(0);
/*
 *  @encoding  UTF-8
 *  @date      May 7, 2012
 *  @name      Customer.php
 *  @author    Jared Blalock (info@molotovbliss.com)
 */

class Mb_Dataflowimport_Model_Customer extends Mage_Core_Model_Abstract {

    /**
     * Grab data from imported CSV files in tables, members_import
     * members_billing_import and members_shipping_import
     * and build arrays to send to a custom dataflow import method
     *
     * @param type $numOfRecords
     * @param type $offset
     */
    public function importCustomers($numOfRecords = "1000", $offset = "0", $debug = false) {

        Mage::log("Import Started", null, "dataflowimport.log");

        if(!$numOfRecords) { $numOfRecords = "1000"; }
        if(!$offset) { $offset = "0"; }

        // get Read DB Connection
        $connection = Mage::getSingleton('core/resource')->getConnection('core_read');

        // get total number of member records to import
        //$sql = "SELECT COUNT(id) as total FROM members_import";
        //$numRecords = $connection->fetchAll($sql);
        //$totalRecords = $numRecords[0]['total'];

        // Output number of records to import
        //echo "Importing a total of $numOfRecords records.\n\r";

        // profiling
        // $starttime = explode(' ', microtime());
        // $starttime = $starttime[1] + $starttime[0];

        // select customer records to import one at a time
        $sql = "SELECT first_name, last_name, email, member_id, invitation_code, optin  FROM members_import LIMIT $offset, $numOfRecords";

        // loop over member(s) create a new magento customer and create addresses
        foreach ($connection->fetchAll($sql) as $_c) {

        // $_starttime = explode(' ', microtime());
        // $_starttime = $_starttime[1] + $_starttime[0];

            // map billing data
            $sql = "SELECT member_id, first_name, address, address2, city, state, country_iso, zipcode, phone, main FROM members_billing_import WHERE member_id = '" . $_c['member_id'] . "'";
            $billing = $connection->fetchAll($sql);
            if ($billing) {
                foreach ($billing as $_b) {
                    $billingAddresses[] = array(
                        'billing_firstname' => $_b['first_name'],
                        'billing_lastname' => $_b['last_name'],
                        'billing_street1' => $_b['address'],
                        'billing_street2' => $_b['address2'],
                        'billing_city' => $_b['city'],
                        'billing_region' => $_b['state'],
                        'billing_country' => $_b['country_iso'],
                        'billing_postcode' => $_b['zipcode'],
                        'billing_telephone' => $_b['phone'],
                        'default' => $_b['main']
                    );
                }
            } else {
                $billingAddresses = Array();
            }

            // map shipping data
            $sql = "SELECT first_name, last_name, address, address2, city, state, country_iso, zipcode, phone FROM members_shipping_import WHERE member_id = '" . $_c['member_id'] . "'";
            $shipping = $connection->fetchAll($sql);
            if ($shipping) {
                foreach ($shipping as $_s) {
                    $shippingAddresses[] = array(
                        'shipping_firstname' => $_s['first_name'],
                        'shipping_lastname' => $_s['last_name'],
                        'shipping_street1' => $_s['address'],
                        'shipping_street2' => $_s['address2'],
                        'shipping_city' => $_s['city'],
                        'shipping_region' => $_s['state'],
                        'shipping_country' => $_s['country_iso'],
                        'shipping_postcode' => $_s['zipcode'],
                        'shipping_telephone' => $_s['phone'],
                    );
                }
            } else {
                $shippingAddresses = array();
            }

            // Grab customer data and associate arrays for shipping and billing address data
            $newCustomer = array(
                'firstname' => $_c['first_name'],
                'lastname' => $_c['last_name'],
                'email' => $_c['email'],
                'password_hash' => md5($_c['invitation_code']),
                'store_id' => 1,
                'website' => 'base',
                'group' => 'General',
                'old_member_id' => $_c['member_id'],
                'is_subscribed' => $_c['optin'],
                'shipping' => $shippingAddresses,
                'billing' => $billingAddresses
            );

            // debug
            if ($debug) {
                zend_debug::dump($_c);
                zend_debug::dump($billing);
                zend_debug::dump($shipping);
                zend_debug::dump($newCustomer);
                zend_debug::dump($billingAddresses);
                zend_debug::dump($shippingAddresses);
                exit;
            }

            try {

                // Attempt to save the customer with generated data
                $customer = Mage::getModel('customer/convert_adapter_customer');
                $newCustomerId = $customer->saveImportData($newCustomer);

                // profiling
                //$mtime = explode(' ', microtime());
                //$totaltime = $mtime[0] + $mtime[1] - $_starttime;
                //echo '$customerId ' . number_format($totaltime) . ' secs. \n\r';


            } catch (Exception $e) {

                // log all exceptions, possibly remove throw to prevent stopping import
                Mage::log($e->getMessage(), null, "dataflowimport.log");
                //throw new Exception($e->getMessage());
            }

            // attempt to clean up memory
            unset($newCustomer);
            unset($shipping);
            unset($billing);
            unset($billingAddresses);
            unset($shippingAddresses);

            // Create log entry of new customer or error
            if (isset($newCustomerId)) {
                Mage::log("Customer Created with Id: " . $newCustomerId, null, "dataflowimport.log");
            } else {
                Mage::log("Customer Creation FAILED:" . $_c['first_name'] . " " . $_c['last_name'] . ", Memeber Id:" . $_c['member_id']);
            }
        }

       // profiling
       // echo "Import Complete, ";
       // $mtime = explode(' ', microtime());
       // $totaltime = $mtime[0] + $mtime[1] - $starttime;
       // echo 'PHP Time:  ' . number_format($totaltime) . ' secs. <br />';

        Mage::log("Import Completed", null, "dataflowimport.log");
    }

}

?>