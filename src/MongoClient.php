<?php

namespace Oonix\Mongo;

class MongoClient extends \MongoClient {
	
	/**
	 * \PhpRbac\Rbac object
	 */
	protected $_rbac;
	
	/**
	 * Function callback to return the current integer user ID; caching does not occur and is the responsiblity of the developer should it be warranted
	 */
	protected $_rbacUserIdCallback;
	
	/**
	 * Function callback to return custom permission paths in place of ::rbacPath() - if this is not callable, or the returned value not a string then the default from ::rbacPath() is used
	 */
	protected $_rbacPermPathCallback;
	
	/**
	 * If the relevant Rbac permission does not exist, is access allowed by default?
	 * Setting this value to false requires more work but also explicit access provision and hence improved security.
	 */
	protected $_rbacDefaultAllow = false;
	
	/**
	 * Extend the standard constructor to require 3 prepended arguments but otherwise operate as the core \MongoClient.
	 * Uses func_get_args() rather than explicitly stating the arguments to allow for the possibility of additional arguments add in later core versions.
	 *
	 * @param $rbac		\PhpRbac\Rbac		an instance of the RBAC object
	 * @param $userId		callable				a function callback to return the current integer user ID; caching does not occur and is the responsiblity of the developer should it be warranted; expects 0 for guest
	 * @param $permPath	callable				a function callback to return custom permission paths in place of ::rbacPath() - if this is not callable, or the returned value is not a string then the default from ::rbacPath() is used; should take the same arguments as ::rbacPath() as well as an additional callable reference to ::rbacPath()
	 */
	public function __construct(\PhpRbac\Rbac $rbac, callable $userId, callable $permPath = null){
		$args = func_get_args();
		$this->_rbac = array_shift($args);
		$this->_rbacUserIdCallback = array_shift($args);
		$this->_rbacPermPathCallback = array_shift($args);
		call_user_func_array(array('parent', '__construct'), $args);
	}
	
	/**
	 * Return the Rbac object
	 */
	public function rbac(){
		return $this->_rbac;
	}
	
	/**
	 * Select the DB and return an extended \Oonix\Mongo\MongoDB object.
	 */
	public function selectDB($name){
		return new \Oonix\Mongo\MongoDB($this, $name);
	}
	
	/**
	 * Magic getter as a convenience wrapper for ::selectDB()
	 */
	public function __get($dbname){
		return $this->selectDB($dbname);
	}
	
	/**
	 * Select a collection within a database and return an extended \Oonix\Mongo\MongoCollection object.
	 */
	public function selectCollection($db, $collection){
		$db = $this->selectDB($db);
		return $db->selectCollection($collection);
	}
	
	/**
	 * Get or set the current default-allow setting.
	 * Supply the optional single argument to set the value; anything other than an explicit boolean true is considered false.
	 * The current value (unmodified with zero arguments or immediately after being set) is returned.
	 */
	public function rbacDefaultAllow($set = null){
		if(!is_null($set)){
			$this->_rbacDefaultAllow = ($set === true);
		}
		return $this->_rbacDefaultAllow;
	}
	
	/**
	 * A centralised function to enforce RBAC permissions. Builds the permission path, checks for its existence, and returns the result of Rbac::check() or default if the permission does not exist.
	 *
	 * @param $action 	string		one of a set of predefined actions; these can be seen in the switch statement of ::rbacPath()
	 * @param $obj			object		the object in the \Oonix\Mongo namespace that is making the check; these objects are expected to provide $this for the value
	 * @param $metaData	array			any relevant data that the particular action may need in building the permission path (e.g. field name); note that database and collection names can be source from $obj
	 * @throws \Oonix\Mongo\MongoRbacException if the user is [not allowed to to perform the action] or [the action does not exist and the defaulAllow has not been explicitly set to true]
	 */
	public function rbacCheck($action, $obj, array $metaData = null){
		$path = $this->rbacPath($action, $obj, $metaData);
		
		$allow = false;
		$pathId = $this->_rbac->Permissions->pathId($path);
		if(is_null($pathId)){
			if($this->rbacDefaultAllow() !== true){
				throw new \Oonix\Mongo\MongoRbacException("Default deny for action '{$action}' as the permission path '{$path}' does not exist.", $metaData);
			}
		}
		else {
			$userId = call_user_func($this->_rbacUserIdCallback);
			if(($this->_rbac->check($pathId, $userId) !== true) && ($this->_rbac->check($pathId, 0) !== true)){ //0 for guest
				throw new \Oonix\Mongo\MongoRbacException("Permission denied for user '{$userId}' to perform the action '{$action}' with the permission path '{$path}'.", $metaData);
			}
		}
	}
	
	/**
	 * Separate function allows for recursive path building.
	 * Basic format: /mongo/db/collection/sub-collection/${insert,find,update,remove}/${sub-command}
	 * See rbacCheck for parameter details.
	 */
	public function rbacPath($action, $obj, array $metaData = null){
		if(is_callable($this->_rbacPermPathCallback)){
			$path = call_user_func_array($this->_rbacPermPathCallback, array($action, $obj, $metaData, $this));
			if(is_string($path)){
				return $path;
			}
		}
		$parts = explode("_", $action, 2);
		switch($parts[0]){
			case "collection":
				$base = "/mongo/{$obj->getDB()->getName()}/".(str_replace(".", "/", $obj->getName()));
				switch($parts[1]){
					case "insert":
						return "{$base}/\$insert";
					case "distinct":
					case "findOne":
					case "find":
						return "{$base}/\$find";
					case "count":
						return $this->rbacPath("collection_find", $obj, $metaData)."/\$count";
					case "findAndModify":
					case "update":
						return "{$base}/\$update";
					case "remove":
						return "{$base}/\$remove";
					case "drop":
						return "{$base}/\$drop";
				}
				break;
		}
		throw new \Oonix\Mongo\MongoRbacException("The action '{$action}' does not exist.");
	}
	
}
