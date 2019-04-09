<?php

class MongoCore {

	private $manager;
	private $database = null;
	private $collection = null;
	private $conn = null; # db+col

	function __construct(string $conn = null, string $host = null)
	{
		if ($host != null){
			$this->manager = new \MongoDB\Driver\Manager($host);
		} else {
			$this->manager = new \MongoDB\Driver\Manager();
		}

		if ($conn != null){
			$this->setConn($conn);
		}
	}

	public function setConn(string $conn)
	{
		$this->conn = $conn;
		list($this->database, $this->collection) = explode(".", $conn); # it is OK if we don't have a collection

		return $this;
	}

	public function exec(array $cmd)
	{
		try {
			$result = $this->manager->executeCommand($this->database, new \MongoDB\Driver\Command($cmd));
			$result->setTypeMap(['root' => 'array', 'document' => 'array']); 

		} catch (\MongoDB\Driver\Exception\ConnectionTimeoutException $e){
			die("DB::".$e->getMessage());
		} catch (\MongoDB\Driver\Exception\Exception $e) {
			die("DB::".$e->getMessage());
		}

		return $result->toArray();
	}

	private function execBulkWrite($bulk)
	{
		try {
			$result = $this->manager->executeBulkWrite($this->conn, $bulk);

		} catch (\MongoDB\Driver\Exception\BulkWriteException $e) {
			$e->getWriteResult();
			die("DB::".$e->getMessage());
		}

		/* If a write could not happen at all */
		foreach ($result->getWriteErrors() as $writeError) {
			printf("Operation#%d: %s (%d)\n", $writeError->getIndex(), $writeError->getMessage(), $writeError->getCode());
		}

		return $result;
	}

	public function createIndexes(array $indexes)
	{
		return $this->exec([
			"createIndexes" => $this->collection,
			"indexes"       => $indexes
		]);
	}

	public function update(array $data)
	{
		try {

			$bulk = new \MongoDB\Driver\BulkWrite(['ordered' => true]);

			foreach($data as $d){
				if (count($d) == 3) {
					$bulk->update($d[0],$d[1],$d[2]);
				} elseif (count($d) == 2) {
					$bulk->update($d[0],$d[1]);
				} else {
					throw new Exception("Can't update with no criteria");
				}
			}

			$result = $this->execBulkWrite($bulk);

		} catch (Exception $e) {
			die("DB::".$e->getMessage());
		}

		return $result;
	}

	public function updateOne(array $d)
	{
		try {
			$bulk = new \MongoDB\Driver\BulkWrite(['ordered' => true]);

			if (count($d) == 3) {
				$bulk->update($d[0],$d[1],$d[2]);
			} elseif (count($d) == 2) {
				$bulk->update($d[0],$d[1]);
			} else {
				throw new Exception("Can't update with no criteria");
			}			

			$result = $this->execBulkWrite($bulk);

		} catch (Exception $e) {
			die("DB::".$e->getMessage());
		}

		return $result;
	}

	public function remove(array $filter, int $limit = 0)
	{
		$bulk = new \MongoDB\Driver\BulkWrite(['ordered' => true]);
		$options = [];

		if ($limit > 0){
			$options["limit"] = $limit;
		}

		$bulk->delete($filter, $options);

		$result = $this->execBulkWrite($bulk);
	}

	public function findOne(
			array $filter = [],# aka query
			array $projection = []), # eka select
			string $hint = ""
		){

		return $this->find($filter, $projection, $hint, 1);

	}

	public function find(
			array $filter = [],# aka query
			array $projection = [], # eka select
			string $hint = "",
			int $limit = 0,
			int $skip = 0,
			array $sort = []
		){

		$options = [];

		if ($skip > 0){
			$options["skip"] = $skip;
		}

		if ($limit > 0){
			$options["limit"] = $limit;
		} else {
			$options["exhaust"] = true; # throttle
		}

		if (count($sort) > 0){
			$options["sort"] = $sort;
		}

		// "projection" => array(
			// "title" => 1,
			// "article" => 1,
		// )
		if (count($projection) > 0){
			$options["projection"] = $projection;
		}

		if ($hint != null){
			$options["hint"] = $hint;
		}

		$options["modifiers"] = ['$maxTimeMS' => 60000]; # 60 seconds

		try {
			$query = new \MongoDB\Driver\Query($filter, $options);

			$cursor = $this->manager->executeQuery($this->conn, $query);
			$cursor->setTypeMap(['root' => 'array', 'document' => 'array']);

		} catch (\MongoDB\Driver\Exception\InvalidArgumentException $e){
			die("DB::InvalidArgumentsException: ".$e->getMessage());
		} catch (\MongoDB\Driver\Exception\Exception $e) {
			die("DB::".$e->getMessage());
		}

		return $cursor->toArray();
	}

}

?>