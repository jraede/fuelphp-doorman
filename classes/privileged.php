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

abstract class Privileged extends \DataFields\Model {

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
		 * against the concerto.auth.guest_privileges configuration
		 *
		 * @see  \Doorman\Doorman::has_access()
		 */
		if($this instanceof \Doorman\User) {
			$list = array_merge($list, \Config::get('doorman.user_privileges'));
			$list = array_merge($list, \Config::get('doorman.guest_privileges'));
		}
		
		$this->_privileges = $list;
		return array_unique($list);
	}



	/**
	 * Assigns a privilege to the object if it doesn't already have that privilege
	 * 
	 * @param  string $privilege
	 * @return  \InternalResponse
	 */
	public function assign_privilege($privilege) {
		$response = \InternalResponse::forge();
		/**
		 * Make sure the string is formed correctly
		 */
		if($privilege != 'all' && !preg_match('/([A-Za-z_]+)(\.)([A-Za-z_]+)((\.)([0-9]+))?/', $privilege)) {
			$response->error('Malformed privilege string');
		}
		else {
			$privileges = $this->get_privileges();
			if(in_array($privilege, $privileges)) 
				   $response->success();
			else {
				$split = explode('.', $privilege);
				$object = $split[0];
				$action = $split[1];
				
				$this->privileges[] = Auth_Privilege::forge(array('object'=>$object, 'action'=>$action));
				$this->save();
				$response->success();
			}
		}
		return $response;
		
	}




}