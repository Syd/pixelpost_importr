<?php

/*

Importr

Importr imports special tagged pictures from flickr with pixelpost (only flickr --> pp)

Uses phpflickr by Dan Coulter
http://www.phpflickr.com

Version: 0.65

Author: christian cueni

http://www.trivialview.ch


Contact: chrigu [at] trivialview [dot] ch


License: http://www.gnu.org/copyleft/gpl.html

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
*/

//error_reporting(1);

if (strpos(__FILE__, ':') !== false) {
    $path_delimiter = ';';
} else {
    $path_delimiter = ':';
}

//includes

require_once("phpFlickr.php");
require("../../includes/functions.php");
require("../../includes/pixelpost.php");

//Paths

$imgpath = "../../images/";
$thumbpath = "../../thumbnails/";

//Start myql

mysql_connect($pixelpost_db_host, $pixelpost_db_user, $pixelpost_db_pass) || die("Error: ". mysql_error());
mysql_select_db($pixelpost_db_pixelpost) || die("Error: ". mysql_error());

// Create new phpFlickr object
$f = new phpFlickr('apikey', 'apisecret');
$f->setToken("apitoken");

$query = mysql_query("select cat_tag, import_tag, username, thumb, size from ".$pixelpost_db_prefix."importroptions where id='1'");

list($prefix, $flag, $username, $thumbflag, $size) = mysql_fetch_row($query);
mysql_query( $query );

//if no import tag is defined exit
if($flag == "")
{
   mysql_close();
   echo "No import tag found. Exiting...";
   die();
}

//check if GoogleMaps addon is installed.

$google_addon = "false";

$query = "SELECT id FROM {$pixelpost_db_prefix}GoogleMap LIMIT 1";
if(mysql_query( $query ) ) 
   $google_addon = "true";

//Manual override
//$username = '';
//$flag = '';
//$prefix = '';


// Find the NSID of the username inputted via the form
$person = $f->people_findByUsername($username);
    
// Get the friendly URL of the user's photos
$photos_url = $f->urls_getUserPhotos($person['id']);
    
// Get the user's photos that are marked to sync
$photos = $f->photos_search(array("tags"=>$flag, "user_id"=>$person['id']));

if($photos == null)
{
   mysql_close();
   die();
}

// Loop through the photos and output the html
foreach ((array)$photos['photo'] as $photo) 
{
   //check if picture is already in db
   //if ok do all the stuff
   $query = "select * from ".$pixelpost_db_prefix."flickrassoc where flickr_id='".$photo['id']."'";
   $result = mysql_query($query);
   $numrows = mysql_num_rows($result);
   if($numrows != 0 || $result == NULL)
   {
       echo "PictureID: ".$photo['id']." already in DB<br>";
       continue;
    }
      
   
   $info = $f->photos_getInfo($photo['id']);
   $tag_entries = $info['tags'];
   $location = $info['location'];
   $headline = $info['title'];
   $body = $info['description'];
   $pp_tags = "";
   $pp_cats = array();
   
   //var_dump($photo);

   //loop through tags
   foreach ((array)$tag_entries as $tags) 
   {
      foreach ((array)$tags as $tag) 
      { 
         //check if the owner made the tag
         if($tag['author'] == $person['id'])
         {
            //sepreate categories from tags
            if(substr($tag['raw'],0,strlen($prefix)) == $prefix)
            {
               $pp_cats[] = substr($tag['raw'],strlen($prefix));
               $f->photos_removeTag($tag['id']);
            }
            elseif($tag['raw'] == $flag)
            {
                $exif = $f->photos_getExif($photo['id']);
                $location = $f->photos_geo_getLocation($photo['id']);   
                $f->photos_removeTag($tag['id']);
            }
            else
            {
               $pp_tags = $pp_tags." ".$tag['raw'];
            }  
         }
      }
   }

   //get geo information   
   if($info['location']['latitude'] != "" && $info['location']['longitude'] != "")
      $geo = "({$info['location']['latitude']}, {$info['location']['longitude']})";
   else
      $geo = "";
   
   //Start verbose output
   /*
   echo "<p>location: $geo</p>"; 
   echo "<p>title: $headline</p>"; 
   echo "<p>description: $body</p>"; 
       
   echo "<p>pptags:</p>"; 
   var_dump($pp_tags);
   var_dump($info);

   echo "<p>ppcats:</p>"; 
   var_dump($pp_cats);
   */
   //End verbose output
   
   //get date
   $datetime = date("Y-m-d H:i:s",$info['dates']['posted']);
   $taken = $info['dates']['taken'];
   //echo "<br />posted: ".$posted." taken: $taken";
   $time_stamp_r = date("Y-m-d H:i:s",$info['dates']['posted']) .'_';

   //echo "<p>info:</p>"; 
   //var_dump($pp_cats);
   //$f->photos_removeTag($tag['id']);
   //echo "<p>$tag[raw] removed!</p>"; 
              
   
   //create filename & pp timestamp
            
    $userfile = "$photo[id].$info[originalformat]";
    $tz = $cfgrow['timezone'];

    //	if ($cfgrow['timestamp']=='yes')
	$time_stamp_r = gmdate("YmdHis",time()+(3600 * $tz)) .'_';

	$uploadfile = $imgpath .$time_stamp_r .$userfile;
    $ch = curl_init($f->buildPhotoURL($info, $size));
    $fp = fopen($uploadfile, "w");

    //get file from flickr
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_HEADER, 0);

     curl_exec($ch);
     curl_close($ch);
     fclose($fp);
            
     //move picture to repository
     chmod($uploadfile, 0644);
	 $filnamn = $time_stamp_r .$photo['id'];
	 $status = "ok";
	//Get the exif data so we can store it.
	// what about files that don't have exif data??
			
	require_once("../../includes/exifer1_5/exif.php");
	include_once('../../includes/functions_exif.php');
	
	//serialize EXIF  
	
    $exif_info_db = serialize_exif($uploadfile);

	if($postdatefromexif == TRUE)
	{
	   // since we all ready escaped everything for database commit we have
	   // strip the slashes before we can use the exif again.
	   $exif_info = stripslashes($exif_info_db);
	   $exif_result=unserialize_exif($exif_info);
	   $exposuredatetime = $exif_result['DateTimeOriginalSubIFD'];
	   if($exposuredatetime!='')
	   {
	      list($exifyear,$exifmonth,$exifday,$exifhour,$exifmin, $exifsec) = split('[: ]', $exposuredatetime);
	       $datetime = date("Y-m-d H:i:s", mktime($exifhour, $exifmin, $exifsec, $exifmonth, $exifday, $exifyear));
	   }
	   else	$datetime = gmdate("Y-m-d H:i:s",time()+(3600 * $tz));
	} 
	        
	//create thumbnail
	//download thumbnail from flickr if flag set
	        
	if($thumbflag != 2)
	{
	   if($thumbflag == 0)
	      $flickr_format = "square";
	   else
	      $flickr_format = "thumbnail";

	   $userfile = "$photo[id].$info[originalformat]";
            
	   $thumbnail = $thumbpath."thumb_".$time_stamp_r.$userfile;
       $ch = curl_init($f->buildPhotoURL($info, $flickr_format));
       $fp = fopen($thumbnail, "w");
       curl_setopt($ch, CURLOPT_FILE, $fp);
       curl_setopt($ch, CURLOPT_HEADER, 0);
       curl_exec($ch);
       curl_close($ch);
       fclose($fp);
	 }
	        
	 //else create local thumb
	 elseif(function_exists('gd_info'))
	 {
	    $gd_info = gd_info();

		if($gd_info != "")
		{
		   $thumbnail = $filnamn.".jpg";
		   $thumbnail = createthumb2($thumbnail);
		   eval_addon_admin_workspace_menu('thumb_created');

		   // if crop is not '12c' use the oldfashioned crop
		   if ($cfgrow['crop']!='12c')
		   {
		      if ($show_image_after_upload)
			     echo "<img src='".$imgpath."$filnamn'  />";
			   echo "</div><!-- end of content div -->" ; // close content div
		   }// end if
		   /* else it is '12c' crop and show cropdiv and the cropping frame
				at the bottom of the page.
			*/
		   else
		   {
		      // set the size of the crop frame according to the uploaded image
			  setsize_cropdiv ($filnamn);
			  //--------------------------------------------------------
			  $for_echo ="
						<img src='".$imgpath."$filnamn' id='myimg' />
						<div id='cropdiv'>
						<table width='100%' height='100%' border='1' cellpadding='0' cellspacing='0' bordercolor='#000000'>
						<tr>
						<td><img src='".$spacer."' /></td>
						</tr>
						</table>
						</div> <!-- end of crop div -->
						<div id='editthumbnail'>
						<hidden>$admin_lang_ni_crop_background</hidden>
						</div><!-- end of editthumbnail id -->

					</div> <!-- end of content div -->  ";
					echo $for_echo;
				//--------------------------------------------------------
		   } // end else
	    } // gd info
	} // function_exists

     
    $image = $filnamn.".".$info[originalformat];
    
    $headline = mysql_real_escape_string($headline);
    $body = mysql_real_escape_string($body);
            
    if($google_addon == 'false' || $geo == "")
    {
       $query = "insert into ".$pixelpost_db_prefix."pixelpost(datetime,headline,body,image,alt_headline,alt_body,comments,exif_info)
			VALUES('$datetime','$headline','$body','$image','$alt_headline','$alt_body','$comments_settings','$exif_info_db')";
	}
	else 
	{
       $query = "insert into ".$pixelpost_db_prefix."pixelpost(datetime,headline,body,image,alt_headline,alt_body,comments,exif_info,googlemap)
			VALUES('$datetime','$headline','$body','$image','$alt_headline','$alt_body','$comments_settings','$exif_info_db','$geo')";	
	}
	$result = mysql_query($query) || die("Error: ".mysql_error().$admin_lang_ni_db_error);

	$picid = mysql_insert_id(); //Gets the id of the last added image to use in the next "insert"
	        
	//add pic to flickr-pp assoc db
    $query = "insert into ".$pixelpost_db_prefix."flickrassoc(id,flickr_id,pp_id) VALUES(NULL,'$photo[id]','$picid')";
	$result = mysql_query($query) || die("Error: ".mysql_error().$admin_lang_ni_db_error);

    //check for categories the picture goes in
	if (!empty($pp_cats))
	{
       foreach($pp_cats as $val)
       {
          //echo "pp_cat ".$val;
			
		  //check the category already exists in the db
		  //if not add it
		   $query = "SELECT * from ".$pixelpost_db_prefix."categories where name='".$val."'";
		   $result = mysql_query($query);
		   $numrows = mysql_num_rows($result);
		   $theid = -1;
		   if($numrows == NULL || $numrows == 0)
		   {
		      $query = "insert into ".$pixelpost_db_prefix."categories(id,name,alt_name) VALUES(NULL,'$val','$alt_category')";
		      $result = mysql_query($query) || die("Error: ".mysql_error());
		      $theid = mysql_insert_id();
		    }
		    //else get category id
		    else 
		    {
		       $row = mysql_fetch_array($result);
		       $theid = $row["id"];
		    }
			
			//add picture to category db
			//echo "ID: ".$theid." picid: ".$picid;
            $query  = "INSERT INTO ".$pixelpost_db_prefix."catassoc(id,cat_id,image_id) VALUES(NULL,'$theid','$picid')";
            $result = mysql_query($query) || die("Error: ".mysql_error());         
	     }
	  }
		    
     // save tags
	 save_tags_new($pp_tags,$picid);
	 // save the alt_tags to if the variable is set
	 if ($cfgrow['altlangfile'] != 'Off'){
	 //	save_alt_tags_new($pp_tags,$theid);
	 }
		
	 //check for presence of geodb from GoogleMaps addon!
	 //add geo data
	        
 
}

/*

From pixelpost.org's functions.php

Here because of path problems

*/

function createthumb2($file)
{
  global $pixelpost_db_prefix;
  global $imgpath;
  global $thumbpath;
  $cfgquery = mysql_query("select * from ".$pixelpost_db_prefix."config");
  $cfgrow = mysql_fetch_array($cfgquery);
  // credit to codewalkers.com - there is 90% a tutorial there
  $max_width = $cfgrow['thumbwidth'];
  $max_height = $cfgrow['thumbheight'];
  define(IMAGE_BASE, rtrim($imgpath,"/"));
  $image_path = IMAGE_BASE . "/$file";
  $img = null;
  $image_path_exp = explode('.', $image_path);
  $image_path_end = end($image_path_exp);
  $ext = strtolower($image_path_end);
  if ($ext == 'jpg' || $ext == 'jpeg')
  {
    $img = @imagecreatefromjpeg($image_path);
  }
  elseif($ext == 'png')
  {
    $img = @imagecreatefrompng($image_path);
  }
  elseif($ext == 'gif')
  {
    $img = @imagecreatefromgif($image_path);
  }
echo "img dump:";
var_dump($img);
  if($img)
  {
    $width = imagesx($img);
    $height = imagesy($img);
    $scale = max($max_width/$width, $max_height/$height);

    if($scale < 1)
    {
      $new_width = floor($scale*$width);
      $new_height = floor($scale*$height);
      $tmp_img = imagecreatetruecolor($new_width,$new_height);
      // gd 2.0.1 or later: imagecopyresampled
      // gd less than 2.0: imagecopyresized
      if(function_exists(imagecopyresampled))
      {
        imagecopyresampled($tmp_img, $img, 0,0,0,0,$new_width,$new_height,$width,$height);
      }
      else
      {
        imagecopyresized($tmp_img, $img, 0,0,0,0,$new_width,$new_height,$width,$height);
      }

	    imagedestroy($img);
      if ($cfgrow['thumb_sharpening']!=0){
			 $tmp_img = unsharp_mask($tmp_img, $cfgrow['thumb_sharpening']); 
			}
	    $img = $tmp_img;
    }

    if($cfgrow['crop'] == "yes" | $cfgrow['crop'] == "12c")
    {
      // crop
      $tmp_img = imagecreatetruecolor($max_width,$max_height);
      if(function_exists(imagecopyresampled))
      {
        imagecopyresampled($tmp_img, $img, 0,0,0,0,$max_width,$max_height,$max_width,$max_height);
      }
      else
      {
        imagecopyresized($tmp_img, $img, 0,0,0,0,$max_width,$max_height,$max_width,$max_height);
      }
      imagedestroy($img);
      if ($cfgrow['thumb_sharpening']!=0){
			 $tmp_img = unsharp_mask($tmp_img, $cfgrow['thumb_sharpening']); 
			}
      $img = $tmp_img;
    } // end crop yes
  }
  touch($thumbpath."thumb_$file");
  echo $thumbpath."thumb_$file ".$cfgrow['compression'];
  echo $thumbpath."thumb_$file";
  imagejpeg($img,$thumbpath."thumb_$file",$cfgrow['compression']);
  $thumbimage = $thumbpath."thumb_$file";
  chmod($thumbimage,0644);
} 
  
mysql_close();

?>