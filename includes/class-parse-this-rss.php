<?php
/**
 * Helpers for Turning RSS/Atom into JF2
**/

class Parse_This_RSS {

	/*
	 * Parse RSS/Atom into JF2
	 *
	 * @param SimplePie $feed
	 * @return JF2 array
	 */
	public static function parse( $feed, $url ) {
		$items     = array();
		$rss_items = $feed->get_items();
		$title     = $feed->get_title();
		foreach ( $rss_items as $item ) {
			$items[] = self::get_item( $item, $title );
		}
		return array_filter(
			array(
				'type'       => 'feed',
				'_feed_type' => self::get_type( $feed ),
				'summary'    => $feed->get_description(),
				'author'     => self::get_authors( $feed->get_author() ),
				'name'       => htmlspecialchars_decode( $title, ENT_QUOTES ),
				'url'        => $feed->get_permalink(),
				'photo'      => $feed->get_image_url(),
				'items'      => $items,
			)
		);
	}

	public static function get_type( $feed ) {
		if ( $feed->get_type() & SIMPLEPIE_TYPE_NONE ) {
			return 'unknown';
		} elseif ( $feed->get_type() & SIMPLEPIE_TYPE_RSS_ALL ) {
			return 'RSS';
		} elseif ( $feed->get_type() & SIMPLEPIE_TYPE_ATOM_ALL ) {
			return 'atom';
		}
	}

	/*
	 * Takes a SimplePie_Author object and Turns it into a JF2 Author property
	 * @param SimplePie_Author $author
	 * @return JF2 array
	 */
	public static function get_authors( $author ) {
		if ( ! $author ) {
			return array();
		}
		if ( $author instanceof SimplePie_Author ) {
			$author = array( $author );
		}
		$return = array();
		foreach ( $author as $a ) {
			$r   = array(
				'type'  => 'card',
				'name'  => htmlspecialchars_decode( $a->get_name() ),
				'url'   => $a->get_link(),
				'email' => $a->get_email(),
			);
			$dom = new DOMDocument();
			$dom->loadHTML( $r['name'] );
			$links = $dom->getElementsByTagName( 'a' );
			$names = array();
			foreach ( $links as $link ) {
					$names[ wp_strip_all_tags( $link->nodeValue ) ] = $link->getAttribute( 'href' );
			}
			if ( ! empty( $names ) ) {
				if ( 1 === count( $names ) ) {
					reset( $names );
					$r['name'] = key( $names );
				} else {
					foreach ( $names as $name => $url ) {
						$return[] = array(
							'type' => 'card',
							'name' => $name,
							'url'  => $url,
						);
					}
				}
			} else {
				$r['name'] = wp_strip_all_tags( $r['name'] );
				$return[]  = array_filter( $r );
			}
		}
		if ( 1 === count( $return ) ) {
			$return = array_shift( $return );
		}
		return $return;
	}

	/*
	 * Takes a SimplePie_Item object and Turns it into a JF2 entry
	 * @param SimplePie_Item $item
	 * @return JF2
	 */
	public static function get_item( $item, $title = '' ) {
		$return     = array(
			'type'        => 'entry',
			'name'        => $item->get_title(),
			'author'      => self::get_authors( $item->get_authors() ),
			'publication' => $title,
			'summary'     => wp_strip_all_tags( $item->get_description( true ) ),
			'content'     => array_filter(
				array(
					'html' => parse_this_clean_content( $item->get_content( true ) ),
					'text' => wp_strip_all_tags( htmlspecialchars_decode( $item->get_content( true ) ) ),
				)
			),
			'published'   => $item->get_date( DATE_W3C ),
			'updated'     => $item->get_updated_date( DATE_W3C ),
			'url'         => $item->get_permalink(),
			'uid'         => $item->get_id(),
			'location'    => self::get_location( $item ),
			'category'    => self::get_categories( $item->get_categories() ),
			'featured'    => $item->get_thumbnail(),
		);
		$enclosures = $item->get_enclosures();
		foreach ( $enclosures as $enclosure ) {
			$medium = $enclosure->get_type();
			if ( ! $medium ) {
				$medium = $enclosure->get_medium();
			} else {
				$medium = explode( '/', $medium );
				$medium = array_shift( $medium );
			}
			switch ( $medium ) {
				case 'audio':
					$medium = 'audio';
					break;
				case 'image':
					$medium = 'photo';
					break;
				case 'video':
					$medium = 'video';
					break;
			}
			if ( array_key_exists( $medium, $return ) ) {
				if ( is_string( $return[ $medium ] ) ) {
					$return[ $medium ] = array( $return[ $medium ] );
				}
				$return[ $medium ][] = $enclosure->get_link();
			} else {
				$return[ $medium ] = $enclosure->get_link();
			}
		}
		// If there is just one photo it is probably the featured image
		if ( isset( $return['photo'] ) && is_string( $return['photo'] ) && empty( $return['featured'] ) ) {
			$return['featured'] = $return['photo'];
			unset( $return['photo'] );
		}
		$return['post_type'] = post_type_discovery( $return );
		return array_filter( $return );
	}

	private static function get_categories( $categories ) {
		if ( ! is_array( $categories ) ) {
			return array();
		}
		$return = array();
		foreach ( $categories as $category ) {
			$return[] = $category->get_label();
		}
		return $return;
	}

	private static function get_location_name( $item ) {
		$return = $item->get_item_tags( SIMPLEPIE_NAMESPACE_GEORSS, 'featureName' );
		if ( $return ) {
			return $return[0]['data'];
		}
	}


	public static function get_location( $item ) {
		return array_filter(
			array(
				'latitude'  => $item->get_latitude(),
				'longitude' => $item->get_longitude(),
				'name'      => self::get_location_name( $item ),
			)
		);
	}


}
