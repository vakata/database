<?php
namespace vakata\database;

class Settings
{
	public $type		= null;
	public $username	= 'root';
	public $password	= null;
	public $database	= null;
	public $servername	= 'localhost';
	public $serverport	= null;
	public $persist		= false;
	public $timezone	= null;
	public $charset		= 'UTF8';
	public $options		= null;
	public $original	= null;

	public function __construct($settings) {
		$this->original = $settings;
		$str = parse_url($settings);
		if ($str) {
			if (array_key_exists('scheme',$str)) {
				$this->type			= rawurldecode($str['scheme']);
			}
			if (array_key_exists('user',$str)) {
				$this->username		= rawurldecode($str['user']);
			}
			if (array_key_exists('pass',$str)) {
				$this->password		= rawurldecode($str['pass']);
			}
			if (array_key_exists('path',$str)) {
				$this->database		= trim(rawurldecode($str['path']),'/');
			}
			if (array_key_exists('host',$str)) {
				$this->servername	= rawurldecode($str['host']);
			}
			if (array_key_exists('port',$str)) {
				$this->serverport	= rawurldecode($str['port']);
			}
			$this->options = array();
			if (array_key_exists('query',$str)) {
				parse_str($str['query'], $str);
				$this->options = $str;
				$this->persist = (array_key_exists('persist', $str) && $str['persist'] === 'TRUE');
				if (array_key_exists('charset', $str)) {
					$this->charset = $str['charset'];
				}
				if (array_key_exists('timezone', $str)) {
					$this->timezone = $str['timezone'];
				}
			}
		}
		else {
			$str = array_pad(explode('://', $settings, 2), 2, '');
			$this->type = $str[0];
			$str = $str[1];
			$str = array_pad(explode('?', $str, 2), 2, '');
			$this->options = array();
			parse_str($str[1], $this->options);
			$str = $str[0];
			if (strpos($str, '@') !== false) {
				$str = array_pad(explode('@', $str, 2), 2, '');
				list($this->username, $this->password) = array_pad(explode(':', $str[0], 2), 2, '');
				$str = $str[1];
			}
			$this->original = $this->type . '://' . $str;
		}
	}
}