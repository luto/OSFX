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

add_action( 'admin_print_styles', 'admin_scripts_and_styles' );

add_action( 'admin_menu', 'admin_menu' );
add_action( 'admin_init', 'register_settings' );

add_action( 'wp_ajax_osfx-chapters', 'ajax_chapters' );
add_action( 'wp_ajax_osfx-validate', 'ajax_validate' );

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
	register_setting( 'osfx_options', 'osfx_showpad' );
	register_setting( 'osfx_options', 'osfx_affiliations' );
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
		</table>
		<h3>Affiliation</h3>
		Configure your affiliate programs.
		<table class="form-table">        
	        <tr valign="top">
		        <th scope="row">
		        	Affiliations
		        </th>
		        <td>
		        	<table class="podlove_alternating" border="0" cellspacing="0">
		        		<thead>
		        			<tr>
		        				<th>Affiliate Program</th>
		        				<th>Affiliate ID</th>
		        				<th>Delete</th>
		        			</tr>
		        		</thead>
		        		<tbody id="affiliate_program_table_body">

		        		</tbody>
		        	</table>
		        	<input type="button" class="button" id="add_affiliate_program" value="+" />
		        </td>
	        </tr>
	    </table>
	    <?php require('lib/affiliate_programs.php'); ?>
	    <script type="text/template" id="affiliate_line_template">
	    	<tr>
	    		<td>
	    			<select class="chosen affiliate_programs" name="osfx_affiliations[{{counter}}][affiliate_program]">
	    				<option value="">&nbsp;</option>
	    			</select>
	    		</td>
	    		<td>
	    			<input type="text" placeholder="Affiliate ID" value="{{affiliate-id}}" name="osfx_affiliations[{{counter}}][affiliate_id]" />
	    		</td>
	    		<td>
	    			<span class="delete_affiliate_program"></span>
	    		</td>
	    	</tr>
	    </script>
	    <?php 
	    	$existing_affiliations = get_option('osfx_affiliations');
	    	$for_js_existing_affiliation = array();
	    	if ( ! empty($existing_affiliations) )
	    		foreach ($existing_affiliations as $existing_affiliation) {
	    			$for_js_existing_affiliation[$existing_affiliation['affiliate_program']] = $existing_affiliation['affiliate_id'];
	    		}
	    ?>
	    <script type="text/javascript">
	    	var counter = 0;
	    	var affiliate_programs = <?php echo json_encode($affiliate_programs); ?>;
	    	var existing_affiliation = <?php echo json_encode($for_js_existing_affiliation); ?>;

	    	(function($) {
	    	  $( document ).ready( function() {
	    	  	$("#add_affiliate_program").on( 'click', function () {
	    	  		var source = $("#affiliate_line_template").html();
	    	  		source = source.replace( /\{\{affiliate-id\}\}/g, "" );
	    	  		source = source.replace( /\{\{counter\}\}/g, counter );
	    	  		counter++;

	    	  		$("#affiliate_program_table_body").append( source );
	    	  		populate_dropdowns();
	    	  		$(".chosen").chosenImage();

	    	  		$(".delete_affiliate_program").on( 'click', function() {
	    	  			$(this).closest("tr").remove();	
	    	  		} );
	    	  	});

	    	  	$.each( existing_affiliation, function ( id ) {
	    	  		add_affiliation(id);
	    	  		$(".delete_affiliate_program").on( 'click', function() {
	    	  			$(this).closest("tr").remove();	
	    	  		} );
	    	  	});

	    	  	function add_affiliation( id ) {
	    	  		affiliation = affiliate_programs[id];
	    	  		source = $("#affiliate_line_template").html();
	    	  		// Fill in the provided information
	    	  		source = source.replace( /\{\{affiliate-id\}\}/g, existing_affiliation[id] );
	    	  		source = source.replace( /\{\{counter\}\}/g, counter );
	    	  		counter++;
	    	  		// Append new row
	    	  		$("#affiliate_program_table_body").append( source );
	    	  		
	    	  		row = $("#affiliate_program_table_body tr:last");
	    	  		populate_dropdowns();
	    	  		// Select the correct affiliate program
	    	  		row.find('select.affiliate_programs option[value="' + id + '"]').attr('selected',true);
	    	  		$(".chosen").chosenImage();
	    	  	}

	    	    function populate_dropdowns() {
	    	    	$.each( affiliate_programs, function ( id, affiliate_program ) {
	    	    		$(".affiliate_programs").append("<option value='" + id + "' data-img-src='<?php echo plugins_url() ?>/OSFX/img/" + affiliate_program.icon + "'>" + affiliate_program.title +"</option>");
	    	    		
	    	    	});
	    	    }

	    	    populate_dropdowns();
	    	  });
	    	}(jQuery));

	    </script>
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

function ajax_chapters() {
	$chapters = "";

	if ( ! $_POST["source"] )
		return;

	$shownotes = new Shownotes();
	$shownotes->source = $_POST["source"];
	$shownotes->parse();

	foreach ($shownotes->shownotes as $shownote ) {
		if ( $shownote->type == "chapter" )
			$chapters .= date( "H:i:s", $shownote->timestamp ) . " " . $shownote->title . ( $shownote->url ? " <" . urldecode($shownote->url) . ">" : "" ) . "\n";
	}

	respond_with_json( $chapters );
}

function ajax_validate() {
	$errors = "";

	if ( ! $_POST["source"] )
		return;

	$shownotes = new Shownotes();
	$shownotes->source = $_POST["source"];
	$shownotes->parse();
	$shownotes->validate();

	foreach ($shownotes->shownotes as $shownote ) {
		if ( $shownote->isValid )
			continue;

		$errors .= "<tr><td>" . $shownote->line .  "</td><td>" . implode( "<br />", $shownote->errorMessage ) . "</td></tr>\n";
	}

	respond_with_json( $errors );
}

function respond_with_json($result) {
	header( 'Cache-Control: no-cache, must-revalidate' );
	header( 'Expires: Mon, 26 Jul 1997 05:00:00 GMT' );
	header( 'Content-type: application/json' );
	echo json_encode($result);
	die();
}

function save_shownotes( $post_id ) {
	update_post_meta( $post_id, '_shownotes', $_POST['_osfx_shownotes'] );
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

	wp_register_script(
		'majax',
		plugins_url() . '/OSFX/lib/majaX/majax.min.js',
		false
	);
	wp_enqueue_script('majax');

	wp_register_script(
		'osfx_js',
		plugins_url() . '/OSFX/lib/osfx.js',
		false
	);
	wp_enqueue_script('osfx_js');

	wp_register_script(
		'chosen',
		plugins_url() . '/OSFX/lib/chosen/chosen.jquery.min.js',
		false
	);
	wp_enqueue_script('chosen');

	wp_register_script(
		'chosen_image',
		plugins_url() . '/OSFX/lib/chosen/chosenImage.jquery.js',
		false
	);
	wp_enqueue_script('chosen_image');
}

function shownote_box() {
	global $post;

	add_meta_box(
		'shownotes_meta_field',
		'Shownotes',
		function() use ( $post ) {
			?>
				<div class="wrap">
					<h2 class="nav-tab-wrapper">
						<span data-container="source" class="osfx-tab nav-tab nav-tab-active">Source</span>
						<span data-container="chapters" class="osfx-tab nav-tab" id="osfx-chapters-button">Chapters</span>
						<span data-container="validation" class="osfx-tab nav-tab" id="osfx-validate-button">Validation</span>
					</h2>
					<div id="osfx_tabs_wrapper">
						<div id="osfx_source" class="osfx-tab-container osfx-visible">
							<textarea class="large-text" name="_osfx_shownotes" id="_osfx_shownotes" style="height: 200px;"><?php echo get_post_meta( $post->ID, '_shownotes' , TRUE); ?></textarea>

							<?php if ( $showpadid = get_option('osfx_showpad') ) : ?>
							<p>
								<select id="importId"></select>
								<input type="button" class="button" 
									onclick="importShownotes(document.getElementById('_osfx_shownotes'), document.getElementById('importId').value, 'http://cdn.simon.waldherr.eu/projects/showpad-api/getPad/?id=$$$')" 
									value="Import from ShowPad" />
							</p>

							<script type="text/javascript">
								var shownotesname = '<?php echo $showpadid; ?>';
								getPadList(document.getElementById('importId'),shownotesname);
							</script>
							<?php endif; ?>
						</div>

						<div id="osfx_chapters"  class="osfx-tab-container">
							<p id="osfx_chapters_paragraph">
								Spinner!
							</p>
							<p>
								<input type="button" class="button"	id="import_into_publisher_button" value="Import into Podlove Publisher" />
							</p>
						</div>

						<div id="osfx_validation" class="osfx-tab-container ">
							<p>
								<span id="osfx_validation_status">Your Shownotes seem, to be valid.</span>
								<table class="form-table" id="osfx_validation_table">        
							        <tr valign="top">
								        <td>
								        	<table class="podlove_alternating" border="0" cellspacing="0">
								        		<thead>
								        			<tr>
								        				<th>Line</th>
								        				<th>Description</th>
								        			</tr>
								        		</thead>
								        		<tbody id="osfx_validation_table_body" class="code">
								        			<tr>
								        				<td></td>
								        				<td>SPINNER!</td>
								        			</tr>
								        		</tbody>
								        	</table>
								        </td>
							        </tr>
							    </table>
							</p>
						</div>
					</div>
				</div>

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

function template( $args, $template=NULL ) {
	global $post;

	$source = get_post_meta( $post->ID, '_shownotes' , TRUE);
	if ( !$source )
		return;

	if ( $template ) {
		// Replace the WordPress "smart" quotes
		$template = html_entity_decode($template);
		$template = str_replace("“", "\"", $template);
		$template = str_replace("”", "\"", $template);
		$template = str_replace("‘", "\"", $template);
		$template = str_replace("’", "\"", $template);
	} else {
		$template = get_option('osfx_template');
	}

	$shownotes = new Shownotes();
	$shownotes->source = $source;
	$shownotes->parse();

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
	    $shownotes->filter_by_property( "type", $type );
	    return $shownotes->shownotes;
	});
	$twig->addFilter($filtertype);

	// Affiliate the links
	$affiliate = new Twig_SimpleFilter( "affiliate", function ( $shownote ) {
		$shownote->affiliate();
		return $shownote;
	});
	$twig->addFilter($affiliate);

	return $twig->render(
		$template,
			array(
					'shownotes' => $shownotes->shownotes,
					'header' => $shownotes->header
				)
		);

}
add_shortcode( 'shownotes', 'template' );


class Shownote {
	function __construct() {
		$this->type 		= 'note';
		$this->timestamp 	= FALSE;
		$this->title 		= '';
		$this->url			= FALSE;
		$this->tags 		= array();
		$this->revision 	= FALSE;
		$this->level 		= 1;
		$this->shownotes	= array();

		$this->isValid		= TRUE;
		$this->errorMessage	= array();
		$this->line			= 0;
	}

	public function affiliate() {
		if ( ! $this->url )
			return;

		require('lib/affiliate_programs.php');

		$existing_affiliations = get_option('osfx_affiliations');

		if ( empty($existing_affiliations) )
			return;

		foreach ( $existing_affiliations as $existing_affiliation ) {
			if ( strpos( $this->url, $affiliate_programs[$existing_affiliation['affiliate_program']]['url_fragment'] ) === FALSE )
				continue;

			$this->tags[] = 'affiliation';

			$this->url = preg_replace($affiliate_programs[$existing_affiliation['affiliate_program']]['search_fragment'], 
				str_replace( 
						"{{affiliate-id}}", 
						$existing_affiliation['affiliate_id'], 
						$affiliate_programs[$existing_affiliation['affiliate_program']]['replace_fragment']
					), 
				$this->url);
		}
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

		if ( in_array('l', $this->tags) || in_array('link', $this->tags) )
			return 'link';

		if ( in_array('g', $this->tags) || in_array('glossary', $this->tags) )
			return 'glossary';

		if ( in_array('t', $this->tags) || in_array('topic', $this->tags) )
			return 'topic';

		if ( in_array('q', $this->tags) || in_array('quote', $this->tags) )
			return 'quote';

		if ( in_array('r', $this->tags) || in_array('revision', $this->tags) ) {
			$this->revision = true;
			return;
		}		

		return;
	}
}

class Shownotes {
	public $source;

	public function __construct() {
		$this->shownotes = array();
	}

	public function filter_by_property( $property, $value ) {
		$this->shownotes = array_filter($this->shownotes, function ( $shownote ) use ( $property, $value ) {
			if ( $shownote->$property == $value )
				return true;

			return false;
		});
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
					$shownote->errorMessage[] = 'The upper level of items is empty.';
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

	private function parse_contributor_list( $list ) {
		$contributors = array();

		foreach ( explode( "," , $list ) as $contributor_entry_raw ) {
			if ( preg_match( "/([a-z\s]+)[^<]+<(.*)>/i" , $contributor_entry_raw, $contributor ) ) {
				$contributors[] = array( 'name' => trim($contributor[1]), 'url' => trim($contributor[2]));
			} elseif ( !empty( $contributor_entry_raw ) ) {
				$contributors[] = array( 'name' => trim($contributor_entry_raw) );
			}
		}

		return $contributors;
	}

	public function header() {
		$raw_header = $this->header;
		$header = array();

		foreach ( explode( "\n", $raw_header ) as $line ) {
			preg_match( "/([a-z]+):\s(.*)/i", $line, $matched_header_entry ); // [1] var, [2] value
			$header[trim(strtolower($matched_header_entry[1]))] = trim($matched_header_entry[2]);
		}

		$this->header = $header;
		// Convert starttime to date
		if ( $this->header['starttime'] )
			$this->header['starttime'] = strtotime($this->header['starttime']);
		// Convert endtime to date
		if ( $this->header['endtime'] )
			$this->header['endtime'] = strtotime($this->header['endtime']);
		// List Podcast and Shownoter
		if ( $this->header['shownoter'] )
			$this->header['shownoter'] = $this->parse_contributor_list($this->header['shownoter']);
		if ( $this->header['podcaster'] )
			$this->header['podcaster'] = $this->parse_contributor_list($this->header['podcaster']);
	}

	public function parse() {
		// This will be the array filled with shownotes
		$shownotes = array();
		// Dictonary containing all reserved categories
		$reserved_categories = array( 
					'c' => 'chapter',
					'l' => 'link',
					'g' => 'glossary',
					't' => 'topic',
					'q' => 'quote'
				);
		// Indicators
		$linenumber 			= 0;
		$shownote_id 			= 0;
		$initial_unix_timestamp = 0;

		// Remove the Header here. It is not needed for parsing the shownotes.
		if( $header_closure_position = strpos($this->source, '/HEADER') ) {
		    $linenumber = substr_count($this->source, "\n", 0, $header_closure_position) + 1; // Adjusting the linenumber.
		    $this->header = substr( $this->source, 8, strpos($this->source, '/HEADER') - 9 );
		    $this->source = substr( $this->source, strpos($this->source, '/HEADER') + 7 );
		    $this->header();
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
			// Remove the tags from the line.
			foreach ( $tags[0] as $tag ) {
				$line = $this->remove_from_line( $line, $tag );
			}
			foreach ( $tags[1] as $tagkey => $tag ) {
				if ( isset($reserved_categories[$tag]) )
						$tags[1][$tagkey] = $reserved_categories[$tag];
			}

			$shownote->tags = $tags[1]; // Second element in array contains the tags.
			// With respect to the tags, set the type.
			$shownote->set_type();
			// @validation: If first entry is not of type "chapter", there should be no chapter entries at all
			if ( $shownote->type == 'chapter' && isset($this->shownotes[0]) 
					&& $this->shownotes[0]->type !== 'chapter' 
					&& !in_array( "Your first entry is not a chapter, but chapters are being used.", $this->shownotes[0]->errorMessage ) ) {
				$this->shownotes[0]->isValid = FALSE;
				$this->shownotes[0]->errorMessage[] = 'Your first entry is not a chapter, but chapters are being used.';
			}
			// Check for URLs.
			preg_match_all('/\s+<(.*)>/i', $line, $url );
			if ( isset( $url[1][0] ) && isset( $url[0][0] ) ) {
				// @validation: Shownotes containes multiple URLs.
				if ( count($url[1]) > 1 || strrpos($url[1][0], " ") ) {
					$shownote->isValid = FALSE;
					$shownote->errorMessage[] = 'Shownote contains multiple URLs or an unescaped "<".';
				}
				$line = $this->remove_from_line( $line, $url[0][0] );
				// @validation: Missing < must be escaped or closed.
				if ( strrpos($url[1][0], "<") ) {
					$shownote->isValid = FALSE;
					$shownote->errorMessage[] = 'Shownote contains "<" that needs to be escaped or a closed.';
				}
				$shownote->url = urlencode($url[1][0]); // There are no invalid URLs
			}
			// Fetch the timestamps.
			preg_match('/^([0-9|:|.]+)/i', $line, $timestamp);
			if ( isset( $timestamp[0] ) ) {
				$timestamp_in_unix_format = strtotime( ( $this->isValidTimeStamp($timestamp[0]) ? '@' : '' ).$timestamp[0]); // Need to check for specific unix date!
				if ( $initial_unix_timestamp == 0 ) {
					$initial_unix_timestamp = $timestamp_in_unix_format;
				}
				$shownote->timestamp = $timestamp_in_unix_format - $initial_unix_timestamp;
				$line = $this->remove_from_line( $line, $timestamp[0] );
			}
			// Fetch the level.
			preg_match('/^[-][\s|-]+/i', trim($line), $hierachie);
			if ( isset( $hierachie[0] ) ) {
				$line = $this->remove_from_line( $line, $hierachie[0] );
				$level = substr_count($hierachie[0], '-') + 1;
				$shownote->level = ( $level > 2 ? 2 : $level ); // For any level depth higher than two, we set it to two.
			}
			// The rest will be the title of the line.
			$shownote->title = trim($line);
			$shownote->unescape_title_chars();
			$shownote->title = htmlspecialchars($shownote->title);

			$this->shownotes[] = $shownote;

			$linenumber++;
		}
	}

	public function validate() {

		
		
	}

	private function isValidTimeStamp($timestamp) {
	// From https://stackoverflow.com/questions/2524680/check-whether-the-string-is-a-unix-timestamp
    	return ((string) (int) $timestamp === $timestamp) 
        	&& ($timestamp <= PHP_INT_MAX)
        	&& ($timestamp >= ~PHP_INT_MAX);
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