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

require( 'vendor/autoload.php' );

add_action( 'add_meta_boxes', 'shownote_box' );
add_action( 'save_post', 'save_shownotes' );

add_action( 'wp_print_styles', 'scripts_and_styles' );
add_action( 'admin_print_styles', 'admin_scripts_and_styles' );

add_action( 'admin_menu', 'admin_menu' );
add_action( 'admin_init', 'register_settings' );

add_filter( 'posts_clauses', 'shownotes_search_where', 20, 1 );
add_filter( 'posts_groupby', 'shownotes_groupby' );


/*
 * Code by Robert Schmidl. 
 * I think I do not have do write the whole thing again, or?
 */

/* 
 * new search function for the shownotes that doesn't replace the posts query but extends it
 */
function shownotes_search_where($query) {

  // if we are on a search page, modify the generated SQL
  if ( is_search() && !is_admin() ) {

      global $wpdb;
      $custom_fields = array('osfx_shownotes');
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

  return ($query);
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

function admin_menu() {
	add_options_page(
			'OSF X Options',
			'OSF X',
			'manage_options',
			'osfx',
			'osfx_settings_page'
		);
}

function register_settings() {
	register_setting( 'osfx_options', 'osfx_search' );
	register_setting( 'osfx_options', 'osfx_template' );
	register_setting( 'osfx_options', 'osfx_style' );
	register_setting( 'osfx_options', 'osfx_showpad' );
}

function osfx_settings_page() {
	?>
	<div class="wrap">
	<h2>Shownotes Options</h2>
	<form method="post" action="options.php" class="osfx-options">
		<?php settings_fields( 'osfx_options' ); ?>
		<?php do_settings_sections( 'osfx_options' ); ?>
		<h3>General</h3>
		<table class="form-table">
	        <tr valign="top">
	        	<th scope="row">
	        		Search
	        	</th>
	       		<td>
	       			<input type="checkbox" id="osfx_search" name="osfx_search" <?php echo ( get_option('osfx_search') == 'on' ? 'checked' : '' ) ?>/>
	       			<label for="osfx_search">Include Shownotes in WordPress search</label>
	       		</td>
	        </tr>
		</table>
		<h3>Template &amp; Styles</h3>
		Adjust the style and the way how shownotes are presented in your Podcast.
		<table class="form-table">
	        <tr valign="top">
	        	<th scope="row">
	        		Template
	        	</th>
	       		<td>
	       			<textarea cols="80" rows="10" id="osfx_template" name="osfx_template"><?php echo get_option('osfx_template'); ?></textarea>
	       			<br />
	       			<label for="osfx_template">Use the Twig Template Syntax to edit the way how your Shownotes are displayed</label>
	       		</td>
	        </tr>
	         <tr valign="top">
	         	<th scope="row">
	         		Style
	         	</th>
	        		<td>
	        			<?php
	        				$styles = array( 
	        						'None' => '',
	        						'Bitmap' => 'bitmap.css.php'
	        					);
	        			?>
	        			<select id="osfx_style" name="osfx_style">
	        				<?php
	        					foreach ( $styles as $style_name => $style_file ) {
	        						echo "<option value='" . $style_file . "' " . ( get_option('osfx_style') == $style_file ? 'selected' : '' ) . " >" . $style_name . "</option>";
	        					}
	        				?>
	        			</select>
	        			<label for="osfx_style">If you want, you can use one of the default styles, which will style your shownotes</label>
	        		</td>
	         </tr>
		</table>
		<h3>Import from ShowPad</h3>
		The plugin allows you to easily import Shownotes from Showpad.
		<table class="form-table">        
	        <tr valign="top">
		        <th scope="row">
		        	Podcast Name
		        </th>
		        <td>
		        	<input type="text" id="osfx_showpad" name="osfx_showpad" value="<?php echo get_option('osfx_showpad'); ?>" />
		        	<label for="osfx_showpad">enter the Podcastname in the Showpad</label>
		        </td>
	        </tr>
	    </table>
	    <script type="text/javascript">
	    	var editor = CodeMirror.fromTextArea(document.getElementById("osfx_template"), {
	    	  mode: "application/xml",
	    	  styleActiveLine: true,
	    	  lineNumbers: true,
	    	  lineWrapping: true,
	    	  mode: 'application/x-twig'
	    	});
	    </script>
	    <?php submit_button(); ?>
	</form>
	</div>
	<?php
}

function save_shownotes( $post_id ) {
	update_post_meta( $post_id, 'osfx_shownotes', $_POST['_osfx_shownotes'] );
}

function admin_scripts_and_styles() {
	wp_register_script(
		'osfx_codemirror',
		plugins_url() . '/OSFX/lib/codemirror/lib/codemirror.js',
		false
	);
	wp_enqueue_script('osfx_codemirror');

	wp_register_script(
		'osfx_codemirror_twig',
		plugins_url() . '/OSFX/lib/codemirror/mode/twig.js',
		false
	);
	wp_enqueue_script('osfx_codemirror_twig');

	wp_register_script(
		'osfx_codemirror_twigmixed',
		plugins_url() . '/OSFX/lib/codemirror/mode/twigmixed.js',
		false
	);
	wp_enqueue_script('osfx_codemirror_twigmixed');

	wp_register_script(
		'osfx_codemirror_mixedmode',
		plugins_url() . '/OSFX/lib/codemirror/mode/htmlmixed.js',
		false
	);
	wp_enqueue_script('osfx_codemirror_mixedmode');

	wp_register_script(
		'osfx_codemirror_css',
		plugins_url() . '/OSFX/lib/codemirror/mode/css.js',
		false
	);
	wp_enqueue_script('osfx_codemirror_css');

	wp_register_script(
		'osfx_codemirror_javascript',
		plugins_url() . '/OSFX/lib/codemirror/mode/javascript.js',
		false
	);
	wp_enqueue_script('osfx_codemirror_javascript');

	wp_register_script(
		'osfx_codemirror_xml',
		plugins_url() . '/OSFX/lib/codemirror/mode/xml.js',
		false
	);
	wp_enqueue_script('osfx_codemirror_xml');

	wp_register_script(
		'osfx_codemirror_vbscript',
		plugins_url() . '/OSFX/lib/codemirror/mode/vbscript.js',
		false
	);
	wp_enqueue_script('osfx_codemirror_vbscript');

	wp_register_style(
		'osfx_codemirror',
		plugins_url() . '/OSFX/lib/codemirror/lib/codemirror.css',
		false
	);
	wp_enqueue_style('osfx_codemirror');
	wp_register_style(
		'osfx_settings_styles',
		plugins_url() . '/OSFX/osfx.css',
		false
	);
	wp_enqueue_style('osfx_settings_styles');
}

function scripts_and_styles() {
	wp_register_style(
		'osfx_shownote_icons',
		plugins_url() . '/OSFX/styles/bitmap.css.php',
		false
	);
	wp_enqueue_style('osfx_shownote_icons');
}

function shownote_box() {
	global $post;

	add_meta_box(
		'shownotes_meta_field',
		'Shownotes',
		function() use ( $post ) {
			?>
				<textarea class="large-text" name="_osfx_shownotes" style="height: 200px;"><?php echo get_post_meta( $post->ID, 'osfx_shownotes' , TRUE); ?></textarea>
			<?php
		},
		'podcast' );
}

function template() {
	global $post;

	$loader = new Twig_Loader_String();
	$twig = new Twig_Environment($loader);
	$source = get_post_meta( $post->ID, 'osfx_shownotes' , TRUE);

	if ( !$source )
		return;

	$shownotes = parse_shownotes( $source );

	return $twig->render(
		   get_option('osfx_template'),
			array(
				'shownotes' => $shownotes
				)
		   );

}
add_shortcode( 'shownotes', 'template' );

function parse_shownotes( $source ) {
	// Remove Header (is not used yet)
	if( strpos($source, '/HEADER') )
	    $source = substr( $source, strpos($source, '/HEADER') + 7 );

	$data            = array();
	$line_pointer    = 0;
	$first_timestamp = NULL;

	foreach ( explode("\n", $source) as $key => $line ) {
	    $data[$key][] = strtok( $line, " " );
	    while ( $foo=strtok( " " ) ) {
	        $data[$key][] = $foo;
	    }
	}

	foreach ( $data as $line_number => $line_content ) {

	    $line_pointer++;

	    if ( trim($line_content[0]) ) {

	        $shownote           = new OSFShownote;
	        $shownote->title    = new OSFShownoteProperties; // A title should be always used. Therefore we create a corresponding object here
	        $shownote->line     = $line_pointer;

	        foreach ( $line_content as $string_key => $string ) {       

	            $first_character = substr( $string, 0, 1 );
	            
	            switch ( $first_character ) {
	                case "0" : case ( preg_match('/^[0-9]*$/', $first_character ) ? TRUE : FALSE ):
	                    $timestring = FALSE;

	                    // Check for characters indicating a time string
	                    $numbers_of_colon = substr_count( $string , ':' );
	                    $numbers_of_dots  = substr_count( $string , '.' );

	                    if ( $numbers_of_colon == 2 && $numbers_of_dots <= 1 ) {
	                        $shownote->timestamp        = new OSFShownoteProperties;
	                        $shownote->timestamp->value = $string;
	                    } elseif ( strlen($string) == 10 ) {
	                        $shownote->timestamp        = new OSFShownoteProperties;
	                        $shownote->timestamp->value = $string;
	                        if ( is_null( $first_timestamp ) ) 
	                            $first_timestamp = $string;
	                    } else {
	                        $shownote->title->value .= $string.' ';
	                    } 
	                break;
	                case "-":
	                    // If a hyphen appears we need to check for more hyphens to determine the level
	                    $number_of_hyphen = substr_count( $string , '-' );

	                    if ( !is_null( $number_of_hyphen ) && is_null( $shownote->title->value ) ) {
	                        $shownote->level        = new OSFShownoteProperties;
	                        $shownote->level->value = $number_of_hyphen;

	                        // Find the parent element
	                        $previous_shownote_key = count( $shownotes ) - 1;

	                        while ( $previous_shownote_key > 0 ) {
	                            if ( !is_object( $shownotes[$previous_shownote_key]->level ) ||
	                                 $shownotes[$previous_shownote_key]->level->value == $number_of_hyphen - 1 ) {

	                                $shownote->parent = $previous_shownote_key;
	                                break;
	                            }
	                            $previous_shownote_key = $previous_shownote_key - 1;
	                        }
	                    } else {
	                        $shownote->title->value .= $string.' '; 
	                    }
	                break;
	                case "#":
	                    // Check if hash appear multiple times (then add string to title because it is not a valid tag)
	                    $number_of_hash = substr_count( $string , '#' );
	                    $next_char_is_alnum_char = ( isset( $line_content[$string_key + 1][0] ) ? ctype_alnum( $line_content[$string_key + 1][0] ) : FALSE );

	                    if ( $number_of_hash > 1 || strlen( $string ) == 1 || $next_char_is_alnum_char ) {
	                        $shownote->title->value .= $string.' ';
	                    } else {
	                    	$string = trim( substr( $string, 1 ) );

	                    	switch ( strtolower( $string ) ) {
	                    		case 'c' :
	                    			$string = 'chapter';
	                    		break;
	                    		case 't' :
	                    			$string = 'topic';
	                    		break;
	                    		case 'v' :
	                    			$string = 'video';
	                    		break;
	                    		case 'a' :
	                    			$string = 'audio';
	                    		break;
	                    		case 'i' :
	                    			$string = 'image';
	                    		break;
	                    		case 'q' :
	                    			$string = 'quote';
	                    		break;
	                    		case 'r' :
	                    			$string = 'revision';
	                    		break;
	                    	}
	                    	$shownote->tags[] = $string;
	                    }
	                break;
	                case "<":

	                    $found_another_link_element = FALSE;

	                    foreach ( array_slice( $line_content, $string_key + 1 ) as $line_element ) {
	                        if ( $line_element[0] == '<' || ctype_alnum( $line_element[0] ) )
	                            $found_another_link_element = TRUE;
	                    }



	                    if ( strpos( $string , '>' ) && !is_object( $shownote->link ) && $found_another_link_element == FALSE ) {
	                        $shownote->link = new OSFShownoteProperties;
	                        $shownote->link->value = substr( $string , 1, strpos( $string , '>' ) - 1 );
	                    } else {
	                        $shownote->title->value .= htmlspecialchars( $string ).' ';
	                    }
	                break;
	                default:
	                    $shownote->title->value .= $string.' ';
	                break;  
	            }
	        }

	        $shownotes['entries'][] = $shownote;
	    }   
	}

	return $shownotes;
}

class OSFShownote {

    function __construct() {
        $this->timestamp    = NULL;
        $this->title        = NULL;
        $this->link         = NULL;
        $this->level        = NULL;
        $this->tags         = array();
        $this->line         = NULL;
        $this->parent       = NULL;
        $this->children     = array();
    }

}

class OSFShownoteProperties {

	function __tostring() {
		return $this->value;
	}

    function __construct() {
        $this->value    = NULL;
        $this->start    = NULL;
        $this->end      = NULL;
    }

}

?>