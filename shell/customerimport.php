<?php
/*
 *  @encoding  UTF-8
 *  @date      May 6, 2012
 *  @name      customerimport.php
 *  @author    Jared Blalock (info@molotovbliss.com)
 */

require_once 'abstract.php';
require_once 'lib/MultiThreading/MultiThreading.php';
set_time_limit(0);

/**
 * Class wrapper for shell execution of customer import with addresses
 * Utilize multithreading to speed up processing time
 */
class Mage_Shell_Customerimport extends Mage_Shell_Abstract {

    /**
     * Run script
     *
     */
    public function run() {

        // profiling
        $starttime = explode(' ', microtime());
        $starttime = $starttime[1] + $starttime[0];

        $args = $_SERVER['argv'];

        if (isset($args[1])) {
            $numRecordsPerThread = (int) $args[1];
        } else {
            $numRecordsPerThread = 40000; // default if no args
        }

        if (isset($args[2])) {
            $totalRecords = (int) $args[2];
        } else {
            // get Read DB Connection
            $connection = Mage::getSingleton('core/resource')->getConnection('core_read');

            // get total number of member records to import
            $sql = "SELECT COUNT(id) as total FROM members_import";
            $numRecords = $connection->fetchAll($sql);
            $totalRecords = $numRecords[0]['total'];
        }

        // first argument, number of records per thread to process
        // second argument is the offset incremented
        $cmd = 'php -f '. Mage::getBaseDir('base').'/shell/customerimportthread.php';

        for($i=0;$i < $totalRecords;$i = $i + $numRecordsPerThread) {
            $command = $cmd.' '.$numRecordsPerThread.' '. $i . ' 2>&1';
            echo "Adding ".$command." for thread\n\r";
            $commands[] = $command;
        }

        var_dump($commands);
        echo "Creating threads for processing... please wait...\n\r";
        $threads = new Multithread($commands);
        $threads->run();
        foreach ($threads->commands as $key=>$command){
                echo "Command ".$command.":\n\r";
                echo "Output ".$threads->output[$key]."\n\r";
                //echo "Error ".$threads->error[$key]."\n\r";
        }

    }

    /**
     * Retrieve Usage Help Message
     *
     */
    public function usageHelp() {
        return <<<USAGE
Usage:  php -f customerimport.php number_of_records_per_thread total_records_to_import

  help                          This help
  number_of_records_per_thread  integer number of threads per (default 40000)
  total_records_to_import       integer number of total records to import (optional)

EXAMPLE:

Import 100,000 records at 20,000 per thread

php -f shell/customerimport.php 20000 100000


USAGE;
    }

}

$shell = new Mage_Shell_Customerimport();
$shell->run();

$mtime = explode(' ', microtime());
$totaltime = $mtime[0] + $mtime[1] - $starttime;
echo 'Main PHP Total Time:  ' . number_format($totaltime) . ' secs. <br />';
