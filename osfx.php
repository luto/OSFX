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
		'osfx_codemirror_osf',
		plugins_url() . '/OSFX/lib/codemirror/mode/codemirror-osf/osf.js',
		false
	);
	wp_enqueue_script('osfx_codemirror_osf');

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
				<textarea class="large-text" name="_osfx_shownotes" id="_osfx_shownotes" style="height: 200px;"><?php echo get_post_meta( $post->ID, 'osfx_shownotes' , TRUE); ?></textarea>
				<script type="text/javascript">
					var editor = CodeMirror.fromTextArea(document.getElementById("_osfx_shownotes"), {
					  mode: "application/xml",
					  styleActiveLine: true,
					  lineNumbers: true,
					  lineWrapping: true,
					  mode: 'text/x-osf'
					});
				</script>
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

	$shownotes = new Shownotes();
	$shownotes->source = $source;
	$shownotes->parse();
	$entries = $shownotes->shownotes;
	$shownotes->order();
	$chapters = $shownotes->shownotes; 

	return $twig->render(
		   get_option('osfx_template'),
			array(
				'shownotes' => array(
						'entries' => $entries,
						'chapters' => $chapters
					)
				)
		   );

}
add_shortcode( 'shownotes', 'template' );

class Shownote {
	function __construct() {
		$this->type 		= FALSE;
		$this->timestamp 	= FALSE;
		$this->title 		= '';
		$this->url			= FALSE;
		$this->tags 		= FALSE;
		$this->level 		= 1;
		$this->shownotes	= array();

		$this->isValid		= TRUE;
		$this->errorMessage	= '';
		$this->line			= 0;
	}

	// For validation check if < is escaped!

	public function unescape_title_chars() {
		$this->title = str_replace('\>', '>', $this->title);
		$this->title = str_replace('\<', '<', $this->title);
		$this->title = str_replace('\#', '#', $this->title);
	}

	public function set_type() {
		$this->type = $this->filter_type();
	}

	private function filter_type() {
		if ( in_array('c', $this->tags) || in_array('chapter', $this->tags) ) {
			$this->level = 0;
			return 'chapter';
		}

		if ( in_array('v', $this->tags) || in_array('video', $this->tags) )
			return 'video';

		if ( in_array('i', $this->tags) || in_array('image', $this->tags) )
			return 'image';

		if ( in_array('a', $this->tags) || in_array('audio', $this->tags) )
			return 'audio';

		// To be added.
		return;
	}
}

class Shownotes {
	public $source;
	public $reserved_categories = array( 
				'c', 'chapter',
				'i', 'image',
				'a', 'audio',
				'v', 'video'
				// To be added.
			);

	public function __construct() {
		$this->shownotes = array();
	}

	public function order() {
		// Reverse array to read the items backwards.
		krsort($this->shownotes);
		// Collector will be used to collect subitems.
		$collector = $this->empty_collector();
		foreach ( $this->shownotes as $shownote_key => $shownote) {
			if ( $shownote->level == 0 ) {
				$this->shownotes[$shownote_key]->shownotes = array_reverse($collector['items']);
				$collector = $this->empty_collector();
				continue;
			}
			if ( $shownote->level == $collector['level'] ) {
				$collector['items'][] = $shownote;
				unset($this->shownotes[$shownote_key]);
				continue;
			}
			if ( $shownote->level > $collector['level'] ) {
				// Check if level depth is valid.
				if ( $shownote->level - 1 !== $collector['level'] ) {
					$shownote->isValid = FALSE;
					$shownote->errorMessage = 'The upper level of items is empty.';
				}

				$collector = $this->empty_collector();
				$collector['level'] = $shownote->level;
				$collector['items'][] = $shownote;
				unset($this->shownotes[$shownote_key]);
				continue;
			}
			if ( $shownote->level < $collector['level'] ) {
				krsort($collector['items']);
				$this->shownotes[$shownote_key]->shownotes = array_reverse($collector['items']);
				$collector = $this->empty_collector();
				$collector['level'] = $shownote->level;
				$collector['items'][] = $shownote;
				unset($this->shownotes[$shownote_key]);
				continue;
			}
		}
		// Reverse array.
		ksort($this->shownotes);
	}

	private function empty_collector() {
		return array(
				'level' => 0,
				'items'	=> array()
			);
	}

	public function parse() {
		// This will be the array filled with shownotes
		$shownotes = array();

		// Indicators
		$linenumber 			= 0;
		$shownote_id 			= 0;
		$initial_unix_timestamp = 0;

		// Remove the Header here. It is not needed for parsing the shownotes.
		if( $header_closure_position = strpos($this->source, '/HEADER') ) {
		    $linenumber = substr_count($this->source, "\n", 0, $header_closure_position) + 1; // Adjusting the linenumber.
		    $this->source = substr( $this->source, strpos($this->source, '/HEADER') + 7 );
		}

		/*
		 * Header is removed. Now we can start parsing every single line.
		 */
		foreach ( explode("\n", $this->source) as $line) {
			// Remove white-spaces.
			$line = trim($line);
			// Skip empty lines.
			if ( ! $line ) {
				$linenumber++;
				continue;
			}
			
			// Create new Shownote object (every line should contain Shownotes).
			$shownote = new Shownote();
			$shownote->line = $linenumber;
			// Check for Tags.
			preg_match_all('/\s+#(\w+)/i', $line, $tags );
			$shownote->tags = $tags[1]; // Second element in array contains the tags.
			// Remove the tags from the line.
			foreach ( $tags[0] as $tag ) {
				$line = $this->remove_from_line( $line, $tag );
			}
			// With respect to the tags, set the type.
			$shownote->set_type();
			// Check for URLs.
			preg_match_all('/\s+<(.*)>/i', $line, $url );
			if ( isset( $url[1][0] ) && isset( $url[0][0] ) ) {
				if ( count($url[1]) > 1 || strrpos($url[1][0], " ") ) {
					$shownote->isValid = FALSE;
					$shownote->errorMessage = 'Shownote contains multiple URLs.';
				}
				$line = $this->remove_from_line( $line, $url[0][0] );
				$shownote->url = $url[1][0];
			}
			// Fetch the timestamps.
			preg_match('/^([0-9|:|.]+)/i', $line, $timestamp);
			if ( isset( $timestamp[0] ) ) {
				$timestamp_in_unix_format = strtotime('@'.$timestamp[0]); // Need to check for specific unix date!
				if ( $initial_unix_timestamp == 0 ) {
					$initial_unix_timestamp = $timestamp_in_unix_format;
				}
				$shownote->timestamp = $timestamp_in_unix_format - $initial_unix_timestamp;
				$line = $this->remove_from_line( $line, $timestamp[0] );
			}
			// Fetch the level.
			preg_match('/^[-][\s|-]/i', trim($line), $hierachie);
			if ( isset( $hierachie[0] ) ) {
				$line = $this->remove_from_line( $line, $hierachie[0] );
				$shownote->level = substr_count($hierachie[0], '-') + 1;
			}
			// The rest will be the title of the line.
			$shownote->title = trim($line);
			$shownote->unescape_title_chars();

			$this->shownotes[] = $shownote;
			$linenumber++;
		}
	}

	private function remove_from_line( $string, $to_be_removed ) {
		$modifier = str_replace('/', '\/', $to_be_removed);
		$modifier = str_replace('.', '\.', $modifier);
		$modifier = str_replace('-', '\-', $modifier);
		$modifier = str_replace('?', '\?', $modifier);
		$modifier = str_replace('+', '\+', $modifier);
		$modifier = str_replace('(', '\(', $modifier);
		$modifier = str_replace(')', '\)', $modifier);
		return preg_replace("/".$modifier."/i", '', $string, 1);
	}



}

?>