<?php
namespace Concerto;

class Auth_Group extends Auth_Privileged {
	protected static $_table_name = 'auth_groups';
	protected static $_db_cols = array();
	
	protected static $_properties = array(
	    'id'=>array(
		    'label'=>'ID',
		    'fieldtype'=>'ID'
		),
	    'name'=>array(
		   'label'=>'Name',
		   'fieldtype'=>'ShortText'
	    ),
	);
	
	protected static $_has_many = array(
		'privileges'=>array(
		    'key_from'=>'id',
		    'model_to'=>'\\Concerto\\Auth_Privilege',
		    'key_to'=>'group_id',
		    'cascade_save'=>true,
		    'cascade_delete'=>true
		)
	);
        
	protected static $_many_many = array(
		'users'=>array(
			'key_from' => 'id',
			'key_through_from' => 'group', // column 1 from the table in between, should match a posts.id
			'table_through' => 'auth_group_assignments', // both models plural without prefix in alphabetical order
			'key_through_to' => 'user', // column 2 from the table in between, should match a users.id
			'model_to' => '\\Concerto\\Auth_User',
			'key_to' => 'id',
			'cascade_save' => true,
			'cascade_delete' => false
		)
	);

     /**
	 * Assigns a privilege to the group if it doesn't already have that privilege
	 * 
	 * @param int $privilege
	 * @return \Concerto\InternalResponse
	 */
	public function assign_privilege($privilege) {
		$response = \InternalResponse::forge();
		
		/**
		 * Make sure the string is formed correctly
		 */
		if($privilege != 'all' && !preg_match('/([A-Za-z_]+)(\.)([A-Za-z_]+)((\.)([0-9]+))?/', $privilege)) {
			$response->error('Malformed privilege string');
		}
		else {
			$privileges = $this->get_privileges();
			if(in_array($privilege, $privileges)) 
				   $response->success();
			else {
				$split = explode('.', $privilege);
				$object = $split[0];
				$action = $split[1];
				
				$this->privileges[] = Auth_Privilege::forge(array('object'=>$object, 'action'=>$action));
				$this->save();
				$response->success();
			}
		}
		return $response;
		
	}
}

?>
