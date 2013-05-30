<?php
namespace Doorman\DataFields\Fields;

class Password extends \DataFields\Fields\Base {
	
	protected $_rehash = false;
	
	public function add_fields(&$fieldset, $formType) {
		
		$validation = $this->_settings['validation'];
		switch($formType) {
			case 'create':
				$fieldset->add($this->_prop.'[password]', $this->_settings['label'], array('type'=>'password'), $this->_settings['validation']);
				$fieldset->add($this->_prop.'[verify]', 'Verify '.$this->_settings['label'], array('type'=>'password'), array('match_field'=>array('match_field', $this->_prop.'[password]')));
				
				
				
				break;
			case 'verify':
			default:
				$fieldset->add($this->_prop, '[password]', array('type'=>'password'), array());
				break;
			case 'edit':
				$fieldset->add($this->_prop.'[password]', 'Current '.$this->_settings['label'], array('type'=>'password'), array());
				$verification_method = $this->_settings['verification_method'];
				$fieldset->field($this->_prop.'[password]')->add_rule(array($this, 'verify_password_change'), array($this->_model, $this->_prop, $verification_method));
				$fieldset->validation()->set_message('Password:verify_password_change', 'You must provide your current password correctly in order to change your password.');

				$fieldset->add($this->_prop.'[new]', 'New '.$this->_settings['label'], array('type'=>'password'), array());
				$fieldset->add($this->_prop.'[verify_new]', 'Verify '.$this->_settings['label'], array('type'=>'password'), array('match_field'=>array('match_field', $this->_prop.'[new]')));

				$fieldset->validation()->set_message('match_field', 'Your new passwords must match');
				break;
		}
		static::add_validation_messages($this->_settings['validation'], $fieldset);
		
	}
	
	/**
	 * Data array is:
	 *
	 * 0 -> model
	 * 1 -> property
	 * 2 -> verification method
	 */
	public function verify_password_change($password, array $data = array()) {

		$prop = $data[1];
		$model = $data[0];
		$method = $data[2];
		\Log::debug('Verifying password change');
		\Log::debug(var_export($this->_data, true));
		\Log::debug('method:' .$method);
		\Log::debug('password:'.$password);
		$value = $this->_data;
		if(!empty($value['new'])) {
			\Log::debug('New is not empty');
			$return = call_user_func_array($method, array($value['password'], $model->id));
			return ($return) ? true : false;
		}
		return true;

	}

	public function hydrate_from_post() {
		$this->_hydrated_from_post = true;
		switch($this->_model->form_type) {
			case 'verify':
			case 'create':
				$this->_rehash = true;
				if(\Input::post($this->_prop, false))
					$this->_data = \Input::post($this->_prop);
				break;
			case 'edit':
				
				if(\Input::post($this->_prop.'.new_password', false)) {
					$this->_data = \Input::post($this->_prop.'.new_password');
					$this->_rehash = true;
				}
				break;
			default:
				if(\Input::post($this->_prop, false))
					$this->_data =\Input::post($this->_prop);
				break;
		}
	}
	
	
	
	/**
	 * Take the data and split/combine/parse it into database fields.
	 * 
	 * 
	 * @param string $this->_prop The property/field name
	 * @param array $settings Property settings
	 * @param string $formType
	 * @param mixed $data The model's data entry for this property
	 */

	public function to_db() {
		// What's the password?
		if(is_array($this->_data)) {
			extract($this->_data);
			$this->_rehash = true;
			if(!empty($new) && !empty($verify_new) && $new== $verify_new) {
				$db_password = $new;
			}
			else {
				$db_password = $password;
			}
		}
		else {
			$db_password = $this->_data;
		}

		/**
		 * Only hash it if the form type is create
		 */
		if($this->_rehash)
			$pw = $this->_model->get_doorman_instance()->hash_password($db_password);
		else
			$pw = $db_password;

		return array($this->_prop=>$pw);
		
	}
	
	public function multi($formType = null) {
		switch($formType) {
			case 'verify':
			case 'edit':
			case 'create':
				return true;
				break;
			default:
				return false;
				break;
		}
	}
	
	public function show($formType = null) {
		return true;
	}
	
	public static function db_cols($prop, array $settings = array()) {
		
		return array($prop =>array('constraint'=>255, 'type'=>'varchar', 'null'=>false));
	}
}