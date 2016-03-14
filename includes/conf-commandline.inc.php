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
    --confluence-url=<URL of Confluence server REST endpoint>
    --confluence-username=<username>
    --confluence-password=<password, will prompt if blank>
    --target-space=<target space short key name>
    
EOM;

  // Get command line arguments
  $args = CommandLine::parseArgs($_SERVER['argv']);

  // Establish scema
  $validator = new Validator;
  $validator->required('confluence-url')->url();
  $validator->required('confluence-username');
  $validator->required('target-space');
  $validator->optional('confluence-password');

  // Validate
  $result = $validator->validate($args);

  // Handle errors if anything is critically broken
  if (!$result->isValid()) {
    print ("Invalid input:");
    k($result->getMessages());
    die($clihelp);
  } 

  // Ask for passwords if not provided
  if (!array_key_exists("confluence-password", $args)) {
    echo "Confluence password for ${args['confluence-url']}: ";
    $args['confluence-password'] = Seld\CliPrompt\CliPrompt::hiddenPrompt();
  }

  // Normalize URLs
  if (substr($args['confluence-url'], -1) != '/') $args['confluence-url'] .= '/';

  return $args;
}