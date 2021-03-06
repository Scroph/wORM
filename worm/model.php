<?php
namespace worm;

class Model
{
	protected $db;
	protected $table;
	protected $pk;
	protected $cols = array();
	
	public function __construct(\PDO $db, $table, $pk = 'id')
	{
		$this->db = $db;
		$this->table = $table;
		$this->pk = $pk;
	}
	
	public function __get($property)
	{
		if(!array_key_exists($property, $this->cols))
		{
			throw new \OutOfBoundsException('Column not found.');
		}
		
		return $this->cols[$property];
	}
	
	public function __set($property, $value)
	{
		$this->cols[$property] = $value;
	}
	
	public function delete()
	{
		$sql = 'DELETE FROM '.$this->table.' WHERE ';
		try
		{
			/* If the primary key isn't available, it
			 * means this object was created for the sake
			 * of insertion, and not the result of a select query.
			 * If the user wants to delete 
			 * a database entry, he must use the Finder class.
			 */

			$id = $this->cols[$this->pk];
			$sql .= $this->pk.' = ?';
			$args = array($id);

			return $this->raw_exec($sql, $args);
		}
		catch(\OutOfBoundsException $e)
		{
			return false;
		}
	}

	public function insert()
	{
		$sql = sprintf('INSERT INTO %s ', $this->table);
		$cols = array();
		$args = array();

		foreach($this->cols as $k => $v)
		{
			$cols[] = $k;
			$args[] = $v;
		}

		$sql .= '('.implode(', ', $cols).')';
		$sql .= ' VALUES ';
		$sql .= '('.implode(', ', str_split(str_repeat('?', sizeof($args)))).')';

		return $this->raw_exec($sql, $args);
	}

	public function update()
	{
		$sql = sprintf('UPDATE %s SET ', $this->table);
		$cols = array();
		$args = array();

		foreach($this->cols as $k => $v)
		{
			$cols[] = $k.' = ?';
			$args[] = $v;
		}

		$sql .= implode(', ', $cols);
		$sql .= ' WHERE '.$this->pk.' = ?';
		$args[] = $this->cols[$this->pk];
		
		return $this->raw_exec($sql, $args);
	}

	public function raw_exec($sql, array $args = array())
	{
		$query = $this->db->prepare($sql);
		$query->execute($args);
		
		//var_dump($sql, $args);
		//return;

		return $query->rowCount();
	}
}
