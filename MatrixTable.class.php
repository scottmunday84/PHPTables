<?php

class Amorphous
{
	protected $_values = array();
	protected $_callbacks = array();
	
	function __get($property)
	{
		if (isset($this->_values[$property]))
		{
			return $this->_values[$property];
		}
		elseif (isset($this->_callbacks[$property]) && is_callable($this->_callbacks[$property]))
		{
			return ($this->_values[$property] = $this->_callbacks[$property]($this));
		}
		
		return null;
	}
	
	function __set($property, $value)
	{
		$this->_values[$property] = $value;
	}	
};

class MatrixTable extends Amorphous
{ 
	protected $_columns = array();
	protected $_rows = array();
	protected $_cells = array();
	
	protected $_columnCount = 0;	
	protected $_rowCount = 0;
	
	protected $_columnCallbacks = array();
	protected $_rowCallbacks = array();
	protected $_cellCallbacks = array();
	
	const TYPE_TABLE = -1;
	const TYPE_CELL = 0;
	const TYPE_ROW = 1;
	const TYPE_COLUMN = 2;
	
	protected $_renderMap = array();
	
	function __construct($columns, $rows)
	{
		$this->_columnCount = ($columns < 0 ? 1 : $columns);
		$this->_rowCount = ($rows < 0 ? 1 : $rows);		
		
		for ($a = 0; $a <= $this->_columnCount; ++$a)
		{
			$this->_renderMap[$a] = array();
			
			for ($b = 0; $b <= $this->_rowCount; ++$b)
			{
				$this->_renderMap[$a][$b] = null;
			}
		}
	}
	
	private function _build($type)
	{
		switch ($type)
		{
			case self::TYPE_COLUMN:
				list($ignore, $column) = func_get_args();
				
				if (!isset($this->_columns[$column]))
				{
					$this->_columns[$column] = new MatrixColumn($this, $column, $this->_rowCount);
				}				
				
				return $this->_columns[$column];
			case self::TYPE_ROW:
				list($ignore, $row) = func_get_args();
				
				if (!isset($this->_rows[$row]))
				{
					$this->_rows[$row] = new MatrixRow($this, $row, $this->_columnCount);
				}				

				return $this->_rows[$row];
			case self::TYPE_CELL: // Cell considers rows and columns.
				list($ignore, $column, $row) = func_get_args();
				
				$_column = $this->_build(self::TYPE_COLUMN, $column);
				$_row = $this->_build(self::TYPE_ROW, $row);				
				
				if (!isset($this->_cells[$column][$row]))
				{
					$this->_cells[$column][$row] = new MatrixCell($this, $_column, $_row);
				}				
				
				return $this->_cells[$column][$row];
		}
	}			
	
	public function row($row)
	{
		return $this->_build(self::TYPE_ROW, $row);
	}

	public function column($column)
	{
		return $this->_build(self::TYPE_COLUMN, $column);
	}
	
	public function cell($column, $row)
	{
		return $this->_build(self::TYPE_CELL, $column, $row);
	}

	public function callbacks($type)
	{
		switch ($type)
		{
			case self::TYPE_COLUMN:
				return $this->_columnCallbacks;
			case self::TYPE_ROW:
				return $this->_rowCallbacks;
			case self::TYPE_CELL:
				return $this->_cellCallbacks;
		}
		
		return array(); // Return an empty array.
	}
	
	public function callback($type, $property, $callback)
	{
		if (is_callable($callback))
		{
			switch ($type)
			{
				case self::TYPE_COLUMN:
					$this->_columnCallbacks[$property] = $callback;
					break;
				case self::TYPE_ROW:
					$this->_rowCallbacks[$property] = $callback;
					break;
				case self::TYPE_CELL:
					$this->_cellCallbacks[$property] = $callback;
					break;
			}
		}
	}
	
	protected function _parseMapDimension($type, $selection)
	{
		$selected = array();		
		
		$max = 0;
	
		if ($type == self::TYPE_COLUMN)
		{
			$max = $this->_columnCount - 1;
		}
		elseif ($type == self::TYPE_ROW)
		{
			$max = $this->_rowCount - 1;
		}
		
		// List. Reapply.
		$selections = explode(';', $selection);		
		
		foreach ($selections as $selection)
		{
			$tmp = array();
		
			if ($selection == '*') // All.			
			{
				$tmp = range(0, $max);
			}
			elseif ($selection == 'first') // First.
			{
				$tmp = array(0);
			}
			elseif ($selection == 'last') // Last.
			{
				$tmp = array($max);
			}
			elseif ($selection == 'even') // Even.
			{
				$tmp = array(0, $max, 2);
			}
			elseif ($selection == 'odd') // Odd.
			{
				if ($max != 0)
				{
					$tmp = array(1, $max, 2);
				}
			}
			elseif (preg_match('/[1-9][0-9]*-[1-9][0-9]*/', $selection)) // Range.
			{
				list($start, $stop) = explode('-', $selection);
				
				if ($start > $max) { $start = $max; }
				if ($stop < $start) { $stop = $start; }
				
				$tmp = range($start, $stop);
			}
			elseif (preg_match('/[1-9][0-9]*/', $selection)) // Index.
			{
				if ($selection >= 0 && $selection <= $max) { $tmp = array($selection); }
			}
			
			$selected = array_merge($selected, $tmp);
		}		
		
		return array_unique($selected);
	}
	
	protected function _assignRenderMapAttributes(&$location, $attributes)
	{
		foreach ($attributes as $attribute => $callback)
		{
			if (is_callable($callback))
			{
				$location[$attribute] = $callback;
			}
		}
	}	
	
	public function map($selection, $callback, $attributes = array())
	{
		if (!is_callable($callback)) { return; } // Do not move further if a callback isn't provided.
	
		list($x, $y) = explode(',', $selection);
		
		$x = $this->_parseMapDimension(self::TYPE_COLUMN, $x);
		$y = $this->_parseMapDimension(self::TYPE_ROW, $y);
		
		$appliedRowAttributes = false;
		
		foreach ($x as $xOffset)
		{
			foreach ($y as $yOffset)
			{
				$this->_renderMap[$xOffset][$yOffset] = array();				
				$this->_renderMap[$xOffset][$yOffset][0] = $callback; // Index "0" is the render function.
				
				// Assign attributes.
				if (isset($attributes[self::TYPE_CELL]) && is_array($attributes[self::TYPE_CELL]))
				{
					$this->_assignRenderMapAttributes($this->_renderMap[$xOffset][$yOffset], $attributes[self::TYPE_CELL]); // All other index values are properties of the cell.
				}
				
				// Assign attributes.
				if (!$appliedRowAttributes && isset($attributes[self::TYPE_ROW]) && is_array($attributes[self::TYPE_ROW]))
				{
					$this->_assignRenderMapAttributes($this->_renderMap[$this->_columnCount][$yOffset], $attributes[self::TYPE_ROW]);
				}
			}
			
			// Make it this far, you already went through all rows. Don't do again.
			$appliedRowAttributes = true;
			
			// Assign attributes.
			if (isset($attributes[self::TYPE_COLUMN]) && is_array($attributes[self::TYPE_COLUMN]))
			{
				$this->_assignRenderMapAttributes($this->_renderMap[$xOffset][$this->_rowCount], $attributes[self::TYPE_COLUMN]);
			}
		}
		
		// Assign attributes.
		if (isset($attributes[self::TYPE_TABLE]) && is_array($attributes[self::TYPE_TABLE]))
		{
			$this->_assignRenderMapAttributes($this->_renderMap[$this->_columnCount][$this->_rowCount], $attributes[self::TYPE_TABLE]);
		}
	}
	
	protected function _processAttributes($element, $attributes)
	{
		$rtn = '';
		
		if (is_array($attributes))
		{
			foreach ($attributes as $attribute => $callback)
			{
				$rtn .= ' ' . htmlentities($attribute) . '="' . htmlentities($callback($element)) . '"';
			}
		}
		
		return $rtn;
	}
	
	public function render()
	{	
		echo '<table' . $this->_processAttributes($this, $this->_renderMap[$this->_columnCount][$this->_rowCount]) . '>';
		
		for ($a = 0; $a < $this->_rowCount; ++$a)
		{
			echo '<tr' . $this->_processAttributes($this->row($a), $this->_renderMap[$this->_columnCount][$a]) . '>';
		
			for ($b = 0; $b < $this->_columnCount; ++$b)
			{
				$cell = $this->_build(self::TYPE_CELL, $b, $a); // X x Y
				
				echo '<td' . 
					($this->_processAttributes($this->column($b), $this->_renderMap[$b][$this->_rowCount])) .
					($this->_processAttributes($cell, array_slice($this->_renderMap[$b][$a], 1))) .
					'>';
				echo ($this->_renderMap[$b][$a] ? $this->_renderMap[$b][$a][0]($cell) : '&nbsp;');
				echo '</td>';
			}			
			
			echo '</tr>';
		}
		
		echo '</table>';
	}
};

class MatrixColumn extends Amorphous 
{ 
	public $matrix;
	
	public $index;
	public $rows;	

	function __construct($table, $column, $rows)
	{
		$this->table = $table;
		
		$this->index = $column;		
		$this->rows = $rows;		

		$this->_callbacks = $table->callbacks(MatrixTable::TYPE_COLUMN);			
	}
};


class MatrixRow extends Amorphous 
{ 
	public $matrix;
	
	public $index;
	public $columns;
	
	function __construct($table, $row, $columns)
	{
		$this->table = $table;
				
		$this->index = $row;
		$this->columns = $columns;		

		$this->_callbacks = $table->callbacks(MatrixTable::TYPE_ROW);							
	}
};

class MatrixCell extends Amorphous 
{ 
	public $matrix;
	
	public $column;
	public $row;
	
	function __construct($table, $column, $row)
	{
		$this->table = $table;
		
		$this->column = $column;
		$this->row = $row;
		
		$this->_callbacks = $table->callbacks(MatrixTable::TYPE_CELL);					
	}	
};
