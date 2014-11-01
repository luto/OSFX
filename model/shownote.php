<?php

namespace OSFX\Model;

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

		$existing_affiliations = get_option('osfx_affiliations');

		if ( empty($existing_affiliations) )
			return;

		foreach ( $existing_affiliations as $existing_affiliation ) {
			if ( strpos( $this->url, \OSFX\Constants::affiliate_programs[$existing_affiliation['affiliate_program']]['url_fragment'] ) === FALSE )
				continue;

			$this->tags[] = 'affiliation';

			if ( strpos( $this->url, \OSFX\Constants::affiliate_programs[$existing_affiliation['affiliate_program']]['existing_affiliation_identifier'] ) === FALSE ) {
				$this->url = preg_replace(\OSFX\Constants::affiliate_programs[$existing_affiliation['affiliate_program']]['search_fragment'], 
					str_replace( 
							"{{affiliate-id}}", 
							$existing_affiliation['affiliate_id'], 
							\OSFX\Constants::affiliate_programs[$existing_affiliation['affiliate_program']]['replace_fragment']
						), 
					$this->url);	
			} else {
				$this->url = preg_replace( 
							sprintf( "/%s([^$&]+)($|&)/", str_replace( "/", "\/", str_replace("{{affiliate-id}}", "", \OSFX\Constants::affiliate_programs[$existing_affiliation['affiliate_program']]['existing_affiliation_regexp']) ) ),
							sprintf( "%s$2$3", str_replace("{{affiliate-id}}", $existing_affiliation['affiliate_id'], \OSFX\Constants::affiliate_programs[$existing_affiliation['affiliate_program']]['existing_affiliation_regexp']) ),
							$this->url
						);
			}
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
?>