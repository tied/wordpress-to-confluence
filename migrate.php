#!/usr/bin/env php
<?php 

/**
 * Wordpress to Confluence migration utility
 *
 * @author Mike Sollanych <mike.sollanych@visioncritical.com>
 * @package visioncritical/wordpress-to-confluence
 */

require("vendor/autoload.php");

// Handle command line arguments
require("includes/wp-commandline.inc.php");
$args = getArguments();

// Connect to Wordpress
// require("includes/wordpress.inc.php");

// Dump posts to disk, probably as marshalled objects?

// Transfer posts to Confluence 
