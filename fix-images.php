#!/usr/bin/env php
<?php 

/**
 * Wordpress to Confluence migration utility: fix images in Confluence
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
$space = $args["target-space"];

print ("Confluence image fix utility running...");

// // Quick query to make sure things are working and to validate the space key
// print("\nChecking credentials...");
// $spacetest = Request::get($base."/space")
//  ->authenticateWith($args["confluence-username"], $args["confluence-password"])
//  ->send();

// if ($spacetest->code != "200") {
//   die(k($spacetest->raw_headers));
// }

// Create the template
$template = Request::init()  
    ->expectsJson() 
    ->authenticateWith($args["confluence-username"], $args["confluence-password"]);
 
// Set it as a template
Request::ini($template);
 
// Go and get a list of pages for this space
print ("\nGetting a list of pages for space $space...");
$contentremains = true;
$limit = 20;
$start = 0;

$templimit = 3;
$tempcount = 0;

while ($contentremains) {
  print ("\nGetting records from $start to ".($start + $limit)."...");
  $content = Request::get("$base/space/$space/content?limit=$limit&start=$start")->send();

  if ($content->body->page->size < $content->body->page->limit)
    $contentremains = false;
  else 
    $start = $start + $limit;

  // Now for each of these entries, go get the actual blog post
  foreach ($content->body->page->results as $result) {

    // Init for this loop
    unset($dom, $imgmap, $imgpost, $xpathimages);

    // Save Post ID
    $id = $result->id;

    print("\n\nChecking post: ".$result->title);

    // Get that entire post
    $post = Request::get("$base/content/$id?expand=body.storage,version")->send();

    // Make sure it isn't already converted and full of nasty confluence template tags
    if (strpos($post->body->body->storage->value, "<ac:") !== false) {
      print("\nThis page is full of nasty Confluence prefixed XML that makes DOMDocument barf, skipping");
      continue;
    }

    // Parse the XML result as HTML, with a few extra namespaces
    $dom = new domDocument();
    $dom->loadHTML($post->body->body->storage->value);

    if (!$dom) continue;

    // Look for image tags
    $xpath = new DOMXpath($dom);
    $xpathimages = $xpath->query("//img");

    $images = [];
    for ($i = 0; $i < $xpathimages->length; $i++) {
      $imgsrc = $xpathimages->item($i)->getAttribute("src");

      if (strpos(strtolower($imgsrc), "redbook") !== false) {
        // It's a redbook image!
        print ("\n   $imgsrc");
        $images[] = $imgsrc;
      }
    }

    print ("\nFound ".count($images)." images to replace.");
    if (count($images) == 0) {
      print("\nSkipping post...");
      continue;
    }

    // Download them from the source
    $imgmap = [];
    foreach ($images as $imgsrc) {
      $local = Helpers::downloadImage($imgsrc);

      if ($local) {
        print ("\nGot $imgsrc locally as $local");
        $imgmap[basename($local)] = $imgsrc;
        $imgpost[basename($local)] = $local;
      }
      else {
        print ("\nCould not download $imgsrc, leaving broken link as-is.");
      } 
    }

    // Upload them en-masse as attachments to that post
    // print ("\nGoing to upload these images: ");
    // k(array_values($imgmap));

    // Store working attachments as name => old URL to replace
    $attmap = [];

    foreach ($imgpost as $name => $filename) {

      // Woulda done this with Httpful but it can't get the request right 
      // This is clean enough
      $curlauth = $args["confluence-username"].':'.$args["confluence-password"];
      $command = 'curl --silent -u "'.$curlauth.'" -X POST -H "X-Atlassian-Token: no-check" -F "file=@'.$filename.'" -F "comment='.$name.'" "'.$base.'/content/'.$id.'/child/attachment"';

      $output = null;
      $return = null;

      // print ("\n\nGonna curl upload $name : \n$command \n\n");
      print ("\nUploading $filename");
      exec($command, $output, $return);

      if ($return > 0) {
        print("\nError curling! Exit status: $return \n");
        print("\nCommand was: $command\n\n");
        var_dump($output);
        print("\nNot transforming this post. Skipping...");
        continue 2;
      }
      else {
        $upload_result = json_decode($output[0]);
        if (!$upload_result || !$upload_result->results || !$upload_result->results[0]->title) {
          print("\nCouldn't handle upload result. Skipping post.");
          continue 2;
        }

        // Was it OK?
        // k($upload_result);

        // Confirm matching filename in the results
        $result_filename = $upload_result->results[0]->title;
        if ($result_filename != $name) {
          print("\nError with file upload for $filename. Skipping.");
        }
        else {
          // This one is good. 
          // Keep it in the attachment map of working new attachment name => old URL
          $attmap[$imgmap[$name]] = $name;
        }
      }

      // unlink($filename);
    }

    // Adjust the original post to the new format that should line up with the attachments.
    print ("\nAdjusting DOM document now...");
    $changes = 0;
    for ($i = 0; $i < $xpathimages->length; $i++) {

      $imgnode = $xpathimages->item($i);

      $imgsrc = $imgnode->getAttribute("src");
      $imgalt = $imgnode->getAttribute("alt");
      $imgtitle = $imgnode->getAttribute("title");

      // Is the source of this image in the attmap?
      if (!array_key_exists($imgsrc, $attmap)) {
        print ("\nCould not find $imgsrc as a successful upload, skipping...");
        continue;
      }
      else {
        $attname = $attmap[$imgsrc];
        print("\nReplacing $imgsrc with attachment link to $attname");
      }

      // Create the new node.
      // Format is like so:
      // <ac:image ac:alt=\"the cloud\" ac:title=\"the cloud\"><ri:attachment ri:filename=\"clouds.jpg\" /></ac:image>
      $newAcImageNode = $dom->createElement("ac:image");
      $newAcImageNode->setAttribute("ac:alt", $imgalt); // retain that alt tag!
      $newAcImageNode->setAttribute("ac:title", $imgtitle); // and the title too

      // Create the child attachment reference and put it inside
      $newRiAttachmentNode = $dom->createElement("ri:attachment");
      $newRiAttachmentNode->setAttribute("ri:filename", $attname);
      $newAcImageNode->appendChild($newRiAttachmentNode);

      // Find the parent of the image tag
      $imgpar = $imgnode->parentNode;

      // Replace the original img tag with the ac:image tag
      $imgpar->replaceChild($newAcImageNode, $imgnode);

      $changes++;
    }

    if ($changes < 1) {
      print("\nNo changes to this page. Continuing.");
      continue;
    }
    else {
      // Generate the new contents of the body tag (without the tag itself)
      $bodynode = $dom->getElementsByTagName("body")[0];
      $bodyxml = $dom->saveXML($bodynode);
      $bodyxml = str_replace("<body>", "", $bodyxml);
      $bodyxml = str_replace("</body>", "", $bodyxml);
      
      // print("\n\nNew body content:\n$bodyxml");

      // Create the PUT to update the content
      $putdata = [
        "version" => ["number" => ($post->body->version->number + 1)],
        "type" => $post->body->type,
        "body" => ["storage" => ["value" => $bodyxml, "representation" => "storage"]]
      ];

      // Actually go and PUT that to the page
      $putresponse = \Httpful\Request::put("$base/content/$id")  
       ->sendsJson()                               // tell it we're sending (Content-Type) JSON...
       ->body(json_encode($putdata))             // attach a body/payload...
       ->send();    


       if ($putresponse->code == 200) {
        print("\nSuccessfully transformed post!\n\n\n");
      }
      else {
        print ("\nError transforming post: ");
       k($putresponse);
     }
    }

    // Early bailout for now
    // $tempcount++;
    // if ($tempcount > $templimit) break 2;
  }

}