<?php

namespace Oonix\Mongo;

class MongoCollection {
	
	protected $_collection;
	
	protected $_rbacData = array();
	
	/**
	 * An array of methods that don't need to be checked against RBAC.
	 */
	protected $_skipRbac = array("getName");
	
	public function __construct(){
		$args = func_get_args();
		$r = new \ReflectionClass("\MongoCollection");
		$this->_collection = $r->newInstanceArgs($args);
		$this->_rbacData = $args;
	}
	
	/**
	 * A MongoRbacException will be thrown if the current user is not allowed to perform the action.
	 */
	public function rbacCheck($action, array $metaData = null){
		$this->getDB()->getConn()->rbacCheck("collection_{$action}", $this, $metaData);
	}
	
	public function getDB(){
		return $this->_rbacData[0];
	}
	
	public function __get($name){
		return new \Oonix\Mongo\MongoCollection($this->getDB(), "{$this->getName()}.{$name}");
	}
	
	public function skipRbac(array $skip = null){
		if(is_array($skip)){
			$this->_skipRbac = $skip;
		}
		return $this->_skipRbac;
	}
	
	public function skipRbacMerge(array $skip){
		return $this->_skipRbac = array_merge($this->_skipRbac, $skip);
	}
	
	public function __call($func, $args){
		$toCall = array($this->_collection, $func);
		if(!is_callable($toCall)){
			throw new MongoRbacException("MongoCollection::{$func}() does not exist.");
		}
		if(!in_array($func, $this->_skipRbac)){
			$this->rbacCheck($func, $args);
		}
		return call_user_func_array($toCall, $args);
	}
	
	public function getRbacPath(){
		return "{$this->getDB()->getName()}/".(str_replace(".", "/", $this->getName()));
	}

}

?>
