<?php


/**
 * Wordpress to Confluence migration utility: helper functions
 *
 * @author Mike Sollanych <mike.sollanych@visioncritical.com>
 * @package visioncritical/wordpress-to-confluence
 */

class Helpers {

  static function downloadFile($url, $destination) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSLVERSION,3);
    $data = curl_exec ($ch);
    $error = curl_error($ch); 
    curl_close ($ch);

    $file = fopen($destination, "w+");
    fputs($file, $data);
    fclose($file);
  }

  static function downloadImage($url) {

    $destination = realpath(__DIR__."/../tmp-images")."/".basename($url);
    self::downloadFile($url, $destination);

    if (!file_exists($destination)) return false;
    else return $destination;
  }
}