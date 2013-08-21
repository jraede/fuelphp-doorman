<?php

namespace Doorman;

class User_Meta extends \Orm\Model {
	protected static $_properties = array('id', 'user_id', 'key', 'value');
	protected static $_table_name = 'doorman_user_meta';
	protected static $_belongs_to = array(
		'user'=>array(
			'key_from' => 'user_id',
			'model_to' => '\\Doorman\\User',
			'key_to' => 'id',
			'cascade_save' => true,
			'cascade_delete' => false,
		),
	);
}