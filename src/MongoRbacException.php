<?php

namespace Oonix\Mongo;

class MongoRbacException extends \Exception {

	private $_metaData;
	
	public function __construct($msg, array $metaData = null){
		$this->_metaData = $metaData;
		parent::__construct($msg);
	}
	
	public function getMetaData(){
		return $this->_metaData;
	}

}

?>
