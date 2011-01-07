<?php
/**
 * This is the Hierarchical Resource Loader or HRL. It is cakePHP 1.3 helper
 * created to help programmers with managing their css and javascript
 * dependancies. When a file is queued it can have dependancies declared.
 * If a file has dependancies it will not be loaded until all of them are
 * loaded first. Files are identified by keys. These are optional but must
 * be supplied if the file is required by dependant file. Without a key
 * a file can not be identified as a dependency.
 *
 * See the readme for instructions and licence information.
 *
 * @verson 1.0
 * @author Robert Hurst
 */
class HrlHelper extends AppHelper {



	/**
	 * Setting this to true will enable merging the output of the
	 * hrl into single cache files.
	 *
	 * @var bool
	 */
	public $merge = true;




	/**
	 * Setting this to true will enable use of css tidy. Each css file
	 * or cache will be passed through css tidy.
	 *
	 * @var bool
	 */
	public $css_tidy = true;




	/**
	 * Setting this to true will enable use of js min. Each javascript file
	 * or cache will be passed through jsMin.
	 * @var bool
	 */
	public $js_min = false;




	/*
	 * Sets an array to store a pair of buffers. One for css and one
	 * for js. If merge is set to false these are not used.
	 */
	private $buffer = array(
		'css' => '',
		'js' => ''
	);




	private $output = array(
		'css' => '',
		'js' => ''
	);



	/*
	 * Sets an array to save file signatures too.
	 */
	private $signature = array(
		'css' => '',
		'js' => ''
	);


	public $helpers = array( 'Html' );


	// PUBLIC
	// ---------------------------------------------------------




	/**
	 * Use the css method for queueing files. Running this method without
	 * arguments causes it to print the link tags for all queued files
	 * in correct order. If caching is enabled a link to the merged cache
	 * will be printed. If css tidy is enabled then all linked items will
	 * be processed.
	 *
	 * @param null $files
	 * @return void
	 */
	public function css( $files = null ){

		if( ! $files ){
			echo $this->render( 'css' );
		} else {
			$this->queue( 'css', $files );
		}
	}




	/**
	 * Use the js method for queueing files. Running this method without
	 * arguments causes it to print the link tags for all queued files
	 * in correct order. If caching is enabled a link to the merged cache
	 * will be printed. If jsMin is enabled then all linked items will
	 * be processed.
	 *
	 * @param null $files
	 * @return void
	 */
	public function js( $files = null ){

		/*
		 * if the method has been executed without arguments then render
		 * the script tags.
		 */
		if( ! $files ){
			echo $this->render( 'js' );

		/*
		 * If the method has been executed with arguments; an array of
		 * files to queue, pass the array to the queue function.
		 */
		} else {
			$this->queue( 'js', $files );
		}

	}




	/**
	 * Prints a log created during the execution of the hrl.
	 * This method will only work if used after the css and
	 * js methods have been executed without arguments.
	 *
	 * @param bool $comment_out If true the log will be an html comment instead of being wrapped in <pre> tags.
	 * @return void
	 */
	public function print_log( $comment_out = false ){

		/*
		 * if the print log method is executed and is not passed
		 * 'true' then print the log wrapped in pre tags so it
		 * will be visible on page.
		 */
		if( ! $comment_out ){
			echo '<pre>' . $this->log . '</pre>';

		/*
		 * If the method is executed and is passed 'true' print the
		 * log wrapped within an html comment so it can be viewed in
		 * the page source instead of on page.
		 */
		} else {
			echo "<!--\n\n" . $this->log . "\n\n-->";
		}
	}




	/*
	 * !!! WARNING !!! Changing these will break the hrl. Only change this
	 * if you know what you are doing.
	 */
	public $css_ext = '.css';
	public $js_ext = '.js';




	// PRIVATE
	// ---------------------------------------------------------




	//sets and starts the log
	private $log = "\n\n                                 HIERARCHICAL RESOURCE LOADER LOG\n\n====================================================================================================\n\n";




	/*
	 * Sets the default values for file include arrays.
	 * Any file arrays used will be applied over top of
	 * their default below allowing for more flexibility.
	 */
	private $default_file_vals = array(
		'css' => array(
			'key' => '',
			'media' => 'all',
			'url' => '',
			'requires' => array()
		),
		'js' => array(
			'key' => '',
			'url' => '',
			'requires' => array()
		)
	);




	/*
	 * Sets an array containing an array for css files and js files.
	 */
	private $includes = array(
		'css' => array( 'Files' => array() ),
		'js' => array( 'Files' => array() )
	);




	/**
	 * Takes an two arrays and merges them. the first array is used as
	 * a template or mask, and the second array is applied over top.
	 * If the second array contains variables or structure not present
	 * in the first array it is discarded.
	 *
	 * The first array acts as a mask on the second array.
	 *
	 * @author Robert Hurst
	 * @website http://thinktankdesign.ca/
	 *
	 * @param  $defaults
	 * @param  $arguments
	 * @param bool $keep_unset
	 * @return array
	 */
	private function mask_array($defaults, $arguments, $keep_unset = false) {

		/*
		 * If the arguments are invalid or broken fail gracefully by
		 * returning first argument.
		 *
		 * Note: this is done instead of returning false to prevent serious
		 * failures in other methods or functions that depend on this method.
		 * This is extremely important in recursive or self executing functions.
		 */
		if( ! is_array( $defaults ) || ! is_array( $arguments ) ){
			return $defaults; //just return the defaults (something goofed)
		}

		/*
		 * Copy the default array (the mask) to the results array. If the second
		 * array is invalid or does not match the mask at all, the default mask
		 * will be returned.
		 */
		$results = $defaults;

		//loop through the second array
		foreach( $arguments as $key => $argument ){

			/*
			 * Check to see if the method is set to discard unmasked data, or if
			 * the current argument does not fit within the default mask array.
			 * If both of these are false then discard the variable or structure
			 */
			if( ! $keep_unset && ! isset( $defaults[$key] ) )
				continue; //the option is invalid

			/*
			 * If the current argument is more array structure instead of a
			 * variable then check if it fits in the default mask. if it does
			 * re execute on it.
			 */
			if( is_array( $argument ) ){

				/*
				 * If the mask has a variable instead of structure in this position.
				 */
				if( !is_array( $defaults[$key] ) ){

					/*
					 * Check to see if the method is set to discard unmasked structure.
					 * If the method is set to keep unmasked structure then replace the
					 * mask's variable with this structure.
					 */
					if( $keep_unset ){
						$results[$key] = $argument;
					}

					//continue to the next item in the second array.
					continue;
				}

				/*
				 * re execute on the current structure and the current mask position.
				 */
				$results[$key] = $this->mask_array($defaults[$key], $argument, $keep_unset);

			/*
			 * If the current argument is a variable then save it to the results. Make
			 * sure the mask does not specify structure.
			 */
			} else {

				/*
				 * If the mask contains structure at this position then skip to the next
				 * variable.
				 */
				if( isset($defaults[$key]) && is_array( $defaults[$key] ) ){
					continue;
				}


				// save the current variable to the results array.
				$results[$key] = $argument;
			}
		}

		/*
		 * After processing all of the array structure, return the new filtered
		 * array.
		 */
		return $results;

	}




	/**
	 * Adds a css file or a js file to the queue. Both the js method and the
	 * css method depend on this method. The first parameter is the file type.
	 * The second parameter is the files array. See the documentation for the
	 * css or js method above for an example of the array structure accepted
	 * by this method.
	 *
	 * @param  $type
	 * @param  $files
	 * @return void
	 */
	private function queue( $type, $files ){

		/*
		 * Run a series of checks to make sure the $files array is correctly
		 * structured. First make sure that its an array in the first place.
		 * Second make sure it has at least one array (file record) with in it.
		 * Finally make sure that second array has a url key.
		 *
		 * If any of these checks come back false try to format the array
		 * structure.
		 */
		if(
			is_array( $files ) &&
			is_array( $first = reset( $files ) ) &&
			isset( $first['url'] )
		){

			/*
			 * Loop through all of the file records in the files array
			 * and add each of them to the queue.
			 */
			foreach( $files as $file ) {

				/*
				 * Verify the current records structure. This would have been done for the
				 * first record already, but other records will need to be checked as well.
				 */
				if(
					is_array( $file ) &&
					is_string( $file['url'] ) &&
					$file['url']
				){

					/*
					 * Mask the file record with the defaults for the current file type
					 * set at the head of this class. This will add any missing structure
					 * and prune structure that should not be present.
					 */
					$file = $this->mask_array( $this->default_file_vals[$type], $file, true );

					/*
					 * If the file record does not have a key, use the file's url as the
					 * key.
					 */
					if( $file['key'] === '' ){
						$file['key'] = $file['url'];
					}

					/*
					 * If the file has a dependency but its set within a string instead
					 * of being wrapped in an array, wrap it.
					 */
					if( ! empty( $file['requires'] ) && ! is_array( $file['requires'] ) ) {
						$file['requires'] = array( $file['requires'] );
					}

					//Add the record to the includes queue
					$this->includes[$type]['Files'][$file['key']] = $file;
				}
			}

		/*
		 * If the file failed the structure tests then attempt to restructure
		 * the file record.
		 */
		} else {

			/*
			 * If the files parameters is an array of file urls, wrap the
			 * urls in file records.
			 */
			if(
				is_array( $files ) &&
				! isset( $files['url'] )
			){

				/*
				 * For each of the file urls, wrap the url with the file record
				 * structure.
				 */
				foreach( $files as $ik => $file ){
					$files[$ik] = array( 'url' => $file );
				}

				//re execute with the the new structure
				$this->queue( $type, $files );

			/*
			 * If the files parameter is a single file record wrap it in an array
			 * and re execute.
			 */
			} else if ( is_array( $files ) ){
				$this->queue( $type, array( $files ) );

			/*
			 * If the parameter is a single file url wrap it and re execute.
			 */
			} else {
				$this->queue( $type, array( array( 'url' => $files ) ) );
			}
		}
	}




	/**
	 * The render method is the key stone to the HRL. This method is used to re order
	 * the file includes using their dependencies. It also returns a string containing
	 * all of the script tags or link tags. This method is used by both the js and the
	 * css methods.
	 *
	 * @param  $type
	 * @return string
	 */
	private function render( $type ){

		//start the rendering log for the current file type
		$render_title = strtoupper( $type );
		$this->log .= "{$render_title} FILES\n";
		$this->log .= "----------------------------------------------------------------------------------------------------\n";

		/*
		 * If the file includes queue is empty then note the lack of
		 * files in the log. Return an empty string.
		 */
		if( empty( $this->includes[$type]['Files']) ){
			$this->log .= "|   Nothing to load...\n";
		    return '';
		}

		/*
		 * Create an empty array and an empty string to recursively
		 * add the loaded keys and the script and link tags to.
		 */
		$loaded_keys = array();
		$output = '';

		/*
		 * Start a while loop that continues to execute until the
		 * queue is empty and all of the file records have been
		 * processed.
		 */
		while( ! empty( $this->includes[$type]['Files'] ) ){

			/*
			 * Loop through all of the files and attempt to load the
			 * each one.
			 */
			foreach( $this->includes[$type]['Files'] as $file_key => $file ){

				/*
				 * Check the current file for dependancies that have not been
				 * loaded. Note that this file may have been or will be processed
				 * several times. Each time a file dependency is loaded it is removed
				 * from the requires array belonging to the files that depend on
				 * it.
				 *
				 * If the dependancies have been met and the requires array is not
				 * empty, Verify that all of the existing dependancies are not loaded.
				 * If a dependency has been loaded remove it from the requires array.
				 */
				if( ! empty( $file['requires'] ) ){

					//Note in the log that this file had its dependencies checked.
					$this->log .= "|   | DEPENDENCIES: '{$file_key}' - checking dependancies.\n";

					//Loop through each of the dependencies.
					foreach( $file['requires'] as $dependency_key => $required_key ){

						/*
						 * Check to see if the dependency has been loaded by looking
						 * at the loaded keys array. Note that every time a file is loaded
						 * its key is added to the loaded keys array.
						 *
						 * If the dependency has been loaded; its key is in the loaded keys
						 * array, Unset the dependency in the current file record's requires
						 * array.
						 */
						if( in_array($required_key, $loaded_keys) ){
							unset( $this->includes[$type]['Files'][$file_key]['requires'][$dependency_key] );

						/*
						 * Check the queue to make sure the dependency can be met. If
						 * the dependency is not queued; it is not available, Note that
						 * the current file was skipped. Remove the file from the queue.
						 */
						} else if( ! isset( $this->includes[$type]['Files'][$required_key] ) ){

							//log that the file was skipped
							$this->log .= "| ! | - SKIPPED: '{$file_key}' - Cannot find requirement {$required_key}. '{$file_key}' will be skipped.\n";

							//unset the file record from the queue
							unset( $this->includes[$type]['Files'][$file_key] );

							/*
							 * Advance to the next file; Exits this loop of dependencies,
							 * and advances the file records loop one.
							 */
							continue 2;

						/*
						 * If the current dependency exists, but has not yet been loaded, Note
						 * that the file is pending in the log.
						 */
						} else {

							$this->log .= "|   | - PENDING: '{$file_key}' - waiting for file '{$required_key}'...\n";
						}
					}
				}

				/*
				 * Now that the requirements for the current file have been
				 * processed, Check to see if the requires array is empty.
				 * If it is attempt to load the file.
				 */
				if( empty( $file['requires'] ) ){

					/*
					 * Set the base path and the file type extension based
					 * on the file type, and the configured extension set
					 * In the head of this class for the file type.
					 */
					switch( $type ){
						case 'css':
							$base_path = CSS;
							$ext_set = $this->css_ext;
					    break;
						case 'js':
							$base_path = JS;
							$ext_set = $this->js_ext;
					    break;
					}

					/*
					 * Because multiple file extensions can be used for a file type;
					 * sass, css, scss, etc, the configured extensions must be wrapped
					 * in an array. By default only single extensions are used, and
					 * therefore are not in an array.
					 *
					 * If the extension(s) are in string form then wrap them in an array.
					 */
					if( ! is_array( $ext_set ) ){
						$ext_set = array( $ext_set );
					}

					//set a variable for if the file exists.
					$file_exists = false;

					/*
					 * loop through the extensions and check to see if a file exists with
					 * it. If a file is found that matches the extension, note the file exists.
					 */
					foreach( $ext_set as $ext ){
						if( file_exists( $base_path . $file['url'] . $ext ) || @preg_match( '/^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?$/', $file['url']) ){
							$file_exists = true;
						}
					}

					//If the file exists then proceed to load it.
					if( $file_exists ){

						//run the file record through processing
						if( $this->process_file( $type, $file ) ){

							//log that the file was loaded successfully.
							$this->log .= "| # | LOADED: '{$file['key']}' - loaded. \n";

							//add the file key to the loaded_keys array and remove the file from the includes array
							$loaded_keys[] = $file_key;

						//if the modifiers fail to handle the file report the failure.
						} else {
							$this->log .= "| ! | FAILED: File '{$file['key']}' does not exist.\n";
						}
					}

					//remove the file from the queue
					unset( $this->includes[$type]['Files'][$file_key] );
				}
			}
		}

	    //print the output from the modifiers
		$output = $this->get_output( $type );

		//end the hrl log
		$this->log .= "----------------------------------------------------------------------------------------------------\n\n";

		return $output;
	}



	public function process_file( $type, $file ){

		if( $this->merge ){
			return $this->merge( $type, $file );
		} else {
	        return $this->buffer( $type, $file );
		}

	}




	/**
	 * This method returns the link tags and script tags. This method
	 * should only be called once at the very end as this is the end
	 * of the hrl's exception.
	 *
	 * @param  $type
	 * @return array
	 */
	public function get_output( $type ){

		$this->get_merged( $type );

		return $this->output[$type];
	}



	/**
	 * Adds a single file to the string of link and script tags. All
	 * files must be loaded with this function.
	 *
	 * @param  $type
	 * @param  $file
	 * @return bool
	 */
	private function buffer( $type, $file ){

		switch( $type ){
			case 'css':
				return ( $this->output[$type] .= $this->Html->css( $file['url'], null, array( 'media' => $file['media'], 'inline' => true ) ) . "\n" );
			break;
			case 'js':
				return ( $this->output[$type] .= $this->Html->script( $file['url'], array( 'inline' => true ) ) . "\n" );
			break;
		}

	}




	/**
	 * Merge adds the content of queued files to the merge buffers,
	 * creates any missing cache directories, and streams the buffer
	 * into a merged file.
	 *
	 * @param  $type Specify css or js
	 * @param null $file The file include array to be added to the cache
	 * @return array|bool|null
	 */
	private function merge( $type, $file ){

		/*
		 * Check the file url with a regular expression to see if the file url contains
		 * a domain. If it does, then do not append the base path for the current file type.
		 */
		$base_path = '';
		if( ! @preg_match( '/^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?$/', $file['url'] ) ){
			switch( $type ){
				case 'css':
					$base_path = CSS;
				break;
				case 'js':
					$base_path = JS;
				break;
			}
		}

		/*
		 * If the file Open the file and copy it to the buffer. If the file doesn't
		 * exist then log it amd set add an empty string to the buffer.
		 */

		if( filesize( $base_path . $file['url'] . '.' . $type ) > 0 ){
			$Fpointer = fopen( $base_path . $file['url'] . '.' . $type, 'r' );
			$this->buffer[$type] .= fread( $Fpointer, filesize( $base_path . $file['url'] . '.' . $type ) );
			fclose( $Fpointer );
		} else {
			$this->log .= "| ! | ERROR: '{$file['key']}' - File is corrupt or missing.\n";
			return false;
		}

		/*
		 * Add the source file's key to the signature so it can be used
		 * to create a file name.
		 */
		$this->signature[$type] .= $file['key'];
	    return true;
	}




	private function get_merged( $type ){

		/*
		 * If the buffer is populated.
		 */
		if( $this->buffer[$type] != '' ){

			//set the cache directory
			$cache_dir = WWW_ROOT . $type . DS . 'c' . DS;

			//Set the base directory based on merge file type.
			switch( $type ){
				case 'css':
					$base_dir = CSS;
				break;
				case 'js':
					$base_dir = JS;
				break;
			}

			/*
			 * Check to see if the cache directory has been created. Create it if it
			 * does not exist.
			 */
			if( ! file_exists( $cache_dir ) ){
				if( ! @mkdir( $cache_dir, '0777' ) ){

					/*
					 * If The process is denied access to the file system save an error
					 * to the log and return false.
					 */
					$this->log .= "| ! | ERROR: Failed to make the '{$type}/c' directory.\n";
					return false;
				}

				/*
				 * If the directory was created correctly log it.
				 */
				$this->log .= "|   | NEWDIR: The directory '{$type}/c' has been created.\n";
			}

			/*
			 * Generate a file record new cache file so it can be loaded like a standard file.
			 */
			$file = $this->generate_file_name( $type );
			$file_dir = $cache_dir . $file . '.' . $type;
			$file = array(
				'key' => $file,
				'url' => 'c' . DS . $file
			);

		    /*
		     * Merge the defaults mask to add missing structure
		     */
			$file = $this->mask_array( $this->default_file_vals[$type], $file );

			/*
			 * Check the modified date from the source folder and the cache directory,
			 * then compare them. If the source directory has newer files than the cache
			 * directory or if an older cache of the same name does not exist then
			 * continue to rebuild the cache. Otherwise load the old cache.
			 */
			if(
				( $this->dirmtime( $cache_dir ) <  $this->dirmtime( $base_dir ) ) ||
				( ! file_exists( $file_dir . '.' . $type ) )
			){

				//Log the creation of a new cache
				$this->log .= "| # | NEW CACHE: The {$type} has been dumped to cache '{$file['key']}'.\n";

				//Create a new cache file and dump the buffer into it.
				$Fpointer = fopen( $file_dir, 'w+');

				//run filters
				if( $type == 'css' ){
					
					if( $this->css_tidy ){

						App::import( 'Vendor', 'Hrl.csstidy', array( 'file' => 'csstidy' . DS . 'class.csstidy.php' ) );
						$this->CT = new csstidy;
						$this->CT->parse( $this->buffer['css'] );
						$this->buffer['css'] = $this->CT->print->plain();
					    
					}

				}

				fwrite( $Fpointer, $this->buffer[$type] );
				fclose( $Fpointer );

			} else {

				//Note the use of an old cache in the log.
				$this->log .= "| # | CACHE: Loaded the cached {$type} from '{$file['key']}'.\n";
			}

			//load the file
			$this->buffer( $type, $file );

		}

	}





	/**
	 * Gets the modification date of the most recently touched
	 * file within a directory. It will work on complex nesting
	 * without problems.
	 *
	 * @param  $directory
	 * @param bool $recursive
	 * @return int
	 */
	private function dirmtime($directory, $recursive = true) {

		//grab all of the files in the directory
		$allFiles = $this->allFiles($directory, $recursive);

		//set a variable to store the most recent timestamp in
		$highestKnown = 0;

		//loop through the files and grab there timestamp
		foreach( $allFiles as $val ){

			//get the time modified on the file.
			$currentValue = filemtime($val);

			/*
			 * if the date is more recent then the the last most
			 * recent date then save it
			 */
			if( $currentValue > $highestKnown ){
				$highestKnown = $currentValue;
			}

		}

		//return the most recent time modified
		return $highestKnown;

	}




	/**
	 * This method allows the hrl to find all files in the css and js
	 * directories. It allows users to nest and organize there css and
	 * js directories any way they want without having to worry about
	 * the hrl missing nested directories.
	 *
	 * @param  $directory
	 * @param bool $recursive
	 * @return array
	 */
	private function allfiles( $directory, $recursive = true ) {

		//create an empty results array
		$result = array();

		//open the directory and save its memory handle
		$handle =  opendir( $directory );

		//loop through the contents of the directory
		while ( $datei = readdir( $handle ) ){

			//skip the relative directory links (../ and ./)
			if ( ( $datei != '.' ) && ( $datei != '..' ) ){

				//grab the current item
				$file = $directory . $datei;

				/*
				 * if the item is a directory and the method is acting
				 * recursively then re execute on that sub directory,
				 * save the output to the results, and continue.
				 */
				if ( is_dir( $file ) ) {
					if ( $recursive ) {
						$result = array_merge( $result, $this->allFiles( $file . DS ) );
					}

				/*
				 * if the item is a file then add it to the results.
				 */
				} else {
					$result[] = $file;
				}

			}

		}

		//close the directory.
		closedir( $handle );

		// the array of files
		return $result;
	}




	/**
	 * Creates a unique file name (hash) to be used when creating caches.
	 *
	 * @param  $type
	 * @return string
	 */
	private function generate_file_name( $type ) {

		/*
		 * Generate and return a cache name using sha1 and the
		 * files signature.
		 */
		return sha1( $this->signature[$type] );
	    
	}
}