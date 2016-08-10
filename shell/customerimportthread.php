<?php
/*
 *  @encoding  UTF-8
 *  @date      May 7, 2012
 *  @name      customerimport_thread.php
 *  @author    Jared Blalock (info@molotovbliss.com)
 */

require_once 'abstract.php';
set_time_limit(0);

class Mage_Shell_Customerimportthread extends Mage_Shell_Abstract {
    public function run() {

        $args = $_SERVER['argv'];
        /// Grab paramteres
        if (isset($args[1])) {
            $records = $args[1];
        } else {
            $records = "";
        }
        if (isset($args[2])) {
            $offset = $args[2];
        } else {
            $offset = "";
        }

        // start the import processes
        $import = Mage::getModel('dataflowimport/customer')->importCustomers($records, $offset);

    }
}

$shell = new Mage_Shell_Customerimportthread();
$shell->run();

?>
