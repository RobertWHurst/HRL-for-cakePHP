The Hierarchical Resource Loader v1.0
=====================================
Introduction
------------

This is the Hierarchical Resource Loader or HRL. It is cakePHP3 helper created to help programmers with managing their CSS and JavaScript dependencies. When a file is queued it can have dependencies declared. If a file has dependencies it will not be loaded until all of its dependencies are loaded first. Files are identified by keys. These are optional but must be supplied if the file is required by dependent file. Without a key a file cannot be identified as a dependency.

HRL OPTIONS
-----------
There if you want your css and js merged then set __combine_files__ to true.

	$hrl->combine_files = true;

If __combine_files__ is set to false it will include each file with its own link or script tag.

Loading CSS or JavaScript
-------------------------
Loading a CSS or JavaScript file can be done in a few different ways. Note that both __js()__ and __css()__ accept nearly identical input.

__The Easy Way__
To just load a script or stylesheet...

	//load style.css
	$hrl->css( 'style.css' );
	
	//load script.js
	$hrl->css( 'script.js' );

This is exactly the same as using cakes html helper with the exception that it is output by the hrl instead of the html helper. Note that any file loaded this way cannot be a dependency. This is because the file has no key.

__Dependencies__

When you want a file to only load only after its dependencies have then do the following:

	//load myscript
	//Note: depends on jquery and jquery ui.
	$hrl->js(array(
		'url' =>  'myscript.js',
		'key' =>'myscript',
		'requires' => array( 'jQuery', 'jQuery.ui' )
	));
	
	//load jquery
	$hrl->js(array(
		'url' => 'jquery.js',
		'key' => 'jQuery'
	));

	//load jquery ui
	$hrl->js(array(
		'url' => 'jquery.ui.js',
		'key' => 'jQuery.ui'
		'requires' => 'jQuery'
	));

In this example 'myscript' will wait for both jQuery and jQuery UI to be included.

__Printing it all out__

Including your queued files can be done by executing js() or css() without any arguments.

	//prints out the queued css in order
	$hrl->css();

	//prints out the queued JavaScript
	$hrl->js();

__Debugging__

If Can't figure out why a file isn't loading or would like to see the order your files are being loaded in then call __print_log()__ after js() and css() have printed the includes to your layout.

	//prints the log wrapped with <pre> tags
	$hrl->print_log();
	
	//prints the log wrapped in a html comment
	$hrl->print_log( true );

	//example of a log
	<!--
	
	
	
									 HIERARCHICAL RESOURCE LOADER LOG
	
	====================================================================================================
	
	CSS FILES
	----------------------------------------------------------------------------------------------------
	| # | LOADED: File style is loaded.
	----------------------------------------------------------------------------------------------------
	
	JS FILES
	----------------------------------------------------------------------------------------------------
	|   | REQUIRED: File myscript requirements...
	|   | - PENDING: File jquery must load first. postponed...
	| # | LOADED: File jQuery is loaded.
	| # | LOADED: File jQuery.ui is loaded.
	| # | LOADED: File myscript is loaded.
	----------------------------------------------------------------------------------------------------
	
	
	
	-->

__Loading a Couple Files at Once__

	$hrl->css('file1.css', 'file2.css', 'file3.css');
	//or
	$hrl->css(array(
		array(
			'key' => 'file1',
			'url' => 'http://example.com/file1.css',
			'requires' => 'file2'
		),
		array(
			'key' => 'file2',
			'url' => 'file2.css',
		)
		array(
			'url' => 'file3'
		)
	);

__Structure of a File Array__

	[file array] = array(
		'url' => string [file name or url],
		'key' => string [key for file],
		'requires' => string [file key] or array [array of keys]
		(css only) 'media' => string [media tag attribute]
	)

__Credit__

This Helper was written by Robert Hurst. Please do not sell this helper and give credit where credit is due.

Cheers and enjoy.