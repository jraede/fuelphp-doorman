<?php

namespace Doorman;

class ResetRequest extends \Orm\Model {
	protected static $_properties = array('id', 'timestamp', 'user_id', 'key');
	protected static $_table_name = 'doorman_reset_requests';
	protected static $_belongs_to = array(
		'user'=>array(
			'key_from' => 'user_id',
			'model_to' => '\\Doorman\\User',
			'key_to' => 'id',
			'cascade_save' => true,
			'cascade_delete' => false,
		),
	);

	protected function create() {

		// Generate a key
		$this->key = md5(uniqid());

		// Delete all existing requests for this user
		\DB::query("DELETE FROM `doorman_reset_requests` WHERE `user_id`='{$this->user_id}'")->execute();
		parent::create();

		$this->send_email();
	}

	protected function send_email() {

		$email = \Email::forge();

		
		$email->to($this->user->email);

		$email->from(\Config::get('doorman.email.address'), \Config::get('doorman.email.name'));
		$email->subject('Password Reset Request');
		$email->body('Someone has requested to reset the password for this account on Wishee. If this was not you, please ignore this message. Otherwise, please visit http://wishee.com/user/reset_password/?key='.$this->key.' to reset your password. This link will be valid for 24 hours, after which you must submit another reset request.


- The Wishee Team');

		try {
			$email->send();
		}
		catch(\EmailValidationFailedException $e) {
			\Log::info('E-mail tried to send to invalid address.');
			return false;
		}
		catch(\EmailSendingFailedException $e) {
			\Log::error('Failure to send email!');
			return false;
		}

		return true;
	}
}