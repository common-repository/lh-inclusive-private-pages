<?php
/**
 * Plugin Name: LH Inclusive Private Pages
 * Plugin URI: https://lhero.org/portfolio/lh-inclusive-private-pages/
 * Description: Add private pages to the nav menu metabox and parent page dropdown
 * Author: Peter Shaw
 * Version: 1.00
 * Text Domain: lh_inclusive_private_pages
 * Domain Path: /languages
 * Author URI: https://shawfactor.com/
*/


// If this file is called directly, abandon ship.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
* LH Inclusive Private Pages plugin class
*/


if (!class_exists('LH_Inclusive_private_pages_plugin')) {


class LH_Inclusive_private_pages_plugin {
    
    private static $instance;
    private $label;
    
        
    static function get_plugin_namespace(){
        
        return 'lh_inclusive_private_pages';
        
    }

    static function get_includable_statuses() {
        
        return apply_filters('lh_inclusive_private_pages_statuses', array('private'));
        
        
    }
    
    static function map_status_capability($status, $post_type){
        
        if (($status == 'private') && isset($post_type->cap->read_private_posts)){
            
            return $post_type->cap->read_private_posts;
            
            } elseif (isset($post_type->cap->edit_others_posts)) {
                
            return $post_type->cap->edit_others_posts;  
                
            } else {
                
                return false;
                
            }
        
        
        
    }

    static function check_if_user_can_access($menu_object, $post_type){
        
        
    
    if (is_super_admin()){
        
     //return true;
     
    }
    
    $status = get_post_status($menu_object->object_id);
    
    if (($status == 'publish') && !empty($post_type->public)){
        
        return true;
        
    } elseif (($status != 'publish') && is_user_logged_in()){
        
        if (current_user_can(self::map_status_capability($status, $post_type))){
            
            return true;
            
        } else {
            
            return false;
            
        }
        
        
    }
        
        
        
    }

    public function show_private_pages_menu_selection( $args ){
        
        $args->_default_query['post_status'] = array_unique( array_merge(array('publish'),self::get_includable_statuses()));
    
        return $args;
        
    }


    public function show_private_in_search( $query ) {
        
    	if (wp_doing_ajax() && current_user_can('read_private_posts') && isset($_POST['action']) &&  ($_POST['action'] == 'menu-quick-search')) {
    	    
    	    $post_status = array_unique( array_merge(array('publish'),self::get_includable_statuses()));
    
    	    $query->set( 'post_status', $post_status );
    	    
    	}
    	
    }

    public function show_private_pages_in_dropdown($dropdown_args) {
    
        $dropdown_args['post_status'] = array_unique( array_merge(array('publish'),self::get_includable_statuses()));
    
        return $dropdown_args;
        
    }


    /**
     * Add (Status) to titles in nav menu page checklists
     *
     * @param string $title
     * @param object $page
     * @return string $title
     */
    public function menu_checklist_status_label( $title, $page_id = null ) {
    	if ( empty( $page_id ) )
    		return $title;
    	if ( ! function_exists( "get_current_screen" ) )
    		return $title;
    	if ( ! isset( get_current_screen()->base ) ) {
    		return $title;
    	}
    	if ( is_admin() && 'nav-menus' == get_current_screen()->base && ($this->label === TRUE) ) {	
    		$post_status = get_post_status( $page_id );
    		if ( $post_status !== __( 'publish' ) ) {
    			$status = get_post_status_object( $post_status );
    			$title .= " - ".$status->label."";
    		}
    	}
    	return $title;
    }

    public function turn_of_label($menu_obj, $menu){
        
        remove_filter( 'the_title', array($this, 'menu_checklist_status_label'), 11, 2 );    
            
        return $menu_obj;    
        
    }


    public function turn_on_label($items, $args){
        
    
        //add_filter( 'the_title', array($this, 'menu_checklist_status_label'), 10, 2 );    
            
        return $items;    
        
    }


    /**
     * Add (Status) to titles in page parent dropdowns
     *
     * @param string $title
     * @param object $page
     * @return string $title
     */
     
    public function page_parent_dropdown_status_label( $title, $page ) {
    	if ( !is_admin() )
    		return $title;
    		
    	$post_status = $page->post_status;
    	
    	if ( $post_status !== __( 'publish' ) ) {
    	    
    		$status = get_post_status_object( $post_status );
    		$title .= " - ".$status->label;
    		
    	}
    	
    	return $title;
    	
    }


    public function hide_menu_items_if_capability_mising( $items, $menu, $args ) {
        
        if (!is_admin()){
        
            foreach ( $items as $key => $item ) {
                
                if ( get_class($item) == 'WP_Post'){
                    
                    $post_type_object = get_post_type_object($item->object);
    
                    if (($item->object != 'custom') && ($item->type != 'taxonomy') && !self::check_if_user_can_access($item, $post_type_object)){
            
                        unset($items[$key] );   
            
                    }
                    
                }
            
            }
        
        }
    
        return $items;
            
    }
    
    public function add_wp_body_open_hooks(){
        
        //if the user can´t read item remove it from the menu
        add_filter( 'wp_get_nav_menu_items', array($this, 'hide_menu_items_if_capability_mising'), 10, 3 );
    
    }

    
    public function plugin_init(){
    
        //Add query argument for selecting pages to add to a menu
        add_filter( 'nav_menu_meta_box_object', array($this, 'show_private_pages_menu_selection'), 10, 1 );
        
        //include private post status in search results
        add_action( 'pre_get_posts', array($this,'show_private_in_search'));
        
        //does not work atm with gutenberg, might be fixed in the future release
        add_filter('page_attributes_dropdown_pages_args', array($this, 'show_private_pages_in_dropdown'), 10, 1);
        
        //add private to the quick edit
        add_filter('quick_edit_dropdown_pages_args', array($this, 'show_private_pages_in_dropdown'), 10, 1);
        
        //add post status label to menu checklist, works but creates other issues
        //add_filter( 'the_title', array($this, 'menu_checklist_status_label'), 10, 2 );
        
        //turn labelling off
        //add_filter( 'pre_wp_nav_menu', array($this, 'turn_of_label'), 10, 2 );
        
        //turn labelling back on
        //apply_filters( 'wp_nav_menu_items', array($this, 'turn_on_label'), 10, 2 );

        //add the post status to the page parent dropdown
        add_filter( 'list_pages', array($this, 'page_parent_dropdown_status_label'), 10, 2 );
        
        //add others hooks on body open so that they only run when needed
        add_action( 'wp_body_open', array($this,'add_wp_body_open_hooks'));

    }
	

    /**
     * Gets an instance of our plugin.
     *
     * using the singleton pattern
     */
     
    public static function get_instance(){
        
        if (null === self::$instance) {
            
            self::$instance = new self();
            
        }
 
        return self::$instance;
        
    }



    public function __construct() {
        
        $this->label = true;
        
        //run our hooks on plugins loaded to as we may need checks       
        add_action( 'plugins_loaded', array($this,'plugin_init'));
    
    }
    
    
}

$lh_inclusive_private_pages_instance = LH_Inclusive_private_pages_plugin::get_instance();


}



?>