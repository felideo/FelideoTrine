<?php
namespace  Libs\QueryBuilder;

// colocar comentado no sql em qual local do php esta sendo rodada a query!!!

class QueryBuilder{
	private $db;

	private $query;
	private $where          = [];
	private $tables_x_alias = [];
	private $join_on        = [];
	private $first;
	private $select = [];


	private $parametros = [
		'select'     => [],
		'from'       => '',
		'left_join'  => [],
		'right_join' => [],
		'inner_join' => [],
		'where'      => [],
		'order_by'   => '',
		'group_by'   => '',
		'where_in'   => [],
		'limit'      => '',
		'offset'     => '',
		'limit_from' => [],
	];

	public function __construct($db){
		$this->db = $db;
	}

	public function select($select){
		$this->parametros['select'] = $this->tratar_select($select);
		return $this;
	}

	public function addSelect($select){
		$this->parametros['select'] = array_merge($this->parametros['select'], $this->tratar_select($select));
		return $this;
	}

	public function from($from){
		$this->parametros['from'] = $this->tratar_from($from);
		return $this;
	}

	public function leftJoin($leftJoin){
		$this->parametros['left_join'][] = $this->tratar_join($leftJoin);
		return $this;
	}

	public function rightJoin($rightJoin){
		$this->parametros['right_join'][] = $this->tratar_join($rightJoin);
		return $this;
	}

	public function innerJoin($innerJoin){
		$this->parametros['inner_join'][] = $this->tratar_join($innerJoin);
		return $this;
	}

	public function where($where, $and_or = null){
		$this->parametros['where'][] = $this->tratar_where($where, $and_or = null);
		return $this;
	}

	public function andWhere($where, $and_or = null){
		$this->parametros['where'][] = $this->tratar_where($where, $and_or = null, 'AND');
		return $this;
	}

	public function orWhere($where, $and_or = null){
		$this->parametros['where'][] = $this->tratar_where($where, $and_or = null, 'OR');
		return $this;
	}

	public function orderBy($order_by){
		$this->parametros['order_by'] = $order_by;
		return $this;
	}

	public function groupBy($group_by){
		$this->parametros['group_by'] = $group_by;
		return $this;
	}

	public function whereIn($where_in){
		$this->parametros['where_in'][] = ' ( ' . $where_in . ' ) ';
		return $this;
	}

	public function limit($limit){
		$this->parametros['limit'] = $limit;
		return $this;
	}

	public function offset($offset){
		$this->parametros['offset'] = $offset;
		return $this;
	}

	public function limitFrom($limit, $offset = null, $order = null){
		$this->tratar_limit_from($limit, $offset, $order);
		return $this;
	}







	public function getParameters(){
		return $this->parametros;
	}

	public function getSqlQuery(){
		$this->build_query();
		return $this->query;
	}

	public function getQuery(){
		$this->build_query();
		return $this->query;
	}










	private function tratar_select($select){
		$select = trim(str_replace(' ', '', str_replace("\t", '', str_replace("\n", '', preg_replace('!\s+!', ' ', $select)))));

		if(substr($select, -1) == ','){
			$select = substr($select, 0, -1);
		}

		$select = explode(',', $select);

		foreach ($select as &$item) {
			$table_column = explode('.', $item);

			if(!isset($table_column[1])){
				$item = $table_column[0];
				continue;
			}

			if($table_column[1] == '*'){
				$item = $table_column[0] . '.' . $table_column[1];
				continue;
			}

			$item = $table_column[0] . '.' . $table_column[1] . ' AS ' . $table_column[0] . '__' . $table_column[1];
		}

		return $select;
	}

	private function tratar_from($from){
		$this->find_tables_name($from);
		$from = explode(' ', $from);

		$this->join_on[$from[1]] = [
			'from_table' => 0,
			'table'      => $from[1],
			'primary'    => $this->execute_sql_query("SHOW KEYS FROM {$from[0]} WHERE Key_name = 'PRIMARY'")[0]['Column_name']
		];

		return $from;
	}

	private function tratar_join($join){
		$this->find_tables_name($join);
		$this->find_join_on($join);

		return $join;
	}

	private function tratar_where($where, $and_or = null, $default = null){
		if(empty($and_or) || ($and_or != 'AND' && $and_or != 'and' && $and_or != 'OR' && $and_or != 'or')){
			$and_or = !empty($default) ? $default : 'AND';
		}

		return [
			' ( ' . $where . ' ) ',
			strtoupper($and_or),
		];
	}

	private function tratar_limit_from($limit, $offset, $order){
		if(!empty($offset)){
			$offset = ($limit * $offset);
		}

		$this->parametros['limit_from']['limit']  = $limit;
		$this->parametros['limit_from']['offset'] = !empty($offset) ? $offset : 0;
		$this->parametros['limit_from']['order']  = !empty($order) ? $order : 0;
	}


















	private function find_tables_name($join){
		$table = explode(' ', trim(preg_replace('!\s+!', ' ', $join)));

		if(!isset($table[1])){
			debug2('Obrigatorio o uso de ALIAS em todas as tabelas!');
			exit;
		}

		$this->tables_x_alias[$table[1]] = trim($table[0]);
		return $table[0];
	}

	private function find_join_on($join){
		$join = preg_replace('!\s+!', ' ', explode('=', preg_split('/ on /i', $join)[1]));

		$join_table = trim(explode('.', trim($join[0]))[0]);
		$from_table = trim(explode('.', trim($join[1]))[0]);

		$this->join_on[$join_table] = [
			'from_table' => $from_table,
			'table'      => $join_table,
			'primary'    => $this->execute_sql_query("SHOW KEYS FROM {$this->tables_x_alias[$join_table]} WHERE Key_name = 'PRIMARY'")[0]['Column_name']
		];
	}



	public function fetchArray($first = null){
		$this->first = $first;

		$retorno =  $this->execute_sql_query($this->getQuery());

		if($first == 'first'){
			return $this->convert_to_tree($retorno);
		}

		$return = $this->convert_to_tree($retorno);

		$this->clean_class();

		return $return;
	}

	private function clean_class(){
		unset($this->select);
		unset($this->query);
		unset($this->where);
		unset($this->tables_x_alias);
		unset($this->join_on);
		unset($this->first);
		unset($this->parametros);
	}

	private function execute_sql_query($sql) {
		$sth = $this->db->prepare($sql);

		$retorno = [
			$sth->execute(),
			$sth->errorCode(),
			$sth->errorInfo()
		];

		if(isset($retorno[2][2]) && !empty($retorno[2][2])){
			return [
				'error' => $retorno[2],
				'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
			];
		}

		return $sth->fetchAll(\PDO::FETCH_ASSOC);
	}



	private function build_limit_from(){
		$query = 'SELECT ' . $this->join_on[$this->parametros['from'][0]]['primary']
			. ' FROM ' . $this->parametros['from'][0];

		if(isset($this->parametros['where']) && !empty($this->parametros['where'])){
			foreach ($this->parametros['where'] as $indice => $where){
				if(preg_match('/' . $this->parametros['from'][1] . '/', $where[0])){
					$where_from[] = $where;
				}
			}

			if(!empty($where_from)){
				$where = $this->mount_where($where_from);
				$query .= $where;
			}
		}

		if(!empty($this->parametros['limit_from']['order'])){
			if(strtolower($this->parametros['limit_from']['order']) == 'rand()' || strtolower($this->parametros['limit_from']['order']) == 'rand'){
				$query .= " ORDER BY RAND()";
			}else{
				$query .= " ORDER BY '{$this->parametros['limit_from']['order']}'";
			}
		}

		$query .= ' LIMIT ' . $this->parametros['limit_from']['limit']
			. ' OFFSET ' . $this->parametros['limit_from']['offset'];

		$where = $this->execute_sql_query($query);
		$cu = [];
		if(!empty($where)){
			foreach ($where as $indice => $item) {
				$cu[] = $item['id'];
			}
		}

		if(empty($cu)){
			$cu[] = 9999999999999;
		}

		$cu = implode(',', $cu);

		$query = $this->parametros['from'][1] . '.' . $this->join_on[$this->parametros['from'][0]]['primary']
			. ' IN (' . $cu . ')';

		$this->whereIn($query);

		// debug2($this->parametros);
	}

	private function mount_where($parametros_where){
		$query = '';

		if(!empty($parametros_where)){
			$query .= " \nWHERE " . $parametros_where[0][0];

			foreach ($parametros_where as $indice => $where) {
				if($indice == 0){
					continue;
				}

				$query .= ' ' . $where[1] . ' ' . $where[0];
			}
		}

		return $query;
	}

	private function build_query(){
		foreach ($this->join_on as $table) {
			$this->parametros['select'][] = $table['table'] . '.' . $table['primary'] . ' AS ' . $table['table'] . '__' . $table['primary'];
		}

		$merge = [];

		foreach ($this->parametros['select'] as $indice => $select){
			if(!stristr($select, '*')){
				continue;
			}

			$select_porra_toda = $this->try_get_select_columns($this->tables_x_alias[explode('.', $select)[0]]);

			if(!empty($select_porra_toda)){
				$this->process_select_all(explode('.', $select)[0], $select_porra_toda);
				unset($this->parametros['select'][$indice]);
			}

			$merge = array_merge($merge, $select_porra_toda);
		}

		$this->parametros['select'] = array_merge($this->parametros['select'], $merge);
		$this->parametros['select'] = array_unique($this->parametros['select']);

		$this->select = trim(str_replace("\t", '', str_replace("\n", '', preg_replace('!\s+!', ' ', implode(', ', $this->parametros['select'])))));

		if(substr($this->select, -1) == ','){
			$this->select = substr($this->select, 0, -1);
		}

		if(!empty($this->select)){
			$this->query = 'SELECT ' . $this->select;
		}

		if(!empty($this->parametros['from'])){
			$this->query .= " \nFROM " . $this->parametros['from'][0] . ' ' . $this->parametros['from'][1];
		}

		if(!empty($this->parametros['left_join'])){
			$this->query .= " \nLEFT JOIN " . implode(" \nLEFT JOIN ", $this->parametros['left_join']);
		}

		if(!empty($this->parametros['right_join'])){
			$this->query .= " \nRIGHT JOIN " . implode(" \nRIGHT JOIN ", $this->parametros['right_join']);
		}

		if(!empty($this->parametros['inner_join'])){
			$this->query .= " \nINNER JOIN " . implode(" \nINNER JOIN ", $this->parametros['inner_join']);
		}

		if(isset($this->parametros['where']) && !empty($this->parametros['where'])){
			$where = $this->mount_where($this->parametros['where']);
			$this->query .= $where;
		}

		if(isset($this->parametros['limit_from']) && !empty($this->parametros['limit_from'])){
			$this->query .= $this->build_limit_from();
		}

		if(!empty($this->parametros['where_in']) && !empty($this->parametros['where'])){
			$this->query .= " \nAND " . implode(' AND ', $this->parametros['where_in']);
		}elseif(!empty($this->parametros['where_in']) && empty($this->parametros['where'])){
			$this->query .= " \nWHERE " . implode(' AND ', $this->parametros['where_in']);
		}

		if(!empty($this->parametros['group_by'])){
			$this->query .= " \nGROUP BY " . $this->parametros['group_by'];
		}

		if(!empty($this->parametros['order_by'])){
			$this->query .= " \nORDER BY " . $this->parametros['order_by'];
		}



		if(!empty($this->first)){
			$this->query .= " \nLIMIT 1";
		}elseif(!empty($this->parametros['limit'])){
			$this->query .= " \nLIMIT " . $this->parametros['limit'];
		}


		// 	if(!empty($this->parametros['limit'])){
		// 			$limit = [];

		// 			// debug2($this->parametros['where']);

		// 			foreach ($this->parametros['where'] as $select){
		// 				$original_from = strpos($select[0], $this->parametros['from'][1]);

		// 				if(!empty($original_from) || $original_from == 0){
		// 					$limit[] = $select[0];
		// 				}

		// 			}
		// 				// debug2($this->join_on[$this->parametros['from'][1]]);

		// 				$query_limit = 'SELECT ' . $this->parametros['from'][1] . '.' . $this->join_on[$this->parametros['from'][1]]['primary']
		// 					. ' FROM ' . $this->parametros['from'][0] . ' ' . $this->parametros['from'][1]
		// 					. ' WHERE ' . implode(' AND ', $limit)
		// 					. ' LIMIT ' . $this->parametros['limit'];

		// 				if(isset($this->parametros['offset']) && !empty($this->parametros['offset'])){
		// 					$query_limit = ' OFFSET ' . $this->parametros['offset'];
		// 				}


		// debug2();

		// 				debug2($query_limit);
		// 				exit;


		// 			if(!empty($this->parametros['where_in']) || !empty($this->parametros['where'])){
		// 				$this->query .= "\n AND " . $this->parametros['from'][1] . '.' . $this->join_on[$this->parametros['from'][1]]['primary'] . ' IN ';
		// 			}else{
		// 				$this->query .= "\n WHERE" . $this->parametros['from'][1] . '.' . $this->join_on[$this->parametros['from'][1]]['primary'] . ' IN ';
		// 			}

		// 			 $this->query .= $query_limit;

		// 			// debug2($query_limit);
		// 			// debug2($limit);

		// 		// debug2($this->query);

		// 		// debug2($this->parametros);
		// 		// exit;
		// 		}


		if(!empty($this->parametros['offset']) && empty($this->first)){
			$this->query .= " \nOFFSET " . $this->parametros['offset'];
		}
	}

	private function convert_to_tree($query){
		if(empty($query)){
			return null;
		}

		if(isset($query['error']) && !empty($query['error'])){
			return $query;
		}

		$this->get_height_nodes();
		$this->order_by_node_height($this->join_on, 'level', 'desc');

		$alias = [];

		foreach ($query[0] as $indice => $item) {
			$selected_alias = explode('__', $indice)[0];
			$alias[$selected_alias] = $selected_alias;
		}

		$ordenado_por_tabela = [];

		foreach($query as $indice_01 => $tabela) {
			$primary_from = $this->parametros['from'][1] . '__' . $this->join_on[$this->parametros['from'][1]]['primary'];

			foreach ($tabela as $indice => $coluna) {
				$tabela_x_coluna = explode('__', $indice);

				$primary = $tabela_x_coluna[0] . '__' . $this->join_on[$tabela_x_coluna[0]]['primary'];

				$from_foreign_primary = $tabela[$primary_from] . '__' . $tabela[$primary];

				if(!empty($this->join_on[$tabela_x_coluna[0]]['from_table'])){
					$foreign = $this->join_on[$this->join_on[$tabela_x_coluna[0]]['from_table']]['table'] . '__' . $this->join_on[$this->join_on[$tabela_x_coluna[0]]['from_table']]['primary'];
					$from_foreign_primary = $tabela[$primary_from] . '__' . $tabela[$foreign] .  '__' . $tabela[$primary];

					$prepare_foreign_father = explode('__', $foreign)[0];


					if(!empty($this->join_on[$prepare_foreign_father]['from_table'])){
						$foreign_father = $this->join_on[$prepare_foreign_father]['from_table'] . '__' . $this->join_on[$this->join_on[$prepare_foreign_father]['from_table']]['primary'];
					}
				}

				$ordenado_por_tabela[$tabela_x_coluna[0]][$from_foreign_primary][$tabela_x_coluna[1]]  = $coluna;
				$ordenado_por_tabela[$tabela_x_coluna[0]][$from_foreign_primary]['join_on']            = $this->join_on[$tabela_x_coluna[0]];
				$ordenado_por_tabela[$tabela_x_coluna[0]][$from_foreign_primary]['join_on']['primary'] = $tabela[$primary];
 				$ordenado_por_tabela[$tabela_x_coluna[0]][$from_foreign_primary]['join_on']['foreign']        = isset($foreign) ? $tabela[$foreign] : null;
 				$ordenado_por_tabela[$tabela_x_coluna[0]][$from_foreign_primary]['join_on']['foreign_father'] = isset($foreign_father) ? $tabela[$foreign_father] : null;
 				$ordenado_por_tabela[$tabela_x_coluna[0]][$from_foreign_primary]['join_on']['primary_from']   = $tabela[$primary_from];

 				unset($foreign);
				unset($foreign_father);

			}
		}

		foreach($this->join_on as $level) {
			foreach ($ordenado_por_tabela[$level['table']] as $indice => $resultado){
				if(!isset($resultado['join_on'])){
					continue;
				}

				$index = $resultado['join_on']['primary_from'];

				if(!empty($resultado['join_on']['foreign_father'])){
					$index .= '__' . $resultado['join_on']['foreign_father'];
				}

				if(!empty($resultado['join_on']['foreign'])){
					$index .= '__' . $resultado['join_on']['foreign'];
				}

				$tabela_join = $resultado['join_on']['table'];

				if(empty($resultado['join_on']['primary'])){
					$resultado = [];
				}

				unset($resultado['join_on']);

				if(empty($resultado) && (!isset($ordenado_por_tabela[$level['from_table']][$index][$tabela_join]))){
					$ordenado_por_tabela[$level['from_table']][$index][$tabela_join] = [];
				}else{
					$ordenado_por_tabela[$level['from_table']][$index][$tabela_join][] = $resultado;
				}

			}

			unset($ordenado_por_tabela[$level['table']]);
		}

		$ordenado_por_tabela = $this->replace_index_with_table_name($ordenado_por_tabela);

		$retorno = [];

		foreach (array_values($ordenado_por_tabela[0]) as $resultado){
			$retorno[] = $resultado[$this->parametros['from'][0]][0];
		}

		return $retorno;
	}

	private function get_height_nodes(){
		$join_on = [];
		$levels = 0;

		while(count($join_on) != count($this->join_on)) {
			foreach ($this->join_on as $indice => $table) {
				if(isset($join_on[$indice])){
					continue;
				}

				if(empty($table['from_table'])){
					$join_on[$indice]          = $table;
					$join_on[$indice]['level'] = 0;
					continue;
				}

				if(isset($join_on[$table['from_table']]['level'])){
					$join_on[$indice]          = $table;
					$join_on[$indice]['level'] = $join_on[$table['from_table']]['level'] + 1;

					if($join_on[$indice]['level'] > $levels){
						$levels = $join_on[$indice]['level'];
					}

					continue;
				}
			}
		}

		$this->join_on = $join_on;
	}

	private function order_by_node_height(&$array, $coluna, $direcao = 'asc') {
		if ($direcao != 'asc' && $direcao != 'desc') {
			$direcao = 'asc';
		}

	    $newarr = null;
	    $sortcol = array();
	    foreach ($array as $a) {
	        $sortcol[$a[$coluna]][] = $a;
	    };
	    ksort($sortcol);
	    foreach ($sortcol as $col) {
	        foreach ($col as $row)
	            $newarr[] = $row;
	    }

	    if ($direcao == 'desc')
	        if ($newarr) {
	            $array = array_reverse($newarr);
	        } else {
	            $array = $newarr;
	        } else
	        $array = $newarr;

	    $rename_index = [];

	    foreach ($array as $item) {
	    	$rename_index[$item['table']] = $item;
	    }

	    $array = $rename_index;
	}

	private function try_get_select_columns($table){
		$columns = $this->get_columns_name($table);
		if (!empty($columns)) {
			$retorno = [];

			foreach ($columns as $column) {
				$retorno[] = $column['column_name'];
			}

			return $retorno;
		}
		return false;
	}

	private function get_columns_name($table){
		return $this->execute_sql_query("SELECT column_name FROM information_schema.columns WHERE table_name = '{$table}' AND TABLE_SCHEMA = '" . DB_NAME . "'");
	}

	private function process_select_all($table, &$selects){
		foreach ($selects as &$select) {
			$select = $table . '.' . $select . ' AS ' . $table . '__' . $select;
		}
	}





	private function replace_index_with_table_name($array) {
	    if(!is_array($array)){
	    	return $array;
	    }

    	$array_values = 0;
    	$new_array = [];

	    	foreach($array as $indice => $value) {
	    		if(is_numeric($indice)){
	    			$new_array[$array_values] = $value;
	    			$array_values++;
	    		}else{
	    			$new_array[$indice] = $value;
	    		}

	    	}

	    $novo_array = array();

	    foreach ($new_array as $indice => $item) {
	    	$novo_indice = isset($this->tables_x_alias[$indice]) ? $this->tables_x_alias[$indice] : $indice;
	        $novo_array[$novo_indice] = $this->replace_index_with_table_name($item);
	    }

	    return $novo_array;
	}

}
