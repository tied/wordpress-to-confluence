<?php 

/**
 * Wordpress to Confluence migration utility: connect to Wordpress, get posts
 *
 * @author Mike Sollanych <mike.sollanych@visioncritical.com>
 * @package visioncritical/wordpress-to-confluence
 */


$wp = new \HieuLe\WordpressXmlrpcClient\WordpressClient();

$wp->setCredentials($args['wordpress-url'] . "xmlrpc.php", 
                          $args['wordpress-username'], 
                          $args['wordpress-password']);
try {
  $posts = $wp->getPost(11507);
}
catch (Exception $e) {
  die($e->getMessage());
}

k($posts);