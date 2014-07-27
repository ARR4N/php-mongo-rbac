<?php

namespace Oonix\Mongo;

class MongoCollection extends \MongoCollection{
	
	private $_rbacData = array();
	
	/**
	 * Extend the core constructor and act identically except first check for permission.
	 */
	public function __construct(){
		$args = func_get_args();
		call_user_func_array(array('parent', '__construct'), $args);
		$this->_rbacData = $args;
		$this->rbacCheck("construct"); //parent::construct() is called first because it saves the $db property; doing this manually caused an exception to be throwns
	}
	
	public function rbacCheck($action, array $metaData = null){
		$this->db->getConn()->rbacCheck("collection_{$action}", $this, $metaData);
	}
	
	public function getName(){
		return $this->_rbacData[1];
	}

}

?>
