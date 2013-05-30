<?php
namespace Doorman;

/**
 * This is the doorman user. It holds all the methods for validating a user, checking his/her privileges,
 * and validating user CRUD functions based on existing database entries.
 *
 * Note that if you extend this class and want to add additional ORM properties, you MUST include
 * the ORM properties on this class in your child class.
 *
 * @package  Doorman
 * @author  Jason Raede <jason@torchedm.com>
 */
class User extends Privileged {

	/**
	 * Name of the users table in the database;
	 * @var string
	 */
	protected static $_table_name = 'users';

	/**
	 * 1:1 database columns, from the Data Fields package
	 * @var array
	 * @see  \DataFields\Model::_db_cols
	 */
	protected static $_db_cols = array();


	protected static $_doorman_instance = 'default';

	public static function set_doorman_instance($instance) {
		static::$_doorman_instance = $instance;
	}


	public function get_doorman_instance() {
		return \Doorman::instance(static::$_doorman_instance);
	}

	
	public function get_doorman_config($config) {
		$doorman = \Doorman::instance(static::$_doorman_instance);
		return $doorman->get_config($config); 
	}


	
	/**
	 * Properties as defined by the Data Fields package
	 * @var array
	 */
	protected static $_properties = array(
		'id'=>array(
		    'label'=>'ID',
		    'fieldtype'=>'ID'
		),
	    'username'=>array(
		   'label'=>'Username',
		   'fieldtype'=>'\\Doorman\\DataFields\\Fields\\Username',
		   'unique_test'=>'\\Doorman\\User::username_is_unique'
	    ),
	    'email'=>array(
		   'label'=>'E-Mail',
		   'fieldtype'=>'\\Doorman\\DataFields\\Fields\\Email',
		   'unique_test'=>'\\Doorman\\User::email_is_unique'
	    ),
	    'password'=>array(
		   'label'=>'Password',
		   'fieldtype'=>'\\Doorman\\DataFields\\Fields\\Password',
		   'validation'=>array('required'),
		   'verification_method'=>'\\Doorman\\User::verify_password'
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
			'key_through_from' => 'user', 
			'table_through' => 'doorman_group_assignments', 
			'key_through_to' => 'group',
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

	/**
	 * If there's a config option to return a different user object (must extend this class)
	 * then it's saved here with late static binding
	 * @var boolean
	 */
	protected static $_user_class = null;
	


	/**
	 * Retrieves the user class and binds it with LSB if it is set to null. 
	 * @return string	 
	 */
	protected static function _user_class() {
		if(is_null(static::$_user_class)) {
			$class = \Config::get('doorman.user_class');
			if($class) static::$_user_class = $class;
			else static::$_user_class = '\\Doorman\\User';
		}

		return static::$_user_class;
	}
	
	public static function get_by_username($username) {
		$class = static::_user_class();
		$user = $class::find()->where('username', '=', $username)->get_one();
		if($user) return $user;
		return false;
	}
	public static function get_by_email($email) {
		$class = static::_user_class();
		$user = $class::find()->where('email', '=', $email)->get_one();
		if($user) return $user;
		return false;
	}
	
	/**
	 * Checks if a username is unique, optionally ignoring a user with a certain ID (ie, 
	 * if they are editing their profile you want to ignore their own username). No need to 
	 * use the user-defined user class since it would have these same methods anyway.
	 * @param  string $username
	 * @param  int $id 
	 * @return bool
	 */
	public static function username_is_unique($username, $id = null) {
		if($id)
			$check = static::find()->where('username', '=', $username)->where('id', '!=', $id)->count();
		else
			$check = static::find()->where('username', '=', $username)->count();
		return ($check) ? false : true;
	}
	
	/**
	 * Checks if an email is unique, optionally ignoring a user with a certain ID. No need
	 * to use the user-defined user class since it would have these same methods anywa.
	 * @param  string $email
	 * @param  int $id   
	 * @return bool   
	 */
	public static function email_is_unique($email, $id = null) {
		if($id)
			$check = static::find()->where('email', '=', $email)->where('id', '!=', $id)->count();
		else
			$check = static::find()->where('email', '=', $email)->count();
		return ($check) ? false : true;
	}
	
	public static function get_by_login($identifier, $password) {
		
		$doorman = \Doorman::instance(static::$_doorman_instance);
		$class = $doorman->get_config('user_class');
		$password = $doorman->hash_password($password);
		
		$id_type = $doorman->get_config('identifier');

		$user = $class::find('first', array(
			'where'=>array(
				array('password', '=', $password),
				array($id_type, '=', $identifier)
			)
		));

		if($user) return $user;
		return false;
	}
	
	/**
	 * Makes sure a password is the correct one for a user with a certain ID
	 * @param  string $password The plain text, pre-hashed password
	 * @param  int $id 
	 * @return bool
	 */
	public static function verify_password($password, $id) {
		$doorman = \Doorman::instance(static::$_doorman_instance);
		$hashed = $doorman->hash_password($password);
		\Log::debug('Checking for password '.$password.' where id is '.$id);
		$check = static::find()->where('id', '=', $id)->where('password', '=', $hashed)->get_one();
		return ($check) ? true : false;
	}
	
	/**
	 * Updates the user's login hash, to prevent the same user from logging in in two
	 * different places.
	 * @param  string $hash
	 */
	public function update_hash($hash) {
		\Log::debug('Updating hash');
		$this->login_hash = $hash;
		$this->save();
	}
	
	/**
	 * Placeholder function meant for overwriting in child classes.
	 * @return string
	 */
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