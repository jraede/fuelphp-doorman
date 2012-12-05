<?php
namespace Doorman;

class User extends Privileged {
	protected static $_table_name = 'users';
	protected static $_db_cols = array();
	
	protected static $_properties = array(
		'id'=>array(
		    'label'=>'ID',
		    'fieldtype'=>'ID'
		),
	    'username'=>array(
		   'label'=>'Username',
		   'fieldtype'=>'Username'
	    ),
	    'email'=>array(
		   'label'=>'E-Mail',
		   'fieldtype'=>'Email'
	    ),
	    'password'=>array(
		   'label'=>'Password',
		   'fieldtype'=>'Password'
	    ),
	    'login_hash'=>array(
		   'fieldtype'=>'System',
		   'constraint'=>'255',
		   'coltype'=>'varchar'
	    )
	);
	
	protected static $_many_many = array(
	    'groups'=>array(
			'key_from' => 'id',
			'key_through_from' => 'user', // column 1 from the table in between, should match a posts.id
			'table_through' => 'doorman_group_assignments', // both models plural without prefix in alphabetical order
			'key_through_to' => 'group', // column 2 from the table in between, should match a users.id
			'model_to' => '\\Doorman\\Group',
			'key_to' => 'id',
			'cascade_save' => true,
			'cascade_delete' => false
	    )	
	);
	
	protected static $_has_many = array(
		'privileges'=>array(
		    'key_from'=>'id',
		    'model_to'=>'\\Doorman\\Privilege',
		    'key_to'=>'user_id',
		    'cascade_save'=>true,
		    'cascade_delete'=>true
		)
	);
	

	
	public static function get_by_username($username) {
		$user = self::find()->where('username', '=', $username)->get_one();
		if($user) return $user;
		return false;
	}
	public static function get_by_email($email) {
		$user = self::find()->where('email', '=', $email)->get_one();
		if($user) return $user;
		return false;
	}
	
	public static function username_is_unique($username, $id = null) {
		if($id)
			$check = self::find()->where('username', '=', $username)->where('id', '!=', $id)->count();
		else
			$check = self::find()->where('username', '=', $username)->count();
		return ($check) ? false : true;
	}
	
	public static function email_is_unique($email, $id = null) {
		if($id)
			$check = self::find()->where('email', '=', $email)->where('id', '!=', $id)->count();
		else
			$check = self::find()->where('email', '=', $email)->count();
		return ($check) ? false : true;
	}
	
	public static function get_by_login($identifier, $password) {
		$password = Doorman::hash_password($password);
		
		
		$id_type = \Config::get('doorman.identifier');
		$user = static::find('first', array(
		    'where'=>array(
			array('password', '=', $password),
			array($id_type, '=', $identifier)
		    )
		));
		if($user) return $user;
		return false;
	}
	
	public static function verify_password($password, $id) {
		$hashed = Doorman::hash_password($password);
		$check = static::find()->where('id', '=', $id)->where('password', '=', $hashed)->get_one();
		return ($check) ? true : false;
	}
	
	public function update_hash($hash) {
		$this->login_hash = $hash;
		$this->save();
	}
	
	public function display_name() {
		return $this->username;
	}
	
	
	
	/**
	 * Returns TRUE if the user is a member of the group
	 * @param int|string $group
	 * @return bool
	 */
	public function member_of($group) {
		if(is_numeric($group)) {
			return in_array($group, array_keys($this->groups));
		}
		else {
			$group = Group::find('first', array('where'=>array(
			    array('name', 'LIKE', $group)
			)));
			if(!$group) return false;
			return in_array($group->id, array_keys($this->groups));
		}
	}
	
	/**
	 * Returns the user id
	 * 
	 * @return int
	 */
	public function id() {
		return $this->id;
	}
	
	/**
	 * Assigns the user to a group
	 * 
	 * @param int $group
	 * @return \InternalResponse
	 */
	public function assign_to($group) {
		$response = \InternalResponse::forge();
		$group = Group::find($group);
		if(!$group) $response->error('Group not found');
		else {
			$this->groups[$group->id] = $group;
			$this->save();
			$response->success();
		}
		return $response;
	}
	
	/**
	 * Removes the user from a group
	 * 
	 * @param int $group
	 * @return \InternalResponse
	 */
	public function remove_from($group) {
		$response = \InternalResponse::forge();
		$group = Group::find($group);
		if(!$group) $response->error('Group not found');
		else {
			unset($this->groups[$group->id]);
			$this->save();
			$response->success();
		}
		return $response;
	}
	
	
}