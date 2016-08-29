<?php 

/**
 * Wordpress to Confluence migration utility: handle command line arguments
 *
 * @author Mike Sollanych <mike.sollanych@visioncritical.com>
 * @package visioncritical/wordpress-to-confluence
 */

use Particle\Validator\Validator;

function getArguments() {
  $clihelp = <<<'EOM'

  Wordpress to Confluence migration utility

  Parameters:
    --wordpress-url=<URL of Wordpress server>
    --wordpress-username=<Wordpress username>
    --wordpress-password=<Wordpress password, will prompt if blank>
EOM;

  // Get command line arguments
  $args = CommandLine::parseArgs($_SERVER['argv']);

  // Establish scema
  $validator = new Validator;
  $validator->required('wordpress-url')->url();
  $validator->required('wordpress-username');
  $validator->optional('wordpress-password');

  // Validate
  $result = $validator->validate($args);

  // Handle errors if anything is critically broken
  if (!$result->isValid()) {
    print ("Invalid input:");
    k($result->getMessages());
    die($clihelp);
  } 

  // Ask for passwords if not provided
  if (!array_key_exists("wordpress-password", $args)) {
    echo "Wordpress password for ${args['wordpress-url']}: ";
    $args['wordpress-password'] = Seld\CliPrompt\CliPrompt::hiddenPrompt();
  }

  // Normalize URLs
  if (substr($args['wordpress-url'], -1) != '/') $args['wordpress-url'] .= '/';

  return $args;
}