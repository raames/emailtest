<?php



class EmailTest extends CIUnit_TestCase {

	protected $tables = array(
		'emails' => 'emails',
	);
	
	public function __construct($name = NULL, array $data = array(), $dataName = '') {
		parent::__construct($name, $data, $dataName);
	}
	
	public function setUp() {
		parent::setUp();
	}
	
	public function tearDown() {
		parent::tearDown();
	}

	public function test_db_table_name() {
		$this->assertEquals(Email::$table_name, 'emails');
	}

	private function generate_email() {

		Email::push(array(
			'type' => 'test',
			'from' => 'colin@tercet.io',
			'to' => 'clyons@olivemedia.co',
			'subject' => 'This is the subject',
			'body' => 'This is the body.',
			'attachment' => realpath(dirname(__FILE__)) . '/EmailTest.php',
		));

		return Email::find('last');
	}

	/* set_from */

	

	public function test_set_from_with_blank_email() {

		$email = new Email;

		$this->setExpectedException('EmailFromInvalidException');
		$email->from = '';
	}

	

	public function test_set_from_with_invalid_email() {

		$email = new Email;

		$this->setExpectedException('EmailFromInvalidException');
		$email->from = 'invalid-email';
	}

	

	public function test_set_from_with_valid_email() {

		$email = new Email;
		$email->from = 'colin@tercet.io';

		$this->assertEquals($email->from, 'colin@tercet.io');
	}

	/* set_to */

	

	public function test_set_to_with_blank_email() {

		$email = new Email;

		$this->setExpectedException('EmailToInvalidException');
		$email->to = '';
	}

	

	public function test_set_to_with_invalid_email() {

		$email = new Email;

		$this->setExpectedException('EmailToInvalidException');
		$email->to = 'invalid-email';
	}

	

	public function test_set_to_with_valid_email() {

		$email = new Email;
		$email->to = 'colin@tercet.io';

		$this->assertEquals($email->to, 'colin@tercet.io');
	}

	

	public function test_set_to_with_valid_email_trimmed_and_lowered() {

		$email = new Email;
		$email->to = 'CoLiN@TeRcEt.Io   ';

		$this->assertEquals($email->to, 'colin@tercet.io');
	}

	/* set_subject */

	

	public function test_set_subject_required() {

		$email = new Email;

		$this->setExpectedException('EmailSubjectRequiredException');
		$email->subject = '';
	}

	
	public function test_set_subject() {

		$email = new Email;
		$email->subject = 'This is a subject';

		$this->assertEquals($email->subject, 'This is a subject');
	}

	
	public function test_set_subject_trimmed() {

		$email = new Email;
		$email->subject = 'This is a subject    ';

		$this->assertEquals($email->subject, 'This is a subject');
	}

	/* set_body */


	public function test_set_body_required() {

		$email = new Email;

		$this->setExpectedException('EmailBodyRequiredException');
		$email->body = '';
	}

	

	public function test_set_body() {

		$email = new Email;
		$email->body = 'This is a body.';

		$this->assertEquals($email->body, 'This is a body.');
	}

	
	public function test_set_body_trimmed() {

		$email = new Email;
		$email->body = 'This is a body.    ';

		$this->assertEquals($email->body, 'This is a body.');
	}

	/* set_type */


	public function test_set_type_required() {

		$email = new Email;

		$this->setExpectedException('EmailTypeRequiredException');
		$email->type = '';
	}

	

	public function test_set_type_invalid() {

		$email = new Email;

		$this->setExpectedException('EmailTypeInvalidException');
		$email->type = 'invalid-email-type';
	}

	
	public function test_set_type() {

		$email = new Email;
		$email->type = 'test';

		$this->assertEquals($email->type, 'test');
	}

	/* set_attachment */

	

	public function test_set_attachment_blank_file_not_exists() {

		$email = new Email;

		$this->setExpectedException('EmailAttachmentNotExistsException');
		$email->attachment = '';
	}

	

	public function test_set_attachment_path_not_exists() {

		$email = new Email;

		$this->setExpectedException('EmailAttachmentNotExistsException');
		$email->attachment = '/path/to/attachment/';
	}

	public function test_set_attachment() {

		$email = new Email;

		$email->attachment = realpath(dirname(__FILE__)) . '/EmailTest.php';

		$this->assertEquals($email->attachment_path, realpath(dirname(__FILE__)) . '/EmailTest.php');
	}

	

	public function test_mark_sendable_now() {

		$email = new Email;

		$this->assertNull($email->next_send_at);

		$reflection = new ReflectionClass(get_class($email));
		$method = $reflection->getMethod('mark_sendable_now');
		$method->setAccessible(true);

		$method->invoke($email);

		$this->assertNotNull($email->next_send_at);
	}

	

	public function test_increment_next_send_at() {

		$email = new Email;

		$this->assertNull($email->next_send_at);

		$reflection = new ReflectionClass(get_class($email));
		$method = $reflection->getMethod('increment_next_send_at');
		$method->setAccessible(true);

		$method->invoke($email);

		$this->assertNotNull($email->next_send_at);
	}


	public function test_mark_as_sent() {

		$email = new Email;

		$reflection = new ReflectionClass(get_class($email));
		$method = $reflection->getMethod('mark_as_sent');
		$method->setAccessible(true);

		$method->invoke($email);

		$this->assertEquals($email->status, Email::STATUS_SENT);
		$this->assertNull($email->next_send_at);
		$this->assertNotNull($email->sent_at);
	}

	
	public function test_increment_send_count() {

		$email = new Email;

		$this->assertEquals($email->send_count, 0);

		$reflection = new ReflectionClass(get_class($email));
		$method = $reflection->getMethod('increment_send_count');
		$method->setAccessible(true);

		$method->invoke($email);

		$this->assertEquals($email->send_count, 1);
	}

	

	public function test_mark_as_resendable() {

		$email_fixture = $this->emails_fixt[1];
		$email = Email::find_by_id($email_fixture['id']);

		$this->assertNull($email->next_send_at);

		$reflection = new ReflectionClass(get_class($email));
		$method = $reflection->getMethod('mark_as_resendable');
		$method->setAccessible(true);

		$method->invoke($email);

		$this->assertNotNull($email->next_send_at);

		$new_email_fixture = $this->emails_fixt[2];
		$new_email = Email::find_by_id($new_email_fixture['id']);

		$this->assertNull($new_email->next_send_at);

		$reflection = new ReflectionClass(get_class($email));
		$method = $reflection->getMethod('mark_as_resendable');
		$method->setAccessible(true);

		$method->invoke($new_email);

		$this->assertEquals($new_email->send_expired, 1);
		$this->assertEquals($new_email->status, 'expired');
	}

	

	public function test_expire_resend() {

		$email = new Email;

		$reflection = new ReflectionClass(get_class($email));
		$method = $reflection->getMethod('expire_resend');
		$method->setAccessible(true);

		$method->invoke($email);

		$this->assertEquals($email->status, Email::STATUS_EXPIRED);
		$this->assertEquals($email->send_expired, 1);
	}

	/* push */

	

	public function test_push() {

		$email = $this->generate_email();

		$this->assertEquals($email::STATUS_PENDING, $email->status);
		$this->assertEquals($email->type, 'test');
		$this->assertEquals($email->from, 'colin@tercet.io');
		$this->assertEquals($email->to, 'clyons@olivemedia.co');

		$this->assertEquals($email->subject, 'This is the subject');
		$this->assertEquals($email->body, 'This is the body.');

		$this->assertNotNull($email->next_send_at);
		$this->assertEquals($email->send_count, 0);
		$this->assertEquals($email->send_expired, 0);
		$this->assertNull($email->send_error);
	}

	/* pop */

	

	public function test_pop_when_queue_empty() {

		$this->setExpectedException('EmailQueueEmptyException');
		$email = Email::pop();
	}

	
	public function test_pop_when_queue_not_empty() {

		$this->generate_email();

		$email = Email::pop();

		$this->assertEquals($email::STATUS_PENDING, $email->status);
	}

	/* send */

	public function test_send()
	{
		$email_id = $this->emails_fixt['4']['id'];
		$email = Email::find_by_id($email_id);

		$CI =& get_instance();

		$CI->load->library('email');
		$CI->load->helper('email');
		$CI->email->initialize($CI->config->config['mail']);

		$this->assertNull($email->next_send_at);
		$this->assertNull($email->sent_at);
		$email->send($CI);
		$this->assertNull($email->next_send_at);
		$this->assertNotNull($email->sent_at);

	}

	public function test_send_with_attachment()
	{
		$email_id = $this->emails_fixt['4']['id'];
		$email = Email::find_by_id($email_id);

		$email->attachment = APPPATH.'index.html';

		$CI =& get_instance();

		$CI->load->library('email');
		$CI->load->helper('email');
		$CI->email->initialize($CI->config->config['mail']);

		$this->assertNull($email->next_send_at);
		$this->assertNull($email->sent_at);
		$email->send($CI);
		$this->assertNull($email->next_send_at);
		$this->assertNotNull($email->sent_at);
	}

	public function test_send_without_recipients()
	{
		$email_id = $this->emails_fixt['5']['id'];
		$email = Email::find_by_id($email_id);

		$CI =& get_instance();

		$CI->load->library('email');
		$CI->load->helper('email');
		$CI->email->initialize($CI->config->config['mail']);

		$this->assertNull($email->next_send_at);
		$this->assertNull($email->sent_at);
		$email->send($CI);
		$this->assertEquals($email->send_count, 1);
		$this->assertNotNull($email->next_send_at);
		$this->assertNull($email->sent_at);
		$this->assertNotNull($email->send_error);
	}


	/* validate list */

	

	public function test_validate_list() {

		$input = 'kguragai@olivemedia.co, clyons@olivemedia.co, oronocornor@olivemedia.co';

		$email_fixture = $this->emails_fixt[1];
		$email = Email::find_by_id($email_fixture['id']);

		$email_list = $email->validate_list($input);

		$this->assertEquals($email_list[0], 'kguragai@olivemedia.co');
		$this->assertEquals($email_list[1], 'clyons@olivemedia.co');
		$this->assertEquals($email_list[2], 'oronocornor@olivemedia.co');
	}

	/**
	*@covers Email::validate_list
	*/

	public function test_validate_list_invalid() {

		$input = 'kguragai@olivemedia.co, my_mail_id';

		$email_fixture = $this->emails_fixt[1];
		$email = Email::find_by_id($email_fixture['id']);

		$this->setExpectedException('EmailValidationException');

		$email_list = $email->validate_list($input);

		print_r('EmailTest');
	}
}
	
