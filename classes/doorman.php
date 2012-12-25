<?php
namespace Doorman;


class DoormanException extends \FuelException {}


/**
 * Doorman user authorization class.
 * 
 * Based on Auth package for FuelPHP
 * 
 * @package Doorman
 * @author Jason Raede <jason@torchedm.com>
 */

class Doorman
{

	protected static $_instance = null;
	
	/**
	 * Current logged in user
	 * 
	 * @var \Doorman\User
	 */
	protected $user = false;

	protected static $_auth_drivers = array();
	
	/**
	 * Class used to hash passwords
	 */
	protected $hasher;
	
	protected static $_privileges;


	/**
	 * Initialize the class. Called on bootstrap
	 */
	public static function _init() {
		static::forge();
	}

	public static function forge() {
		static::$_instance = new static();
	}
	
	public static function instance() {
		return static::$_instance;
	}
	
	protected static function _config($value) {
		return \Config::get('doorman.'.$value);
	}
	
	/**
	 * Magic method used to retrieve driver instances and check them for validity
	 *
	 * @param   string
	 * @param   array
	 * @return  mixed
	 * @throws  BadMethodCallException
	 */
	public static function __callStatic($method, $args) {
		$args = array_pad($args, 3, null);
		if(method_exists(static::$_instance, $method)) {
			return call_user_func_array(array(static::$_instance, $method), $args);
		}

		throw new \BadMethodCallException('Invalid method: '.get_called_class().'::'.$method);
	}

	public static function add_auth_driver($driver) {
		if(class_exists($driver)) {
			static::$_auth_drivers[] = $driver;
		}
	}
	
	
	/**
	 * Check for login
	 *
	 * @return  bool
	 */
	protected function check_login() {
		$identifier    = \Session::get('identifier');
		$login_hash  = \Session::get('login_hash');
		$id_type = static::_config('identifier');

		// only worth checking if there's both an identifier and login-hash
		if ( ! empty($identifier) && ! empty($login_hash))
		{
			if (!$this->user || ($this->user && $id_type == 'username' && $this->user->username != $identifier) || ($id_type == 'email' && $this->user->email != $identifier))
			{
				$this->user = ($id_type == 'email') ? User::get_by_email($identifier) : User::get_by_username($identifier);
			}
			// return true when login was verified
			if ($this->user && $this->user->login_hash === $login_hash)
			{
				return true;
			}
		}
		if(count(static::$_auth_drivers)) {
			foreach(static::$_auth_drivers as $driver) {
				if($this->user = $driver::check_login()) {
					return true;
				}
			}
		}
		// no valid login when still here, ensure empty session and optionally set guest_login
		$this->user = false;
		\Session::delete('identifier');
		\Session::delete('login_hash');
		return false;
	}
	
	/**
	 * Checks if the login has been verified, doesn't try to verify like check_login
	 */
	protected function logged_in() {
		return ($this->user) ? true : false;
	}
	
	
	/**
	 * Validates login from HTTP POST request
	 * 
	 * @see \Doorman::login()
	 * @return \InternalResponse
	 */
	protected function validate_login() {
		$validate = \InternalResponse::forge();
		$identifier = static::_config('identifier');
		$check = \Auth::login(\Input::post($identifier), \Input::post('password'));
		if(!$check) {
			$validate->error('Login failed');
		}
		else {
			$validate->success('Login successful');
		}
		
		return $validate;
	}
	
	/**
	 * Log in user
	 *
	 * @param   string
	 * @param   string
	 * @return  bool
	 */
	protected function login($identifier = '', $password = '') {
		if ( ! ($this->user = $this->validate_user($identifier, $password))) {
			$this->user = false;
			\Session::delete('identifier');
			\Session::delete('login_hash');
			return false;
		}
		
		if(static::_config('identifier') == 'username')
			\Session::set('identifier', $this->user->username);
		else
			\Session::set('identifier', $this->user->email);
		
		
		\Session::set('login_hash', $this->create_login_hash());
		\Session::instance()->rotate();
		
		
		return true;
	}
	
	/**
	 * Check the user exists before logging in
	 *
	 * @return  bool
	 */
	protected function validate_user ($identifier = '', $password = '') {
		echo "validating user with id $identifier, pw $password";
		if (empty($identifier) || empty($password)) {
			return false;
		}
		$this->user = User::get_by_login($identifier, $password);
		
		return $this->user ?: false;
	}
	
	/**
	 * Creates a temporary hash that will validate the current login
	 *
	 * @return  string
	 */
	protected function create_login_hash () {
		
		if (empty($this->user))
		{
			throw new \AuthException ('User not logged in, can\'t create login hash.', 10);
		}

		$last_login = \Date::forge()->get_timestamp();
		$login_hash = sha1(static::_config('hash_salt').$this->user->username.$last_login);
		
		
		$this->user->update_hash($login_hash);

		return $login_hash;
	}
	
	/**
	 * Default password hash method
	 *
	 * @param   string
	 * @return  string
	 */
	protected function hash_password($password) {
		return base64_encode($this->hasher()->pbkdf2($password, static::_config('hash_salt'), 10000, 32));
	}
	
	/**
	 * Returns the hash object and creates it if necessary
	 *
	 * @return  PHPSecLib\Crypt_Hash
	 */
	protected function hasher() {
		if ( ! class_exists('PHPSecLib\\Crypt_Hash', false)) {
			import('phpseclib/Crypt/Hash', 'vendor');
		}
		is_null($this->hasher) and $this->hasher = new \PHPSecLib\Crypt_Hash();

		return $this->hasher;
	}
	
	/**
	 * Allows methods to be called on user publicly without modifying the user
	 * 
	 * @throws \BadFunctionCallException
	 */
	public static function user() {
		/**
		 * Initialize the user if not done already
		 */
		if(!static::$_instance->user)
			static::$_instance->check_login();
		
		/**
		 * If still no user, then just return false
		 */
		if(!static::$_instance->user) return false;
		
		return static::$_instance->user;
	}
	
	/**
	 * Checks if the current user can perform an action on an object.
	 * 
	 * If the object has an ID (ie, one of the Concerto objects), access can be granted in the following ways:
	 * 
	 * 1) The user has the **_own privilege and is the owner of the object
	 * 2) The user has the **_offspring privilege and has the edit privilege for an object higher up in the hierarchy
	 * 3) The user has the ** privilege, and can perform this action on all objects
	 * 
	 * @param string $object
	 * @param string $action
	 * @param string $id
	 * @return bool
	 */
	protected function has_access($object, $action, $id) {
		$privileges = $this->get_privileges();
		if(in_array('all', $privileges)) return true;
		/**
		 * If they have that privilege without an object id, they have it for all objects
		 */
		elseif(in_array($object.'.all', $privileges)) return true;
		/**
		 * If they have that privilege without an object id, they have it for all objects
		 */
		elseif(in_array($object, $privileges)) return true;
		elseif(in_array($object.'.'.$action, $privileges)) return true;
		elseif(in_array($object.'.'.$action.'.'.$id, $privileges)) return true;
		
		
		
          return false;
	
	}
	
	protected function get_privileges() {
		if(static::$_privileges) return static::$_privileges;
		
		
		if(!$this->user) static::check_login();
		
		/**
		 * If there's no user, privileges are the list of guest privileges from config
		 */
		if(!$this->user) {
			$privileges = static::_config('guest_privileges');
		}
		
		/**
		 * Otherwise get the privilege list from the current user
		 */
		else
			$privileges = $this->user->get_privileges();
		
		if(in_array('all', $privileges)) return true;
		
		/**
		 * Now expand all object-independent privileges, like object.create, to include all objects
		 */
		$types = \Concerto\Model\Object::get_all_types();
		foreach($privileges as $privilege) {
			$split = explode('.', $privilege);
			if($split[0] == 'object') {
				foreach($types as $type) {
					$privileges[] = $type.'.'.$split[1];
				}
			}
		}
		static::$_privileges = $privileges;
		return $privileges;
	}
	
	
	/**
	 * Logout user
	 *
	 * @return  bool
	 */
	public function logout() {
		$this->user = false;
		if(count(Doorman::$_auth_drivers)) {
			foreach(Doorman::$_auth_drivers as $driver) {
				if(method_exists($driver, 'logout'))
				$driver::logout();
			}
		}
		\Session::delete('identifier');
		\Session::delete('login_hash');
		return true;
	}

	
}
