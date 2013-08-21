<?php
/**
 * This is an abstract class for use in creating objects with privileges
 * (user groups, users, etc)
 *
 * Note that ORM relations for privileges must be defined within the subclass
 * so that the relations will return that subclass instead of an instance
 * of Privileged. Additionally, if you add any other "privileged" objects
 * besides Group and User, you will need to add to the belongs_to ORM for
 * the Privilege model.
 *
 * @package  Doorman
 * @author  Jason Raede <jason@torchedm.com>
 */

namespace Doorman;

abstract class Privileged extends \Orm\Model {

	/**
	 * Array of the privileges this object has
	 * @var array
	 */
	protected $_privileges = array();


	/**
	 * Gets the privileges of this object. 
	 * 
	 * Privileges are assigned per user group or per user, and some are granted
	 * to all logged in users
	 * 
	 * Returns an array of privilege strings (object.action)
	 * 
	 * @return  array
	 */
	public function get_privileges() {
		if(!empty($this->_privileges)) return $this->_privileges;
		
		$privileges = $this->privileges ?: array();

		if($this instanceof \Doorman\User) {
			foreach($this->groups as $group) {
				$privileges = array_merge($privileges, $group->privileges);
			}
		}
		
		$list = array();
		foreach($privileges as $privilege) {
			$list[] = $privilege->object.(($privilege->action) ?'.'.$privilege->action : '').(($privilege->object_id) ? '.'.$privilege->object_id : '');
		}
		
		/**
		 * If this is an User object,
		 * combine it with basic user and guest privileges from config
		 *
		 * Note that if the user isn't logged in, the \Auth::has_access method checks
		 * against the doorman.auth.guest_privileges configuration
		 *
		 * @see  \Doorman\Doorman::has_access()
		 */
		if($this instanceof \Doorman\User) {
			$list = array_merge($list, (is_array(\Config::get('doorman.user_privileges')) ? \Config::get('doorman.user_privileges') : array()));
			$list = array_merge($list, (is_array(\Config::get('doorman.guest_privileges')) ? \Config::get('doorman.guest_privileges') : array()));
		}
		
		$this->_privileges = $list;
		return array_unique($list);
	}



	/**
	 * Assigns a privilege to the object if it doesn't already have that privilege
	 * 
	 * @param  string $privilege
	 */
	public function assign_privilege($privilege) {
		/**
		 * Make sure the string is formed correctly
		 */
		if($privilege != 'all' && !preg_match('/([A-Za-z_]+)(\.)([A-Za-z_]+)((\.)([0-9]+))?/', $privilege)) {
			throw new \Exception('Malformed privilege string: '.$privilege);
		}
		else {
			$privileges = $this->get_privileges();
			if(in_array($privilege, $privileges)) 
				return true;
			else {
				$split = explode('.', $privilege);
				$object = $split[0];
				$action = $split[1];
				$id = $split[2];
				$info = static::relations('privileges');
		
				$privilege_class = $info->model_to;
				$new_privilege = $privilege_class::forge(array('object'=>$object, 'action'=>$action, 'object_id'=>$id));
				$new_privilege->save();

				$this->privileges[] = $new_privilege;
				$this->save();
				return $new_privilege->id;
			}
		}
		
	}


	public function revoke_privilege($privilege) {
		$info = static::relations('privileges');
		$split = explode('.', $privilege);
		$privilege_class = $info->model_to;
		$query = $privilege_class::query();
		if($split[0]) {
			$query->where('object', $split[0]);
		}
		if($split[1]) {
			$query->where('action', $split[1]);
		}
		if($split[2]) {
			$query->where('object_id', $split[2]);
		}

		// Is this a group or a user
		if($this instanceof \Doorman\User) {
			$query->where('user_id', '=', $this->id);
		}
		else {
			$query->where('group_id', '=', $this->id);
		}

		$privilege_to_revoke = $query->get_one();

		if($privilege_to_revoke) {
			$privilege_to_revoke->delete();
		}

	}




}