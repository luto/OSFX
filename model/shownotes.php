<?php

namespace OSFX\Model;

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
				$shownote->url = $url[1][0];
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
		foreach ($this->shownotes as $shownote) {
			// Check for invalid URLs
			if ( $shownote->url && ! filter_var($shownote->url, FILTER_VALIDATE_URL) ) {
				$shownote->isValid = FALSE;
				$shownote->errorMessage[] = 'Shownote contains an invalid URL.';
			}
		}
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