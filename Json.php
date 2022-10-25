<?php

namespace Irbis;


/**
 * @package 	irbis
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		2.0
 */
class Json {

	private static $jsonsCache = [];
	public static $decodeAssoc = true;
	
	protected static $_messages = array(
		JSON_ERROR_NONE => 'JSON, No error has occurred',
		JSON_ERROR_DEPTH => 'JSON, The maximum stack depth has been exceeded',
		JSON_ERROR_STATE_MISMATCH => 'JSON, Invalid or malformed JSON',
		JSON_ERROR_CTRL_CHAR => 'JSON, Control character error, possibly incorrectly encoded',
		JSON_ERROR_SYNTAX => 'JSON, Syntax error',
		JSON_ERROR_UTF8 => 'JSON, Malformed UTF-8 characters, possibly incorrectly encoded',
		JSON_ERROR_RECURSION => 'JSON, One or more recursive references',
		JSON_ERROR_INF_OR_NAN => 'JSON, One or more NAN or INF values',
		JSON_ERROR_UNSUPPORTED_TYPE => 'JSON, A value of a type that cannot be encoded was provided',
		JSON_ERROR_INVALID_PROPERTY_NAME => 'JSON, A property name was given that cannot be encoded',
		JSON_ERROR_UTF16 => 'JSON, Malformed UTF-16 characters, possibly incorrectly encoded',
	);

	public static function encode($value, $options = JSON_UNESCAPED_UNICODE) {
		$result = json_encode($value, $options);
		if ($result !== false) return $result;
		throw new \RuntimeException(static::$_messages[json_last_error()]);
	}

	public static function decode(string $json, bool $assoc = false) {
		$result = json_decode($json, $assoc);
		if ($result !== null) return $result;
		throw new \RuntimeException(static::$_messages[json_last_error()]);
	}
}
