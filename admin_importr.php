<?php

/*

Importr

Importr imports special tagged pictures from flickr with pixelpost (only flickr --> pp)

Version: 0.65

Author: christian cueni

www.trivialview.ch


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

/************************************************************
* Add-on information
************************************************************/
$addon_name = "Importr";
$addon_description = "Import photos from flickr to pixelpost";
$addon_version = "0.65";

// The workspace. Where to activate the function inside index.php
$addon_workspace = "options";
$addon_workspace2 = "image_deleted";


// menu where the addon should appear in admin panel. in this case:
$addon_menu = "options";
$addon_menu2 = "";

// What would be the title of submenu of this addon:
$addon_admin_submenu = "Importr";
$addon_admin_submenu2 = "";

// What is the function
$addon_function_name = "Importr_options";
$addon_function_name2 = "Importr_delete_img";

/************************************************************
* Create tables 
************************************************************/


//check if tables exist
$query = "SHOW COLUMNS FROM {$pixelpost_db_prefix}flickrassoc";
$result = mysql_query($query);
//echo mysql_error();
if(!$result) 
{

       //add id association table
       $query = "CREATE TABLE IF NOT EXISTS `{$pixelpost_db_prefix}flickrassoc` (`id` int(11) NOT NULL AUTO_INCREMENT, `flickr_id` varchar(20) COLLATE utf8_unicode_ci NOT NULL, `pp_id` int(11) NOT NULL, PRIMARY KEY (`id`));";
       mysql_query( $query );
       echo mysql_error();
}

$query = "SHOW COLUMNS FROM {$pixelpost_db_prefix}importroptions";
$result = mysql_query($query);
//echo mysql_error();
if(!$result) 
{
       $query = "CREATE TABLE IF NOT EXISTS `{$pixelpost_db_prefix}importroptions` (`id` INT NOT NULL AUTO_INCREMENT,`import_tag` VARCHAR( 30 ) NOT NULL ,`cat_tag` VARCHAR( 30 ) NOT NULL,`username` VARCHAR( 30 ) NOT NULL ,`thumb` VARCHAR( 2 ) NOT NULL , `size` VARCHAR( 10 ) NOT NULL ,PRIMARY KEY ( `id` ));";
       mysql_query( $query );
       echo mysql_error();
       $query = "INSERT INTO ".$pixelpost_db_prefix."importroptions(id,import_tag, cat_tag, username, thumb, size) VALUES(NULL,'', '','','0','medium');";
       echo $query;
       mysql_query( $query );
       echo mysql_error();
}

add_admin_functions($addon_function_name,$addon_workspace,$addon_menu,$addon_admin_submenu);
add_admin_functions($addon_function_name2,$addon_workspace2,'','');


//used for making changes to the importr options
function Importr_options()
{

   global $addon_admin_functions;
   global $pixelpost_db_prefix;
   

   echo "
      <div id='caption'>Importr Options</div>
      <div id='submenu'>";
  
  
   if(!isset($_GET['action']))
   {
      $query = mysql_query("select cat_tag, import_tag, username, thumb, size from ".$pixelpost_db_prefix."importroptions where id='1'");
      list($cat_tag, $import_tag, $username, $thumb, $size) = mysql_fetch_row($query);
      echo "<div class='jcaption'>Options</div>
      <div class='content'>
    <form method='post' action='$PHP_SELF?view=options&amp;optionsview=importr&amp;action=edit' accept-charset='UTF-8'>
    <p>Import Tag : &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	   <input type='text' name='import_tag' value='".$import_tag."' class='input' style='width:300px;' /></p>
	<p>Category Prefix :&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	   <input type='text' name='cat_tag' value='".$cat_tag."' class='input' style='width:300px;' /></p>
	<p>Flickr username:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	   <input type='text' name='username' value='".$username."' class='input' style='width:300px;' /></p>
	<p>Thumbs:</p>";	
	if($thumb == "0")
	   echo "<p><input type='radio' name='thumb_options' value='0' checked>Import flickr square<br>
	        <input type='radio' name='thumb_options' value='1'>Import flickr thumb<br>
          <input type='radio' name='thumb_options' value='2' >Generate Pixelpost thumbs</p>";
    elseif($thumb == "1")
	   echo "<p><input type='radio' name='thumb_options' value='0'>Import flickr square<br>
	        <input type='radio' name='thumb_options' value='1' checked>Import flickr thumb<br>
          <input type='radio' name='thumb_options' value='2' >Generate Pixelpost thumbs</p>";
    else
	   echo "<p><input type='radio' name='thumb_options' value='0'>Import flickr square<br>
	        <input type='radio' name='thumb_options' value='1'>Import flickr thumb<br>
          <input type='radio' name='thumb_options' value='2' checked>Generate Pixelpost thumbs</p>";
    echo "<p>Size (please check if the required size if available on flickr):</p>";
    if($size == "original")
	   echo "<p><input type='radio' name='size_options' value='original' checked>Original<br>
	   <input type='radio' name='size_options' value='large'>Large<br>
       <input type='radio' name='size_options' value='medium'>Medium</p>";
    elseif ($size == "large")
 	   echo "<p><input type='radio' name='size_options' value='original'>Original<br>
	   <input type='radio' name='size_options' value='large' checked>Large<br>
       <input type='radio' name='size_options' value='medium'>Medium</p>";
    else
       echo "<p><input type='radio' name='size_options' value='original'>Original<br>
	   <input type='radio' name='size_options' value='large'>Large<br>
       <input type='radio' name='size_options' value='medium' checked>Medium</p>";
	  
	  echo "<input type='submit' value='Save' />

    
    </form>
    </div>";
   }
   if($_GET['action']=='edit')
   {
       $import_tag = clean($_POST['import_tag']);
       $cat_tag = clean($_POST['cat_tag']);
       $username = clean($_POST['username']);
       $thumb = clean($_POST['thumb_options']);
       $size = clean($_POST['size_options']);
       $query = "update ".$pixelpost_db_prefix."importroptions set import_tag='$import_tag', cat_tag='$cat_tag' , username='$username', thumb='$thumb', size='$size' where id='1'";
       mysql_query( $query );
       echo mysql_error();
       echo "
       <div class='jcaption'>Options</div>
        <div class='content'>
    <form method='post' action='$PHP_SELF?view=options&amp;optionsview=importr&amp;action=edit' accept-charset='UTF-8'>
    <p>Database updated</p>
    <p>Import Tag : &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	   <input type='text' name='import_tag' value='".$import_tag."' class='input' style='width:300px;' /></p>
	<p>Category Prefix :&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	   <input type='text' name='cat_tag' value='".$cat_tag."' class='input' style='width:300px;' /></p>
	<p>Flickr username:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	   <input type='text' name='username' value='".$username."' class='input' style='width:300px;' /></p>";
	if($thumb == "0")
	   echo "<p><input type='radio' name='thumb_options' value='0' checked>Import flickr square<br>
	        <input type='radio' name='thumb_options' value='1'>Import flickr thumb<br>
          <input type='radio' name='thumb_options' value='2' >Generate Pixelpost thumbs</p>";
    elseif($thumb == "1")
	   echo "<p><input type='radio' name='thumb_options' value='0'>Import flickr square<br>
	        <input type='radio' name='thumb_options' value='1' checked>Import flickr thumb<br>
          <input type='radio' name='thumb_options' value='2' >Generate Pixelpost thumbs</p>";
    else
	   echo "<p><input type='radio' name='thumb_options' value='0'>Import flickr square<br>
	        <input type='radio' name='thumb_options' value='1'>Import flickr thumb<br>
          <input type='radio' name='thumb_options' value='2' checked>Generate Pixelpost thumbs</p>";
    echo "<p>Size (please check if the required size if available on flickr):</p>";
    if($size == "original")
	   echo "<p><input type='radio' name='size_options' value='original' checked>Original<br>
	   <input type='radio' name='size_options' value='large'>Large<br>
       <input type='radio' name='size_options' value='medium'>Medium</p>";
    elseif ($size == "large")
 	   echo "<p><input type='radio' name='size_options' value='original'>Original<br>
	   <input type='radio' name='size_options' value='large' checked>Large<br>
       <input type='radio' name='size_options' value='medium'>Medium</p>";
    else
       echo "<p><input type='radio' name='size_options' value='original'>Original<br>
	   <input type='radio' name='size_options' value='large'>Large<br>
       <input type='radio' name='size_options' value='medium' checked>Medium</p>"; 
	   echo "<input type='submit' value='Save' />
    </select><p />
    </form>
    </div>";

   }
}

//delete picture from flickrassoc table    
function Importr_delete_img()
{
   global $addon_admin_functions;
   global $pixelpost_db_prefix;
   
   $imgid = intval($_GET['imageid']);
   $query = "delete from ".$pixelpost_db_prefix."flickrassoc where pp_id='$imgid'";
   mysql_query( $query );
   echo mysql_error();
}