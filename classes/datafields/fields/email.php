<?php
namespace Doorman\DataFields\Fields;

class Email extends \DataFields\Fields\Base {
	public function add_fields(&$fieldset, $formType) {
		$value = (\Input::post($this->_prop)) ? \Input::post($this->_prop) : $this->_model->{$this->_prop};
		$validation = array();
		if($formType != 'verify') $validation['valid_email'] = array('valid_email');
		switch($formType) {
			case 'create':
				$validation['unique'] = array($this->_settings['unique_test']);
				break;
			case 'edit':
				$validation['unique'] = array($this->_settings['unique_test'], $this->_model->id);
				break;
				
		}
		$validation['required'] = array('required');
		$fieldset->add($this->_prop, $this->_settings['label'], array('type'=>'text', 'value'=>$value), $validation);
		

		
		$rule_name = str_replace('::', ':', \Inflector::denamespace($this->_settings['unique_test']));
		$fieldset->validation()->set_message($rule_name, \Config::get('datafields.errors.unique_email'));
		
		
		static::add_validation_messages($this->_settings['validation'], $fieldset);

	}
	
	public function show($formType = null) {
		if($formType == 'verify' && $this->_model->get_doorman_config('identifier') == 'email')
			return true;
		elseif($formType != 'verify')
			return true;
		return false;
	}
	
	
	public static function db_cols($prop, array $settings = array()) {
		if(!array_key_exists('validation', $settings)) {
			$settings['validation'] = array();
		}
		if(array_search('required', $settings['validation'])) {
			return array($prop => array('constraint'=>100, 'type'=>'varchar', 'null'=>false));
		}
		return array($prop => array('constraint'=>100, 'default'=>'null', 'type'=>'varchar'));
	}
	

	
}