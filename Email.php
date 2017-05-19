<?php

class EmailFromInvalidException extends Exception {}
class EmailToInvalidException extends Exception {}

class EmailSubjectRequiredException extends Exception {}
class EmailBodyRequiredException extends Exception {}

class EmailTypeRequiredException extends Exception {}
class EmailTypeInvalidException extends Exception {}

class EmailValidationException extends Exception {}
class EmailAttachmentNotExistsException extends Exception {}
class EmailQueueEmptyException extends Exception {}

class Email extends ActiveRecord_Base {

	const TYPE_DEFAULT = 'standard';

	const STATUS_PENDING = 'pending';
	const STATUS_SENT = 'sent';
	const STATUS_EXPIRED = 'expired';

	const STATUS_BLACKLISTED = 'blacklisted';

	const SEND_CEILING = 3;

	static $valid_types = array(
		'test',
		'Inquiry',
		'enrolment-alert',
		'enrolment-reminder',
		'enrolment-successful',
		'member-invite',
		'member-create',
		'password-reset',
		'madigan-gill-form-module',
		'finance-order-receipt',
		'payment-receipt',
		'payment-admin-receipt',
		'enrolment-expiry-alert',
		'rendition-complete-notification',
		'team-report',
		'org-report',
		'audit-form-report',
		'enrolments-export',
		'membership',
		'course-deadline-alert',
        'voucher-code'
	);

	/* Table Name */

	static $table_name = 'emails';

	/* Associations */
	/* Observers */
	/* Validations */
	/* Public functions - Setters */

	public function set_from($from) {

		$from = strtolower(trim($from));

		if(!filter_var($from, FILTER_VALIDATE_EMAIL)) {
			throw new EmailFromInvalidException;
		}

		$this->assign_attribute('from', $from);
	}

	public function set_to($to) {

		$to = strtolower(trim($to));

		if(!filter_var($to, FILTER_VALIDATE_EMAIL)) {
			throw new EmailToInvalidException("The email is invalid");
		}

		$this->assign_attribute('to', $to);
	}

	public function set_subject($subject) {

		$subject = trim($subject);

		if(!$subject) {
			throw new EmailSubjectRequiredException;
		}

		$this->assign_attribute('subject', $subject);
	}

	public function set_body($body) {

		$body = trim($body);

		if(!$body) {
			throw new EmailBodyRequiredException('No Body');
		}

		$this->assign_attribute('body', $body);
	}

	private function check_valid_type($type) {

		if(!in_array($type, self::$valid_types)) {
			throw new EmailTypeInvalidException;
		}
	}

	public function set_type($type) {

		if(!$type) {
			throw new EmailTypeRequiredException;
		}

		$this->check_valid_type($type);

		$this->assign_attribute('type', $type);
	}

	public function set_attachment($attachment) {

		if(!file_exists($attachment[0])) {
			throw new EmailAttachmentNotExistsException('Email attachment not found');
		}

		$this->assign_attribute('attachment_path', json_encode($attachment));
	}

	/* Public functions - Getters */
	/* Private functions - General */

	private function mark_sendable_now() {
		$date = new DateTime();
		$this->next_send_at = $date->format('Y-m-d H:i:s');
	}

	private function increment_next_send_at() {

		$date = new DateTime();
		$date->add(new DateInterval('PT1M'));

		$this->next_send_at = $date->format('Y-m-d H:i:s');
	}
 
	private function mark_as_sent() {

		$date = new DateTime();
		$date->add(new DateInterval('PT1M'));

		$this->sent_at = $date->format('Y-m-d H:i:s');
		$this->assign_attribute('status', self::STATUS_SENT);
		$this->next_send_at = null;
	}

	private function mark_as_resendable() {

		if(!$this->has_hit_resend_ceiling()) {
			return $this->increment_next_send_at();
		}

		$this->expire_resend();
	}

	private function mark_as_blacklisted() {

		$this->status = self::STATUS_BLACKLISTED;
		$this->next_send_at = null;
	}

	private function increment_send_count() {
		$this->send_count = $this->send_count + 1;
	}

	private function has_hit_resend_ceiling() {
		return ($this->send_count >= self::SEND_CEILING);
	}

	private function expire_resend() {
		$this->status = self::STATUS_EXPIRED;
		$this->send_expired = 1;
	}

	private function check_is_blacklisted() {

		$emails = BlacklistedEmail::find('all', array(
			'conditions' => array(
				'email_address = ? AND reason in (?) AND is_deleted = ? ',
				$this->to,
				array('bounce', 'suppress-bounce'),
				0
			)
		));

		if($emails) {
			return true;
		} else {
			return false;
		}
	}

	/* Public functions - General */

	public function send($CI) {

		$this->increment_send_count();

		$emailer = $CI->email;

		$emailer->clear(TRUE);

		$attachments = array();

		if($this->attachment_path) {

			$attachments = json_decode($this->attachment_path, true);

			foreach ($attachments as $attachment) {
				$emailer->attach($attachment);
			}
		}

		$emailer->from($this->from);
		$emailer->to($this->to);
		$emailer->subject($this->subject);
		$emailer->message($this->body);


		if(!$this->check_is_blacklisted()) {
			if (!$emailer->send()){
				$this->mark_as_resendable();
				$this->save();
				return $this->send_error = $emailer->print_debugger();
			}
			$this->mark_as_sent();
			$this->save();
			return "Email to ". $this->to. " was sent successfully.\n";
		} else {

			$this->mark_as_blacklisted();
			$this->save();
			return "Email ".$this->to. " is blacklisted.\n";
		}

		
	}

	/* Public static functions */

	public static function pop() {

		$email = Email::find(
			'first',
			array('conditions' => 
				array('status = ? AND send_expired = 0 and next_send_at <= NOW()', self::STATUS_PENDING)
			)
		);
		
		if(!$email) { 
			throw new EmailQueueEmptyException();
		}

		$email->increment_next_send_at();
		$email->save();

		return $email;
	}

	public static function push($params) {

		$email = new Email;

		$email->from = array_key_exists('from', $params) ? $params['from'] : null;
		$email->to = array_key_exists('to', $params) ? $params['to'] : null;

		$email->subject = array_key_exists('subject', $params) ? $params['subject'] : null;
		$email->body = array_key_exists('body', $params) ? $params['body'] : null;

		$email->type = array_key_exists('type', $params) ? $params['type'] : self::TYPE_DEFAULT;
		$email->status = self::STATUS_PENDING;

		if(array_key_exists('attachment', $params)) {
			$email->attachment = $params['attachment'];
		}

		$email->mark_sendable_now();
		$email->save();
	}

	public static function validate_list($input) {

		$exploded_emails = explode("|", (string)str_replace(array("\r", "\r\n", "\n", ","), '|', $input));

		$email_list = array();

		foreach ($exploded_emails as $email) {

			$email = trim($email);

			if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
				throw new EmailValidationException();
			}

			$email_list[] = $email;

		}

		return $email_list;

	}

}
