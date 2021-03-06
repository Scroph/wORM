<?php
namespace worm;

class QueryHelper
{
	protected $db;
	protected $table;
	protected $pk;
	protected $model;
	
	protected $cols = array();
	protected $distinct = false;
	protected $conds = array();
	protected $order;
	protected $group;
	protected $limit = array();
	
	public function __construct(\PDO $db, $table, $pk = 'id', $model = null)
	{
		$this->db = $db;
		$this->table = $table;
		$this->pk = $pk;
		
		if(is_null($model))
		{
			$possibility = ucfirst($table);
			if(class_exists(__NAMESPACE__.'\\'.$possibility))
			{
				$this->model = $possibility;
			}
			else
			{
				$this->model = 'Model';
			}
		}
	}

	public function select()
	{
		$this->cols += func_get_args();
		return $this;
	}

	public function distinct()
	{
		$this->distinct = true;
		return $this;
	}
	
	public function where($cond, $val)
	{
		$this->where_op($cond, '=', $val);
		return $this;
	}
	
	/* where_op('score', '>=', 10) */
	public function where_op($cond, $operator, $val)
	{
		$this->conds[$cond.' '.$operator] = $val;
		return $this;
	}
	
	/* where_like('username', '%admin%') */
	public function where_like($cond, $val)
	{
		$this->where_op($cond, 'LIKE', $val);
		return $this;
	}

	public function by_pk($id)
	{
		return $this->where($this->pk, $id);
	}
	
	public function order_by($col)
	{
		$this->order = $col;
		return $this;
	}
	
	public function limit($offset, $length)
	{
		$this->limit = array($offset, $length);
		return $this;
	}
	
	/* Allows for the quick n easy deletion of database entries.
	 * As opposed to $obj = $finder->by_pk(1)->find(true); $obj->delete();
	 * this method will remove the entry without running a select query
	 * first : $finder->by_pk->delete();
	 * Returns the number of affected rows.
	 */

	public function delete()
	{
		$sql = sprintf('DELETE FROM %s WHERE ', $this->table);
		$cols = array();
		$args = array();

		foreach($this->conds as $k => $v)
		{
			$cols[] = $k.' ?';
			$args[] = $v;
		}

		$sql .= implode(' AND ', $cols);
		
		if(!empty($this->order))
		{
			$sql .= ' ORDER BY ?';
			$args[] = $this->order;
		}
		
		if(!empty($this->limit))
		{
			$sql .= sprintf(' LIMIT %d, %d', $this->limit[0], $this->limit[1]);
		}
		
		return $this->raw_exec($sql, $args);
	}
	
	/* Returns an array of models or, if $one is
	 * set to true, a single model.
	 * If no matches are found, it will return false.
	 * If an error occurs, the PDO instance will throw
	 * a PDOException, as usual.
	 */
	public function find($one = false)
	{
		/* If the user didn't specify any columns, using the *
		 * joker to retrieve all the available columns.
		 */
		if(empty($this->cols))
		{
			$this->cols = array('*');
		}
		
		/* If $one is set to true, this method will return
		 * a single model instead of an array of models.
		 */
		if($one)
		{
			if(empty($this->limit))
			{
				$this->limit = array(0, 1);
			}
			else
			{
				$this->limit[1] = 1;
			}
		}
		
		$args = array();
		$sql = sprintf('SELECT %s%s FROM %s',
			$this->distinct ? 'DISTINCT ' : '',
			implode(', ', $this->cols),
			$this->table
		);

		if(!empty($this->conds))
		{
			$sql .= ' WHERE ';
			$cols = array();
			
			foreach($this->conds as $k => $v)
			{
				$cols[] = $k.' ?';
				$args[] = $v;
			}
			
			$sql .= implode(' AND ', $cols);
		}
		
		if(!empty($this->order))
		{
			$sql .= ' ORDER BY ?';
			$args[] = $this->order;
		}
		
		if(!empty($this->limit))
		{
			$sql .= sprintf(' LIMIT %d, %d', $this->limit[0], $this->limit[1]);
		}
		
		$results = $this->build_models($sql, $args);

		if($results !== false)
		{
			return $one ? $results[0] : $results;
		}

		return false;
	}

	
	/* Returns an array of Model objects out of the result
	 * of running the given SQL query and argument or false 
	 * if no matches were found in the database.
	 * I made it public in case the user wanted to write his
	 * own query like a big boy, and not rely on the sugarness above.
	 */
	public function build_models($sql, array $args = array())
	{
		$objects = array();
		$constructor_args = array($this->db, $this->table, $this->pk);
		$query = $this->db->prepare($sql);
		
		//$query->setFetchMode();
		$query->execute($args);
		$objects = $query->fetchAll(
			\PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE,
			 __NAMESPACE__.'\\'.$this->model, 
			 $constructor_args
		);
		
		if(empty($objects))
		{
			return false;
		}

		return $objects;
	}

	/* Runs an INSERT, UPDATE or DELETE query and returns the
	 * number of affected rows.
	 */
	public function raw_exec($sql, array $args = array())
	{
		$query = $this->db->prepare($sql);
		$query->execute();

		return $query->rowCount();
	}
	
	/* Runs a SELECT query and returns an iterator on the
	 * results.
	 */
	public function raw_select($sql, array $args = array())
	{
		$query = $this->db->prepare($sql);
		$query->execute();

		return $query;
	}
}
