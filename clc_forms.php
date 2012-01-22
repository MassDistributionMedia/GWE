<?php
/*
Plugin Name: CLC Forms
Plugin URI: Links the CLC website with the forms and spreadsheets of Google Docs
Description: 
Author: Jeffrey Johnson of MDM
Version: 0.5.0
Author URI: ofshard@gmail.com
*/   
   
/*  Copyright 2009  

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
 
if (!class_exists('clc_forms')) {
	class clc_forms {
		//This is where the class variables go, don't forget to use @var to tell what they're for
		/**
		* @var string The options string name for this plugin
		*/
		var $optionsName = 'clc_forms_options';

		/**
		* @var string $localizationDomain Domain used for localization
		*/
		var $localizationDomain = "clc_forms";

		/**
		* @var string $pluginurl The path to this plugin
		*/ 
		var $url = '';
		/**
		* @var string $pluginurlpath The path to this plugin
		*/
		var $urlpath = '';
		
		/**
		* @var string $adminpage The url of the admin page.
		*/
		var $adminpage = '';
		
		/**
		* @var array $options Stores the options for this plugin
		*/
		var $options = array();
		
		/**
		 * @var boolean $ranShortcode Stores whether a CLC Forms shortcode ran.
		 */
		var $ranShortcode = false;
		
		//Class Functions
		/**
		* PHP 4 Compatible Constructor
		*/
		function clc_forms(){$this->__construct();}

		/**
		* PHP 5 Constructor
		*/        
		function __construct(){
			//Language Setup
			$locale = get_locale();
			$mo = dirname(__FILE__) . "/languages/" . $this->localizationDomain . "-".$locale.".mo";
			load_textdomain($this->localizationDomain, $mo);

			set_include_path(get_include_path().PATH_SEPARATOR.dirname(__FILE__).'/lib');
			
			//"Constants" setup
			$this->url = plugins_url(basename(__FILE__), __FILE__);
			$this->urlpath = plugins_url('', __FILE__);
			$this->adminpage = admin_url('options-general.php').'?page='.basename(__FILE__);

			//Initialize the options
			//This is REQUIRED to initialize the options when the plugin is loaded!
			$this->getOptions();

			//Actions        
			add_action("admin_menu", array(&$this,"admin_menu_link"));

			//Ajax
			if( is_admin() ) {
				add_action('wp_ajax_clc_forms_from_spreadsheet', array(&$this, 'ajax_from_spreadsheet'));
				add_action('wp_ajax_clc_forms_edit', array(&$this, 'ajax_from_admin'));
				add_action('wp_ajax_nopriv_clc_forms_from_spreadsheet', array(&$this, 'ajax_from_spreadsheet'));
			}
			
			//Widget Registration Actions
			//add_action('plugins_loaded', array(&$this,'register_widgets'));

			//Filters
			/*
			add_filter('the_content', array(&$this, 'filter_content'), 0);
			*/

			// Add scripts and styles.
			add_action('init', array(&$this, 'ready_shortcode_script'));
			add_action('wp_footer', array(&$this, 'print_shortcode_script'));
			
			//Shortcodes
			add_shortcode('clc_form_tabs', array(&$this, 'handle_form_tabs_shortcode'));
		}

		/**
		* Retrieves the plugin options from the database.
		* @return array
		*/
		function getOptions() {
			//Don't forget to set up the default options
			if (!$theOptions = get_option($this->optionsName)) {
				/*
				 * 'forms' is an array of associative arrays of the format:
				 * 		'ss' => 		spreadsheet id
				 * 		'form' =>		form key
				 * 		'name' =>		form name
				 * 		'label' =>		tab label
				 * 		'max' =>		maximum entries
				 * 		'entries' =>	current number of entries
				 * 		'visible' =>	whether this form appears in the form tabs
				 */
				$theOptions = array(
					'forms'=>array()
				  , 'google_account'=>''
				  , 'google_password'=>''
				  , 'template'=>''
				);
				update_option($this->optionsName, $theOptions);
			}
			
			$this->options = $theOptions;

			//!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
			//There is no return here, because you should use the $this->options variable!!!
			//!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
		}
		/**
		* Saves the admin options to the database.
		*/
		function saveAdminOptions(){
			return update_option($this->optionsName, $this->options);
		}

		/**
		* @desc Adds the options subpanel
		*/
		function admin_menu_link() {
			//If you change this from add_options_page, MAKE SURE you change the filter_plugin_actions function (below) to
			//reflect the page filename (ie - options-general.php) of the page your plugin is under!
			$page = add_options_page('CLC Forms', 'CLC Forms', 'edit_users', basename(__FILE__), array(&$this,'admin_clc_forms_page'));
			add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array(&$this, 'filter_plugin_actions'), 10, 2 );
			
			// Admin Scripts
			add_action('admin_print_styles-'.$page, array(&$this, 'admin_scripts'));
		}

		/**
		* @desc Adds the admin scripts
		*/
		function admin_scripts() {
        	wp_register_script('clc_forms_jquery', plugins_url('js/jquery-1.7.1.min.js', __FILE__));
        	wp_register_script('clc_forms_jquery_ui', plugins_url('js/jquery-ui-1.8.16.custom.min.js', __FILE__), array('clc_forms_jquery'));
        	wp_register_style('jquery-ui-smoothness', plugins_url('css/smoothness/jquery-ui-1.8.16.custom.css', __FILE__));
        	
			wp_enqueue_style('clc_forms_admin_style', plugins_url('css/clc_forms_admin_style.css', __FILE__), array('jquery-ui-smoothness'));
			wp_enqueue_script('clc_forms_admin_script', plugins_url('js/clc_forms_admin_script.js', __FILE__), array('clc_forms_jquery', 'clc_forms_jquery_ui', 'json2'));
			
			/*
			wp_localize_script( 'jj_clc_forms_script', 'jj_clc_forms_lang', array(
					'required' => __('Please enter a number.', $this->localizationDomain),
					'number'   => __('Please enter a number.', $this->localizationDomain),
					'min'	  => __('Please enter a value greater than or equal to 1.', $this->localizationDomain),
				));
			*/
		}
		
		/**
		* @desc Adds the Settings link to the plugin activate/deactivate page
		*/
		function filter_plugin_actions($links, $file) {
			//If your plugin is under a different top-level menu than Settiongs (IE - you changed the function above to something other than add_options_page)
			//Then you're going to want to change options-general.php below to the name of your top-level page
			$settings_link = '<a href="options-general.php?page=' . basename(__FILE__) . '">' . __('Settings') . '</a>';
			array_unshift( $links, $settings_link ); // before other links

			return $links;
		}
		
		/**
		* @desc Responds to Publish requests from Google Spreadsheets.
		*/
		function ajax_from_spreadsheet() {
			$unescape = str_replace('\"', '"', $_POST['data']);
			
			$data = json_decode($unescape, true);
			
			$formquery = array();
			parse_str(parse_url($data['form'], PHP_URL_QUERY), $formquery);
			$form_key = $formquery['formkey'];
			
			$found = false;
			$response = '';
			
			$forms = &$this->options['forms'];
			for( $i=0; $i<count($forms); $i++ ) {
				if( $forms[$i]['ss'] == $data['id'] ) {
					
					if( $data['entries'] >= $data['max'] ) {
						array_splice($forms, $i, 1);
						
						$response = 'deleted';
					}
					else {
						$forms[$i]['name'] = $data['name'];
						$forms[$i]['form'] = $form_key;
						$forms[$i]['max'] = $data['max'];
						$forms[$i]['entries'] = $data['entries'];
						$forms[$i]['visible'] = true;
						
						$resonse = 'updated';
					}
					
					$this->saveAdminOptions();
					
					$found = true;
					break;
				}
			}
			
			if( !$found && $data['max'] > $data['entries'] ) {
				$form = array(
					'ss' => 		$data['id']
				  , 'form' =>		$form_key
				  , 'max' =>		$data['max']
				  , 'entries' =>	$data['entries']
				  , 'name' => 		$data['name']
				  , 'label' =>		$data['name']
				  , 'visible' =>	true
				);
				
				array_push($forms, $form);
				
				$this->saveAdminOptions();
				
				$response = 'added';
			}
			
			die($response);
		}
		
		/**
		 * @desc Get the Google Spreadsheet's URL for viewing/editing.
		 * @param string $ss_key The key of the spreadsheet.
		 */
		function get_spreadsheet_url( $ss_key ) {
			return 'https://docs.google.com/spreadsheet/ccc?key='.$ss_key;
		}
		
		/**
		 * @desc Get the form's iframe code for embedding.
		 * @param string $form_key The key of the form.
		 */
		function get_form_embed_code( $form_key ) {
			return '<iframe src="https://docs.google.com/spreadsheet/embeddedform?formkey='.$form_key.'" width="100%" height="1069" frameborder="0" marginheight="0" marginwidth="0">Loading...</iframe>';
		}
		
		/**
		 * @desc Respond to AJAX from the admin edit page.
		 */
		function ajax_from_admin() {
			check_ajax_referer('clc_forms_update_form');
			
			$nonce = $_POST['_ajax_nonce'];
			
			if( isset($_POST['form_action']) ) {
				$form = null;
				$found = false;
				
				if( !empty($_POST['form_id']) ) {
					
					foreach( $this->options['forms'] as &$form ) {
						if( $form['ss'] == $_POST['form_id'] ) {
							$found = true;
							break;
						}
					}
				}
				
				if( !$found )
					unset($form);
				
				if( $_POST['form_action'] == 'edit' && $found ) {
					$form['label'] = $_POST['form_label'];
					$form['visible'] = ($_POST['form_visible'] === true || strtolower($_POST['form_visible']) === "true");
					
					$this->saveAdminOptions();
				}
				
				if( $_POST['form_action'] == 'create' ) {
					if( empty($this->options['google_account'])
					  || empty($this->options['google_password'])
					  || empty($this->options['template']) )
					{
						die("Please set your Google options.");
					}
					
					require_once("Zend/Loader.php");
					Zend_Loader::loadClass("Zend_Gdata_ClientLogin");
					Zend_Loader::loadClass("Zend_Gdata_Docs");
					Zend_Loader::loadClass("Zend_Gdata_Spreadsheets");
					
					$gdClient = Zend_Gdata_ClientLogin::getHttpClient(
						$this->options['google_account']
					  , $this->options['google_password']
					  , Zend_Gdata_Docs::AUTH_SERVICE_NAME);
					$docsService = new Zend_Gdata_Docs($gdClient);
					
					$xml = 
"<?xml version='1.0' encoding='UTF-8'?>
".'<entry xmlns="http://www.w3.org/2005/Atom">
  <id>'.$this->options['template'].'</id>
</entry>';
					
					$gdClient->setHeaders(array(
						'GData-Version' => '3.0'
					  , 'Authorization' => 'GoogleLogin auth='.$gdClient->getClientLoginToken()
					  , 'Content-Type' => 'application/atom+xml'));
					$gdClient->setUri("https://docs.google.com/feeds/default/private/full");
					$gdClient->setMethod('POST');
					$gdClient->setRawData($xml);
					
					$gdClient->request();
					
					$spreadsheet = Zend_Gdata_Docs::importString($gdClient->getLastResponse()->getRawBody(),'Zend_Gdata_Spreadsheets_SpreadsheetEntry');
					
					if( $spreadsheet ) {
						$ssquery = array();
						parse_str(parse_url($spreadsheet->getAlternateLink()->getHref(), PHP_URL_QUERY), $ssquery);
						$ss_key = $ssquery['key'];
						
						$form = array(
							'ss'=>		$ss_key
						  , 'visible'=>	false
						  , 'name'=>	$spreadsheet->getTitleValue()
						  , 'label'=>	$spreadsheet->getTitleValue()
						  , 'max'=>		30
						);
						
						array_push($this->options['forms'], $form);
						
						$this->saveAdminOptions();
						
						echo '<a href="'.$this->get_spreadsheet_url($ss_key).'" target="_blank">'.$spreadsheet->getTitleValue().'</a>';
					}
					else {
						echo 'failure';
					}
				}
			}
			
			die();
		}
		
		/**
		* Adds settings/options page
		*/
		function admin_clc_forms_page() {
			
			if ( !empty($_POST['clc_forms_save']) ) {
				// Update CLC Forms Options.
				
				$this->options['google_account'] = $_POST['clc_forms_google_account'];
				$this->options['google_password'] = $_POST['clc_forms_google_password'];
				$this->options['template'] = $_POST['clc_forms_template_id'];
				
				$this->saveAdminOptions();
				
				echo '<div class="updated"><p>Success! Your changes were sucessfully saved!</p></div>';
			}
			
			if( isset($_GET['form_action']) && check_admin_referer('clc_forms_update_form') ){
				
				if( $_GET['form_action'] == 'remove' && isset($_GET['form_id']) ) {
					for( $i=0; $i<count($this->options['forms']); $i++ ) {
						if( $this->options['forms'][$i]['ss'] == $_GET['form_id'] ) {
							array_splice($this->options['forms'], $i, 1);
							$this->saveAdminOptions();
							break;
						}
					}
				}
			}
			
			$nonce = wp_create_nonce('clc_forms_update_form');
?>
            <div class="wrap">
				<h2>CLC Forms Options</h2>
				<form method="post" id="clc_forms_options">
                	<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo $nonce; ?>" />
                    <table width="100%" cellspacing="2" cellpadding="5" class="form-table"> 
                        <tr valign="top"> 
                            <th width="33%" scope="row"><?php _e('Google Account:', $this->localizationDomain); ?></th>
                            <td><input type="text" name="clc_forms_google_account" value="<?php echo $this->options['google_account']; ?>" /></td>
                        </tr>
                        <tr valign="top">
                            <th width="33%" scope="row"><?php _e('Google Password:', $this->localizationDomain); ?></th>
                            <td><input type="password" name="clc_forms_google_password" value="<?php echo $this->options['google_password']; ?>" /></td>
                        </tr>
                        <tr valign="top">
                            <th width="33%" scope="row"><?php _e('Template Spreadsheet Id:', $this->localizationDomain); ?></th>
                            <td>
                            	<input type="text" name="clc_forms_template_id" value="<?php echo $this->options['template']; ?>" />
                            	<!--  TODO:
                            		Create From URL button, which opens a dialog to insert a URL. Parse the input
                            		for the key, and then insert it into the input text field.
                            		
                            		To get the query string, create a <a>, set href to the url, then use the DOM's
                            		a.search property, which will return the query string. Remove the starting '?'
                            		character, then split on the '&' character, then split on the '=' character.
                            	-->
                            </td>
                        </tr>
                        <tr>
                            <th colspan="2"><input type="submit" name="clc_forms_save" value="Save" /></th>
                        </tr>
                    </table>
                </form>
				
                <h2>CLC Forms List
					<a class="add-new-h2" href="<?php echo $this->adminpage."&form_action=create&_wpnonce=$nonce"; ?>">Add New</a>
				</h2>
				
				<div id="dialog-create-form" title="Create a new form based on the template." style="display:none;">
					<button id="btn-create-form" title="This will create a new copy of the form template.">Create New Form</button>
					<div id="form-ajax-output"></div>
					<div id="form-progress" style="display:none;"></div>
				</div>
				
                <table width="100%" cellspacing="2" cellpadding="5" class="form-table widefat fixed">
                    <thead>
						<tr>
							<th class="column-name"><span>Name</span></th>
							<th class="column-label"><span>Label</span></th>
							<th class="column-max"><span>Max Entries</span></th>
							<th class="column-visibility"><span>Visibility</span></th>
						</tr>
					</thead>
					<tbody id="the-list">
<?php				foreach ( $this->options['forms'] as $form ) { ?>
						<tr class="form-<?php echo $form['ss']; ?> form type-form" id="form-<?php echo $form['ss']; ?>">
							<td class="column-name">
								<span class="column-name"><?php echo $form['name']; ?></span>
								<div class="row-actions">
									<span class="edit">
										<a href="<?php echo $this->adminpage."&form_id={$form[ss]}&form_action=edit&_wpnonce=$nonce"; ?>">Edit Tab</a>
										|
									</span>
									<span class="spreadsheet">
										<a href="<?php echo $this->get_spreadsheet_url($form['ss']); ?>" target="_blank">Spreadsheet</a>
										|
									</span>
									<span class="remove">
										<a href="<?php echo $this->adminpage."&form_id={$form[ss]}&form_action=remove&_wpnonce=$nonce"; ?>">Remove</a>
									</span>
								</div>
							</td>
							<td class="column-label"><span class="column-label"><?php echo $form['label']; ?></span></td>
							<td class="column-max"><span class="column-max"><?php echo sprintf('%d', $form['max']); ?></span></td>
							<td class="column-visibility"><span class="column-visibility"><?php echo ($form['visible'] === true) ? 'Visible' : 'Hidden'; ?></span></td>
						</tr>
<?php				} ?>
						<tr style="display:none" class="inline-edit-row inline-edit-row-page-file inline-editor inline-editor-file quick-edit-row quick-edit-row-file" id="form-editor"><td class="colspanchange" colspan="4">
							<fieldset class="inline-edit-col-left"><div class="inline-edit-col">
								<h4>Quick Edit</h4>
								<div class="clear"></div>
								<label class="alignleft">
									<span class="title">Label</span>
									<span class="input-text-wrap"><input type="text" value="" class="pname" name="form_label"></span>
								</label>
								<br class="clear" />
								<label class="alignleft">
									<input type="checkbox" value="visible" name="form_visible">
									<span class="checkbox-title">Visible</span>
								</label>
								<br class="clear" />
							</div></fieldset>

							<p class="submit inline-edit-save">
								<a class="button-secondary cancel alignleft" title="Cancel" href="#inline-edit" accesskey="c">Cancel</a>
								<a class="button-primary save alignright" title="Update" href="#inline-edit" accesskey="s">Update</a>
								<img alt="" src="<?php echo admin_url('images/wpspin_light.gif'); ?>" style="display: none;" class="waiting">
								<input type="hidden" value="list" name="post_view">
								<input type="hidden" value="edit-page" name="screen">
								<input type="hidden" name="action" id="ajax_url" value="<?php echo admin_url('admin-ajax.php'); ?>" />
								<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo $nonce; ?>" />
								<span style="display: none;" class="error"></span>
								<br class="clear">
							</p>
						</td></tr>
					</tbody>
                    <tfoot>
						<tr>
							<th class="column-name"><span>Name</span></th>
							<th class="column-label"><span>Label</span></th>
							<th class="column-max"><span>Max Entries</span></th>
							<th class="column-visibility"><span>Visibility</span></th>
						</tr>
					</tfoot>
                    </table>
			</div>
            <?php
        }
        
        function handle_form_tabs_shortcode( $atts, $content=null, $code=null ) {
        	$forms = $this->options['forms'];
        	
        	$ret .= '';
        	
        	if( count($forms) > 0 ) {
        		ob_start(); 
        		//wp_print_styles(array('jquery-ui-smoothness'));
        		?>
<div id="tabs">
   <ul>
   <?php $i=0;
   foreach( $this->options['forms'] as $form ) { if( $form['visible'] === true ) { ?>
      <li><a href="#tabs-<?php echo $i++; ?>"><?php echo $form['label']; ?></a></li>
   <?php } } ?>
   </ul>
<?php $i=0;
foreach( $this->options['forms'] as $form ) { if( $form['visible'] === true ) {?>
   <div id="tabs-<?php echo $i++; ?>">
      <p><?php echo $this->get_form_embed_code($form['form']); ?></p>
   </div>
<?php } } ?>
</div>
<?php
				$ret .= ob_get_clean();
				$this->ranShortcode = true;
				//wp_enqueue_style('jquery-ui-smoothness');
				wp_enqueue_script('clc_forms_script');
			}
			else {
				$ret .= '<p>There are no available events.</p>';
			}
			
			return $ret;
        }
        
        function ready_shortcode_script() {
        	wp_register_script('clc_forms_jquery', plugins_url('js/jquery-1.7.1.min.js', __FILE__));
        	wp_register_script('clc_forms_jquery_ui', plugins_url('js/jquery-ui-1.8.16.custom.min.js', __FILE__), array('clc_forms_jquery'), null, true);
        	wp_register_script('clc_forms_script', plugins_url('js/clc_forms_script.js', __FILE__), array('clc_forms_jquery_ui'), '1.0', true);
        	wp_register_style('jquery-ui-smoothness', plugins_url('css/smoothness/jquery-ui-1.8.16.custom.css', __FILE__));
        	wp_enqueue_style('jquery-ui-smoothness');
        }
        
        function print_shortcode_script() {
        	if( $this->ranShortcode ) {
	        	//wp_print_styles(array('jquery-ui-smoothness'));
	        	//wp_print_scripts(array('clc_forms_script'));
        	}
        }
        
  	} //End Class
} //End if class exists statement

if (class_exists('clc_forms')) {
    $clc_forms_var = new clc_forms();
}
?>