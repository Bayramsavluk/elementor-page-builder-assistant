<?php
/**
 * Translation functionality
 *
 * @package Elementor_Bulk_Importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Translator class
 */
class EBI_Translator {

	/**
	 * Instance
	 *
	 * @var EBI_Translator
	 */
	private static $instance = null;

	/**
	 * Get instance
	 *
	 * @return EBI_Translator
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Translate text using selected API
	 *
	 * @param string $text Text to translate.
	 * @param string $target_lang Target language code (e.g., 'tr', 'en').
	 * @param string $api_id API ID to use.
	 * @return string|WP_Error Translated text or error.
	 */
	public function translate( $text, $target_lang = 'tr', $api_id = 'mymemory' ) {
		if ( empty( $text ) ) {
			return $text;
		}

		$translation_settings = get_option( 'ebi_translation_settings', array() );
		
		// Check if API is enabled
		if ( ! isset( $translation_settings['apis'][ $api_id ]['enabled'] ) || ! $translation_settings['apis'][ $api_id ]['enabled'] ) {
			return new \WP_Error( 'api_disabled', __( 'Seçilen çeviri API\'si aktif değil.', 'elementor-bulk-importer' ) );
		}

		$api_key = isset( $translation_settings['apis'][ $api_id ]['key'] ) ? $translation_settings['apis'][ $api_id ]['key'] : '';

		switch ( $api_id ) {
			case 'mymemory':
				return $this->translate_mymemory( $text, $target_lang, $api_key );
			
			case 'libretranslate':
				return $this->translate_libretranslate( $text, $target_lang, $api_key );
			
			case 'deepl':
				return $this->translate_deepl( $text, $target_lang, $api_key );
			
			case 'microsoft':
				return $this->translate_microsoft( $text, $target_lang, $api_key );
			
			case 'yandex':
				return $this->translate_yandex( $text, $target_lang, $api_key );
			
			case 'argostranslate':
				return $this->translate_argostranslate( $text, $target_lang, $api_key );
			
			default:
				return new \WP_Error( 'unknown_api', __( 'Bilinmeyen çeviri API\'si.', 'elementor-bulk-importer' ) );
		}
	}

	/**
	 * Translate using MyMemory Translation API
	 *
	 * @param string $text Text to translate.
	 * @param string $target_lang Target language code.
	 * @param string $api_key API key (optional).
	 * @return string|WP_Error Translated text or error.
	 */
	private function translate_mymemory( $text, $target_lang, $api_key = '' ) {
		// Detect source language (assume English if not Turkish)
		$source_lang = 'en';
		if ( preg_match( '/[ığüşöçİĞÜŞÖÇ]/u', $text ) ) {
			$source_lang = 'tr';
		}

		$url = 'https://api.mymemory.translated.net/get';
		$params = array(
			'q'      => $text,
			'langpair' => $source_lang . '|' . $target_lang,
		);

		if ( ! empty( $api_key ) ) {
			$params['key'] = $api_key;
		}

		$url .= '?' . http_build_query( $params );

		$response = wp_remote_get( $url, array(
			'timeout' => 30,
			'sslverify' => true,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! $data || ! isset( $data['responseData'] ) || ! isset( $data['responseData']['translatedText'] ) ) {
			$error_message = isset( $data['responseStatus'] ) ? $data['responseStatus'] : __( 'Çeviri hatası', 'elementor-bulk-importer' );
			return new \WP_Error( 'translation_error', $error_message );
		}

		// Decode HTML entities and fix Unicode characters
		$translated_text = $data['responseData']['translatedText'];
		$translated_text = html_entity_decode( $translated_text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		
		return $translated_text;
	}

	/**
	 * Translate using LibreTranslate API
	 *
	 * @param string $text Text to translate.
	 * @param string $target_lang Target language code.
	 * @param string $api_url API URL (optional, defaults to public instance).
	 * @return string|WP_Error Translated text or error.
	 */
	private function translate_libretranslate( $text, $target_lang, $api_url = '' ) {
		$source_lang = 'en';
		if ( preg_match( '/[ığüşöçİĞÜŞÖÇ]/u', $text ) ) {
			$source_lang = 'tr';
		}

		$url = ! empty( $api_url ) ? $api_url : 'https://libretranslate.com/translate';
		
		$body = array(
			'q' => $text,
			'source' => $source_lang,
			'target' => $target_lang,
			'format' => 'text',
		);

		$response = wp_remote_post( $url, array(
			'timeout' => 30,
			'body' => json_encode( $body ),
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'sslverify' => true,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_body = wp_remote_retrieve_body( $response );
		$data = json_decode( $response_body, true );

		if ( ! $data || ! isset( $data['translatedText'] ) ) {
			return new \WP_Error( 'translation_error', __( 'LibreTranslate çeviri hatası', 'elementor-bulk-importer' ) );
		}

		// Decode HTML entities and fix Unicode characters
		$translated_text = $data['translatedText'];
		$translated_text = html_entity_decode( $translated_text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		
		return $translated_text;
	}

	/**
	 * Translate using DeepL API
	 *
	 * @param string $text Text to translate.
	 * @param string $target_lang Target language code.
	 * @param string $api_key API key (required).
	 * @return string|WP_Error Translated text or error.
	 */
	private function translate_deepl( $text, $target_lang, $api_key ) {
		if ( empty( $api_key ) ) {
			return new \WP_Error( 'missing_key', __( 'DeepL API key gerekli.', 'elementor-bulk-importer' ) );
		}

		$url = 'https://api-free.deepl.com/v2/translate';
		
		$body = array(
			'text' => array( $text ),
			'target_lang' => strtoupper( $target_lang ),
		);

		$response = wp_remote_post( $url, array(
			'timeout' => 30,
			'body' => $body,
			'headers' => array(
				'Authorization' => 'DeepL-Auth-Key ' . $api_key,
			),
			'sslverify' => true,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_body = wp_remote_retrieve_body( $response );
		$data = json_decode( $response_body, true );

		if ( ! $data || ! isset( $data['translations'] ) || empty( $data['translations'] ) ) {
			return new \WP_Error( 'translation_error', __( 'DeepL çeviri hatası', 'elementor-bulk-importer' ) );
		}

		// Decode HTML entities and fix Unicode characters
		$translated_text = $data['translations'][0]['text'];
		$translated_text = html_entity_decode( $translated_text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		
		return $translated_text;
	}

	/**
	 * Translate using Microsoft Translator API
	 *
	 * @param string $text Text to translate.
	 * @param string $target_lang Target language code.
	 * @param string $api_key API key (required).
	 * @return string|WP_Error Translated text or error.
	 */
	private function translate_microsoft( $text, $target_lang, $api_key ) {
		if ( empty( $api_key ) ) {
			return new \WP_Error( 'missing_key', __( 'Microsoft Translator API key gerekli.', 'elementor-bulk-importer' ) );
		}

		// Microsoft Translator requires getting an access token first
		$token_url = 'https://api.cognitive.microsoft.com/sts/v1.0/issueToken';
		$token_response = wp_remote_post( $token_url, array(
			'timeout' => 10,
			'headers' => array(
				'Ocp-Apim-Subscription-Key' => $api_key,
			),
			'sslverify' => true,
		) );

		if ( is_wp_error( $token_response ) ) {
			return $token_response;
		}

		$access_token = wp_remote_retrieve_body( $token_response );

		$source_lang = 'en';
		if ( preg_match( '/[ığüşöçİĞÜŞÖÇ]/u', $text ) ) {
			$source_lang = 'tr';
		}

		$url = 'https://api.cognitive.microsofttranslator.com/translate?api-version=3.0&from=' . $source_lang . '&to=' . $target_lang;
		
		$body = json_encode( array(
			array( 'Text' => $text ),
		) );

		$response = wp_remote_post( $url, array(
			'timeout' => 30,
			'body' => $body,
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Content-Type' => 'application/json',
			),
			'sslverify' => true,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_body = wp_remote_retrieve_body( $response );
		$data = json_decode( $response_body, true );

		if ( ! $data || ! isset( $data[0] ) || ! isset( $data[0]['translations'] ) || empty( $data[0]['translations'] ) ) {
			return new \WP_Error( 'translation_error', __( 'Microsoft Translator çeviri hatası', 'elementor-bulk-importer' ) );
		}

		// Decode HTML entities and fix Unicode characters
		$translated_text = $data[0]['translations'][0]['text'];
		$translated_text = html_entity_decode( $translated_text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		
		return $translated_text;
	}

	/**
	 * Translate using Yandex Translate API
	 *
	 * @param string $text Text to translate.
	 * @param string $target_lang Target language code.
	 * @param string $api_key API key (required).
	 * @return string|WP_Error Translated text or error.
	 */
	private function translate_yandex( $text, $target_lang, $api_key ) {
		if ( empty( $api_key ) ) {
			return new \WP_Error( 'missing_key', __( 'Yandex Translate API key gerekli.', 'elementor-bulk-importer' ) );
		}

		$source_lang = 'en';
		if ( preg_match( '/[ığüşöçİĞÜŞÖÇ]/u', $text ) ) {
			$source_lang = 'tr';
		}

		$url = 'https://translate.yandex.net/api/v1.5/tr.json/translate';
		
		$params = array(
			'key' => $api_key,
			'text' => $text,
			'lang' => $source_lang . '-' . $target_lang,
		);

		$url .= '?' . http_build_query( $params );

		$response = wp_remote_get( $url, array(
			'timeout' => 30,
			'sslverify' => true,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! $data || ! isset( $data['text'] ) || empty( $data['text'] ) ) {
			$error_message = isset( $data['message'] ) ? $data['message'] : __( 'Yandex çeviri hatası', 'elementor-bulk-importer' );
			return new \WP_Error( 'translation_error', $error_message );
		}

		// Decode HTML entities and fix Unicode characters
		$translated_text = $data['text'][0];
		$translated_text = html_entity_decode( $translated_text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		
		return $translated_text;
	}

	/**
	 * Translate using Argos Translate API
	 *
	 * @param string $text Text to translate.
	 * @param string $target_lang Target language code.
	 * @param string $api_url API URL (optional).
	 * @return string|WP_Error Translated text or error.
	 */
	private function translate_argostranslate( $text, $target_lang, $api_url = '' ) {
		// Argos Translate uses same format as LibreTranslate
		return $this->translate_libretranslate( $text, $target_lang, $api_url );
	}

	/**
	 * Translate Elementor content recursively
	 *
	 * @param array $element Elementor element data.
	 * @param string $target_lang Target language code.
	 * @param string $api_id API ID.
	 * @return array|WP_Error Translated element or error.
	 */
	public function translate_elementor_content( $element, $target_lang, $api_id ) {
		if ( ! is_array( $element ) ) {
			return $element;
		}

		// Translate widget settings - only translate specific text fields
		if ( isset( $element['settings'] ) && is_array( $element['settings'] ) ) {
			foreach ( $element['settings'] as $key => $value ) {
				// Check if this field should be translated
				if ( $this->should_translate_field( $key, $value ) ) {
					$translated = $this->translate( $value, $target_lang, $api_id );
					if ( ! is_wp_error( $translated ) ) {
						$element['settings'][ $key ] = $translated;
					}
				} elseif ( is_array( $value ) ) {
					// Recursively translate nested arrays (like icon_list items)
					$element['settings'][ $key ] = $this->translate_array_content( $value, $target_lang, $api_id );
				}
			}
		}

		// Recursively translate child elements
		if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
			foreach ( $element['elements'] as $index => $child ) {
				$element['elements'][ $index ] = $this->translate_elementor_content( $child, $target_lang, $api_id );
			}
		}

		return $element;
	}

	/**
	 * Check if a field should be translated
	 *
	 * @param string $key Field key.
	 * @param mixed  $value Field value.
	 * @return bool
	 */
	private function should_translate_field( $key, $value ) {
		// Must be a non-empty string with actual content
		if ( ! is_string( $value ) || empty( trim( $value ) ) || strlen( trim( $value ) ) < 2 ) {
			return false;
		}

		// Skip technical fields
		$skip_keys = array(
			'id', 'type', 'class', 'url', 'link', 'src', 'href', 'widgettype', 'eltype',
			'icon', 'image', 'library', 'value', 'unit', 'size', 'position', 'align',
			'color', 'background', 'border', 'shadow', 'animation', 'transition',
			'tag', 'html_tag', 'wrapper_tag', 'view', 'shape', 'style', 'layout',
			'margin', 'padding', 'width', 'height', 'gap', 'spacing', 'radius',
			'font', 'weight', 'family', 'typography', 'transform', 'decoration',
			'_id', '__globals__', 'selected_icon', 'icon_list', 'slides', 'tabs'
		);

		// Check if key contains any skip keyword
		$key_lower = strtolower( $key );
		foreach ( $skip_keys as $skip ) {
			if ( strpos( $key_lower, $skip ) !== false ) {
				return false;
			}
		}

		// Only translate if key contains text-related keywords
		$text_keywords = array(
			'title', 'text', 'editor', 'html', 'content', 'description', 'caption',
			'subtitle', 'label', 'placeholder', 'button', 'heading', 'message',
			'alert', 'tab', 'item', 'name', 'address', 'phone', 'email_text',
			'form_name', 'field_label', 'submit', 'custom_text', 'prefix', 'suffix',
			'accordion', 'toggle', 'counter', 'blockquote', 'testimonial', 'quote',
			'author', 'company', 'job_title', 'bio', 'excerpt', 'intro'
		);

		$has_text_keyword = false;
		foreach ( $text_keywords as $keyword ) {
			if ( strpos( $key_lower, $keyword ) !== false ) {
				$has_text_keyword = true;
				break;
			}
		}

		if ( ! $has_text_keyword ) {
			return false;
		}

		// Advanced content filtering
		return $this->is_translatable_content( $value );
	}

	/**
	 * Check if content is actually translatable text
	 *
	 * @param string $value Content to check.
	 * @return bool
	 */
	private function is_translatable_content( $value ) {
		// Skip URLs
		if ( filter_var( $value, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		// Skip email addresses
		if ( filter_var( $value, FILTER_VALIDATE_EMAIL ) ) {
			return false;
		}

		// Skip hex colors (#fff, #000000)
		if ( preg_match( '/^#[0-9a-fA-F]{3,8}$/', $value ) ) {
			return false;
		}

		// Skip CSS classes, IDs, and single lowercase words with dashes/underscores
		if ( preg_match( '/^[a-z0-9_-]+$/', $value ) && strpos( $value, ' ' ) === false ) {
			return false;
		}

		// Skip shortcodes [shortcode]
		if ( preg_match( '/^\[.*\]$/', $value ) ) {
			return false;
		}

		// Skip icon codes (fa-, icon-, lnr-, etc.)
		if ( preg_match( '/^(fa-|fas |far |fab |icon-|lnr-|eicon-|ti-)/i', $value ) ) {
			return false;
		}

		// Skip JSON strings
		if ( preg_match( '/^[\{\[].*[\}\]]$/', $value ) ) {
			return false;
		}

		// Skip numbers only or numbers with units (100px, 50%, etc.)
		if ( preg_match( '/^[0-9]+(px|%|em|rem|vh|vw)?$/', $value ) ) {
			return false;
		}

		// Skip common widget type names
		$widget_types = array( 'container', 'section', 'column', 'widget', 'heading', 
			'text-editor', 'image', 'button', 'divider', 'spacer', 'icon', 'icon-box' );
		if ( in_array( strtolower( $value ), $widget_types ) ) {
			return false;
		}

		// Skip if contains only special characters
		if ( ! preg_match( '/[a-zA-Z0-9\p{L}]/u', $value ) ) {
			return false;
		}

		// Must contain at least one letter (avoid pure numbers or symbols)
		if ( ! preg_match( '/[a-zA-Z\p{L}]/u', $value ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Translate array content (for nested structures like icon_list items)
	 *
	 * @param array  $array Array to translate.
	 * @param string $target_lang Target language code.
	 * @param string $api_id API ID.
	 * @return array Translated array.
	 */
	private function translate_array_content( $array, $target_lang, $api_id ) {
		if ( ! is_array( $array ) ) {
			return $array;
		}

		foreach ( $array as $key => $value ) {
			if ( is_string( $value ) && $this->should_translate_field( $key, $value ) ) {
				$translated = $this->translate( $value, $target_lang, $api_id );
				if ( ! is_wp_error( $translated ) ) {
					$array[ $key ] = $translated;
				}
			} elseif ( is_array( $value ) ) {
				$array[ $key ] = $this->translate_array_content( $value, $target_lang, $api_id );
			}
		}

		return $array;
	}
}

