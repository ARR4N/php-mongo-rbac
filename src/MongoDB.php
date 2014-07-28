<?php

namespace Oonix\Mongo;

class MongoDB extends \MongoDB {
	
	private $_rbacData = array();
	
	/**
	 * Extend the core constructor and act identically except first check for permission.
	 */
	public function __construct(){
		$args = func_get_args();
		call_user_func_array(array('parent', '__construct'), $args);
		$this->_rbacData = $args;
	}
	
	public function rbacCheck($action, array $metaData = null){
		$this->getConn()->rbacCheck("db_{$action}", $this, $metaData);
	}
	
	public function getConn(){
		return $this->_rbacData[0];
	}
	
	public function getName(){
		return $this->_rbacData[1];
	}
	
	/**
	 * Select a Collection and return an extended \Oonix\Mongo\MongoCollection object.
	 */
	public function selectCollection($name){
		return new \Oonix\Mongo\MongoCollection($this, $name);
	}
	
	/**
	 * Magic getter as a convenience wrapper for ::selectCollection()
	 */
	public function __get($name){
		return $this->selectCollection($name);
	}
	
}
