<?php
namespace Felideo\FelideoTrine;

class QueryBuilder{

	private $db;
	private $select;
	private $from;
	private $query;
	private $where          = [];
	private $leftJoin       = [];
	private $rightJoin      = [];
	private $innerJoin      = [];
	private $tables_x_alias = [];
	private $join_on        = [];
	private $select_array = [];
	private $order_by;
	private $limit;
	private $first;
	private $offset;
	private $group_by;
	private $where_in = [];


	public function __construct($db){
		$this->db = $db;
	}

	public function select($select){
		$select = trim(str_replace(' ', '', str_replace("\t", '', str_replace("\n", '', preg_replace('!\s+!', ' ', $select)))));

		if(substr($select, -1) == ','){
			$select = substr($select, 0, -1);
		}

		$select = explode(',', $select);



		foreach ($select as &$item) {
			$table_column = explode('.', $item);

			if(!isset($table_column[1])){
				$item = $table_column[0];
				$this->tables_get_primary[$table_column[0]] = 'from';
				continue;
			}

			if($table_column[1] == '*'){
				$item = $table_column[0] . '.' . $table_column[1];
				$this->tables_get_primary[$table_column[0]] = $table_column[0];
				continue;
			}

			$item = $table_column[0] . '.' . $table_column[1] . ' AS ' . $table_column[0] . '__' . $table_column[1];
			$this->tables_get_primary[$table_column[0]] = $table_column[0];
		}

		$this->select_array = $select;

		return $this;
	}

	public function orderBy($order_by){
		$this->order_by = $order_by;
		return $this;
	}

	public function whereIn($where_in){
		$this->where_in[] = $where_in;
		return $this;
	}

	public function limit($limit){
		$this->limit = $limit;
		return $this;
	}

	public function from($from){
		$this->find_tables_name($from);
		$this->from = explode(' ', $from);

		$this->join_on[$this->from[1]] = [
			'from_table' => 0,
			'table'      => explode(' ', $from)[1],
			'primary'    => $this->db->select("SHOW KEYS FROM {$this->from[0]} WHERE Key_name = 'PRIMARY'")[0]['Column_name']
		];

		return $this;
	}

	public function groupBy($group_by){
		$this->group_by = $group_by;
		return $this;
	}

	public function where($where){
		$this->where[] = $where;
		return $this;
	}

	public function leftJoin($leftJoin){
		$this->find_tables_name($leftJoin);
		$this->find_join_on($leftJoin);
		$this->leftJoin[] = $leftJoin;
		return $this;
	}

	public function rightJoin($rightJoin){
		$this->find_tables_name($rightJoin);
		$this->find_join_on($rightJoin);
		$this->rightJoin[] = $rightJoin;
		return $this;
	}

	public function innerJoin($innerJoin){
		$this->find_tables_name($innerJoin);
		$this->find_join_on($innerJoin);
		$this->innerJoin[] = $innerJoin;
		return $this;
	}

	public function offset($offset){
		$this->offset = $offset;
		return $this;
	}

	public function fetchArray($first = null){
		$this->first = $first;
		$retorno =  $this->db->select($this->getQuery());

		if($first == 'first'){
			return $this->convert_to_tree($retorno);
		}

		return $this->convert_to_tree($retorno);
	}

	public function getQuery(){
		$this->build_query();



		return $this->query;
	}

	private function build_query(){
		foreach ($this->join_on as $table) {
			$this->select_array[] = $table['table'] . '.' . $table['primary'] . ' AS ' . $table['table'] . '__' . $table['primary'];
		}

		$merge = [];

		foreach ($this->select_array as $indice => $select){
			if(!stristr($select, '*')){
				continue;
			}

			$select_porra_toda = $this->try_get_select_columns($this->tables_x_alias[explode('.', $select)[0]]);

			if(!empty($select_porra_toda)){
				$this->process_select_all(explode('.', $select)[0], $select_porra_toda);
				unset($this->select_array[$indice]);
			}

			$merge = array_merge($merge, $select_porra_toda);
		}

		$this->select_array = array_merge($this->select_array, $merge);
		$this->select_array = array_unique($this->select_array);

		$this->select = trim(str_replace("\t", '', str_replace("\n", '', preg_replace('!\s+!', ' ', implode(', ', $this->select_array)))));

		if(substr($this->select, -1) == ','){
			$this->select = substr($this->select, 0, -1);
		}

		if(!empty($this->select)){
			$this->query = 'SELECT ' . $this->select;
		}

		if(!empty($this->from)){
			$this->query .= " \nFROM " . $this->from[0] . ' ' . $this->from[1];
		}

		if(!empty($this->leftJoin)){
			$this->query .= " \nLEFT JOIN " . implode(" \nLEFT JOIN ", $this->leftJoin);
		}

		if(!empty($this->rightJoin)){
			$this->query .= " \nRIGHT JOIN " . implode(" \nRIGHT JOIN ", $this->rightJoin);
		}

		if(!empty($this->innerJoin)){
			$this->query .= " \nINNER JOIN " . implode(" \nINNER JOIN ", $this->innerJoin);
		}

		if(!empty($this->where)){
			$this->query .= " \nWHERE " . implode(' AND ', $this->where);
		}

		if(!empty($this->where_in) && !empty($this->where)){
			$this->query .= " \nAND " . implode(' AND ', $this->where_in);
		}elseif(!empty($this->where_in) && empty($this->where)){
			$this->query .= " \nWHERE " . implode(' AND ', $this->where_in);
		}

		if(!empty($this->order_by)){
			$this->query .= " \nORDER BY " . $this->order_by;
		}

		if(!empty($this->group_by)){
			$this->query .= " \nGROUP BY " . $this->group_by;
		}

		if(!empty($this->first)){
			$this->query .= " \nLIMIT 1";
		}elseif(!empty($this->limit)){
			$this->query .= " \nLIMIT " . $this->limit;
		}

		if(!empty($this->offset) && empty($this->first)){
			$this->query .= " \nOFFSET " . $this->offset;
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

		foreach($query as $tabela) {
			// debug2($tabela);

			$primary_from = $this->from[1] . '__' . $this->join_on[$this->from[1]]['primary'];

			foreach ($tabela as $indice => $coluna) {
				$tabela_x_coluna = explode('__', $indice);

				$primary = $tabela_x_coluna[0] . '__' . $this->join_on[$tabela_x_coluna[0]]['primary'];
// debug2($indice);
// debug2($tabela_x_coluna[0]);
// exit;

				if(!empty($this->join_on[$tabela_x_coluna[0]]['from_table'])){
					$foreign = $this->join_on[$this->join_on[$tabela_x_coluna[0]]['from_table']]['table'] . '__' . $this->join_on[$this->join_on[$tabela_x_coluna[0]]['from_table']]['primary'];
				}

				$ordenado_por_tabela[$tabela_x_coluna[0]][$tabela[$primary_from] . '__' . $tabela[$primary]][$tabela_x_coluna[1]]  = $coluna;
				$ordenado_por_tabela[$tabela_x_coluna[0]][$tabela[$primary_from] . '__' . $tabela[$primary]]['join_on']            = $this->join_on[$tabela_x_coluna[0]];
				$ordenado_por_tabela[$tabela_x_coluna[0]][$tabela[$primary_from] . '__' . $tabela[$primary]]['join_on']['primary'] = $tabela[$primary];
 				$ordenado_por_tabela[$tabela_x_coluna[0]][$tabela[$primary_from] . '__' . $tabela[$primary]]['join_on']['foreign']      = isset($foreign) ? $tabela[$foreign] : null;
 				$ordenado_por_tabela[$tabela_x_coluna[0]][$tabela[$primary_from] . '__' . $tabela[$primary]]['join_on']['primary_from'] = $tabela[$primary_from];
			}
		}

		foreach($this->join_on as $level) {
			foreach ($ordenado_por_tabela[$level['table']] as $resultado){

				$index = $resultado['join_on']['primary_from'] . '__' . $resultado['join_on']['foreign'];

				$tabela_join = $resultado['join_on']['table'];
				unset($resultado['join_on']);

				$ordenado_por_tabela[$level['from_table']][$index][$tabela_join][] = $resultado;
			}

			unset($ordenado_por_tabela[$level['table']]);
		}

		$ordenado_por_tabela = $this->replace_index_with_table_name($ordenado_por_tabela);

		$retorno = [];

		foreach (array_values($ordenado_por_tabela[0]) as $resultado){
			$retorno[] = $resultado[$this->from[0]][0];
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
		// debug2($table);

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

		return $this->db->select("SELECT column_name FROM information_schema.columns WHERE table_name = '{$table}'");
	}

	private function process_select_all($table, &$selects){
		foreach ($selects as &$select) {
			$select = $table . '.' . $select . ' AS ' . $table . '__' . $select;
		}
	}

	private function find_tables_name($join){
		$join = trim(preg_replace('!\s+!', ' ', $join));
		$table = explode(' ', $join);
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
			'primary'    => $this->db->select("SHOW KEYS FROM {$this->tables_x_alias[$join_table]} WHERE Key_name = 'PRIMARY'")[0]['Column_name']
		];
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
