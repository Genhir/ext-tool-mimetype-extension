#!/bin/env php
<?php

	$debug = php_sapi_name() !== 'cli';

	// load json mimetypes and extensions database:
	$data = json_decode(file_get_contents(__DIR__ . '/../mime-db/db.json'));
	$code = '';
	
	// complete mimetypes with extensions and mimetypes without extensions:
	$mimesExts = [];
	$noExts = [];
	$maxMimeTypeWithExtsLength = 0;
	$maxMimeTypeWithoutExtsLength = 0;
	foreach ($data as $mime => $item) {
		$mimeLength = mb_strlen($mime);
		if (isset($item->extensions)) {
			if (isset($mimesExts[$mime])) {
				$exts = & $mimesExts[$mime];
				foreach ($item->extensions as $ext) {
					if (!in_array($ext, $exts)) $exts[] = $ext;
				}
			} else {
				$mimesExts[$mime] = $item->extensions;
			}
			if ($mimeLength > $maxMimeTypeWithExtsLength) 
				$maxMimeTypeWithExtsLength = $mimeLength;
		} else {
			$noExts[$mime] = 1;
			if ($mimeLength > $maxMimeTypeWithoutExtsLength) 
				$maxMimeTypeWithoutExtsLength = $mimeLength;
		}
	}
	unset($data);
	
	// add custom mime types and extensions
	$customMimes = json_decode(file_get_contents(__DIR__ . '/../custom-mimes.json'));
	foreach ($customMimes as $mime => $exts) {
		foreach ($exts as $ext) {
			if (isset($mimesExts[$mime])) {
				if (!in_array($ext, $mimesExts[$mime])) {
					$mimesExts[$mime][] = $ext;
				}
			} else {
				$mimesExts[$mime] = [$ext];
			}
		}
	}
	
	// sort by mimes:
	ksort($mimesExts);
	ksort($noExts);
	
	// complete result php code:
	$tabSize = 4.0;
	$baseTabsCount = 2;
	$maxMimeTypeWithExtsLength += 2 + $tabSize;
	$maxMimeTypeWithoutExtsLength += 3 + $tabSize;
	
	// complete mime types with extensions:
	$mimesExtsLines = [];
	foreach ($mimesExts as $mime => $exts) {
		$mimeKey = "'" . $mime . "'";
		$restCharsLength = floatval($maxMimeTypeWithExtsLength - mb_strlen($mimeKey));
		$tabsCount = intval(floor($restCharsLength / $tabSize));
		$mimesExtsLines[] = str_pad('', $baseTabsCount, "\t")
			. $mimeKey
			. str_pad('', $tabsCount, "\t")
			. "=> ['" . implode("','", $exts) . "'],";
	}
	
	// complete mime types without extensions:
	$noExtsLines = [];
	foreach ($noExts as $mime => $dummyOne) {
		$mimeKey = "'" . $mime . "'";
		$restCharsLength = floatval($maxMimeTypeWithoutExtsLength - mb_strlen($mimeKey));
		$tabsCount = intval(floor($restCharsLength / $tabSize));
		$noExtsLines[] = str_pad('', $baseTabsCount, "\t")
			. $mimeKey
			. str_pad('', $tabsCount, "\t")
			. "=> 1,";
	}
	
	$code = <<<'CODE'
<?php

/**
 * MvcCore
 *
 * This source file is subject to the BSD 3 License
 * For the full copyright and license information, please view 
 * the LICENSE.md file that are distributed with this source code.
 *
 * @copyright	Copyright (c) 2016 Tom Flídr (https://github.com/mvccore/mvccore)
 * @license		https://mvccore.github.io/docs/mvccore/4.0.0/LICENCE.md
 */

namespace MvcCore\Ext\Tools;

/**
 * Responsibility - return extension(s) by mimetype or mimetype(s) by extension.
 */
class MimeTypesExtensions
{
	/**
	 * Array with mime types and their extensions.
	 * Key is mimetype string and value is
	 * array with string extensions.
	 * @var array
	 */
	protected static $mimesExts = [

CODE;
	$code .= implode("\n", $mimesExtsLines);
	$code .= <<<'CODE'

	];
	
	/**
	 * Array with extensions and their mimetypes.
	 * Key is extension and value is array with mimetypes.
	 * Keys in this subarray is mimetype string and value
	 * is number describing how many extensions are in 
	 * array `self::$mimesExts` for this mimetype.
	 * This array is completed on demand from `self::$mimesExts`;
	 * @var array|NULL
	 */
	protected static $extsMimes = NULL;
	
	/**
	 * Array with mimetypes without extensions.
	 * Key is mimetype string, value is dummy number `1`.
	 * @var array
	 */
	protected static $noExts = [

CODE;
	$code .= implode("\n", $noExtsLines);
	$code .= <<<'CODE'

	];
	
	/**
	 * Return array of strings, extensions by given mimetype.
	 * If mimetype has defined file type with no extension, return
	 * array with one record - the empty string. If there is 
	 * no data for given mimetype, return `NULL`.
	 * @param string $mimeType
	 * @return \string[]|NULL
	 */
	public static function GetExtensionsByMimeType ($mimeType) {
		$mimeTypeStr = (string) $mimeType;
		if (isset(static::$mimesExts[$mimeTypeStr])) {
			return static::$mimesExts[$mimeTypeStr];
		} else if (isset(static::$noExts[$mimeTypeStr])) {
			return [''];
		} else {
			return NULL;
		}
	}
	
	/**
	 * Return array of strings, mimetypes by given extension.
	 * Returned mime types are sorted by extensions count in
	 * `self::$mimesExts` array under returned mimetypes.
	 * If there is no data for given extension, return `NULL`.
	 * @param string $extension
	 * @return \string[]|NULL
	 */
	public static function GetMimeTypesByExtension ($extension) {
		$extensionStr = (string) $extension;
		if (static::$extsMimes === NULL) 
			static::setUpExtsMimes();
		if (isset(static::$extsMimes[$extensionStr])) {
			$mimes = static::$extsMimes[$extensionStr];
			ksort($mimes);
			asort($mimes);
			return array_keys($mimes);
		} else {
			return NULL;
		}
	}
	
	/**
	 * Initialize once `self::$extsMimes` array by `self::$mimesExts`.
	 * @return void
	 */
	protected static function setUpExtsMimes () {
		$extsMimes = [];
		foreach (static::$mimesExts as $mime => & $exts) {
			$extsCnt = count($exts);
			foreach ($exts as $ext) {
				if (isset($extsMimes[$ext])) {
					$extsMimes[$ext][$mime] = $extsCnt;
				} else {
					$rec = [];
					$rec[$mime] = $extsCnt;
					$extsMimes[$ext] = $rec;
				}
			}
		}
		static::$extsMimes = & $extsMimes;
	}
}
CODE;
	
	$dirFullPath = realpath(__DIR__ . '/..');
	$dirFullPath = str_replace('\\', '/', $dirFullPath);
	$dirFullPath .= '/src/MvcCore/Ext/Tools';
	if (!is_dir($dirFullPath)) mkdir($dirFullPath, 0777, TRUE);
	$fileFullPath = $dirFullPath . '/MimeTypesExtensions.php';
	if (is_file($fileFullPath)) unlink($fileFullPath);
	file_put_contents($fileFullPath, $code);
	
	if ($debug) {
		header('Content-Type: text/plain; charset=utf-8');
		echo $code;
	}
	
	exit;
	