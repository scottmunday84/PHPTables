<?php
namespace MatrixTables;

const TYPE_TABLE = -1;
const TYPE_CELL = 0;
const TYPE_ROW = 1;
const TYPE_COLUMN = 2;

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

class Column extends Amorphous 
{ 
	public $table;
	
	public $index;
	public $rows;	

	function __construct($table, $column, $rows)
	{
		$this->table = $table;
		
		$this->index = $column;		
		$this->rows = $rows;		

		$this->_callbacks = $table->callbacks(TYPE_COLUMN);			
	}
};

class Row extends Amorphous 
{ 
	public $table;
	
	public $index;
	public $columns;
	
	function __construct($table, $row, $columns)
	{
		$this->table = $table;
				
		$this->index = $row;
		$this->columns = $columns;		

		$this->_callbacks = $table->callbacks(TYPE_ROW);							
	}
};

class Cell extends Amorphous 
{ 
	public $table;
	
	public $column;
	public $row;
	
	public $rows = 1;
	public $columns = 1;
	
	function __construct($table, $column, $row)
	{
		$this->table = $table;
		
		$this->column = $column;
		$this->row = $row;
		
		$this->_callbacks = $table->callbacks(TYPE_CELL);					
	}

	public function expand($columns = 1, $rows = 1)
	{
		$this->columns = ($columns <= 0 ? 1 : (int)$columns);
		$this->rows = ($rows <= 0 ? 1 : (int)$rows);
	}
};

class Table extends Amorphous
{ 
	protected $_columns = array();
	protected $_rows = array();
	protected $_cells = array();
	
	protected $_columnCount = 0;	
	protected $_rowCount = 0;
	
	protected $_columnCallbacks = array();
	protected $_rowCallbacks = array();
	protected $_cellCallbacks = array();
	
	const RENDER = 0;
	
	const SKIP = -1;	
	
	protected $_renderMap = array();
	
	function __construct($columns, $rows)
	{
		$this->_columnCount = ($columns <= 0 ? 1 : (int)$columns);
		$this->_rowCount = ($rows <= 0 ? 1 : (int)$rows);		
		
		for ($a = 0; $a <= $this->_columnCount; ++$a)
		{
			$this->_renderMap[$a] = array();
			
			for ($b = 0; $b <= $this->_rowCount; ++$b)
			{
				$this->_renderMap[$a][$b] = array(self::RENDER => null);
			}
		}		
	}
	
	private function _build($type)
	{
		switch ($type)
		{
			case TYPE_COLUMN:
				list($ignore, $column) = func_get_args();
				
				if (!isset($this->_columns[$column]))
				{
					$this->_columns[$column] = new Column($this, $column, $this->_rowCount);
				}				
				
				return $this->_columns[$column];
			case TYPE_ROW:
				list($ignore, $row) = func_get_args();
				
				if (!isset($this->_rows[$row]))
				{
					$this->_rows[$row] = new Row($this, $row, $this->_columnCount);
				}				

				return $this->_rows[$row];
			case TYPE_CELL: // Cell considers rows and columns.
				list($ignore, $column, $row) = func_get_args();
				
				$_column = $this->_build(TYPE_COLUMN, $column);
				$_row = $this->_build(TYPE_ROW, $row);				
				
				if (!isset($this->_cells[$column][$row]))
				{
					$this->_cells[$column][$row] = new Cell($this, $_column, $_row);
				}				
				
				return $this->_cells[$column][$row];
		}
	}			
	
	public function row($row)
	{
		return $this->_build(TYPE_ROW, $row);
	}

	public function column($column)
	{
		return $this->_build(TYPE_COLUMN, $column);
	}
	
	public function cell($column, $row)
	{
		return $this->_build(TYPE_CELL, $column, $row);
	}

	public function callbacks($type)
	{
		switch ($type)
		{
			case TYPE_TABLE:
				return $this->_callbacks;
			case TYPE_COLUMN:
				return $this->_columnCallbacks;
			case TYPE_ROW:
				return $this->_rowCallbacks;
			case TYPE_CELL:
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
				case TYPE_TABLE:
					$this->_callbacks[$property] = $callback;
					break;			
				case TYPE_COLUMN:
					$this->_columnCallbacks[$property] = $callback;
					break;
				case TYPE_ROW:
					$this->_rowCallbacks[$property] = $callback;
					break;
				case TYPE_CELL:
					$this->_cellCallbacks[$property] = $callback;
					break;
			}
		}
	}
	
	protected function _parseMapDimension($type, $selection)
	{
		$selected = array();		
		
		$max = 0;
	
		if ($type == TYPE_COLUMN)
		{
			$max = $this->_columnCount - 1;
		}
		elseif ($type == TYPE_ROW)
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
			elseif ($selection == 'odd') // Odd.
			{
				$tmp = range(0, $max, 2);
			}
			elseif ($selection == 'even') // Even.
			{
				if ($max != 0)
				{
					$tmp = range(1, $max, 2);
				}
			}
			elseif (preg_match('/0|[1-9][0-9]*-0|[1-9][0-9]*/', $selection)) // Range.
			{
				list($start, $stop) = explode('-', $selection);
				
				if ($start > $max) { $start = $max; }
				if ($stop < $start) { $stop = $start; }
				
				$tmp = range($start, $stop);
			}
			elseif (preg_match('/0|[1-9][0-9]*/', $selection)) // Index.
			{
				if ($selection >= 0 && $selection <= $max) { $tmp = array($selection); }
			}
			
			$selected = array_merge($selected, $tmp);
		}		
		
		return array_unique($selected);
	}
	
	protected function _assignRenderMapRender(&$location, $callback)
	{
		if (is_callable($callback))
		{
			if (!is_array(@$location))
			{
				$location = array();
			}
		
			$location[] = $callback;
		}
	}
	
	protected function _assignRenderMapAttributes(&$location, $attributes)
	{
		foreach ($attributes as $attribute => $callback)
		{
			if (is_callable($callback))
			{
				if (!is_array(@$location[$attribute]))
				{
					$location[$attribute] = array();
				}
		
				$location[$attribute][] = $callback;
			}
		}
	}	
	
	public function map($selection, $callback, $attributes = array())
	{
		list($x, $y) = explode(',', $selection);
		
		$x = $this->_parseMapDimension(TYPE_COLUMN, $x);
		$y = $this->_parseMapDimension(TYPE_ROW, $y);
		
		$appliedRowAttributes = false;
		
		foreach ($x as $xOffset)
		{
			foreach ($y as $yOffset)
			{
				$this->_assignRenderMapRender($this->_renderMap[$xOffset][$yOffset][self::RENDER], $callback);
				
				// Assign attributes.
				if (isset($attributes[TYPE_CELL]) && is_array($attributes[TYPE_CELL]))
				{
					$this->_assignRenderMapAttributes($this->_renderMap[$xOffset][$yOffset], $attributes[TYPE_CELL]); // All other index values are properties of the cell.
				}
				
				// Assign attributes.
				if (!$appliedRowAttributes && isset($attributes[TYPE_ROW]) && is_array($attributes[TYPE_ROW]))
				{
					$this->_assignRenderMapAttributes($this->_renderMap[$this->_columnCount][$yOffset], $attributes[TYPE_ROW]);
				}
			}
			
			// Make it this far, you already went through all rows. Don't do again.
			$appliedRowAttributes = true;
			
			// Assign attributes.
			if (isset($attributes[TYPE_COLUMN]) && is_array($attributes[TYPE_COLUMN]))
			{
				$this->_assignRenderMapAttributes($this->_renderMap[$xOffset][$this->_rowCount], $attributes[TYPE_COLUMN]);
			}
		}
		
		// Assign attributes.
		if (isset($attributes[TYPE_TABLE]) && is_array($attributes[TYPE_TABLE]))
		{
			$this->_assignRenderMapAttributes($this->_renderMap[$this->_columnCount][$this->_rowCount], $attributes[TYPE_TABLE]);
		}
	}
	
	protected function _processAttributes($element, $attributes, $previousAttributes = array())
	{
		$rtn = $previousAttributes;
		
		if (is_array($attributes))
		{
			foreach ($attributes as $attribute => $callbacks)
			{
				if (is_array($callbacks))
				{
					foreach ($callbacks as $callback)
					{
						$rtn[$attribute] = $callback($element, @$rtn[$attribute]);
					}
				}
			}
		}
		
		return $rtn;
	}
	
	protected function _renderAttributes()
	{
		$attributes = array();
		$parameters = func_get_args();
		
		$rtn = '';
		
		foreach ($parameters as $parameter)
		{
			$attributes = array_merge($attributes, $parameter);
		}
		
		foreach ($attributes as $attribute => $value)
		{
			$rtn .= ' ' . htmlentities($attribute) . '="' . htmlentities($value) . '"';			
		}
		
		return $rtn;
	}
	
	public function _render($element, $renders)
	{
		$rtn = '';
		
		foreach ($renders as $render)
		{
			$rtn = $render($element, $rtn);
		}
		
		return $rtn;
	}
	
	public function render()
	{	
		echo '<table' . $this->_renderAttributes($this->_processAttributes($this, $this->_renderMap[$this->_columnCount][$this->_rowCount])) . '>' . PHP_EOL;
		
		for ($a = 0; $a < $this->_rowCount; ++$a)
		{
			echo "\t" . '<tr' . $this->_renderAttributes($this->_processAttributes($this->row($a), $this->_renderMap[$this->_columnCount][$a])) . '>' . PHP_EOL;
		
			for ($b = 0; $b < $this->_columnCount; ++$b)
			{
				$cell = $this->_build(TYPE_CELL, $b, $a); // X x Y
				
				if ($this->_renderMap[$b][$a][self::RENDER] != self::SKIP)
				{
					echo "\t\t" . '<td colspan="' . htmlentities($cell->columns) . '" rowspan="' . htmlentities($cell->rows) . '"' . 
						$this->_renderAttributes(
							$this->_processAttributes($this->column($b), $this->_renderMap[$b][$this->_rowCount]),
							$this->_processAttributes($cell, array_slice($this->_renderMap[$b][$a], 1))
						) .
						'>';
					echo ($this->_renderMap[$b][$a][self::RENDER] ? $this->_render($cell, $this->_renderMap[$b][$a][self::RENDER]) : '&nbsp;');
					echo '</td>' . PHP_EOL;
					
					// Map to the cell expansion.
					for ($c = $a, $d = min($this->_rowCount, $c + $cell->rows); $c < $d; ++$c)
					{
						for ($e = $b, $f = min($this->_columnCount, $e + $cell->columns); $e < $f; ++$e)
						{						
							if ($c == $a && $e == $b) { continue; }
							
							$childCell = $this->_build(TYPE_CELL, $e, $c);
							
							if (is_array($this->_renderMap[$e][$c][self::RENDER]))
							{
								$this->_render($childCell, $this->_renderMap[$e][$c][self::RENDER]);
							}
							
							$this->_renderMap[$e][$c][self::RENDER] = self::SKIP;
						}
					}					
				}
			}
			
			echo "\t" . '</tr>' . PHP_EOL;			
		}
		
		echo '</table>' . PHP_EOL;
	}
};
