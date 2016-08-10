Customer Import with addresses with pseudo-multi-threading
Written by: Jared Blalock (info@molotovbliss.com))


REQUIREMENTS:
================================================================================
members_import
members_shipping_import
members_billing_import

3 DB Tables are created and must be filled with pre-existing customer data that
will be used during import. LOAD DATA INFILE is the easiest way to import this data into
these 3 tables via MySQL.  A previous ID is required in the ```members_id``` table in order to
associate billing and shipping addresses properly.



SETUP:
================================================================================
For the script to work as-is using the below previous customer data needs to be loaded
into 3 DB Tables.  This can be reworked obviously to simply read data directly from CSV
files but for easier querying of existing data, 3 tables are needed.

Field data can be found in each LOAD DATA SQL Query below for each table, data can vary
however the ```member_id``` is the legacy customer ID used to associate customers with address
data.

    TRUNCATE TABLE `<database>`.`members_import`;
    TRUNCATE TABLE `<database>`.`members_billing_import`;
    TRUNCATE TABLE `<database>`.`members_shipping_import`;

    mysql -h <ip_here> -u <username> -p -e "LOAD DATA LOCAL INFILE '/full/local/path/members.csv' \
    IGNORE INTO TABLE `<database>`.`members_import` CHARACTER SET utf8 FIELDS TERMINATED BY ',' \
    OPTIONALLY ENCLOSED BY '"' LINES TERMINATED BY '\r\n' IGNORE 1 LINES \
    (`member_id`, `invitation_code`, `email`, `password`, `key`, `role`, `join_date`, `first_name`, \
    `last_name`, `zipcode`, `country_iso`, `gender`, `birthday`, `last_visit`, `soft_login`, `fraud_flag`, \
    `recurring_order_exempt`, `fetchback`, `sid`, `mid`, `cid`, `aid`, `username`, `inv_camp_id`, \
    `pub_site_id`, `tid`, `member_status`, `optin`, `optin_modified`, `esp`, `notes`);" <database>

    LOAD DATA LOCAL INFILE 'billing_full.csv' IGNORE INTO TABLE `<database>`.`members_billing_import` \
    CHARACTER SET utf8 FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '"' LINES TERMINATED BY '\r\n' \
    IGNORE 1 LINES (`billing_id`, `member_id`, `first_name`, `last_name`, `address`, `address2`, `city`, \
    `state`, `zipcode`, `country_iso`, `create_date`, `label`, `phone`, `active`, `card_number_mcrypt`, \
    `card_number`, `card_type`, `email`, `update_time`, `card_exp_year`, `card_exp_month`, `keep`, \
    `paypal_ba_id`, `payment_method`, `verified`, `main`);

    LOAD DATA LOCAL INFILE 'shipping_full.csv' IGNORE INTO TABLE `<database>`.`members_shipping_import` \
    CHARACTER SET utf8 FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '"' LINES TERMINATED BY '\r\n' \
    IGNORE 1 LINES (`shipping_id`, `member_id`, `first_name`, `last_name`, `company`, `address`, \
    `address2`, `city`, `state`, `zipcode`, `country_iso`, `create_date`, `label`, `phone`, `active`, \
    `email`, `update_time`);
    SHOW WARNINGS;

USAGE:
================================================================================

Usage:  ```php -f customerimport.php [number_of_records_per_thread] [total_records_to_import]```

  number_of_records_per_thread  integer number of threads per (default 25000)
  total_records_to_import       integer number of total records to import (optional)


EXAMPLE:
================================================================================

Import 100,000 records at 20,000 per thread

    php -f shell/customerimport.php 20000 100000

To monitor the activity during import, ```tail -f var/log/dataflowimport.log```

Get total cores available: ```cat /proc/cpuinfo | grep processor | wc -l```

NOTES: Executing without any number of records or total records to import will use
25000, with 600,000 records is 24 cores will be utilized during the process.
PHP & MySQL memory and timeout limits can lead to errors in processing.
