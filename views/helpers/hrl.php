<?php
/**
 * This is the Hierarchical Resource Loader or HRL. It is cakePHP3 helper
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

	// PUBLIC
	// ---------------------------------------------------------

	/**
	 * Setting this to true will enable merging the output of the
	 * hrl into single cache files.
	 *
	 * @var bool
	 */
	public $merge = true;

	/**
	 * Queues a css file(s). See the readme for accepted format.
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
	 * Queues a javascript file(s). See the readme for accepted format.
	 *
	 * @param null $files
	 * @return void
	 */
	public function js( $files = null ){

		if( ! $files ){
			echo $this->render( 'js' );
		} else {
			$this->queue( 'js', $files );
		}
	}

	/**
	 * Prints a log created while css and javascript are reordered and printed.
	 *
	 * @param bool $comment_out If true the log will be an html comment instead of being wrapped in <pre> tags.
	 * @return void
	 */
	public function print_log($comment_out = false){
		if( ! $comment_out ){
			echo '<pre>' . $this->log . '</pre>';
		} else {
			echo "<!--\n\n" . $this->log . "\n\n-->";
		}
	}

	//public $css_ext = '.css';
	public $css_ext = '.css';
	public $js_ext = '.js';

	//include the html helper
	public $helpers = array( 'Html' );

	// PRIVATE
	// ---------------------------------------------------------

	private $log = "\n\n                                 HIERARCHICAL RESOURCE LOADER LOG\n\n====================================================================================================\n\n";

	private $buffer = array(
		'css' => '',
		'js' => ''
	);

	private $signature = array(
		'css' => '',
		'js' => ''
	);

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

	private $includes = array(
		'css' => array( 'Files' => array() ),
		'js' => array( 'Files' => array() )
	);


	private function allfiles($directory, $recursive = true) {
		 $result = array();
		 $handle =  opendir($directory);
		 while ($datei = readdir($handle))
		 {
			  if (($datei != '.') && ($datei != '..'))
			  {
				   $file = $directory.$datei;
				   if (is_dir($file)) {
						if ($recursive) {
							 $result = array_merge($result, $this->allfiles($file.'/'));
						}
				   } else {
						$result[] = $file;
				   }
			  }
		 }
		 closedir($handle);
		 return $result;
	}

	private function dirmtime($directory, $recursive = true) {
		 $allFiles = $this->allfiles($directory, $recursive);
		 $highestKnown = 0;
		 foreach ($allFiles as $val) {
			  $currentValue = filemtime($val);
			  if ($currentValue > $highestKnown) $highestKnown = $currentValue;
		 }
		 return $highestKnown;
	}

	private function generate_file_name( $type ) {
		return sha1( $this->signature[$type] );
	}

	private function parse_args($defaults, $arguments, $keep_unset = false) {

		if( ! is_array( $defaults ) || ! is_array( $arguments ) ){
			return $defaults; //just return the defaults (something goofed)
		}

		//copy the defaults
		$results = $defaults;
		foreach($arguments as $key => $argument){

			//if the argument is invalid continue the loop
			if( ! $keep_unset && ! isset( $defaults[$key] ) )
				continue; //the option is invalid

			//if the argument is actually an array of argument
			if(is_array($argument)){
				//if keep_unset is true and the default is not an array add the array
				if($keep_unset && !is_array($defaults[$key])){
					$results[$key] = $argument;
					continue; //advance the loop
				}

				//if the argument is an array then make sure it is valid
				if(!is_array($defaults[$key]))
					continue; //the option is not an array

				//set the suboptions
				$subdefaults = $defaults[$key];
				$results[$key] = $this->parse_args($subdefaults, $argument, $keep_unset);
			} else {

				//just set it
				$results[$key] = $argument;
			}
		}

		return $results;
	}

	private function merge( $type, $file = null ){

		//if the merge is being dumped to a file
		if( $type && ! $file ){

			if( $this->buffer[$type] != '' ){

				//check for the output folder and make it if it doesn't exist
				$cache_dir = WWW_ROOT . '/c' . $type . '/';

				switch( $type ){
					case 'css':
						$base_dir = CSS;
					break;
					case 'js':
						$base_dir = JS;
					break;
				}

				if( ! file_exists( $cache_dir ) ){
					if( ! @mkdir( $cache_dir, '0777' ) ){
						//if the directory could not be made return false
						$this->log .= "| ! | ERROR: Failed to make the 'c{$type}' directory.\n";
						return false;
					}
					$this->log .= "|   | NEWDIR: The directory 'c{$type}' has been created.\n";
				}

				//generate a file record
				$file_url = $this->generate_file_name( $type );
				$file = array(
					'key' => $file_url,
					'url' => $file_url
				);

				if( $this->dirmtime( $cache_dir ) <  $this->dirmtime( $base_dir ) ){

					$this->log .= "| # | NEW CACHE: The {$type} has been dumped to cache file '{$file['key']}'.\n";

					//create a new cache file and dump the buffer into it.
					$Fpointer = fopen( $cache_dir . $file_url . '.' . $type, 'w+');
					fwrite( $Fpointer, $this->buffer[$type] );
					fclose( $Fpointer );
				} else {
					$this->log .= "| # | CACHE: Loaded the cached {$type} from file '{$file['key']}'.\n";
				}

				return $file;

			}

		//add file to the output buffer
		} else if( $type && $file ){

			if( ! @preg_match( '/^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?$/', $file['url']) ){
				switch( $type ){
					case 'css':
						$base_path = CSS;
					break;
					case 'js':
						$base_path = JS;
					break;
				}
			} else {
				$base_path = '';
			}

			//dump the file to the buffer
			$Fpointer = fopen( $base_path . $file['url'] . '.' . $type, 'r' );

			if( filesize( $base_path . $file['url'] . '.' . $type ) > 0 ){
				$Fstring = fread( $Fpointer, filesize( $base_path . $file['url'] . '.' . $type ) );
			} else {
				$this->log .= "| ! | ERROR: File '{$file['key']}' is corrupt or missing.\n";
				$Fstring = '';
			}

			fclose( $Fpointer );
			$this->buffer[$type] .= $Fstring . "\n";

			//save the file key to create a signature from
			$this->signature[$type] .= $file['key'];

		} else {
			return false;
		}
	}

	private function queue( $type, $files ){

		//make sure the files array contains one or more arrays with a key of url
		if( is_array( $files ) && is_array( $first = reset( $files ) ) && isset( $first['url'] ) ) {

			foreach( $files as $file ) {

				//parse the file against the default values
				$file = $this->parse_args($this->default_file_vals[$type], $file, true);

				if( $file['key'] === '' ){
					$try_key = 0;
					while( $file['key'] === '' ){
						if( ! isset( $this->includes[$type]['Files'][$try_key] ) ){
							$file['key'] = $try_key;
						} else {
							$try_key += 1;
						}
					}
				}

				//make sure the file has the required values
				if( is_array( $file ) && is_string( $file['url'] ) && $file['url'] ) {

					//correct bad dependency data format
					if( ! empty( $file['requires'] ) && ! is_array( $file['requires'] ) ) {
						$file['requires'] = array($file['requires']);
					}

					$this->includes[$type]['Files'][$file['key']] = $file;
				} else {
					$this->includes[$type]['Files'][$file['key']] = array( 'key' => $file['key'], 'url' => $file );
				}
			}

		} else {

			//start over after wrapping the single file correctly
			if( is_array( $files ) && ! isset( $files['url'] ) ){
				foreach( $files as $ik => $file ){
					$files[$ik] = array( 'url' => $file );
				}
				$this->queue( $type, $files );
			} else if ( is_array( $files ) ){
				$this->queue( $type, array( $files ) );
			} else {
				$this->queue( $type, array( array( 'url' => $files ) ) );
			}
		}
	}

	private function render( $type ){

		$render_title = strtoupper( $type );

		$this->log .= "{$render_title} FILES\n";
		$this->log .= "----------------------------------------------------------------------------------------------------\n";

		if( empty( $this->includes[$type]['Files']) ){
			$this->log .= "|   Nothing to load...\n";
		}

		//create an array to keep a record of loaded keys in
		$loaded_keys = array();

		$output = '';

		//RENDER IN ORDER
		//continue to output records procedurally until none are left
		while( ! empty( $this->includes[$type]['Files'] ) ){
			foreach( $this->includes[$type]['Files'] as $file_key => $file ){

				//WAITING
				//if the record has dependancies attached strip the ones that have been loaded
				if( ! empty( $file['requires'] ) ){

					$this->log .= "|   | REQUIRED: Proccessing file '{$file_key}' dependancies.\n";

					foreach( $file['requires'] as $dependancy_key => $required_key ){
						//if the requirement does not exist skip to the next file
						if( in_array($required_key, $loaded_keys) ){
							unset( $this->includes[$type]['Files'][$file_key]['requires'][$dependancy_key] );
						} else if( ! isset( $this->includes[$type]['Files'][$required_key] ) ){

							$this->log .= "| ! | - SKIPPED: file requirement {$required_key} does not exist. File {$file_key} will be skipped.\n";

							unset( $this->includes[$type]['Files'][$file_key] );
							continue 2;
						} else {

							$this->log .= "|   | - PENDING: File {$required_key} must load first. postponed...\n";
						}
					}
				}

				//LOADING
				//check the dependancies again, if it is now empty then render the file and add the key as loaded
				if( empty( $file['requires'] ) ){

					//make sure the file exists
					if( $type == 'css' ){
						$base_path = CSS;
						$ext_set = $this->css_ext;
					} else if( $type == 'js' ) {
						$base_path = JS;
						$ext_set = $this->js_ext;
					}

					if( ! is_array( $ext_set ) ){
						$ext_set = array( $ext_set );
					}

					$file_exists = false;
					$file_exists = false;
					foreach( $ext_set as $ext ){
						if( file_exists( $base_path . $file['url'] . $ext ) || @preg_match( '/^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?$/', $file['url']) ){
							$file_exists = true;
						}
					}

					if( $file_exists ){
						if( $this->merge == true ){

							$this->merge( $type, $file );

						} else {
							switch( $type ){
								case 'css':
									$output .= $this->Html->css( $file['url'], null, array( 'media' => $file['media'], 'inline' => true ) ) . "\n";
								break;
								case 'js':
									$output .= $this->Html->script( $file['url'], array( 'inline' => true ) ) . "\n";
								break;
							}
						}
						$this->log .= "| # | LOADED: File '{$file['key']}' is loaded. \n";

						//add the file key to the loaded_keys array and remove the file from the includes array
						$loaded_keys[] = $file_key;
					} else {
						$this->log .= "| ! | FAILED: File '{$file['key']}' does not exist.\n";
					}

					unset( $this->includes[$type]['Files'][$file_key] );
				}
			}
		}

		//check for merged source and add to to the output
		if( ! empty( $this->buffer[$type] ) ){

			//get the merged file
			$bFile = $this->merge( $type );

			switch( $type ){
				case 'css':
					$output .= $this->Html->css( '/c' . $type . '/' . $bFile['url'], null, array( 'media' => 'all', 'inline' => true ) ) . "\n";
				break;
				case 'js':
					$output .= $this->Html->script( '/c' . $type . '/' . $bFile['url'], array( 'inline' => true ) ) . "\n";
				break;
			}

		}

		$this->log .= "----------------------------------------------------------------------------------------------------\n\n";

		return $output;
	}

}