<?php

$beginn = microtime(true); 

$source = ( isset( $_POST['shownotes'] ) ? $_POST['shownotes'] : '' );

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
                        $shownote->tags[] = substr( $string, 1 );
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

function has_subelements( $key, $shownotes) {
    $subelements = array();
    foreach ( $shownotes as $shownote_key => $shownote_object ) {
        if ( isset( $shownote_object->parent ) && $shownote_object->parent == $key )
            $subelements[] = $shownote_key;
    }
    return ( empty( $subelements ) ? $subelements : FALSE );
}

if ( isset( $shownotes ) ) {

    // Let children objects be children objects ;-)
    foreach ( $shownotes as $shownote_key => $shownote_object ) {
        if ( is_null( $shownote_object->parent ) )
            continue;

        $subelements = has_subelements( $shownote_key, $shownotes );
        if ( !$subelements && !is_null( $shownote_object->parent ) ) {
            $shownotes[$shownote_object->parent]->children[] = $shownote_object;
        } else {
            foreach ( $subelements as $subelement_key => $subelement ) {
                $shownote_object->children[] = $subelement;
            }

        }
    }

    // Clean up the Shownote Object array
    foreach ( $shownotes as $shownote_key => $shownote_object ) {
        if ( $shownote_object->parent > 0 )
            unset($shownotes[$shownote_key]);
    }

    $convert2ul = function ($list, $next) use (&$convert2ul, $first_timestamp) {
        $timestamp = ( is_object( $next->timestamp ) ? $next->timestamp->value : NULL );

        if ( !is_null( $timestamp ) ) {
            if ( strlen( $timestamp ) == 10 )
                $timestamp = gmdate( 'H:i:s.u', $timestamp - $first_timestamp );
        }
        $renderedElement = '<li>' . ( isset($next->link->value) ? '<a href="' . $next->link->value . '">' . stripslashes( $next->title->value ) . '</a>' : stripslashes( $next->title->value ) )
        . ( !is_null($timestamp) ? '(Timestamp: ' . $timestamp . ')' : NULL )
        . ( !empty($next->tags) ? '(Verschlagwortet mit: ' . implode(', ', $next->tags) . ')' : NULL ) . '';
        if ( isset( $next->children[0] ) && count($next->children[0]) > 0) {
            $renderedElement .= '<ul>' . array_reduce($next->children, $convert2ul, '') . '</ul>';
        }
        $list .= $renderedElement . '</li>';
        return $list;
        };
    $ul = array_reduce($shownotes, $convert2ul, '');

}

?>


<!DOCTYPE html>
<html>
<head>
    <title>Shownote test</title>
    <meta charset="UTF-8" />
</head>
<body>

<form method="POST">
    <textarea name="shownotes" rows="22" cols="122"><?php echo ( isset( $_POST['shownotes'] ) ? stripslashes( $_POST['shownotes'] ) : NULL ); ?></textarea>
    <input type="submit" value="Parse!" />
</form>

<?php
 
echo '<ul>' . ( isset( $ul ) ? mb_convert_encoding($ul, 'HTML-ENTITIES', "UTF-8") : NULL ) . '</ul>';

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

$dauer = microtime(true) - $beginn; 
echo "Verarbeitung des Skripts: $dauer Sek.";

?>

</body>
</html>