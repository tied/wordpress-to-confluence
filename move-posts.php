#!/usr/bin/env php
<?php 

/**
 * Wordpress to Confluence migration utility: move blog posts to new parent space
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
$source = $args["source-space"];
$target = $args["target-space"];

// Create the template
$template = Request::init()  
    ->expectsJson() 
    ->authenticateWith($args["confluence-username"], $args["confluence-password"]);
 
// Set it as a template
Request::ini($template);

// Begin
print ("Confluence blog post move running...");

print("\nGetting space details...");
$spacetest = Request::get($base."/space")->send();

if ($spacetest->code != "200") {
  die(k($spacetest->raw_headers));
}

// Get ID of target space
$target_id = false;
foreach($spacetest->body->results as $res) {
  if ($res->key == $target) $target_id = $res->id;
}

if (!$target_id) die("\nCould not find target space!");
 
// Go and get a list of pages for this space
print ("\nGetting a list of pages for space $source...");
$contentremains = true;
$limit = 20;
$start = 0;

$templimit = 0;
$tempcount = 0;

while ($contentremains) {
  print ("\nGetting records from $start to ".($start + $limit)."...");
  $content = Request::get("$base/space/$source/content?limit=$limit&start=$start")->send();

  if ($content->body->blogpost->size < $content->body->blogpost->limit)
    $contentremains = false;
  else 
    $start = $start + $limit;

  // Now for each of these entries, go get the actual blog post
  foreach ($content->body->blogpost->results as $result) {

    // Save Post ID
    $id = $result->id;

    print("\nMoving post: ".$result->title);

    // Get that entire post
    $post = Request::get("$base/content/$id?expand=body.storage,version")->send();

    // Create the PUT to update the content
    $putdata = [
      "version" => ["number" => ($post->body->version->number + 1)],
      "type" => "page",
      "ancestors" => [["id" => $target_id]]
    ];

    // Actually go and PUT that to the page
    $putresponse = \Httpful\Request::put("$base/content/$id")  
     ->sendsJson()                               // tell it we're sending (Content-Type) JSON...
     ->body(json_encode($putdata))             // attach a body/payload...
     ->send();    


    if ($putresponse->code == 200) {
      print("\nSuccessfully moved post!\n\n\n");
    }
    else {
      print ("\nError moving post: ");
     k($putresponse);
    }

    // Early bailout for now
    $tempcount++;
    if ($tempcount > $templimit) break 2;
  }

}