<?php
/**
 * Plugin Name: OSF X
 * Plugin URI:  http://wordpress.org/extend/plugins/podlove-podcasting-plugin-for-wordpress/
 * Description: (Non breaking) OSF Shownotes
 * Version:     0.1-alpha
 * Author:      Podlove
 * Author URI:  http://podlove.org
 * License:     MIT
 * License URI: license.txt
 * Text Domain: osfx
 */

require('vendor/autoload.php');
// Settings
require('settings/settings.php');
// Post fields
require('settings/post.php');
// Models
require('model/shownotes.php');
require('model/shownote.php');
// Ajax
require('lib/ajax.php');
// Constants
require('lib/constants.php');

add_action( 'add_meta_boxes', array( 'OSFX\Settings\Post', 'shownote_box' ) );
add_action( 'save_post', array( 'OSFX\Settings\Post', 'save_shownotes') );
add_action( 'admin_print_styles', array( 'OSFX\Settings\Settings', 'admin_scripts_and_styles') );
add_action( 'admin_menu', array( 'OSFX\Settings\Settings', 'admin_menu') );
add_action( 'admin_init', array( 'OSFX\Settings\Settings', 'register_settings') );

add_action( 'wp_ajax_osfx-chapters', array( 'OSFX\Ajax', 'chapters') );
add_action( 'wp_ajax_osfx-validate', array( 'OSFX\Ajax', 'validate') );

add_filter( 'posts_clauses', array( 'OSFX', 'shownotes_search_where'), 20, 1 );
add_filter( 'posts_groupby', array( 'OSFX', 'shownotes_groupby') );

add_shortcode( 'shownotes', array( 'OSFX', 'template' ) );

class OSFX {

	/*
	 * Code by Robert Schmidl. 
	 * I think I do not have do write the whole thing again, or?
	 */

	/* 
	 * new search function for the shownotes that doesn't replace the posts query but extends it
	 */
	function shownotes_search_where($query) {

	  // if we are on a search page, modify the generated SQL
	  if ( get_option('osfx_search') == 'on' && is_search() && !is_admin() ) {

	      global $wpdb;
	      $custom_fields = array('_shownotes');
	      $keywords = explode(' ', get_query_var('s')); // build an array from the search string
	      $shownotes_query = "";
	      foreach ($custom_fields as $field) {
	           foreach ($keywords as $word) {
	               $shownotes_query .= "((joined_tables.meta_key = '".$field."')";
	               $shownotes_query .= " AND (joined_tables.meta_value  LIKE '%{$word}%')) OR ";
	           }
	      }
	      
	      // if the shownotes query is not an empty string, append it to the existing query
	      if (!empty($shownotes_query)) {
	          // add to where clause
	          $query['where'] = str_replace(
	          			"(".$wpdb->prefix."posts.post_title LIKE '%",
	          			"({$shownotes_query} ".$wpdb->prefix."posts.post_title LIKE '%",
	          			$query['where']
	          		);

	          $query['join'] = $query['join'] . " INNER JOIN {$wpdb->postmeta} AS joined_tables ON ({$wpdb->posts}.ID = joined_tables.post_id)";
	      }

	  }

	  return $query;
	}

	/* 
	 * we need this filter to add a grouping to the SQL string - prevents duplicate result rows
	 */
	function shownotes_groupby($groupby){
	  
	  global $wpdb;

	  // group by post id to avoid multiple results in the modified search
	  $groupby_id = "{$wpdb->posts}.ID";
	  
	  // if this is not a search or the groupby string already contains our groupby string, just return
	  if(!is_search() || strpos($groupby, $groupby_id) !== false) {
	    return $groupby;
	  } 

	  // if groupby is empty, use ours
	  if(strlen(trim($groupby)) === 0) {
	    return $groupby_id;
	  } 

	  // groupby wasn't empty, append ours
	  return $groupby.", ".$groupby_id;
	}

	function template( $args=array() ) {
		global $post;
		if ( isset($args['scope']) && $args['scope'] == 'global' ) {
			global $wpdb;
			$source = '';
			$shownotes_array = $wpdb->get_col( "SELECT DISTINCT postmeta.meta_value FROM {$wpdb->postmeta} postmeta LEFT JOIN {$wpdb->posts} post ON post.ID = postmeta.post_id WHERE postmeta.meta_key = '_shownotes' AND post.post_status = 'publish'	AND post.post_type = 'podcast'" );
			foreach ($shownotes_array as $shownote) {
				$source .= preg_replace("/HEADER(.*)\/HEADER/si", '', $shownote);
			}
		} else {
			$source = get_post_meta( $post->ID, '_shownotes' , TRUE);
		}

		if ( empty($args['id']) || ! $source )
			return;

		$twig_template = "";

		foreach ( get_option('osfx_template') as $template ) {
			if ( $template['id'] == $args['id'] ) {
				$twig_template = $template['source'];
				break;
			}
		}

		$shownotes = new OSFX\Model\Shownotes();
		$shownotes->source = $source;
		$shownotes->parse();
		$shownotes->validate();

		$loader = new Twig_Loader_String();
		$twig = new Twig_Environment($loader);

		/*
		 * Implement additional Twig functions
		 */
		// Get the Favicon for the current website via Google S2
		$getURLIcon = new Twig_SimpleFunction( 'getURLIcon', function ( $shownote ) {
		    return "https://www.google.com/s2/favicons?domain=" . $shownote->url;
		} );
		$twig->addFunction($getURLIcon);
		$URLIcon = new Twig_SimpleFunction( 'URLIcon', function ( $shownote ) {
			if ( empty( $shownote->url ) )
				return;

		    return "<img src=\"https://www.google.com/s2/favicons?domain=" . $shownote->url . "\" alt=\"" . $shownote->title ."\" title=\"" . $shownote->title ."\" />";
		}, array('is_safe' => array('html') ) );
		$twig->addFunction($URLIcon);

		// Link the title if it has a URL
		$linkedTitle = new Twig_SimpleFunction( 'linkedTitle', function ( $shownote ) {
			if ( empty( $shownote->url ) )
				return $shownote->title;

			return "<a href=\"" . $shownote->url . "\">" . $shownote->title . "</a>";
		}, array('is_safe' => array('html') ) );
		$twig->addFunction($linkedTitle);

		// Order the Shownotes
		$nest = new Twig_SimpleFilter( "nest", function ( $notes ) use ( $shownotes ) {
		    $shownotes->order();
		    return $shownotes->shownotes;
		});
		$twig->addFilter($nest);

		// Display a specific type only
		$filtertype = new Twig_SimpleFilter( "type", function ( $notes, $type ) use ( $shownotes ) {
			$filtered_shownotes = clone $shownotes;
			$filtered_shownotes->filter_by_property( "type", $type );

		    return $filtered_shownotes->shownotes;
		});
		$twig->addFilter($filtertype);

		// Affiliate the links
		$affiliate = new Twig_SimpleFilter( "affiliate", function ( $shownote ) {
			$shownote->affiliate();
			return $shownote;
		});
		$twig->addFilter($affiliate);

		return $twig->render(
			$twig_template,
				array(
						'shownotes' => $shownotes->shownotes,
						'header' => $shownotes->header
					)
			);

	}

}

?>