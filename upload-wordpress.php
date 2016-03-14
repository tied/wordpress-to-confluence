#!/usr/bin/env php
<?php 

/**
 * Wordpress to Confluence migration utility: upload Wordpess XML to confluence
 *
 * @author Mike Sollanych <mike.sollanych@visioncritical.com>
 * @package visioncritical/wordpress-to-confluence
 */

require("vendor/autoload.php");
require("includes/helpers.class.php");

use Httpful\Request;

// Handle command line arguments
require("includes/conf-commandline.inc.php");
$args = getArguments();

// REST base URL
$base = $args["confluence-url"] . 'rest/api';
$target = $args["target-space"];

// XML file
$wpxmlfile = $args["wordpress-xml"];
if (!file_exists($wpxmlfile)) die("Could not find $wpxmlfile");

// Looking good. Now let's fuck the shit out of these stupid XML namespaces
$wpxmlraw = file_get_contents($wpxmlfile);
foreach(["wp", "dc", "content", "excerpt"] as $fuckingnamespace) {
  $wpxmlraw = str_replace("<$fuckingnamespace:", "<", $wpxmlraw);
  $wpxmlraw = str_replace("</$fuckingnamespace:", "</", $wpxmlraw);
}

$wpxml = new SimpleXMLElement($wpxmlraw);
if (!$wpxml) die("Could not load $wpxmlfile as XML");

// k($wpxml);

// Create the template
$template = Request::init()  
    ->expectsJson() 
    ->authenticateWith($args["confluence-username"], $args["confluence-password"]);
 
// Set it as a template
Request::ini($template);

// Begin
print ("Confluence blog post upload running...");

// print("\nGetting space details...");
// $spacetest = Request::get($base."/space")->send();

// if ($spacetest->code != "200") {
//   die(k($spacetest->raw_headers));
// }

// Get ID of target space
// $target_id = false;
// foreach($spacetest->body->results as $res) {
//   if ($res->key == $target) $target_id = $res->id;
// }

// if (!$target_id) die("\nCould not find target space!");

// Loop over items
foreach ($wpxml->channel->item as $item) {

  print("\nUploading post: ".(string)$item->title);
  
  // Assemble the object to post
  $newpage = [
    "type" => "page",
    "title" => (string)$item->title,
    "space" => [ "key" => $target ],
    "body" => [ "storage" => [ "value" => (string)$item->encoded[0], "representation" => "storage" ] ]
  ];

  // Go post it
  $postresponse = Request::post("$base/content")
    ->sendsJson()
    ->body(json_encode($newpage))
    ->send();

  if ($postresponse->code != 200) {
    print("\nError uploading: code ".$postresponse->code."\n");
    k($postresponse->body->message);
    print("\nSkipping...");
    continue;
  }
  else {
    print("\nSuccessful upload. ID: ".$postresponse->body->id);
  }

  // Add tags
  $tags = [];
  if ($item->category) foreach ($item->category as $cat) {
    if ($cat->attributes()['nicename']) {
      $tags[] = (string)$cat->attributes()['nicename'];
    }
  }

  if (count($tags) > 0) {
    // Assemble into Confluence's expected format
    $ctags = [];
    foreach ($tags as $tag) {
      $ctags[] = ["prefix" => "global", "name" => $tag];
    }

    // Go post them to the content
    print("\nAdding labels: ".implode(", ", $tags));
    $labelpostresponse = Request::post("$base/content/".$postresponse->body->id."/label")
      ->sendsJson()
      ->body(json_encode($ctags))
      ->send();


      if ($postresponse->code != 200) {
        print("\nError adding labels: code ".$postresponse->code."\n");
        k($postresponse->body->message);
      }
      else {
        print("\nSuccessfully added labels.");
      }
  }
}