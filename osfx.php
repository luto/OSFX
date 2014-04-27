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


function save_shownotes( $post_id ) {
	update_post_meta( $post_id, 'osfx_shownotes', $_POST['_osfx_shownotes'] );
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
		   "<div class='osfx-shownote-block'>
			   {% for shownote in shownotes %}
			   {% if 'chapter' in shownote.tags %}
			   		</div>
			   			<h2 class='osfx-chapter'>
			   				{{ block('title') }}
			   				<span class='osfx-timestamp'>{{ shownote.timestamp.value }}</span>
			   			</h2>
			   		<div class='osfx-shownote-block'>
			    {% else %}
			  	    <span class='osfx-shownote-item {{ shownote.tags|join(' ') }}'>
			   		 	{{ block('title') }}
					</span>
				{% endif %}
				{% endfor %}
			</div>

			{% block title %}
				{% if shownote.link.value %}
				<a href=\"{{ shownote.link.value }}\">{{ shownote.title.value|trim }}</a>
				{% else %}
					{{ shownote.title.value|trim }}
				{% endif %}
			{% endblock %}",
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

	                        while ( $previous_shownote_key >= 0 ) {
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

	        $shownotes[] = $shownote;
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

    function __construct() {
        $this->value    = NULL;
        $this->start    = NULL;
        $this->end      = NULL;
    }

}

?>