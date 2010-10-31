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
 * See the readme for instructions.
 *
 * @verson 1.0
 * @author Robert Hurst
 */
class HrlHelper extends AppHelper {

	// PUBLIC
	// ---------------------------------------------------------

	/**
	 * @description Queues a css file(s). See the readme for accepted format.
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

	//include the html helper
	public $helpers = array( 'Html' );

	// PRIVATE
	// ---------------------------------------------------------

	private $log = "\n\n                                 HIERARCHICAL RESOURCE LOADER LOG\n\n====================================================================================================\n\n";

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

	private function queue( $type, $files ){

		//make sure the files array contains one or more arrays with a key of url
		if( is_array( $files ) && is_array( $first = reset( $files ) ) && isset( $first['url'] ) ) {

			foreach( $files as $file ) {

				//parse the file against the default values
				$file = parse_args($this->default_file_vals[$type], $file, true);

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

					$this->log .= "|   | REQUIRED: File {$file_key} requirements...\n";

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
					if( file_exists( CSS . $file['url'] ) || @preg_match( '/http/', $file['url']) ){
						switch( $type ){
							case 'css':
								$output .= $this->Html->css( $file['url'], array( 'media' => $file['media'], 'inline' => true ) ) . "\n";
							break;

							case 'js':
								$output .= $this->Html->script( $file['url'], array( 'inline' => true ) ) . "\n";
							break;
						}
						$this->log .= "| # | LOADED: File {$file['key']} is loaded. \n";

						//add the file key to the loaded_keys array and remove the file from the includes array
						$loaded_keys[] = $file_key;
					} else {
						$this->log .= "| ! | FAILED: File {$file['key']} does not exist.\n";
					}

					unset( $this->includes[$type]['Files'][$file_key] );
				}

			}
		}

		$this->log .= "----------------------------------------------------------------------------------------------------\n\n";

		return $output;
	}
}