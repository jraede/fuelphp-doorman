<?php
namespace Doorman;
/**
 * Privilege class
 */
class Privilege extends \Orm\Model {
	protected static $_properties = array('id', 'object', 'action', 'object_id', 'group_id', 'user_id');
	protected static $_table_name = 'doorman_privileges';
	protected static $_belongs_to = array(
		'group'=>array(
			'key_from' =>'group_id',
			'model_to' => '\\Doorman\\Group',
			'key_to'=>'id',
			'cascade_save'=>true,
			'cascade_delete'=>false
		),
		'user'=>array(
			'key_from'=>'user_id',
			'model_to' => '\\Doorman\\User',
			'key_to'=>'id',
			'cascade_save'=>true,
			'cascade_delete'=>false
		)
	);

}

?>
