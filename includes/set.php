<?php

return array(
	'folder'         => array(
		'name'        => 'Snapshots Folder',
		'type'        => 'text',
		'description' => 'Absolute path to the folder where the snapshots are stored.',
		'default'     => WP_CONTENT_DIR . '/.snapshots',
	),
	'cli_path'       => array(
		'name'        => 'CLI Path',
		'type'        => 'text',
		'description' => 'The path to the WP-CLI binaries.',
		'default'     => '/usr/local/bin',
	),
	'cli_allow_root' => array(
		'name'        => 'CLI Allow Root',
		'type'        => 'boolean',
		'description' => 'Allow running the WP-CLI commands as root.',
		'default'     => false,
	),
	'max_shots'      => array(
		'name'        => 'Max Shots',
		'type'        => 'number',
		'description' => 'The number of shots kept with the same name.',
		'default'     => 2,
	),
	'php_path'       => array(
		'name'        => 'PHP Path',
		'type'        => 'text',
		'description' => 'The path to the PHP binaries.',
		'default'     => '/usr/local/bin/php',
	),
	'save_files'     => array(
		'name'        => 'Save Files',
		'type'        => 'boolean',
		'description' => 'Include files from the content folder.',
		'default'     => true,
	),
	'save_location'  => array(
		'name'        => 'Save Location',
		'type'        => 'boolean',
		'description' => 'Include location (URL) during creation to redirect on restore.',
		'default'     => true,
	),

);
