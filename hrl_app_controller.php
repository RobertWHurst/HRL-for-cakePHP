<?php
class HrlAppController extends AppController {

	public function beforeFilter () {

		App::import( 'Vendor', 'cssTidy', array( 'file' => 'csstidy' . DS . 'class.csstidy.php' ) );

	}
	
}