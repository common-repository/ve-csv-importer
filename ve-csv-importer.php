<?php
/*
Plugin Name: VE CSV Importer
Plugin URI: http://www.virtualemployee.com/
Description: Import Pages/Posts from CSV files into WordPress.
Version: 1.2
Author: virtualemployee
Author URI: http://www.virtualemployee.com
Text Domain: ve-importer
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
	
class VirtualCsvImporter {
    var $log = array();

    /**
     * Determine value of option $name from database, $default value or $params,
     * save it to the db if needed and return it.
     *
     * @param string $name
     * @param mixed  $default
     * @param array  $params
     * @return string
     */
    function process_option($name, $default, $params) {
        if (array_key_exists($name, $params)) {
            $value = stripslashes($params[$name]);
        } elseif (array_key_exists('_'.$name, $params)) {
            // unchecked checkbox value
            $value = stripslashes($params['_'.$name]);
        } else {
            $value = null;
        }
        $stored_value = get_option($name);
        if ($value == null) {
            if ($stored_value === false) {
                if (is_callable($default) &&
                    method_exists($default[0], $default[1])) {
                    $value = call_user_func($default);
                } else {
                    $value = $default;
                }
                add_option($name, $value);
            } else {
                $value = $stored_value;
            }
        } else {
            if ($stored_value === false) {
                add_option($name, $value);
            } elseif ($stored_value != $value) {
                update_option($name, $value);
            }
        }
        return $value;
    }

    /**
     * Plugin's interface
     *
     * @return void
     */
    function form() {
        $opt_draft = $this->process_option('csv_importer_import_as_draft',
            'publish', $_POST);
        $opt_cat = $this->process_option('csv_importer_cat', 0, $_POST);

        if ('POST' == $_SERVER['REQUEST_METHOD']) {
            $this->post(compact('opt_draft', 'opt_cat'));
        }

        // form HTML {{{
?>
<div id="virtual-settings"> 
<div class="wrap">
    <h1>Virtual CSV Importer Settings</h1>
    <p align="center"><a href="<?php echo  plugins_url('/import-sample/sample.csv',__FILE__);?>"> Download Sample CSV</a> | <a href="mailto:wordpress@virtualemployee.com"> SEND YOUR QUERY </a></p>
    <hr />
    <form class="add:the-list: validate" method="post" enctype="multipart/form-data">
		   <?php wp_nonce_field( 've_import_csv_action', 've_csv_nonce_field' ); ?>
		<!-- Parent category -->
        <p><label for="page_type">Choose Post Type:</label> 
        <select name="page_type" id="page_type">
			<option value="">Select post type</option>
			<option value="post">post</option>
			<option value="page">page</option>
			<option value="product">product</option>
	</select>

        <!-- File input --></p>
		<!-- Parent category -->
        <p><label for="cat_type">Choose Taxonomy Type:</label> 
        <select name="cat_type" id="cat_type">
			<option value="">Select category type</option>
			<option value="category">Category</option>
					
		</select>

        <!-- File input --></p>
          <p><label for="page_type">Custom Field Index:</label> 
        <select name="default_field_count" id="default_field_count">
			<?php 
			for($i=9; $i > 2; $i--) 
			{

				echo '<option value="'.$i.'" >'.$i.'</option>';
			}

			?>
	</select>

        <!-- File input --></p>
        <p><label for="csv_import">Upload file:</label><input name="csv_import" id="csv_import" type="file" value="" aria-required="true" /></p>
        <p class="submit"><label for="submit">&nbsp;</label><input type="submit" class="button button-primary" name="submit" value="Import now" /></p>
    </form>
</div><!-- end wrap -->
</div>
<?php
        // end form HTML }}}

    }

    function print_messages() {
        if (!empty($this->log)) {

        // messages HTML {{{
?>

<div class="wrap">
    <?php if (!empty($this->log['error'])): ?>

    <div class="error">

        <?php foreach ($this->log['error'] as $error): ?>
            <p><?php echo $error; ?></p>
        <?php endforeach; ?>

    </div>

    <?php endif; ?>

    <?php if (!empty($this->log['notice'])): ?>

    <div class="updated fade">

        <?php foreach ($this->log['notice'] as $notice): ?>
            <p><?php echo $notice; ?></p>
        <?php endforeach; ?>

    </div>

    <?php endif; ?>
    
    <?php if (!empty($this->log['success'])): ?>

    <div class="updated fade">

        <?php foreach ($this->log['success'] as $success): ?>
            <p><?php echo $success; ?></p>
        <?php endforeach; ?>

    </div>

    <?php endif; ?>
</div><!-- end wrap -->

<?php
        // end messages HTML }}}

            $this->log = array();
        }
    }

    /**
     * Handle POST submission
     *
     * @param array $options
     * @return void
     */
    function post($options) {
       
        if ( ! isset( $_POST['ve_csv_nonce_field'] ) || ! wp_verify_nonce( $_POST['ve_csv_nonce_field'], 've_import_csv_action' ) ) {
				 $this->log['error'][] = 'Invalid attempt';
                 $this->print_messages();
				return;
			}
			
        if (empty($_POST['page_type'])) {
            $this->log['error'][] = 'Please select post type';
            $this->print_messages();
            return;
        }
      
        if (empty($_FILES['csv_import']['tmp_name'])) {
            $this->log['error'][] = 'No file uploaded, aborting.';
            $this->print_messages();
            return;
        }

        if (!current_user_can('publish_pages') || !current_user_can('publish_posts')) {
            $this->log['error'][] = 'You don\'t have the permissions to publish posts and pages. Please contact the blog\'s administrator.';
            $this->print_messages();
            return;
        }
        
        $csv_file = $_FILES['csv_import']['tmp_name'];
		$type = strtolower(substr($_FILES['csv_import']['name'],-3));
        if ($type!='csv') {
            $this->log['error'][] = 'File format is wrong.';
            $this->print_messages();
            return;
        }
        
     if (! is_file( $csv_file )) {
            $this->log['error'][] = 'Failed to load file';
            $this->print_messages();
            return;
        }

      $pageType=$_POST['page_type'];
      $catType=$_POST['cat_type'];
		
/** 
 *  Video posts 
 * */
	if($pageType!=''){    
	/** Store .csv file value into a array */
		$arry=$this->csvIndexArray($csv_file, ",", null, 1);
		$skipped = 0;
		$imported = 0;
		$time_start = microtime(true);
		$default_field_count = isset($_POST['default_field_count']) ? $_POST['default_field_count'] : 9;
		global $post,$wpdb;
		if(count($arry) > 0):
				foreach ($arry as $data) 
				{
					$data = wp_slash($data);
					
					$custom_fieldsAry =array_slice($data,$default_field_count);

					if(!isset($data['unique_id']))
					{
					$this->log['error'][] = 'Unique id is missing.';
					$this->print_messages();
					die();
					}
					
					if(!isset($data['title']))
					{
					$this->log['error'][] = 'Title is missing.';
					$this->print_messages();
					die();
					}
					
					if(isset($data['category']) && $data['category']!='' && $catType=='' )
					{
					$this->log['error'][] = 'Please select taxonomy type!';
					$this->print_messages();
					die();
					}
				
				
					wp_reset_postdata();
					$user_id =get_current_user_id();
					$existpost_id=$post_slug=$querytext=$custom_id=$post_title='';	$leftjoin='';$checkpoststatus='0';
					if(isset($data['author_id']) && $data['author_id']!='')
						{
							$user_id=$data['author_id'];
						}
						
						$post_title=$data['title']; // post tilte
						
						/* check post exist or not */
						if(isset($data['unique_id']) && $data['unique_id']!='')
						{
							
							$customId=trim($data['unique_id']);
							$mainquery="SELECT wp_posts.ID FROM wp_posts, wp_postmeta    WHERE 
							wp_posts.ID = wp_postmeta.post_id 
							AND wp_postmeta.meta_key = '_ve_unique_id' 
							AND wp_postmeta.meta_value = '$customId' 
							AND wp_posts.post_type = '$pageType'
							limit 0,1";
							$csvpage = $wpdb->get_results($mainquery, OBJECT);
						
						}
						else
						{
							$csvpage=true;
							return;
						} 

						if (!$csvpage){
								/* create new post */	
								$new_post = array(
											'post_title'   => convert_chars($post_title),
											'post_type'    => $pageType,
											'post_author'  => $user_id,
											);
								if(isset( $data['order']) &&  $data['order']!='')
								{
									$new_post['menu_order'] = $data['order']; // insert menu_order
								}
								
								if(isset( $data['slug']) &&  $data['slug']!='')
								{
									$new_post['post_name'] = trim($data['slug']); // insert post slug
								}
								
								if(isset( $data['content']) &&  $data['content']!='')
								{
									$new_post['post_content'] =convert_chars($data['content']); // insert post content
								}
								
								$post_status ='publish';
								if(isset( $data['status']) &&  $data['status']!='')
								{
									$post_status = trim($data['status']); // insert post status
								}
								
								$new_post['post_status'] = $post_status;
								
								// Insert the post into the database
								$existpost_id = wp_insert_post($new_post);
								//echo $existpost_id;
								//echo '<pre>'; print_r($new_post);

						}else
						{
							/* update exist post details */
							$existpost_id =$csvpage[0]->ID;
							$update_post = array(
									'ID' 		    => $existpost_id,
									'post_title'    => convert_chars($post_title),
									'post_modified' => date('Y-m-d h:m:s'),
									'post_author'   => $user_id,
								);
							

							if(isset( $data['status']) &&  $data['status']!='')
							{
								$update_post['post_status'] = $data['status']; // update post status
							}
						
							if(isset( $data['slug']) &&  $data['slug']!='')
							{
								$update_post['post_name'] = trim($data['slug']); // update post slug
							}
						
							if(isset( $data['order']) &&  $data['order']!='')
							{
								$update_post['menu_order'] = (int) $data['order']; // update post order
							}
							
							if(isset($data['content']) && $data['content'] != 'N/A') 
							{
								$update_post['post_content'] = wpautop(convert_chars($data['content'])); // update post content
							}	
							wp_update_post($update_post); // execute query
							$checkpoststatus='1';
						}
						/** category ids **/
						if((isset($data['cat_id']) && $data['cat_id']!='') && isset($catType) && $catType!='')
						{
							$cat_ids = explode(',',(int) $data['cat_id']);
							//print_r($cat_ids);	  
							$cat_ids = array_map( 'intval', $cat_ids );
							$cat_ids = array_unique( $cat_ids );
							//print_r($cat_ids); exit;
							$term_taxonomy_ids = wp_set_object_terms($existpost_id,$cat_ids,$catType);
						}
						/** category names **/
						if((isset($data['cat_name']) && $data['cat_name']!='') && isset($catType) && $catType!='')
						{	  
							$cat_names = $data['cat_name'];
							$term_taxonomy_ids = wp_set_object_terms($existpost_id,$cat_names,$catType);
						}
						/** update Unique_ID **/
						
						if(isset($data['unique_id']) && $data['unique_id']!='')
							 {
								$custom_id=$data['unique_id'];
								if(!add_post_meta($existpost_id, '_ve_unique_id',$custom_id, true))
								{
									update_post_meta($existpost_id, '_ve_unique_id',$custom_id); 
									
									}else
									{
										add_post_meta($existpost_id, '_ve_unique_id',$custom_id, true);
										
										}
								}
							/* Start featured image*/
							if(isset($data['featured_image']) && $data['featured_image']!='')
							{
								$this->virtual_set_featured_image($existpost_id,$data['featured_image']);
							}
						   /* End featured image*/
							
						/* Start custom meta fields */
						if($existpost_id):
							if(count($custom_fieldsAry ) > 0)
							{
								foreach($custom_fieldsAry as $index => $datval)
								{
									if(!add_post_meta($existpost_id, $index,$datval, true))
									{
										update_post_meta($existpost_id, $index,$datval); // update exist meta value
										//echo "meta update";
										
										}else
										{
											add_post_meta($existpost_id, $index,$datval, true); // add post meta value
											//echo "meta created";
											
										}
									
									}
							}else
							{
								echo 'no custom field';
								}
						 /* End custom meta fields */

						$imported++;
						else:
						$skipped++;
						endif;

						if($checkpoststatus=='1'){$msg='Updated';}else{$msg='Created';}	
						$this->log['success'][] = '#'.$existpost_id.'  '.$data['title'].' page is <b>'.$msg.'</b>';
						$this->print_messages();
						} 
		endif;
	}
/** End import condition  */

        if (file_exists($csv_file)) {
            @unlink($csv_file);
        }

        $exec_time = microtime(true) - $time_start;
		
        if ($skipped) {
            $this->log['notice'][] = "<b>Skipped {$skipped} posts (most likely due to empty title, body and excerpt).</b>";
        }
        $this->log['notice'][] = sprintf("<b>Imported {$imported} pages in %.2f seconds.</b>", $exec_time);
        $this->print_messages();
 }
/** Reterive data from csv file to array format */
function csvIndexArray($filePath='', $delimiter='|', $header = null, $skipLines = -1) {
         $lineNumber = 0;
        
         $dataList = array();
         //$headerItems = array();
        if (($handle = fopen($filePath, 'r')) != FALSE) {
			
		   while (($items = fgetcsv($handle, 1000, ",")) !== FALSE) 
		   {
			    
			    if($skipLines==1)
			    {
					if($lineNumber == 0)
					{ 
						$lineNumber++; continue; 
					}
					
					if($lineNumber == 1)
					{ 
						$header = $items; // return header fields
						$lineNumber++; continue; 
					}
			   }
			   
			   if($skipLines==0)
			    {
					$header = $items; // return header fields
				    $lineNumber++; continue; 
				}
				
				$record = array();
				for($index = 0, $m = count($header); $index < $m; $index++){
					//If column exist then and then added in data with header name
					if(isset($items[$index])) {
				   $itmcont = trim(mb_convert_encoding(str_replace('"','',$items[$index]), "utf-8", "HTML-ENTITIES" ));
				   $record[$header[$index]] = $itmcont;
					}
				}
				$dataList[] = $record; 				
			}			
           fclose($handle);
        }
        return $dataList;
    }
/* Set featured images */
function virtual_set_featured_image($post_id,$imgpath) {  
 // only want to do this if the post has no thumbnail
    if(!has_post_thumbnail($post_id)) 
    { 
			 // next, download the URL of the youtube image 
			media_sideload_image($imgpath, $post_id, 'Page thumbnail.'); 
		    // find the most recent attachment for the given post 
		   $attachments = get_posts( array( 'post_type' => 'attachment',
             'numberposts' => 1,
             'order' => 'ASC',
             'post_parent' => $post_id
            )
        );
        $attachment = $attachments[0];
        // and set it as the post thumbnail
        set_post_thumbnail( $post_id, $attachment->ID );
    //} // end if
	}
} // End featured image options
}
/** add js into admin footer */
if(isset($_GET['page']) && $_GET['page']=='virtual-import'){
add_action('admin_footer','init_virtual_admin_scripts');
if(!function_exists('init_virtual_admin_scripts')):
function init_virtual_admin_scripts()
{
echo $script='<style type="text/css">
	#virtual-settings {width: 90%; padding: 10px; margin: 10px;}
	 #virtual-settings label{   width: 125px;
    display: inline-block;}
	</style>';
}
endif;
}	
// Add settings link to plugin list page in admin
if(!function_exists('virtual_add_settings_link')):
function virtual_add_settings_link( $links ) {
  $settings_link = '<a href="tools.php?page=virtual-csv-import">' . __( 'Import Now', 'wpsb' ) . '</a>';
   array_unshift( $links, $settings_link );
  return $links;
}
endif;
$plugin = plugin_basename( __FILE__ );
add_filter( "plugin_action_links_$plugin", 'virtual_add_settings_link' );
function ve_csv_importer_admin_menu() {
    require_once ABSPATH . '/wp-admin/admin.php';
    $plugin = new VirtualCsvImporter;
    add_submenu_page('tools.php','Virtual CSV Importer', 'Virtual CSV Importer', 'manage_options','virtual-csv-import',
        array($plugin, 'form'));
}
add_action('admin_menu', 've_csv_importer_admin_menu');

?>
