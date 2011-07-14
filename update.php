<?php

define( 'ZOT_DB_UPDATE_VERSION' , 1000 );


/**
 *
 * update.php - automatic system update
 *
 * Automatically update database schemas and any other development changes such that
 * copying the latest files from the source code repository will always perform a clean
 * and painless upgrade.
 *
 * Each function in this file is named update_nnnn() where nnnn is an increasing number 
 * which began counting at 1000.
 * 
 * At the top of the file "boot.php" is a define for ZOT_DB_VERSION. Any time there is a change
 * to the database schema or one which requires an upgrade path from the existing application,
 * the ZOT_DB_VERSION and the ZOT_DB_UPDATE_VERSION at the top of this file are incremented.
 *
 * The current ZOT_DB_VERSION is stored in the config area of the database. If the application starts up
 * and ZOT_DB_VERSION is greater than the last stored build number, we will process every update function 
 * in order from the currently stored value to the new ZOT_DB_VERSION. This is expected to bring the system 
 * up to current without requiring re-installation or manual intervention.
 *
 * Once the upgrade functions have completed, the current ZOT_DB_VERSION is stored as the current value.
 * The ZOT_DB_VERSION will always be one greater than the last numbered script in this file. 
 *
 * If you change the database schema, the following are required:
 *    1. Update the file database.sql to match the new schema.
 *    2. Update this file by adding a new function at the end with the number of the current ZOT_DB_VERSION.
 *       This function should modify the current database schema and perform any other steps necessary
 *       to ensure that upgrade is silent and free from requiring interaction.
 *    3. Increment the ZOT_DB_VERSION in boot.php *AND* the ZOT_DB_UPDATE_VERSION in this file to match it
 *    4. TEST the upgrade prior to checkin and filing a pull request.
 *
 */
