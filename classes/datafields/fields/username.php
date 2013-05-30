<?php
namespace Doorman\DataFields\Fields;

class Username extends \DataFields\Fields\Base {
	
	public function add_fields(&$fieldset, $formType) {
		$value = (\Input::post($this->_prop)) ? \Input::post($this->_prop) : $this->_model->{$this->_prop};
		$validation = $this->_settings['validation'];
		$validation['min_length'] = array('min_length', 5);

		switch($formType) {
			case 'create':
				$validation['unique'] = array($this->_settings['unique_test']);
				$validation['required'] = array('required');
				$fieldset->add($this->_prop, $this->_settings['label'], array('type'=>'text', 'value'=>$value), $validation);
				
				
				
				$rule_name = str_replace('::', ':', \Inflector::denamespace($this->_settings['unique_test']));

				$fieldset->validation()->set_message($rule_name, \Config::get('datafields.errors.unique_username'));
				break;
			case 'verify':
			default:
				$fieldset->add($this->_prop, $this->_settings['label'], array('type'=>'text', 'value'=>$value), array());
				break;
			case 'edit':
				$validation['unique'] = array($this->_settings['unique_test'], $this->_model->id);
				$rule_name = str_replace('::', ':', \Inflector::denamespace($this->_settings['unique_test']));
				$fieldset->validation()->set_message($rule_name, \Config::get('datafields.errors.unique_username'));
				$new = $fieldset->add($this->_prop, $this->_settings['label'], array('type'=>'text', 'value'=>$value), $validation);
				break;
		}
		
		
		
		static::add_validation_messages($fieldset, $this->_settings['validation']);


		

	}
	
	public static function db_cols($prop, array $settings = array()) {
		return array($prop =>array('constraint'=>200, 'type'=>'varchar', 'null'=>false));
	}
	
	public function show($formType = null) {
		if($formType == 'verify' && \Config::get('doorman.identifier') == 'username')
			return true;
		elseif($formType != 'verify')
			return true;
		return false;
	}
	

	
}