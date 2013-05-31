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

	protected static $_instances = array();
	protected static $_active_instance = false;

	/**
	 * Initialize the class. Called on bootstrap
	 */
	public static function instance($name = null, $config_override = null) {
		$name = $name ?: 'default';
		if(array_key_exists($name, static::$_instances)) {
			return static::$_instances[$name];
		}


		$config = \Config::get('doorman.'.$name);

		if(!$config) {
			$config = \Config::get('doorman.default');
		}


		if(is_array($config_override)) {
			$config = \Arr::merge($config, $config_override);
		}

		if(!is_array($config)) {
			throw new DoormanException('Configuration not found.');
		}

		static::$_instances[$name] = new static($name, $config);
		return static::$_instances[$name];
	}

	/**
	 * Magic method used to call instance methods on the default instance
	 *
	 * @param   string
	 * @param   array
	 * @return  mixed
	 * @throws  BadMethodCallException
	 */
	public static function __callStatic($method, $args) {
		$instances = array_values(static::$_instances);
		$instance = $instances[0];
		if(!$instance) {
			throw new \BadMethodCallException('Invalid method: '.get_called_class().'::'.$method);
		}

		$args = array_pad($args, 3, null);
		if(method_exists($instance, $method)) {
			return call_user_func_array(array($instance, $method), $args);
		}

		throw new \BadMethodCallException('Invalid method: '.get_called_class().'::'.$method);
	}


	protected $_name;

	/**
	 * Current logged in user
	 * 
	 * @var \Doorman\User
	 */
	protected $_user = false;

	protected $_auth_drivers = array();

	protected $_access_drivers = array();
	
	protected $_config = array();
	/**
	 * Class used to hash passwords
	 */
	protected $_hasher;
	
	protected $_privileges;

	/**
	 * Should only be called using static::instance()
	 */
	protected function __construct($name, $config) {
		$this->_name = $name;
		$this->_config = $config;
	}


	public function __call($method, $args) {
		$args = array_pad($args, 3, null);
		if(substr($method, 0, 1) == '_') { // Protected method
			throw new \BadMethodCallException('Cannot call protected method '.$method.' through the magic method implementation');
		}

		if(method_exists($this, $method)) {
			return call_user_func_array(array($this, $method), $args);
		}

		throw new \BadMethodCallException('Invalid method: '.get_called_class().'::'.$method);
	}
	
	/**
	 * Sets a config value on the fieldset
	 *
	 * @param   string
	 * @param   mixed
	 * @return  Fieldset  this, to allow chaining
	 */
	protected function set_config($config, $value = null) {
		$config = is_array($config) ? $config : array($config => $value);
		foreach ($config as $key => $value)
		{
			if (strpos($key, '.') === false)
			{
				$this->_config[$key] = $value;
			}
			else
			{
				\Arr::set($this->_config, $key, $value);
			}
		}

		return $this;
	}

	/**
	 * Get a single or multiple config values by key
	 *
	 * @param   string|array  a single key or multiple in an array, empty to fetch all
	 * @param   mixed         default output when config wasn't set
	 * @return  mixed|array   a single config value or multiple in an array when $key input was an array
	 */
	protected function get_config($key = null, $default = null) {
		if ($key === null)
		{
			return $this->_config;
		}

		if (is_array($key))
		{
			$output = array();
			foreach ($key as $k)
			{
				$output[$k] = $this->get_config($k, $default);
			}
			return $output;
		}

		if (strpos($key, '.') === false)
		{
			return array_key_exists($key, $this->_config) ? $this->_config[$key] : $default;
		}
		else
		{
			return \Arr::get($this->_config, $key, $default);
		}
	}
	
	

	protected function add_auth_driver($driver) {
		if(class_exists($driver)) {
			$this->_auth_drivers[] = $driver;
		}
	}

	protected function add_access_driver($driver) {
		if(class_exists($driver)) {
			$this->_access_drivers[] = $driver;
		}
	}

	protected function _session_get($var) {
		\Log::debug('getting '.$this->_name.'_'.$var);
		return \Session::get($this->_name.'_'.$var);
	}

	protected function _session_set($var, $val) {
		\Session::set($this->_name.'_'.$var, $val);
		\Log::debug('Setting '.$this->_name.'_'.$var.' to '.$val);
	}

	protected function _session_delete($var) {
		\Session::delete($this->_name.'_'.$var);
		\Log::debug('deleting session');
	}

	/**
	 * Check for login
	 *
	 * @return  bool
	 */
	protected function check_login() {
		$identifier    = $this->_session_get('identifier');
		\Log::debug('Got identifier from session: '.$identifier);
		$id_type = $this->get_config('identifier');
		$user_class = $this->get_config('user_class');


		// only worth checking if there's an identifier
		if (!empty($identifier)) {
			if (!$this->_user || ($this->_user && $this->_user->$id_type != $identifier)) {
				
				$method = 'find_by_'.$id_type;
				$this->_user = $user_class::$method($identifier);
			}


			if($this->get_config('use_login_hash')) {
				$login_hash  = $this->_session_get('login_hash');

				if ($this->_user && $this->_user->login_hash === $login_hash) {
					return true;
				}
			}
			else if($this->_user) {
				return true;
			}
			
		}

		/**
		 * Now check alternative authorization drivers on this doorman instance.
		 *
		 * E.g., Facebook connect
		 */
		if(count($this->_auth_drivers)) {
			foreach($this->_auth_drivers as $driver) {
				if($this->_user = $driver::check_login()) {

					/**
					 * We just use the alternate drivers for authentication. If they're authenticated,
					 * set the session values here so we don't have to talk to external APIs each time
					 * check_login is run. HOWEVER, this also means that if you have features that rely
					 * on connectivity to other networks, like Facebook, that you should run that driver's
					 * check_login method or equivalent before you try to interact with that API.
					 */
					$this->_session_set('identifier', $this->_user->{$this->get_config('identifier')});
					
					
					if($this->get_config('use_login_hash')) {
						$this->_session_set('login_hash', $this->create_login_hash());
					}
					
					\Session::instance()->rotate();
					return true;
				}
			}
		}
		// no valid login when still here, ensure empty session and optionally set guest_login
		$this->_user = false;
		$this->_session_delete('identifier');
		$this->_session_delete('login_hash');
		return false;
	}
	
	/**
	 * Checks if the login has been verified, doesn't try to verify like check_login
	 */
	protected function logged_in() {
		return ($this->_user) ? true : false;
	}
	
	
	/**
	 * Log in user
	 *
	 * @param   string
	 * @param   string
	 * @return  bool
	 */
	protected function login($identifier = '', $password = '') {

		if ( ! ($this->_user = $this->validate_user($identifier, $password))) {
			$this->_user = false;
			$this->_session_delete('identifier');
			$this->_session_delete('login_hash');
			return false;
		}
		
		$this->set_logged_in($this->_user);
	}
	

	public function set_logged_in($user) {
		
		$this->set_user($user);
		$this->_session_set('identifier', $this->_user->{$this->get_config('identifier')});
		

		if($this->get_config('use_login_hash')) {
			$this->_session_set('login_hash', $this->create_login_hash());
		}
		\Session::instance()->rotate();
	}
	/**
	 * Check the user exists before logging in
	 *
	 * @return  bool
	 */
	protected function validate_user ($identifier = '', $password = '') {
		if (empty($identifier) || empty($password)) {
			return false;
		}
		$user_class = $this->get_config('user_class');
		$this->_user = $user_class::get_by_login($identifier, $password);
		
		return $this->_user ?: false;
	}
	
	/**
	 * Creates a temporary hash that will validate the current login
	 *
	 * @return  string
	 */
	protected function create_login_hash () {
		
		if (empty($this->_user))
		{
			throw new \DoormanException ('User not logged in, can\'t create login hash.', 10);
		}

		$last_login = \Date::forge()->get_timestamp();
		$login_hash = sha1($this->get_config('hash_salt').$this->_user->{$this->get_config('identifier')}.$last_login);
		
		
		$this->_user->update_hash($login_hash);

		return $login_hash;
	}
	
	/**
	 * Default password hash method
	 *
	 * @param   string
	 * @return  string
	 */
	protected function hash_password($password) {
		return base64_encode($this->hasher()->pbkdf2($password, $this->get_config('hash_salt'), 10000, 32));
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
		is_null($this->_hasher) and $this->_hasher = new \PHPSecLib\Crypt_Hash();

		return $this->_hasher;
	}
	
	/**
	 * Allows methods to be called on user publicly without modifying the user
	 *
	 * @return  mixed an object of the user class defined by the config settings
	 */
	protected function user() {
		/**
		 * Initialize the user if not done already
		 */
		if(!$this->_user)
			$this->check_login();
		
		/**
		 * If still no user, then return a blank user object to avoid "call to method on
		 * non-object" errors
		 */
		if(!$this->_user) {
			$user_class = $this->get_config('user_class');

			$this->_user = $user_class::forge();
		}
		
		return $this->_user;
	}
	
	/**
	 * Checks if the current user can perform an action on an object.
	 * 
	 * @param string $object
	 * @param string $action
	 * @param string $id
	 * @return bool
	 */
	protected function has_access($object, $action, $id) {

		if(count($this->_access_drivers)) {
			foreach($this->_access_drivers as $driver) {
				if($driver::has_access($object, $action, $id)) {
					return true;
				}
			}
		}

		$privileges = $this->_user()->get_privileges();
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
	
	
	/**
	 * Logout user
	 *
	 * @return  bool
	 */
	protected function logout() {
		$this->_user = false;
		if(count($this->_auth_drivers)) {
			foreach($this->_auth_drivers as $driver) {
				if(method_exists($driver, 'logout'))
				$driver::logout();
			}
		}

		\Log::debug('Deleting session variables');
		$this->_session_delete('identifier');
		$this->_session_delete('login_hash');
		return true;
	}



	protected function set_user($user) {

		if($user instanceof \Doorman\User) {
			
			$this->_user = $user;
		}
	}

	
}
