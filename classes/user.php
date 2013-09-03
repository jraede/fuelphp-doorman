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
	protected static $_table_name = 'doorman_users';

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


	protected static $_observers = array(
		'Orm\Observer_CreatedAt' => array(
			'events' => array('before_insert'),
			'mysql_timestamp' => true,
		),
		'Orm\Observer_Self' => array(
			'events' => array('before_save'),
		),
		'Orm\Observer_Validation' => array(
   			'events' => array('before_save')
   		),
   		'Orm\Observer_Self' => array(
   			'events'=>array('before_save')
   		)
	);

	protected static $_eav = array(
		'metas' => array(		// we use the statistics relation to store the EAV data
			'attribute' => 'key',	// the key column in the related table contains the attribute
			'value' => 'value',		// the value column in the related table contains the value
		)
	);


	/**
	 * Properties as defined by the Data Fields package
	 * @var array
	 */
	protected static $_properties = array('id',
		'username'=>array(
			'data_type' => 'varchar',
			'null' => false,
			'validation' => array(
				'required',
				'unique_username'
			),
		),
		'password'=>array(
			'data_type'=>'varchar',
			'null'=>false
		),'login_hash','last_login','created_at');
	
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
		),
		'metas'=>array(
		    'key_from'=>'id',
		    'model_to'=>'\\Doorman\\User_Meta',
		    'key_to'=>'user_id',
		    'cascade_save'=>true,
		    'cascade_delete'=>true
		)
	);


	public static function _validation_unique_username($val, $id = null) {
		if($id) {
			return !(static::query()->where('username', $val)->where('id', '!=', $id)->count());
		}
		else {
			return !(static::query()->where('username', $val)->count());
		}
	}


	public function _event_before_save() {
		if($this->is_changed('password')) {
			// Hash the password
			$this->password = \Doorman::instance(static::$_doorman_instance)->hash_password($this->password);
		}
	}


	

	/**
	 * If there's a config option to return a different user object (must extend this class)
	 * then it's saved here with late static binding
	 * @var boolean
	 */
	protected static $_user_class = null;
	


	/**
	 * @return string	 
	 */
	protected static function _user_class() {
		$doorman = \Doorman::instance(static::$_doorman_instance);
		return $doorman->get_config('user_class');
	}
	
	public static function get_by_username($username) {
		$class = static::_user_class();
		$user = $class::query()->where('username', '=', $username)->get_one();
		if($user) return $user;
		return false;
	}
	public static function get_by_email($email) {
		$class = static::_user_class();
		$user = $class::query()->where('email', '=', $email)->get_one();
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
			$check = static::query()->where('username', '=', $username)->where('id', '!=', $id)->count();
		else
			$check = static::query()->where('username', '=', $username)->count();
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
			$check = static::query()->where('email', '=', $email)->where('id', '!=', $id)->count();
		else
			$check = static::query()->where('email', '=', $email)->count();
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
		$check = static::query()->where('id', '=', $id)->where('password', '=', $hashed)->get_one();
		return ($check) ? true : false;
	}
	
	/**
	 * Updates the user's login hash, to prevent the same user from logging in in two
	 * different places.
	 * @param  string $hash
	 */
	public function update_hash($hash) {
		
		//\Log::debug('Updating hash');
		$this->login_hash = $hash;
		//\Log::debug('About to save with hash set to '.$this->login_hash);
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
	public function assign_to(\Doorman\Group $group) {

		$this->groups[$group->id] = $group;
		$this->save();
	}
	
	/**
	 * Removes the user from a group
	 * 
	 * @param int $group
	 * @return \InternalResponse
	 */
	public function remove_from(\Doorman\Group $group) {
		unset($this->groups[$group->id]);
		$this->save();
	}



	public function meta($key, $val = null) {
		if($key && ($val !== null)) {
			// Setting
			
			if(array_key_exists($key, static::properties())) {
				throw new \Exception('Cannot have a meta property with the same name as object property');
			}
			// Does it already exist? Check by trying to access the property and seeing if 
			// we get an exception
			try {
				$this->$key = $val;
			}
			catch(\OutOfBoundsException $e) {
				$meta = User_Meta::forge(array('user_id'=>$this->id, 'key'=>$key, 'value'=>$val));
				$this->metas[] = $meta;
			}

			$this->save();
		}

		else {
			try {
				$val = $this->$key;
			}
			catch(\OutOfBoundsException $e) {
				$val = false;
			}

			return $val;
		}
	}
	
	
}