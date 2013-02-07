<?php

namespace Doorman;

class Controller extends \Controller_Template {
	public $template = 'container';

	public function action_login() {
		
		
		
		
		if(\Doorman::check_login()) {
			/**
			 * Already logged in, take them to the dashboard page or the redirect page
			 */
			return Response::redirect('me');
		}
		
		/**
		 * Display the login form
		 */
		
		$form = \Doorman\User::forge()->generate_form('verify');
		
		$this->template->set('content', $form, false);
		$this->template->content->errors = array();
		if(Input::post()) {
			$session = \Session::get_flash('form_redirect');

			$redirect = ($session) ?: '/me';

			if(\Doorman::login(\Input::post('email'), \Input::post('password'))) {

				return Response::redirect($redirect);
			}
			else {
				$this->template->content->errors = array('Login failed.');
			}
		}
		
		
		
	}

	public function action_logout() {
		\Doorman::logout();
		\Session::set_flash('form_message', 'You have logged out successfully!');
		\Session::set_flash('form_message_class', 'success');
		return Response::redirect('/login');
	}

	protected function create_update_form($object, $form_type) {
		
		
		/**
		 * If there was a post request, we assume that they submitted the form
		 * or created one via the API. So try validating it
		 */
		
		if(\Input::post()) {
			$object->update_from_post($form_type);
			$this->template->content = $object->generate_form($form_type);
			$this->template->content->set('errors', array(), false);
			/**
			 * @var \InternalResponse
			 * @see \DataFields\Model::validate()
			 */
			$validated = $object->validate($form_type);

			/**
			 * If valid, save the object
			 */
			if($validated->status() == 'success') {

				
				if($form_type == 'edit') {
					\Session::set_flash('form_message', 'Success! Settings have been saved.');
					\Session::set_flash('form_message_class', 'success');
				}



				$saved = $object->save();


				if($form_type == 'create') {

					// Now log them in
					\Doorman::set_user($object);
					if(\Config::get('doorman.identifier') == 'username')
						\Session::set('identifier', $object->username);
					else
						\Session::set('identifier', $object->email);
					
					
					\Session::set('login_hash', \Doorman::create_login_hash());
					\Session::instance()->rotate();
				}

				/**
				 * API requests
				 */
				if(\Input::is_ajax() || \Input::get('view') == 'api')
					$this->response($saved->response());
				
				/**
				 * If there is a redirect setting for this object type after
				 * creation, redirect
				 */
				
				elseif($redirect = Redirector::check_for_redirect('user', $form_type, 'success', $saved)) {
					
				}
				
				/**
				 * Otherwise generate the form again and pass the response as data so it can show 
				 * the results
				 */
				else {
					$this->template->set('response', $saved, false);
				}
					
			}
			/**
			 * Otherwise report the errors
			 */
			else {
				/**
				 * API request
				 */
				if(\Input::is_ajax() || \Input::get('view') == 'api')
					$this->response($validated->response());
				
				/**
				 * If there is a redirect setting for this object type after
				 * creation error, redirect
				 */
				elseif($redirect = Redirector::check_for_redirect('user', $form_type, 'error', $validated)) {
					return $redirect;
				}
				
				/**
				 * Otherwise generate the form again and pass the response as data so it can show 
				 * the results
				 */
				else {
					$this->template->content->set('errors', $validated->errors(), false);
				}
				
				
			}
		}
		else {
			$this->template->content = $object->generate_form($form_type);
			$this->template->content->set('errors', array(), false);
		}

	}

	public function action_signup() {
		if(\Doorman::check_login()) {
			$this->template->content = \View::forge('user/error/double_signup');
			return;
		}

		
		$object = \Model\User::forge();
		$this->create_update_form($object, 'create');
	}

	public function action_settings() {
		
		/**
		 * Must be logged in
		 */
		if(!\Doorman::check_login()) {
			\Session::set_flash('form_redirect', '/user/settings');
			\Session::set_flash('form_message', 'Please log in to continue.');
			\Session::set_flash('form_message_class', 'error');
			return \Response::redirect('/login');
		}
		$this->create_update_form(\Doorman::user(), 'edit');



	}

	public function action_reset_password() {
		if(\Doorman::check_login()) {
			return Response::redirect('/me');
		}

		if($request_id = \Input::post('request_id')) {
			$request = \DB::select('*')->from('doorman_reset_requests')->where('id', $request_id)->where(\DB::expr("TIMESTAMPDIFF(HOUR, NOW(), `timestamp`)"), '<', 24)->as_object('\\Doorman\\ResetRequest')->execute()->as_array();
			$this->template->content = View::forge('user/forms/choose-new-password');
			$this->template->content->success = false;
			$this->template->content->errors = array();

			if($request) {
				$request = $request[0];
				$this->template->content->request = $request;
				$password1 = \Input::post('password1');
				$password2 = \Input::post('password2');
				
				$errors = array();
				if(strlen($password1) < 5) {
					$this->template->content->errors[] = 'Your password must be at least 5 characters in length.';
				}
				elseif($password1 != $password2) {
					$this->template->content->errors[] = 'Your passwords do not match.';
				}
				else {
					/**
					 * Reset the user's PW
					 */
					$request->user->password = \Doorman::hash_password($password1);
					$request->user->save();
					\Session::set_flash('form_message', 'Password reset successfully. Please login below.');
					\Session::set_flash('form_message_class', 'success');
					return Response::redirect('/login');

				}
				$this->template->content->show_form = true;
			}
			else {
				$this->template->content->errors[] = 'An unexpected error occurred.';
				$this->template->content->show_form = false;
			}
		}
		elseif($key = \Input::get('key')) {
			$this->template->content = View::forge('user/forms/choose-new-password');
			$this->template->content->success = false;
			$this->template->content->errors = array();
			$request = \DB::select('*')->from('doorman_reset_requests')->where('key', $key)->where(\DB::expr("TIMESTAMPDIFF(HOUR, NOW(), `timestamp`)"), '<', 24)->as_object('\\Doorman\\ResetRequest')->execute()->as_array();
			if($request) {
				$request = $request[0];
				$this->template->content->request = $request;
				$this->template->content->show_form = true;
			}
			else {
				$this->template->content->errors[] = 'An unexpected error occurred.';
				$this->template->content->show_form = false;
			}
		}
		else {
			$this->template->content = View::forge('user/forms/reset-password');
			$this->template->content->success = false;
			$this->template->content->errors = array();
			if($email = \Input::post('email')) {
				/**
				 * Check if the user exists
				 */
				$user = \Model\User::find_by_email($email);

				if(!$user) {
					$this->template->content->errors[] = 'There is no user registered with that e-mail address.';
				}
				else {
					/**
					 * Create a new password reset request
					 */
					$request = \Doorman\ResetRequest::forge(array('user_id'=>$user->id));
					$request->save();
					$this->template->content->success = true;
				}

			}
		}
		
	}
}