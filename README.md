# wordpress-to-confluence
Wordpress to Confluence migration utility

upload-wordpress.php takes Wordpress export XML and uploads it
move-posts.php moves posts en-masse within Confluence
fix-images.php is VC-specific and looks for 'Redbook' image URLs, downloads the image and uploads it to Confluence and then fixes the links within the post