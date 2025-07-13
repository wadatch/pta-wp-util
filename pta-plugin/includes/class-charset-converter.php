<?php
/**
 * Charset Converter Class
 *
 * @package PTA_Plugin
 */

namespace PTA_Plugin;

// Direct access protection
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Charset_Converter
 * Handles character conversion for UTF-8 3-byte limitation
 */
class Charset_Converter {
	/**
	 * 4-byte UTF-8 characters that cause issues in utf8 (not utf8mb4) databases
	 * Maps problematic characters to HTML entity references
	 */
	private static $conversion_table = array(
		// Emoji - People & Body
		'😀' => '&#128512;', // grinning face
		'😁' => '&#128513;', // beaming face with smiling eyes
		'😂' => '&#128514;', // face with tears of joy
		'🤣' => '&#129315;', // rolling on the floor laughing
		'😃' => '&#128515;', // grinning face with big eyes
		'😄' => '&#128516;', // grinning face with smiling eyes
		'😅' => '&#128517;', // grinning face with sweat
		'😆' => '&#128518;', // grinning squinting face
		'😉' => '&#128521;', // winking face
		'😊' => '&#128522;', // smiling face with smiling eyes
		'😋' => '&#128523;', // face savoring food
		'😎' => '&#128526;', // smiling face with sunglasses
		'😍' => '&#128525;', // smiling face with heart-eyes
		'😘' => '&#128536;', // face blowing a kiss
		'🥰' => '&#129392;', // smiling face with hearts
		'😗' => '&#128535;', // kissing face
		'😙' => '&#128537;', // kissing face with smiling eyes
		'😚' => '&#128538;', // kissing face with closed eyes
		'🙂' => '&#128578;', // slightly smiling face
		'🤗' => '&#129303;', // hugging face
		'🤩' => '&#129321;', // star-struck
		'🤔' => '&#129300;', // thinking face
		'🤨' => '&#129320;', // face with raised eyebrow
		'😐' => '&#128528;', // neutral face
		'😑' => '&#128529;', // expressionless face
		'😶' => '&#128566;', // face without mouth
		'🙄' => '&#128580;', // face with rolling eyes
		'😏' => '&#128527;', // smirking face
		'😣' => '&#128547;', // persevering face
		'😥' => '&#128549;', // sad but relieved face
		'😮' => '&#128558;', // face with open mouth
		'🤐' => '&#129296;', // zipper-mouth face
		'😯' => '&#128559;', // hushed face
		'😪' => '&#128554;', // sleepy face
		'😫' => '&#128555;', // tired face
		'😴' => '&#128564;', // sleeping face
		'😌' => '&#128524;', // relieved face
		'😛' => '&#128539;', // face with tongue
		'😜' => '&#128540;', // winking face with tongue
		'😝' => '&#128541;', // squinting face with tongue
		'🤤' => '&#129316;', // drooling face
		'😒' => '&#128530;', // unamused face
		'😓' => '&#128531;', // downcast face with sweat
		'😔' => '&#128532;', // pensive face
		'😕' => '&#128533;', // confused face
		'🙃' => '&#128579;', // upside-down face
		'🤑' => '&#129297;', // money-mouth face
		'😲' => '&#128562;', // astonished face
		'🙁' => '&#128577;', // slightly frowning face
		'😖' => '&#128534;', // confounded face
		'😞' => '&#128542;', // disappointed face
		'😟' => '&#128543;', // worried face
		'😤' => '&#128548;', // face with steam from nose
		'😢' => '&#128546;', // crying face
		'😭' => '&#128557;', // loudly crying face
		'😦' => '&#128550;', // frowning face with open mouth
		'😧' => '&#128551;', // anguished face
		'😨' => '&#128552;', // fearful face
		'😩' => '&#128553;', // weary face
		'🤯' => '&#129327;', // exploding head
		'😬' => '&#128556;', // grimacing face
		'😰' => '&#128560;', // anxious face with sweat
		'😱' => '&#128561;', // face screaming in fear
		'🥵' => '&#129397;', // hot face
		'🥶' => '&#129398;', // cold face
		'😳' => '&#128563;', // flushed face
		'🤪' => '&#129322;', // zany face
		'😵' => '&#128565;', // dizzy face
		'🥴' => '&#129396;', // woozy face
		'😷' => '&#128567;', // face with medical mask
		'🤒' => '&#129298;', // face with thermometer
		'🤕' => '&#129301;', // face with head-bandage
		'🤢' => '&#129314;', // nauseated face
		'🤮' => '&#129326;', // face vomiting
		'🤧' => '&#129319;', // sneezing face
		'😇' => '&#128519;', // smiling face with halo
		'🥳' => '&#129395;', // partying face
		'🥺' => '&#129402;', // pleading face
		'🤠' => '&#129312;', // cowboy hat face
		'🤡' => '&#129313;', // clown face
		'🤥' => '&#129317;', // lying face
		'🤫' => '&#129323;', // shushing face
		'🤭' => '&#129325;', // face with hand over mouth
		'🧐' => '&#129488;', // face with monocle
		'🤓' => '&#129299;', // nerd face

		// Hand gestures
		'👍' => '&#128077;', // thumbs up
		'👎' => '&#128078;', // thumbs down
		'👌' => '&#128076;', // OK hand
		'✌️' => '&#9996;',   // victory hand
		'🤞' => '&#129310;', // crossed fingers
		'🤟' => '&#129311;', // love-you gesture
		'🤘' => '&#129304;', // sign of the horns
		'🤙' => '&#129305;', // call me hand
		'👈' => '&#128072;', // backhand index pointing left
		'👉' => '&#128073;', // backhand index pointing right
		'👆' => '&#128070;', // backhand index pointing up
		'🖕' => '&#128405;', // middle finger
		'👇' => '&#128071;', // backhand index pointing down
		'☝️' => '&#9757;',   // index pointing up
		'👋' => '&#128075;', // waving hand
		'🤚' => '&#129306;', // raised back of hand
		'🖐️' => '&#128400;', // hand with fingers splayed
		'✋' => '&#9995;',   // raised hand
		'🖖' => '&#128406;', // vulcan salute
		'👏' => '&#128079;', // clapping hands
		'🙌' => '&#128588;', // raising hands
		'👐' => '&#128080;', // open hands
		'🤲' => '&#129330;', // palms up together
		'🤝' => '&#129309;', // handshake
		'🙏' => '&#128591;', // folded hands

		// Hearts and symbols
		'❤️' => '&#10764;',  // red heart
		'🧡' => '&#129505;', // orange heart
		'💛' => '&#128155;', // yellow heart
		'💚' => '&#128154;', // green heart
		'💙' => '&#128153;', // blue heart
		'💜' => '&#128156;', // purple heart
		'🖤' => '&#128420;', // black heart
		'🤍' => '&#129293;', // white heart
		'🤎' => '&#129294;', // brown heart
		'💔' => '&#128148;', // broken heart
		'❣️' => '&#10083;',  // heavy heart exclamation
		'💕' => '&#128149;', // two hearts
		'💞' => '&#128158;', // revolving hearts
		'💓' => '&#128147;', // beating heart
		'💗' => '&#128151;', // growing heart
		'💖' => '&#128150;', // sparkling heart
		'💘' => '&#128152;', // heart with arrow
		'💝' => '&#128157;', // heart with ribbon
		'💟' => '&#128159;', // heart decoration

		// Mathematical and special symbols
		'∞' => '&#8734;',   // infinity
		'π' => '&#960;',    // pi
		'√' => '&#8730;',   // square root
		'∑' => '&#8721;',   // summation
		'∆' => '&#8710;',   // increment
		'Ω' => '&#937;',    // omega
		'α' => '&#945;',    // alpha
		'β' => '&#946;',    // beta
		'γ' => '&#947;',    // gamma
		'δ' => '&#948;',    // delta
		'ε' => '&#949;',    // epsilon
		'θ' => '&#952;',    // theta
		'λ' => '&#955;',    // lambda
		'μ' => '&#956;',    // mu
		'σ' => '&#963;',    // sigma
		'φ' => '&#966;',    // phi
		'χ' => '&#967;',    // chi
		'ψ' => '&#968;',    // psi

		// Currency and special characters
		'€' => '&#8364;',   // euro
		'£' => '&#163;',    // pound
		'¥' => '&#165;',    // yen
		'₹' => '&#8377;',   // indian rupee
		'₩' => '&#8361;',   // won
		'₽' => '&#8381;',   // ruble
		'©' => '&#169;',    // copyright
		'®' => '&#174;',    // registered
		'™' => '&#8482;',   // trademark
		'°' => '&#176;',    // degree
		'±' => '&#177;',    // plus-minus
		'×' => '&#215;',    // multiplication
		'÷' => '&#247;',    // division
		'≠' => '&#8800;',   // not equal
		'≤' => '&#8804;',   // less than or equal
		'≥' => '&#8805;',   // greater than or equal
		'≈' => '&#8776;',   // approximately equal
		'∈' => '&#8712;',   // element of
		'∉' => '&#8713;',   // not element of
		'∩' => '&#8745;',   // intersection
		'∪' => '&#8746;',   // union
		'⊂' => '&#8834;',   // subset of
		'⊃' => '&#8835;',   // superset of
		'⊆' => '&#8838;',   // subset of or equal
		'⊇' => '&#8839;',   // superset of or equal
		'∀' => '&#8704;',   // for all
		'∃' => '&#8707;',   // there exists
		'∄' => '&#8708;',   // there does not exist
		'∧' => '&#8743;',   // logical and
		'∨' => '&#8744;',   // logical or
		'¬' => '&#172;',    // logical not
		'→' => '&#8594;',   // rightwards arrow
		'←' => '&#8592;',   // leftwards arrow
		'↑' => '&#8593;',   // upwards arrow
		'↓' => '&#8595;',   // downwards arrow
		'↔' => '&#8596;',   // left right arrow
		'⇒' => '&#8658;',   // rightwards double arrow
		'⇐' => '&#8656;',   // leftwards double arrow
		'⇑' => '&#8657;',   // upwards double arrow
		'⇓' => '&#8659;',   // downwards double arrow
		'⇔' => '&#8660;',   // left right double arrow
	);

	/**
	 * Convert problematic characters to HTML entities
	 *
	 * @param string $text Text to convert.
	 * @return string Converted text.
	 */
	public static function convert_to_entities( $text ) {
		if ( empty( $text ) ) {
			return $text;
		}

		// Apply custom conversion table
		$converted = str_replace( array_keys( self::$conversion_table ), array_values( self::$conversion_table ), $text );

		// Also handle any remaining 4-byte UTF-8 characters
		$converted = self::convert_remaining_4byte_chars( $converted );

		return $converted;
	}

	/**
	 * Convert HTML entities back to original characters
	 *
	 * @param string $text Text with HTML entities.
	 * @return string Converted text.
	 */
	public static function convert_from_entities( $text ) {
		if ( empty( $text ) ) {
			return $text;
		}

		// Convert our custom entities back
		$converted = str_replace( array_values( self::$conversion_table ), array_keys( self::$conversion_table ), $text );

		// Also decode standard HTML entities
		$converted = html_entity_decode( $converted, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return $converted;
	}

	/**
	 * Check if text contains 4-byte UTF-8 characters
	 *
	 * @param string $text Text to check.
	 * @return bool True if contains 4-byte chars.
	 */
	public static function contains_4byte_chars( $text ) {
		// Check for 4-byte UTF-8 sequences (emojis, etc.)
		return preg_match( '/[\x{10000}-\x{10FFFF}]/u', $text ) === 1;
	}

	/**
	 * Convert remaining 4-byte UTF-8 characters to numeric HTML entities
	 *
	 * @param string $text Text to convert.
	 * @return string Converted text.
	 */
	private static function convert_remaining_4byte_chars( $text ) {
		// Convert any remaining 4-byte UTF-8 characters to numeric entities
		return preg_replace_callback(
			'/[\x{10000}-\x{10FFFF}]/u',
			function( $matches ) {
				$char = $matches[0];
				$codepoint = self::utf8_to_codepoint( $char );
				return '&#' . $codepoint . ';';
			},
			$text
		);
	}

	/**
	 * Convert UTF-8 character to Unicode code point
	 *
	 * @param string $char UTF-8 character.
	 * @return int Unicode code point.
	 */
	private static function utf8_to_codepoint( $char ) {
		$bytes = unpack( 'C*', $char );
		$code = 0;

		if ( count( $bytes ) === 1 ) {
			// 1-byte character (ASCII)
			$code = $bytes[1];
		} elseif ( count( $bytes ) === 2 ) {
			// 2-byte character
			$code = ( ( $bytes[1] & 0x1F ) << 6 ) | ( $bytes[2] & 0x3F );
		} elseif ( count( $bytes ) === 3 ) {
			// 3-byte character
			$code = ( ( $bytes[1] & 0x0F ) << 12 ) | ( ( $bytes[2] & 0x3F ) << 6 ) | ( $bytes[3] & 0x3F );
		} elseif ( count( $bytes ) === 4 ) {
			// 4-byte character
			$code = ( ( $bytes[1] & 0x07 ) << 18 ) | ( ( $bytes[2] & 0x3F ) << 12 ) | ( ( $bytes[3] & 0x3F ) << 6 ) | ( $bytes[4] & 0x3F );
		}

		return $code;
	}

	/**
	 * Get the conversion table
	 *
	 * @return array Conversion table.
	 */
	public static function get_conversion_table() {
		return self::$conversion_table;
	}

	/**
	 * Add custom character conversion
	 *
	 * @param string $char Original character.
	 * @param string $entity HTML entity.
	 */
	public static function add_conversion( $char, $entity ) {
		self::$conversion_table[ $char ] = $entity;
	}

	/**
	 * Remove character conversion
	 *
	 * @param string $char Character to remove.
	 */
	public static function remove_conversion( $char ) {
		if ( isset( self::$conversion_table[ $char ] ) ) {
			unset( self::$conversion_table[ $char ] );
		}
	}

	/**
	 * Check if conversion is enabled for the site
	 *
	 * @return bool True if conversion is enabled.
	 */
	public static function is_conversion_enabled() {
		return get_option( 'pta_charset_conversion_enabled', true );
	}

	/**
	 * Get database charset
	 *
	 * @return string Database charset.
	 */
	public static function get_database_charset() {
		global $wpdb;
		
		$charset = $wpdb->get_var( "SELECT @@character_set_database" );
		return $charset ? $charset : 'unknown';
	}

	/**
	 * Check if database needs charset conversion
	 *
	 * @return bool True if conversion is needed.
	 */
	public static function needs_conversion() {
		$charset = self::get_database_charset();
		
		// UTF8 (3-byte) needs conversion, UTF8MB4 (4-byte) doesn't
		return ( $charset === 'utf8' || strpos( $charset, 'utf8' ) === 0 ) && strpos( $charset, 'utf8mb4' ) === false;
	}

	/**
	 * Convert text for database storage
	 *
	 * @param string $text Text to convert.
	 * @return string Converted text safe for database.
	 */
	public static function prepare_for_database( $text ) {
		if ( ! self::is_conversion_enabled() || ! self::needs_conversion() ) {
			return $text;
		}

		return self::convert_to_entities( $text );
	}

	/**
	 * Convert text from database for display
	 *
	 * @param string $text Text from database.
	 * @return string Converted text for display.
	 */
	public static function prepare_for_display( $text ) {
		if ( ! self::is_conversion_enabled() ) {
			return $text;
		}

		return self::convert_from_entities( $text );
	}
}