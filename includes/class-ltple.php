<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class LTPLE_Seller {

	/**
	 * The single instance of LTPLE_Seller.
	 * @var 	object
	 * @access  private
	 * @since 	1.0.0
	 */
	private static $_instance = null;

	/**
	 * Constructor function.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	 
	var $slug;
	var $tab;
	var $list;
	var $items;
	var $totalItems = 0;
	var $agreements = array();
	var $max_num_pages;
	var $categories = array();
	 
	public function __construct ( $file='', $parent, $version = '1.0.0' ) {

		$this->parent = $parent;
	
		$this->_version = $version;
		$this->_token	= md5($file);
		
		$this->message = '';
		
		// Load plugin environment variables
		
		$this->file 		= $file;
		$this->dir 			= dirname( $this->file );
		$this->views   		= trailingslashit( $this->dir ) . 'views';
		$this->vendor  		= WP_CONTENT_DIR . '/vendor';
		$this->assets_dir 	= trailingslashit( $this->dir ) . 'assets';
		$this->assets_url 	= esc_url( trailingslashit( plugins_url( '/assets/', $this->file ) ) );
		
		//$this->script_suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		$this->script_suffix = '';

		register_activation_hook( $this->file, array( $this, 'install' ) );
		
		// Load frontend JS & CSS
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ), 10 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10 );

		// Load admin JS & CSS
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 10, 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_styles' ), 10, 1 );
		
		$this->settings = new LTPLE_Seller_Settings( $this->parent );
		
		$this->admin = new LTPLE_Seller_Admin_API( $this );
		
		$this->templates = new LTPLE_Seller_Templates( $this->parent );
	
		if ( !is_admin() ) {

			// Load API for generic admin functions
			
			add_action( 'wp_head', array( $this, 'header') );
			add_action( 'wp_footer', array( $this, 'footer') );
		}
		
		// Handle localisation
		
		$this->load_plugin_textdomain();
		
		add_action( 'init', array( $this, 'load_localisation' ), 0 );
		
		//init addon 
		
		add_action( 'wp_loaded', array( $this, 'init' ));	

		// add user attributes
		
		add_filter( 'ltple_user_loaded', array( $this, 'add_user_attribute'));			

		// hangle user logs
		
		add_filter( 'ltple_first_log_ever', array( $this, 'handle_first_log_ever'));			
		
		add_filter( 'ltple_first_log_today', array( $this, 'handle_first_log_today'));
		
		// add query vars
		
		add_filter('query_vars', array( $this , 'add_query_vars' ), 1);	
	
		// add panel url
		
		add_filter( 'ltple_urls', array( $this, 'get_panel_url'));
		
		// add url parameters
		
		add_filter( 'template_redirect', array( $this, 'get_url_parameters'));		
		
		// add privacy settings
				
		add_filter('ltple_privacy_settings',array($this,'set_privacy_fields'));		
		
		// add panel shortocode
		
		add_shortcode('ltple-client-seller', array( $this , 'get_panel_shortcode' ) );
			
		// add notification settings
		
		add_filter( 'ltple_notification_settings', array( $this, 'get_notification_settings'));		
		
		// add link to theme menu
		
		add_filter( 'ltple_view_my_profile', array( $this, 'add_theme_menu_link'));	
				
		// add button to navbar
				
		add_filter( 'ltple_left_navbar', array( $this, 'add_left_navbar_button'));	
		add_filter( 'ltple_right_navbar', array( $this, 'add_right_navbar_button'));	
						
		add_filter( 'ltple_layer_options', array( $this, 'add_layer_options'),10,1);
		add_filter( 'ltple_layer_plan_fields', array( $this, 'add_layer_plan_fields'),10,2);
		add_action( 'ltple_save_layer_fields', array( $this, 'save_layer_fields' ),10,1);			
					
		// add layer colums
		
		add_filter( 'ltple_layer_type_columns', array( $this, 'add_layer_columns'));
		add_filter( 'ltple_layer_range_columns', array( $this, 'add_layer_columns'));
		add_filter( 'ltple_layer_option_columns', array( $this, 'add_layer_columns'));
							
		add_filter( 'ltple_layer_column_content', array( $this, 'add_layer_column_content'),10,2);
		
		// handle plan
		
		add_filter( 'ltple_api_layer_plan_option', array( $this, 'add_api_layer_plan_option'),10,1);	
		add_filter( 'ltple_api_layer_plan_option_total', array( $this, 'add_api_layer_plan_option_total'),10,2);
		
		add_filter( 'ltple_plan_shortcode_attributes', array( $this, 'add_plan_shortcode_attributes'),10,2);
		add_filter( 'ltple_plan_subscribed', array( $this, 'handle_subscription_plan'),10);
		
		add_filter( 'ltple_plan_delivered', array( $this, 'handle_item_delivery'),10,2);
		
		add_filter( 'ltple_user_plan_option_total', array( $this, 'add_user_plan_option_total'),10,2);
		add_filter( 'ltple_user_plan_info', array( $this, 'add_user_plan_info'),10,1);
		
		//add_filter( 'ltple_gallery_before_output', array( $this, 'set_gallery'),10,2);
		
		$this->add_star_triggers();
		
	} // End __construct ()
	
	public function init(){	 
		
		if( is_admin() ) {
			
			add_filter('seller_custom_fields', array( $this, 'get_seller_fields' ));
		}
		else{
			
			if( !empty($this->parent->layer->types) ){
				
				//Add Custom API Endpoints
				
				add_action( 'rest_api_init', function(){
					
					foreach( $this->parent->layer->types as $term ){
					
						register_rest_route( 'ltple-seller/v1', '/' . $term->slug . '/', array(
							
							'methods' 	=> 'GET',
							'callback' 	=> array($this,'get_seller_rows'),
						));
					}
				});			
			}
			
			if( !empty($_POST) ){
				
				$this->save_product_frontend();
			}
		}
	}
	
	public function set_privacy_fields(){
		 
		/*
		$this->parent->profile->privacySettings['addon-policy'] = array(

			'id' 			=> $this->parent->_base . 'policy_' . 'addon-policy',
			'label'			=> 'Addon policy',
			'description'	=> 'Addon provacy policy',
			'type'			=> 'switch',
			'default'		=> 'on',
		);
		*/
	}
	
	
	public function get_seller_fields(){
		
		$fields=[];


		return $fields;
	}
	
	public function get_seller_items($layer_type) {
	
		$seller_items = array();
				
		// set query arguments
		
		$args = array(
			
			'post_type'			=> 'cb-default-layer',
			'post_status'		=> array('publish','draft','pending'),
			'author'			=> $this->parent->user->ID,
			'posts_per_page' 	=> -1,
		);			
		
		/*
		$mq = 0;
		
		// filter price
		
		$args['meta_query'][$mq][] = array(

			'key' 		=> 'layerPrice',
			'value' 	=> 0,
			'compare' 	=> '>',
			'type' 		=> 'NUMERIC'			
		);
		*/
		
		// filter layer type
		
		$args['tax_query'] = array('relation'=>'AND');
		
		$args['tax_query'][] = array(
		
			'taxonomy' 			=> 'layer-type',
			'field' 			=> 'slug',
			'terms' 			=> $layer_type->slug,
			'include_children' 	=> false,
			'operator'			=> 'IN'
		);

		$q = new WP_Query( $args );		
		
		if( !empty($q->posts) ){
			
			foreach( $q->posts as $item ){
				
				$item->layer_type 	= $layer_type->slug;
				
				if( $item_meta = get_post_meta($item->ID) ){
					
					$item->price = intval($item_meta['layerPrice'][0]);
				}
				else{
					
					$item->price = 0;
				}
				
				$seller_items[] = $item;
			}
		}

		return $seller_items;
	}	
	
	public function get_seller_rows($request) {
		
		$seller_rows = [];
		
		$layer_type = explode( '?', $this->parent->urls->current );
		$layer_type = basename($layer_type[0]);
		
		$term = null;
	
		foreach( $this->parent->layer->types as $term ){
			
			if( $term->slug == $layer_type) {
				
				if( $seller_items = $this->get_seller_items($term) ){
		
					foreach( $seller_items as $item ){
						
						$edit_url = add_query_arg(array(
							
							'tab' 		=> $item->layer_type,
							'action' 	=> 'edit',
							'id' 		=> $item->ID,
							
						), $this->parent->urls->seller );

						$row = [];
						$row['preview'] 		= '<div class="thumb_wrapper" style="background:url(' . $this->parent->layer->get_thumbnail_url($item) . ');background-size:cover;background-repeat:no-repeat;background-position:center;width:300px;display:inline-block;"></div>';
						$row['name'] 			= ucfirst($item->post_title);
						$row['status'] 			= $this->get_product_status($item);
						$row['price'] 			= $item->price;
						$row['action'] 			= '<a href="' . $edit_url . '" class="btn btn-sm btn-warning">Edit</a>';
						
						$seller_rows[] = $row;
					}
				}				
				
				break;
			}
		}
		
		return $seller_rows;
	}
	
	public function header(){
		
		//echo '<link rel="stylesheet" href="https://raw.githubusercontent.com/dbtek/bootstrap-vertical-tabs/master/bootstrap.vertical-tabs.css">';	
	}
	
	public function footer(){
		
		
	}
	
	public function add_user_attribute(){
		
		// add user attribute
			
		//$this->parent->user->userAttribute = new LTPLE_Seller_User( $this->parent );	
	}
	
	public function handle_first_log_ever(){
		

	}
	
	public function handle_first_log_today(){
		

	}
	
	public function get_panel_shortcode(){
		
		if($this->parent->user->loggedin){
			
			if( !empty($_REQUEST['output']) && $_REQUEST['output'] == 'widget' ){
				
				include($this->views . '/widget.php');
			}
			else{
			
				include($this->parent->views . '/navbar.php');
			
				include($this->views . '/panel.php');
			}
		}
		else{
			
			echo'<div style="font-size:20px;padding:20px;margin:0;" class="alert alert-warning">';
				
				echo'You need to log in first...';
				
				echo'<div class="pull-right">';

					echo'<a style="margin:0 2px;" class="btn-lg btn-success" href="'. wp_login_url( $this->parent->request->proto . $_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'] ) .'">Login</a>';
					
					echo'<a style="margin:0 2px;" class="btn-lg btn-info" href="'. wp_login_url( $this->parent->urls->editor ) .'&action=register">Register</a>';
				
				echo'</div>';
				
			echo'</div>';
		}				
	}
	
	public function get_product_form( $layer_type = '', $post = null ){
		
		if( $layer_type != '' ){
			
			$fields = $this->parent->layer->get_default_layer_fields($post);
																					
			//$supports = get_all_post_type_supports( 'cb-default-layer' );
											
			echo '<form method="post">';
				
				echo'<div class="row">';
					
					echo'<div class="col-md-9">';
					
						echo'<input type="hidden" name="id" value="' . ( !empty($post->ID) ? $post->ID : 0 ) . '" />';
						
						echo'<input type="hidden" name="tax_input[layer-type]" value="' . $layer_type . '" />';
						
						echo'<div class="panel panel-default">';

							echo'<div class="panel-heading">';
								
								echo 'Product Title';
								
							echo'</div>';
							
							echo'<div class="panel-body">';
							
								echo'<input type="text" placeholder="Title" name="post_title" value="' . ( !empty($post->post_title) ? $post->post_title : '' ) . '" style="width:100%;padding:10px;font-size:20px;border-radius:2px;" required="required"/>';
						
							echo'</div>';
						
						echo'</div>';
						
					echo'</div>';
					
				

					// publish panel
					
					if( !empty($post) ){
						
						echo'<div class="col-md-3">';
					
							echo'<div class="panel panel-default">';
							
								echo'<div class="panel-heading">';
									
									echo 'Publish';
									
								echo'</div>';
									
								echo'<div class="panel-body">';
									
									echo 'Status: ' . $this->get_product_status($post);						
								
								echo'</div>';
								
								echo'<div class="panel-footer">';
								
									echo'<div class="row" style="padding: 0 10px;">';
								
										echo '<input type="submit" value="' . ( ( $post->post_status == 'publish' || $post->post_status == 'pending' ) ? 'Update' : 'Submit' ) . '" class="btn btn-md btn-primary pull-right" style="font-size:12px;" />';
									
									echo'</div>';
									
								echo'</div>';
							
							echo'</div>';
						
						echo'</div>';
					}
					else{
						
						echo'<div class="clearfix"></div>';
						
						echo'<div class="col-md-9">';
							
							echo'<div class="panel" style="box-shadow:none;">';
							
								echo'<div class="panel-body">';
							
									echo '<input type="submit" value="Next" class="btn btn-md btn-primary pull-right" style="font-size:12px;" />';
								
								echo'</div>';
								
							echo'</div>';
							
						echo'</div>';
					}
					
				echo'</div>';
				
				echo'<div class="row">';
					
					echo'<div class="col-md-3 col-md-push-9">';

						// metaboxes
			
						foreach ( $fields as $field ) {
							
							if( !isset($field['metabox']['frontend']) || $field['metabox']['frontend'] === true ){
							
								if( !isset($field['metabox']['context']) || $field['metabox']['context'] == 'side' ){
									
									echo'<div class="panel panel-default">';
										
										if( !empty($field['metabox']['title']) ){	
											
											echo'<div class="panel-heading">';
											
												echo $field['metabox']['title'];
											
											echo'</div>';
										}
											
										echo'<div class="panel-body">';
										
											$this->parent->admin->display_meta_box_field( $field, $post );
										
										echo'</div>';
										
									echo'</div>';
								}
							}
						}
						
					echo'</div>';
					
					echo'<div class="col-md-9 col-md-pull-3">';
						
						foreach ( $fields as $field ) {
							
							if( !isset($field['metabox']['frontend']) || $field['metabox']['frontend'] === true ){
								
								if( isset($field['metabox']['context']) && $field['metabox']['context'] == 'advanced' ){
									
									echo'<div class="panel panel-default">';
										
										if( !empty($field['metabox']['title']) ){	
											
											echo'<div class="panel-heading">';
											
												echo $field['metabox']['title'];
											
											echo'</div>';
										}
											
										echo'<div class="panel-body">';
										
											$this->parent->admin->display_meta_box_field( $field, $post );
										
										echo'</div>';
										
									echo'</div>';
								}
							}
						}
						
					echo'</div>';
					
				echo'</div>';
				
			echo'</form>';
		}
	}
	
	public function get_product_status($post){
							
		if( $post->post_status == 'publish' ){
			
			$status = '<span class="label label-success">Active</span>';
		}
		elseif( $post->post_status == 'pending' ){
			
			$status = '<span class="label label-warning">Pending</span>';
		}
		else{
			
			$status = '<span class="label label-default">Draft</span>';
		}

		return $status;
	}
	
	public function add_query_vars( $query_vars ){
		
		if(!in_array('tab',$query_vars)){
		
			$query_vars[] = 'tab';
		}
		
		return $query_vars;	
	}
	
	public function get_panel_url(){
		
		$slug = get_option( $this->parent->_base . 'sellerSlug' );
		
		if( empty( $slug ) ){
			
			$post_id = wp_insert_post( array(
			
				'post_title' 		=> 'Seller',
				'post_type'     	=> 'page',
				'comment_status' 	=> 'closed',
				'ping_status' 		=> 'closed',
				'post_content' 		=> '[ltple-client-seller]',
				'post_status' 		=> 'publish',
				'menu_order' 		=> 0
			));
			
			$slug = update_option( $this->parent->_base . 'sellerSlug', get_post($post_id)->post_name );
		}
		
		$this->parent->urls->seller = $this->parent->urls->home . '/' . $slug . '/';	

		// add rewrite rules
		
		/*
		
		add_rewrite_rule(
		
			$this->slug . '/([^/]+)/?$',
			'index.php?pagename=' . $this->slug . '&tab=$matches[1]',
			'top'
		);
		
		add_rewrite_rule(
		
			$this->slug . '/([^/]+)/([0-9]+)/?$',
			'index.php?pagename=' . $this->slug . '&tab=$matches[1]&aid=$matches[2]',
			'top'
		);
		
		*/
	}

	public function get_url_parameters(){

		// get tab name
		/*
		if( !$this->tab = get_query_var('tab') ){
			
			$this->tab = 'addon-tab';
		}
		*/
	}
	
	public function get_notification_settings(){
		
		/*
		$this->parent->email->notification_settings['addon-channel'] = array(
			
			'default' 		=> 'true',
			'description' 	=> '',
		);
		*/
	}
	
	public function add_theme_menu_link(){

		// add theme menu link
		
		/*
		echo'<li style="position:relative;">';
			
			echo '<a href="'. $this->parent->urls->addon . '"><span class="glyphicon glyphicon-link" aria-hidden="true"></span> Addon Panel</a>';

		echo'</li>';
		*/
	}
	
	public function add_left_navbar_button(){
	
	}
	
	public function add_right_navbar_button(){
		
		
		
	}
	
	public function add_layer_options($term_slug){
		
		/*
		
		if(!$addon_amount = get_option('addon_amount_' . $term_slug)){
			
			$addon_amount = 0;
		}

		$this->parent->layer->options = array(
			
			'addon_amount' 	=> $addon_amount,
		);
		*/
	}
	
	public function add_layer_plan_fields( $taxonomy, $term_slug = '' ){
		
		/*
		
		$data = [];

		if( !empty($term_slug) ){
		
			$data['addon_amount'] = get_option('addon_amount_' . $term_slug); 
			$data['addon_period'] = get_option('addon_period_' . $term_slug); 
		}

		echo'<div class="form-field" style="margin-bottom:15px;">';
			
			echo'<label for="'.$taxonomy.'-addon-amount">Addon plan attribute</label>';

			echo $this->get_layer_addon_fields($taxonomy,$data);
			
		echo'</div>';
		
		*/
	}
	
	public function get_layer_addon_fields( $taxonomy_name, $args = [] ){
		
		/*
		
		//get periods
		
		$periods = $this->parent->plan->get_price_periods();
		
		//get price_amount
		
		$amount = 0;
		
		if(isset($args['addon_amount'])){
			
			$amount = $args['addon_amount'];
		}

		//get period
		
		$period = '';
		
		if(isset($args['addon_period'])&&is_string($args['addon_period'])){
			
			$period = $args['addon_period'];
		}
		
		//get fields
		
		$fields='';

		$fields.='<div class="input-group">';

			$fields.='<span class="input-group-addon" style="color: #fff;padding: 5px 10px;background: #9E9E9E;">$</span>';
			
			$fields.='<input type="number" step="0.1" min="-1000" max="1000" placeholder="0" name="'.$taxonomy_name.'-addon-amount" id="'.$taxonomy_name.'-addon-amount" style="width: 60px;" value="'.$amount.'"/>';
			
			$fields.='<span> / </span>';
			
			$fields.='<select name="'.$taxonomy_name.'-addon-period" id="'.$taxonomy_name.'-addon-period">';
				
				foreach($periods as $k => $v){
					
					$selected = '';
					
					if($k == $period){
						
						$selected='selected';
					}
					elseif($period=='' && $k=='month'){
						
						$selected='selected';
					}
					
					$fields.='<option value="'.$k.'" '.$selected.'> '.$v.' </option>';
				}
				
			$fields.='</select>';					
			
		$fields.='</div>';
		
		$fields.='<p class="description">The '.str_replace(array('-','_'),' ',$taxonomy_name).' addon used in table pricing & plans </p>';
		
		return $fields;
		*/
	}
	
	public function save_layer_fields($term){
		
		/*
		if( isset($_POST[$term->taxonomy .'-addon-amount']) && is_numeric($_POST[$term->taxonomy .'-addon-amount']) ){

			update_option('addon_amount_' . $term->slug, round(intval(sanitize_text_field($_POST[$term->taxonomy . '-addon-amount'])),1));			
		}
		*/		
	}
	
	public function save_product_frontend(){
		
		$redirect_url = '';
		
		if( !empty($_POST['id']) ){
			
			// edit product
			
			$post_id = intval($_POST['id']);
			
			if( $post = get_post($post_id) ){
				
				if( $this->parent->user->is_admin || intval($post->post_author) == $this->parent->user->ID ){
					
					//update main arguments
					
					$args = array();
					
					if( !empty($_POST['post_title']) ){
						
						$args['post_title'] = $_POST['post_title'];
					}
					
					if( $post->post_status == 'draft' ){
						
						$args['post_status'] = 'pending';
					}
					
					if( !empty($args) ){
						
						$args['ID'] = $post_id;
						
						wp_update_post($args);
					}

					$fields = $this->parent->layer->get_default_layer_fields($post);
					
					foreach( $fields as $field ){
					
						if( !empty($field['metabox']['taxonomy']) ){
							
							//update terms
							
							$taxonomy = $field['metabox']['taxonomy'];
							
							if( isset($_POST['tax_input'][$taxonomy]) ){
								
								$terms = array();
								
								if( is_string($_POST['tax_input'][$taxonomy]) ){
									
									$terms = array($_POST['tax_input'][$taxonomy]);
								}
								elseif( is_array($_POST['tax_input'][$taxonomy]) ){
									
									$terms = $_POST['tax_input'][$taxonomy];
								}
								
								wp_set_post_terms( $post_id, $terms, $taxonomy, false );
							}
						}
						elseif( isset($_POST[$field['id']]) ){
								
							//update meta
								
							update_post_meta($post_id,$field['id'],$_POST[$field['id']]);
						}
					}
					
					if( $post->post_status == 'draft' ){
						
						// send submit notification
						
						$sender_email 		= get_bloginfo('admin_email');
						$recipient_email 	= $sender_email;
						
						$Email_title = 'New template submission from seller ID:' . $this->parent->user->ID;
						
						$message = 'A new template submited by the seller ID ' . $this->parent->user->ID . ' is waiting for your approval.' . PHP_EOL;
						
						$headers   = [];
						$headers[] = 'From: ' . get_bloginfo('name') . ' <'.$sender_email.'>';
						//$headers[] = 'MIME-Version: 1.0';
						$headers[] = 'Content-type: text/html';
						
						$preMessage = "<html><body><div style='width:100%;padding:5px;margin:auto;font-size:14px;line-height:18px'>" . apply_filters('the_content', $message) . "<div style='clear:both'></div><div style='clear:both'></div></div></body></html>";
				
						if(!wp_mail($recipient_email, $Email_title, $preMessage, $headers)){
							
							global $phpmailer;
							
							wp_mail($this->parent->settings->options->emailSupport, 'Error sending seller submission' , print_r($phpmailer->ErrorInfo,true));			
						}					
					}
				}
			}
			
			$redirect_url = add_query_arg( array( 
			
				'edited' => '',
			
			), $this->parent->urls->current);
		}
		elseif( !empty($_POST['post_title']) && !empty($_POST['tax_input']) ){
			
			// add product
			
			if( $product_id = wp_insert_post( array(
				
				'post_status' 	=> 'draft',
				'post_type' 	=> 'cb-default-layer',
				'post_title' 	=> $_POST['post_title'],
				'tax_input'		=> $_POST['tax_input']
			))){
				
				$redirect_url = add_query_arg( array( 
				
					'action' 	=> 'edit', 
					'id'		=> $product_id,
					
				), $this->parent->urls->current );
			}
		}
		
		if( !empty($redirect_url) ){
			
			wp_redirect($redirect_url);
			exit;			
		}
	}
	
	public function add_layer_columns(){
		
		//$this->parent->layer->columns['addon-column'] = 'Addon columns';
	}
	
	public function add_layer_column_content($column_name, $term){
		
		/*
		if( $column_name === 'addon') {

			$this->parent->layer->column .= 'addon column content';
		}
		*/
	}
	
	public function add_api_layer_plan_option ($terms){
		
		/*
		$this->parent->admin->html .= '<td style="width:150px;">';
		
			foreach($terms as $term){
				
				$this->parent->admin->html .= '<span style="display:block;padding:1px 0 3px 0;margin:0;">';
					
					if($term->options['addon_amount']==1){
						
						$this->parent->admin->html .= '+'.$term->options['addon_amount'].' dom';
					}
					elseif($term->options['addon_amount']>0){
						
						$this->parent->admin->html .= '+'.$term->options['addon_amount'].' doms';
					}	
					else{
						
						$this->parent->admin->html .= $term->options['addon_amount'].' doms';
					}					
			
				$this->parent->admin->html .= '</span>';
			}
		
		$this->parent->admin->html .= '</td>';
		*/
	}
	
	public function sum_addon_amount( &$total_addon_amount=0, $options){
		
		/*
		$total_addon_amount = $total_addon_amount + $options['addon_amount'];
		
		return $total_addon_amount;
		*/
	}
	
	public function add_api_layer_plan_option_total($taxonomies,$plan_options){

		/*
	
		$total_addon_amount = 0;
	
		foreach ( $taxonomies as $taxonomy => $terms ) {
	
			foreach($terms as $term){

				if ( in_array( $term->slug, $plan_options ) ) {
					
					$total_addon_amount 	= $this->sum_addon_amount( $total_addon_amount, $term->options);
				}
			}
		}
		
		$this->parent->admin->html .= '<td style="width:150px;">';
		
			if($total_addon_amount==1){
				
				$this->parent->admin->html .= '+'.$total_addon_amount.' addon';
			}
			elseif($total_addon_amount>0){
				
				$this->parent->admin->html .= '+'.$total_addon_amount.' addons';
			}									
			else{
				
				$this->parent->admin->html .= $total_addon_amount.' addons';
			}		
		
		$this->parent->admin->html .= '</td>';
		*/
	}
	
	public function add_plan_shortcode_attributes($taxonomies,$plan_options){
		
		//$this->parent->plan->shortcode .= 'addon attributes';		
	}
		
	public function handle_subscription_plan(){
				
		
	}
	
	public function add_star_triggers(){

		$this->parent->stars->triggers['plan subscription']['ltple_paid_market_place_item'] = array(
			
			'description' => 'when you purchase an item on the seller'
		);	

		return true;
	}
	
	
	public function handle_item_delivery($plan,$user){
				
		if( !empty($plan['items']) ){
			
			do_action('ltple_paid_market_place_item',$user);
		}
	}
	
	public function add_user_plan_option_total( $user_id, $options ){
		
		//$this->parent->plan->user_plans[$user_id]['info']['total_addon_amount'] 	= $this->sum_addon_amount( $this->parent->plan->user_plans[$user_id]['info']['total_addon_amount'], $options);
	}
	
	public function add_user_plan_info( $user_id ){
		

	}
	
	/*
	public function set_gallery($layer_type,$layer_range){
		
		if( !$this->parent->inWidget ){
		
			$this->items = $this->get_gallery_items($layer_type,$layer_range);
			
			if( $this->totalItems > 0 ){
			
				add_filter( 'ltple_gallery_tab', array( $this, 'add_gallery_tab'),10,2);

				add_filter( 'ltple_gallery_items', array( $this, 'add_gallery_items'),10,2);
			}
		}
	}
	*/
	
	public function add_gallery_tab($layer_type,$layer_range){
		

	}
	
	public function add_gallery_items($layer_type,$layer_range){

	}
	
	/**
	 * Wrapper function to register a new post type
	 * @param  string $post_type   Post type name
	 * @param  string $plural      Post type item plural name
	 * @param  string $single      Post type item single name
	 * @param  string $description Description of post type
	 * @return object              Post type class object
	 */
	public function register_post_type ( $post_type = '', $plural = '', $single = '', $description = '', $options = array() ) {

		if ( ! $post_type || ! $plural || ! $single ) return;

		$post_type = new LTPLE_Client_Post_Type( $post_type, $plural, $single, $description, $options );

		return $post_type;
	}

	/**
	 * Wrapper function to register a new taxonomy
	 * @param  string $taxonomy   Taxonomy name
	 * @param  string $plural     Taxonomy single name
	 * @param  string $single     Taxonomy plural name
	 * @param  array  $post_types Post types to which this taxonomy applies
	 * @return object             Taxonomy class object
	 */
	public function register_taxonomy ( $taxonomy = '', $plural = '', $single = '', $post_types = array(), $taxonomy_args = array() ) {

		if ( ! $taxonomy || ! $plural || ! $single ) return;

		$taxonomy = new LTPLE_Client_Taxonomy( $taxonomy, $plural, $single, $post_types, $taxonomy_args );

		return $taxonomy;
	}

	/**
	 * Load frontend CSS.
	 * @access  public
	 * @since   1.0.0
	 * @return void
	 */
	public function enqueue_styles () {
		
		//wp_register_style( $this->_token . '-frontend', esc_url( $this->assets_url ) . 'css/frontend.css', array(), $this->_version );
		//wp_enqueue_style( $this->_token . '-frontend' );
	} // End enqueue_styles ()

	/**
	 * Load frontend Javascript.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function enqueue_scripts () {
		
		//wp_register_script( $this->_token . '-frontend', esc_url( $this->assets_url ) . 'js/frontend' . $this->script_suffix . '.js', array( 'jquery' ), $this->_version );
		//wp_enqueue_script( $this->_token . '-frontend' );
	} // End enqueue_scripts ()

	/**
	 * Load admin CSS.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function admin_enqueue_styles ( $hook = '' ) {
		
		//wp_register_style( $this->_token . '-admin', esc_url( $this->assets_url ) . 'css/admin.css', array(), $this->_version );
		//wp_enqueue_style( $this->_token . '-admin' );
	} // End admin_enqueue_styles ()

	/**
	 * Load admin Javascript.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function admin_enqueue_scripts ( $hook = '' ) {
		
		//wp_register_script( $this->_token . '-admin', esc_url( $this->assets_url ) . 'js/admin' . $this->script_suffix . '.js', array( 'jquery' ), $this->_version );
		//wp_enqueue_script( $this->_token . '-admin' );
	} // End admin_enqueue_scripts ()

	/**
	 * Load plugin localisation
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function load_localisation () {
		
		load_plugin_textdomain( $this->settings->plugin->slug, false, dirname( plugin_basename( $this->file ) ) . '/lang/' );
	} // End load_localisation ()

	/**
	 * Load plugin textdomain
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function load_plugin_textdomain () {
		
	    $domain = $this->settings->plugin->slug;

	    $locale = apply_filters( 'plugin_locale', get_locale(), $domain );

	    load_textdomain( $domain, WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo' );
	    load_plugin_textdomain( $domain, false, dirname( plugin_basename( $this->file ) ) . '/lang/' );
	} // End load_plugin_textdomain ()

	/**
	 * Main LTPLE_Seller Instance
	 *
	 * Ensures only one instance of LTPLE_Seller is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @static
	 * @see LTPLE_Seller()
	 * @return Main LTPLE_Seller instance
	 */
	public static function instance ( $file = '', $version = '1.0.0' ) {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $file, $version );
		}
		return self::$_instance;
	} // End instance ()

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __clone () {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), $this->_version );
	} // End __clone ()

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup () {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), $this->_version );
	} // End __wakeup ()

	/**
	 * Installation. Runs on activation.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function install () {
		$this->_log_version_number();
	} // End install ()

	/**
	 * Log the plugin version number.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	private function _log_version_number () {
		update_option( $this->_token . '_version', $this->_version );
	} // End _log_version_number ()

}
