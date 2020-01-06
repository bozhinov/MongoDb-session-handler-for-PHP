<?php

/* Original code at: https://github.com/cballou/MongoSession */

class MongoSession implements SessionHandlerInterface {

	protected $_mongo;
	protected $_session;

	public function __construct()
	{
		session_set_save_handler(
			[$this, 'open'],
			[$this, 'close'],
			[$this, 'read'],
			[$this, 'write'],
			[$this, 'destroy'],
			[$this, 'gc']
		);

		// Uncomment all needed that do not match defaults in php.ini
		#ini_set('session.auto_start',               0);
		#ini_set('session.gc_probability',           1);
		#ini_set('session.gc_divisor',               100);
		ini_set('session.gc_maxlifetime',           14400); # session lifetime in seconds
		#ini_set('session.referer_check',            '');
		#ini_set('session.entropy_file',             '/dev/urandom');
		#ini_set('session.entropy_length',           16);
		#ini_set('session.use_cookies',              1);
		#ini_set('session.use_only_cookies',         1);
		#ini_set('session.use_trans_sid',            0);
		#ini_set('session.hash_function',            1);
		#ini_set('session.hash_bits_per_character',  5);
		#ini_set('session.cookie_domain',  $_SERVER['SERVER_NAME']);

		session_cache_limiter('nocache');
		session_set_cookie_params(
			14400, #  session lifetime in seconds
			'/', # 'cookie_path'
			'' #$_SERVER['SERVER_NAME']
		);
		session_name('mongo_sess');

		register_shutdown_function('session_write_close');

		$this->_init();

		session_start();
	}

	/**
	 * Initialize MongoDB.
	 */
	private function _init()
	{
		try {
			$this->_mongo = (new MongoClient("127.0.0.1:37017"))->selectDB("sessions")->trainings;
			$this->_mongo->createIndex(['expiry' => 1], ['name' => 'expiry', 'unique' => false, 'sparse' => true]);
			$this->_mongo->createIndex(['session_id' => 1],['name' => 'session_id', 'unique' => true]);

		} catch (MongoConnectionException $e) {
			die('Error connecting to session server.');
		} catch (MongoException $e) {
			die('Error: ' . $e->getMessage());
		}
	}

	public function open($save_path, $session_name)
	{
		return true;
	}

	public function close()
	{
		unset($this->_mongo);
		return true;
	}

	/**
	 * Read the session data.
	 */
	public function read($id)
	{
		// exclude results that are inactive or expired
		$result = $this->_mongo->findOne([
				'session_id' => $id,
				'expiry'     => ['$gte' => time()],
				'active'     => 1
			]);

		if (isset($result['data'])) {
			$this->_session = $result;
			return $result['data'];
		}

		return '';
	}

	/**
	 * Atomically write data to the session
	 */
	public function write($id, $data)
	{
		// create new session data
		$new_obj = [
			'data' => $data,
			'active' => 1,
			'expiry' => time() + 14400 # session lifetime in seconds
		];

		// check for existing session for merge
		if (!empty($this->_session)) {
			$new_obj = array_merge((array)$this->_session, $new_obj);
		}
		unset($new_obj['_id']);

		// perform the update or insert
		try {
			$result = $this->_mongo->update(['session_id' => $id], ['$set' => $new_obj], ['upsert'=> TRUE]); 
			return $result['ok'] == 1;
		} catch (Exception $e) {
			die($e);
		}

		return true;
	}

	/**
	 * Destroys the session by removing the document with matching session_id.
	 */
	public function destroy($id)
	{
		$this->_mongo->remove(['session_id' => $id]);
		return true;
	}

	/**
	 * Garbage collection. Remove all expired entries atomically.
	 */
	public function gc($maxLifeTime = 3600)
	{
		// update expired elements and set to inactive
		$this->_mongo->update(
			['expiry' => ['$lt' => time()]],
			['$set' => ['active' => 0]],
			['multiple' => TRUE]
		);

		return true;
	}

	/**
	 * Solves issues with write() and close() throwing exceptions.
	 */
	public function __destruct()
	{
		session_write_close();
	}

}

new MongoSession();

?>