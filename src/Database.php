<?php
namespace  Felideo\FelideoTrine;

class Database extends \PDO {
	public function __construct($DB_TYPE, $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS) {
		try {
			parent::__construct($DB_TYPE . ':host=' . $DB_HOST . ';dbname=' . $DB_NAME, $DB_USER, $DB_PASS);
			parent::exec("SET CHARACTER SET utf8");
		} catch (\Fail $e) {
			$e->show_error(true);
		}
	}

	public function select($sql, $array = [], $fetchMode = \PDO::FETCH_ASSOC) {
		try {
			$sql = $this->pre_tratamento($sql);
			$sth = $this->prepare($sql);

			if (!empty($array)) {
				foreach ($array as $key => $value) {
					$sth->bindValue("$key", $value);
				}
			}

			$retorno = [
				$sth->execute(),
				$sth->errorCode(),
				$sth->errorInfo(),
			];

			if (isset($retorno[2][2]) && !empty($retorno[2][2])) {
				throw new \Fail($retorno[2][2], $retorno[2][1]);
			}

			return $sth->fetchAll($fetchMode);
		} catch (\Fail $e) {
			$e->show_error(true);
		}
	}

	public function execute($sql) {
		try {
			$sql = $this->pre_tratamento($sql);
			$sth = $this->prepare($sql);

			$retorno = [
				$sth->execute(),
				$sth->errorCode(),
				$sth->errorInfo(),
			];

			if (isset($retorno[2][2]) && !empty($retorno[2][2])) {
				throw new \Fail($retorno[2][2], $retorno[2][1]);
			}

			return $retorno;
		} catch (\Fail $e) {
			$e->show_error(true);
		}
	}

	public function insert($table, $data) {
		try {
			ksort($data);

			$fieldNames  = implode('`, `', array_keys($data));
			$fieldValues = ':' . implode(', :', array_keys($data));

			$sql = "INSERT INTO $table (`$fieldNames`) VALUES ($fieldValues)";
			$sql = $this->pre_tratamento($sql);
			$sth = $this->prepare($sql);

			foreach ($data as $key => $value) {
				$sth->bindValue(":$key", $value);
			}

			$retorno = [
				$sth->execute(),
				$this->lastInsertId(),
				$sth->errorCode(),
				$sth->errorInfo(),
			];

			return [
				"status"     => $retorno[0] == true ? true : false,
				"id"         => $retorno[1] != 0 ? $retorno[1] : false,
				"error_code" => $retorno[2] != '00000' ? $retorno[2] : false,
				"erros_info" => !is_null($retorno[3][2]) ? $retorno[3][2] : false,
				'dados'      => $data,
			];
		} catch (\Fail $e) {
			$e->show_error(true);
		}
	}

	public function update($table, $data, $where) {
		try {
			ksort($data);
			ksort($where);

			$fieldDetails = NULL;
			foreach ($data as $key => $value) {
				$fieldDetails .= "`$key` = :$key,";
			}

			$mount_where = NULL;
			foreach ($where as $key => $value) {
				$mount_where .= "`$key` = :$key AND";
			}

			$fieldDetails = rtrim($fieldDetails, ',');
			$mount_where  = rtrim($mount_where, 'AND');

			$sql = "UPDATE $table SET $fieldDetails WHERE $mount_where";
			$sql = $this->pre_tratamento($sql);
			$sth = $this->prepare("UPDATE $table SET $fieldDetails WHERE $mount_where");

			foreach ($data as $key => $value) {
				$sth->bindValue(":$key", $value);
			}

			foreach ($where as $key => $value) {
				$sth->bindValue(":$key", $value);
			}

			$retorno = [
				$sth->execute(),
				$this->lastInsertId(),
				$sth->errorCode(),
				$sth->errorInfo(),
			];

			return [
				"status"     => $retorno[0] == true ? true : false,
				"id"         => $retorno[1] != 0 ? $retorno[1] : false,
				"error_code" => $retorno[2] != '00000' ? $retorno[2] : false,
				"erros_info" => !is_null($retorno[3][2]) ? $retorno[3][2] : false,
				'dados'      => $data,
			];
		} catch (\Fail $e) {
			$e->show_error(true);
		}
	}

	public function delete($table, $where, $limit = null) {
		try {
			$sql = "DELETE FROM $table WHERE $where";

			if (!empty($limit)) {
				$sql .= " LIMIT $limit";
			}

			return $this->exec($sql);
		} catch (\Fail $e) {
			$e->show_error(true);
		}
	}

	public function pre_tratamento($sql) {
		if (LOCALIZADO_QUERY) {
			$sql = $this->get_localizador() . $sql;
		}

		if (PREVENT_CACHE) {
			$prevent_cache = '/* ' . date('Y-m-d H:i:s') . '*/ ';
			$sql           = $prevent_cache . $sql;
		}

		return $sql;
	}

	public function get_localizador() {
		$backtrace = [];

		foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $indice => $log) {
			if ($indice == 0) {
				continue;
			}

			if ($indice > 3) {
				break;
			}

			$backtrace[$indice] = [
				'CLASS ' . @$log['class'],
				'FUNCTION ' . @$log['function'],
				'LINE ' . @$log['line'],
			];

			$backtrace[$indice] = implode(' - ', $backtrace[$indice]);
		}

		return '/* ' . implode(' => ', $backtrace) . '*/ ';
	}
}