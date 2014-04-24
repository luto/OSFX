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

add_action( 'init', 'activate' );

function activate() {
	$loader = new Twig_Loader_String();
	$twig = new Twig_Environment($loader);

	echo $twig->render(
		'Hello {% for name in names %} {{ name.sure }} {% endfor %} !',
		 array(
		 	'names' => array( 
		 						array(
		 							'sure' => 'Alex',
		 							'last' => 'Lueken'
		 							),
		 						array(
		 							'sure' => 'Ale2x',
		 							'last' => 'Lueke2n'
		 							)
		 					)
		 	)
		);
}

?>