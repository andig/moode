<?php

/**
 * This file is used to provide cover art to MPoD and MPaD client apps
 *
 * the script will look for cover files- either as Folder.jpg or mp3 meta data
 * if filename is passed in the following way:
 *
 *		/mpodcover.php/some/local/file/name
 *
 * make sure client is configured to hand cover requests to /mpodcover.php
 * or setup and nginx catch-all rule:
 *
 * 		try_files $uri $uri/ /mpodcover.php;
 *
 * Copyright (c) 2015 Andreas Goetz <cpuidle@gmx.de>
 */

set_include_path('inc');

function outImage($mime, $data) {
	switch ($mime) {
		case "image/png":
		case "image/gif":
		case "image/jpg":
		case "image/jpeg":
			header("Content-Type: " . $mime);
			echo $data;
			exit(0);
			break;
		default :
			break;
	}
}

function getImage($path) {
	global $getid3;

	if (!file_exists($path)) {
		return false;
	}

	$ext = pathinfo($path, PATHINFO_EXTENSION);

	switch (strtolower($ext)) {
		case 'png':
		case 'jpg':
		case 'jpeg':
			// physical image file -> redirect
			$path = '/coverroot' . substr($path, strlen('/mnt'));
			$path = str_replace('#', '%23', $path);
			header('Location: ' . $path);
			die;

			// alternative -> return image file contents
			$mime = 'image/' . $ext;
			$data = file_get_contents($path);

			outImage($mime, $data);
			break;

		case 'mp3':
			require_once 'Zend/Media/Id3v2.php';

			try {
				$id3 = new Zend_Media_Id3v2($path);

				if (isset($id3->apic)) {
					outImage($id3->apic->mimeType, $id3->apic->imageData);
				}
			}
			catch (Zend_Media_Id3_Exception $e) {
				// catch any parse errors
			}
			break;

		case 'flac':
			require_once 'Zend/Media/Flac.php';

			try {
				$flac = new Zend_Media_Flac($path);

				if ($flac->hasMetadataBlock(Zend_Media_Flac::PICTURE)) {
					$picture = $flac->getPicture();
					outImage($picture->getMimeType(), $picture->getData());
				}
			}
			catch (Zend_Media_Flac_Exception $e) {
				// catch any parse errors
			}
			break;

		case 'm4a':
			require_once 'getid3/getid3.php';

			try {
				$id3 = new getID3();
				$id3 = $id3->analyze($path);

				if (isset($id3['comments']) && isset($id3['comments']['picture']) && sizeof($id3['comments']['picture'])) {
					$picture = $id3['comments']['picture'][0];
					outImage($picture['image_mime'], $picture['data']);
				}
			}
			catch (Exception $e) {
				// catch any parse errors
			}
			break;
	}

	return false;
}

function parseFolder($path) {
	$covers = array(
		'Folder.jpg',
		'folder.jpg',
		'Folder.png',
		'folder.png',
		'Cover.jpg',
		'cover.jpg',
		'Cover.png',
		'cover.png'
	);

	// default cover files
	foreach ($covers as $file) {
		getImage($path . $file);
	}

	// all (other) files
	foreach (glob($path . '*') as $file) {
		if (is_file($file)) {
			getImage($file);
		}
	}
}

/*
 * MAIN
 */

// Get options- cmd line or GET
$options = getopt('p:', array('path:'));
$path = isset($options['p']) ? $options['p'] : (isset($options['path']) ? $options['path'] : null);

if (null === $path) {
	$self = $_SERVER['SCRIPT_NAME'];
	$path = urldecode($_SERVER['REQUEST_URI']);
	if (substr($path, 0, strlen($self)) === $self) {
		// strip script name if called as /mpodcover.php/path/to/file
		$path = substr($path, strlen($self)+1);
	}
	$path = '/mnt/' . $path;
}

// does file exist and contain image?
getImage($path);

// directory - try all files
if (is_dir($path)) {
	// make sure path ends in /
	if (substr($path, -1) !== '/') {
		$path .= '/';
	}

	parseFolder($path);
}
else {
	// file - try all files in containing folder
	$path = pathinfo($path, PATHINFO_DIRNAME) . '/';

	parseFolder($path);
}

// nothong found -> default cover
header('Location: /images/default-cover.jpg');
