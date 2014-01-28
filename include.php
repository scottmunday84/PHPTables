<?php
namespace PHPTables;

const TYPE_TABLE = 1;
const TYPE_SECTION = 2;
const TYPE_CELL = 3;
const TYPE_ROW = 4;
const TYPE_COLUMN = 5;

const HTML_TAG_TABLE = 'table';

const HTML_TAG_THEAD = 'thead';
const HTML_TAG_TBODY = 'tbody';
const HTML_TAG_TFOOT = 'tfoot';

const HTML_TAG_TR = 'tr';
const HTML_TAG_TH = 'th';
const HTML_TAG_TD = 'td';

const SECTION_HEADER = 1;
const SECTION_BODY = 2;
const SECTION_FOOTER = 3;

const SKIP = false;	

class Amorphous
{
	protected $_values = array();
	protected $_properties = array();
	
	function __get($property)
	{
		if (isset($this->_values[$property]))
		{
			return $this->_values[$property];
		}
		elseif (isset($this->_properties[$property]) && is_callable($this->_properties[$property]))
		{
			return ($this->_values[$property] = $this->_properties[$property]($this));
		}
		
		return null;
	}
	
	function __set($property, $value)
	{
		$this->_values[$property] = $value;
	}	
};

namespace PHPTables\Types;
use PHPTables;

class AmorphousElement extends PHPTables\Amorphous 
{
	protected $_tag = null;
	protected $_attributes = array();
	
	public function attribute($attribute, $callback)
	{
		if (is_callable($callback))
		{
			if (!isset($this->_attributes[$attribute]))
			{
				$this->_attributes[$attribute] =  array();
			}
		
			$this->_attributes[$attribute][] = $callback;
		}
	}
	
	public function attributes()
	{
		$rtn = array();
		
		foreach ($this->_attributes as $attribute => $list)
		{
			$tmp = PHPTables\SKIP;
			
			foreach ($list as $callback)
			{
				$tmp = $callback($this, $tmp);
			}

			$rtn[$attribute] = $tmp;
		}
		
		return $rtn;
	}
	
	protected function _getAttributeString()
	{
		$rtn = '';
		$attributes = $this->attributes();
	
		foreach ($attributes as $attribute => $value)
		{
			if ($value === PHPTables\SKIP) { continue; }
		
			$rtn .= ' ' . htmlentities($attribute) . '="' . htmlentities($value) . '"';
		}
		
		return $rtn;
	}
	
	public function beginElement()
	{
		return ($this->_tag ? '<' . $this->_tag . $this->_getAttributeString() . '>' : '');
	}
	
	public function endElement()
	{
		return ($this->_tag ? '</' . $this->_tag . '>' : '');
	}
	
	public function element()
	{
		return ($this->_tag ? '<' . $this->_tag . $this->_getAttributeString() . ' />' : '');
	}
};

class Column extends AmorphousElement
{ 
	protected $_tag = PHPTables\HTML_TAG_TD;

	public $table;
	public $section;
	
	public $index;
	public $rows;	

	function __construct($table, $section, $column, $rows)
	{
		$this->table = $table;
		$this->section = $section;
		
		$this->index = $column;		
		$this->rows = $rows;		

		$this->_properties = $section->properties(PHPTables\TYPE_COLUMN);			
	}
};

class HeaderColumn extends Column
{
	protected $_tag = PHPTables\HTML_TAG_TH;
}

class Row extends AmorphousElement
{ 
	protected $_tag = PHPTables\HTML_TAG_TR;
	
	public $table;
	public $section;
	
	public $index;
	public $columns;
	
	function __construct($table, $section, $row, $columns)
	{
		$this->table = $table;
		$this->section = $section;
				
		$this->index = $row;
		$this->columns = $columns;		

		$this->_properties = $section->properties(PHPTables\TYPE_ROW);							
	}
};

class Cell extends AmorphousElement
{ 
	protected $_tag = PHPTables\HTML_TAG_TD;

	public $table;
	public $section;
	
	public $column;
	public $row;
	
	public $rows = 1;
	public $columns = 1;
	
	protected $_render = array();
	protected $_willRender = true;
	
	protected $_skipped = array();
	
	function __construct($table, $section, $column, $row)
	{
		$this->table = $table;
		$this->section = $section;
		
		$this->column = $column;
		$this->row = $row;
		
		$this->_properties = $section->properties(PHPTables\TYPE_CELL);					
		
		// At least show a nonblank space.
		$this->_render = array(
			function() 
			{
				return '&nbsp;'; 
			}
		);
	}

	public function expand($columns = 1, $rows = 1)
	{
		$this->columns = ($columns <= 0 ? 1 : (int)$columns);
		$this->rows = ($rows <= 0 ? 1 : (int)$rows);
		
		for ($a = $this->column->index, $b = min($a + $columns, $this->section->count(PHPTables\TYPE_COLUMN)); $a < $b; ++$a)
		{		
			for ($c = $this->row->index, $d = min($c + $rows, $this->section->count(PHPTables\TYPE_ROW)); $c < $d; ++$c)
			{
				if ($a == $this->column->index && $c == $this->row->index) { continue; }
				
				$cell = $this->_skipped[] = $this->section->cell($a, $c);				
				$cell->skip();
			}
		}
	}
	
	public function skip()
	{
		foreach ($this->_skipped as $skipped)
		{
			$skipped->unskip();
		}
	
		return ($this->_willRender = PHPTables\SKIP);
	}
	
	public function unskip()
	{
		 return ($this->_willRender = true);
	}	
	
	public function setRender($callback)
	{
		if (is_callable($callback))
		{
			$this->_render[] = $callback;
		}
	}
	
	public function attributes()
	{
		$rtn = array();
		
		$columnAttributes = $this->column->attributes();
		
		foreach ($this->_attributes as $attribute => $list)
		{
			$tmp = (isset($columnAttributes[$attribute]) ? $columnAttributes[$attribute] : PHPTables\SKIP);
			
			foreach ($list as $callback)
			{
				$tmp = $callback($this, $tmp);
			}

			$rtn[$attribute] = $tmp;
		}
		
		return $rtn;
	}
	
	public function render()
	{
		$render = PHPTables\SKIP;
		
		foreach ($this->_render as $callback)
		{
			$render = $callback($this, $render);
		}
		
		if ($render === PHPTables\SKIP || $this->_willRender === PHPTables\SKIP) { return; }
		
		if ($this->columns > 1)
		{
			$this->attribute(
				'colspan', 
				function($cell)
				{
					return $cell->columns;
				}
			);
		}
		
		if ($this->rows > 1)
		{
			$this->attribute(
				'rowspan', 
				function($cell)
				{
					return $cell->rows;
				}
			);
		}		
		
		echo $this->beginElement() . $render . $this->endElement();
	}
};

class HeaderCell extends Cell
{
	protected $_tag = PHPTables\HTML_TAG_TH;	
}

namespace PHPTables\Sections;
use PHPTables;

class Section extends PHPTables\Types\AmorphousElement
{
	public $table;

	protected $_tag = PHPTables\HTML_TAG_TBODY;
	
	protected $_columns = array();
	protected $_rows = array();
	protected $_cells = array();
	
	protected $_columnCount = 0;	
	protected $_rowCount = 0;
	
	protected $_tableProperties = array();
	protected $_columnProperties = array();
	protected $_rowProperties = array();
	protected $_cellProperties = array();
	
	function __construct($table, &$tableProperties, $columns, $rows)
	{
		$this->table = $table;
		
		$this->_tableProperties = &$tableProperties;
		
		$this->_columnCount = ($columns <= 0 ? 1 : (int)$columns);
		$this->_rowCount = ($rows <= 0 ? 1 : (int)$rows);		
	}
	
	protected function _build($type)
	{
		switch ($type)
		{
			case PHPTables\TYPE_COLUMN:
				list($ignore, $column) = func_get_args();
				
				if (!isset($this->_columns[$column]))
				{				
					$this->_columns[$column] = new PHPTables\Types\Column($this->table, $this, $column, $this->_rowCount);
				}				
				
				return $this->_columns[$column];
			case PHPTables\TYPE_ROW:
				list($ignore, $row) = func_get_args();
				
				if (!isset($this->_rows[$row]))
				{
					$this->_rows[$row] = new PHPTables\Types\Row($this->table, $this, $row, $this->_columnCount);
				}				

				return $this->_rows[$row];
			case PHPTables\TYPE_CELL: // Cell considers rows and columns.
				list($ignore, $column, $row) = func_get_args();
				
				$_column = $this->_build(PHPTables\TYPE_COLUMN, $column);
				$_row = $this->_build(PHPTables\TYPE_ROW, $row);				
				
				if (!isset($this->_cells[$column][$row]))
				{
					$this->_cells[$column][$row] = new PHPTables\Types\Cell($this->table, $this, $_column, $_row);
				}				
				
				return $this->_cells[$column][$row];
		}
	}			
	
	public function column($column)
	{
		return $this->_build(PHPTables\TYPE_COLUMN, $column);
	}
	
	public function row($row)
	{
		return $this->_build(PHPTables\TYPE_ROW, $row);
	}	
	
	public function cell($column, $row)
	{
		return $this->_build(PHPTables\TYPE_CELL, $column, $row);
	}
	
	public function count($type)
	{
		switch ($type)
		{
			case PHPTables\TYPE_COLUMN:
				return $this->_columnCount;
			case PHPTables\TYPE_ROW:
				return $this->_rowCount;
		}
	}	
	
	public function property($type, $property, $callback)
	{
		if (is_callable($callback))
		{
			switch ($type)
			{
				case PHPTables\TYPE_TABLE:
					$this->_tableProperties[$property] = $callback;
					break;			
				case PHPTables\TYPE_SECTION:
					$this->_properties[$property] = $callback;
					break;			
				case PHPTables\TYPE_COLUMN:
					$this->_columnProperties[$property] = $callback;
					break;
				case PHPTables\TYPE_ROW:
					$this->_rowProperties[$property] = $callback;
					break;
				case PHPTables\TYPE_CELL:
					$this->_cellProperties[$property] = $callback;
					break;
			}
		}
	}
	
	public function properties($type)
	{
		switch ($type)
		{
			case PHPTables\TYPE_TABLE:
				return $this->_tableProperties;
			case PHPTables\TYPE_SECTION:
				return $this->_properties;
			case PHPTables\TYPE_COLUMN:
				return $this->_columnProperties;
			case PHPTables\TYPE_ROW:
				return $this->_rowProperties;
			case PHPTables\TYPE_CELL:
				return $this->_cellProperties;
		}
		
		return array(); // Return an empty array.
	}	
	
	protected function _parseMapDimension($type, $selection)
	{
		$selected = array();		
		
		$max = 0;
	
		if ($type == PHPTables\TYPE_COLUMN)
		{
			$max = $this->_columnCount - 1;
		}
		elseif ($type == PHPTables\TYPE_ROW)
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
			elseif (preg_match('/(0|([1-9][0-9]*))-(0|([1-9][0-9]*))/', $selection)) // Range.
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
	
	public function map($selection, $renderCallback, $attributes = array())
	{
		list($x, $y) = explode(',', $selection);
		
		$x = $this->_parseMapDimension(PHPTables\TYPE_COLUMN, $x);
		$y = $this->_parseMapDimension(PHPTables\TYPE_ROW, $y);
		
		$appliedRowAttributes = false;
		
		foreach ($x as $xOffset)
		{
			foreach ($y as $yOffset)
			{
				$cell = $this->cell($xOffset, $yOffset);
				
				$cell->setRender($renderCallback);
				
				// Assign attributes.
				if (isset($attributes[PHPTables\TYPE_CELL]) && is_array($attributes[PHPTables\TYPE_CELL]))
				{
					foreach ($attributes[PHPTables\TYPE_CELL] as $attribute => $callback)
					{
						$cell->attribute($attribute, $callback);
					}
				}
				
				// Assign attributes.
				if (!$appliedRowAttributes && isset($attributes[PHPTables\TYPE_ROW]) && is_array($attributes[PHPTables\TYPE_ROW]))
				{
					$row = $this->row($yOffset);
					
					foreach ($attributes[PHPTables\TYPE_ROW] as $attribute => $callback)
					{
						$row->attribute($attribute, $callback);
					}
				}
			}
			
			// Make it this far, you already went through all rows. Don't do again.
			$appliedRowAttributes = true;
			
			// Assign attributes.
			if (isset($attributes[PHPTables\TYPE_COLUMN]) && is_array($attributes[PHPTables\TYPE_COLUMN]))
			{
				$column = $this->column($xOffset);
				
				foreach ($attributes[PHPTables\TYPE_COLUMN] as $attribute => $callback)
				{
					$column->attribute($attribute, $callback);
				}
			}
		}
		
		// Assign attributes.
		if (isset($attributes[PHPTables\TYPE_SECTION]) && is_array($attributes[PHPTables\TYPE_SECTION]))
		{
			foreach ($attributes[PHPTables\TYPE_SECTION] as $attribute => $callback)
			{
				$this->attribute($attribute, $callback);
			}
		}
		
		// Assign attributes.
		if (isset($attributes[PHPTables\TYPE_TABLE]) && is_array($attributes[PHPTables\TYPE_TABLE]))
		{
			$table = $this->table;
			
			foreach ($attributes[PHPTables\TYPE_TABLE] as $attribute => $callback)
			{
				$table->attribute($attribute, $callback);
			}			
		}
	}
	
	public function render()
	{	
		echo "\t" . $this->beginElement() . PHP_EOL;
	
		for ($a = 0; $a < $this->_rowCount; ++$a)
		{
			$row = $this->row($a);
			
			echo "\t\t" . $row->beginElement() . PHP_EOL;
		
			for ($b = 0; $b < $this->_columnCount; ++$b)
			{
				echo "\t\t\t";
				$this->cell($b, $a)->render();
				echo PHP_EOL;						
			}
			
			echo "\t\t" . $row->endElement() . PHP_EOL;			
		}
		
		echo "\t" . $this->endElement() . PHP_EOL;		
	}	
};

class HeaderSection extends Section
{
	protected function _build($type)
	{
		switch ($type)
		{
			case PHPTables\TYPE_ROW:
				list($type, $row) = func_get_args();
				
				return parent::_build($type, $row);							
			case PHPTables\TYPE_COLUMN:
				list($ignore, $column) = func_get_args();
				
				if (!isset($this->_columns[$column]))
				{				
					$this->_columns[$column] = new PHPTables\Types\HeaderColumn($this->table, $this, $column, $this->_rowCount);
				}				
				
				return $this->_columns[$column];
			case PHPTables\TYPE_CELL: // Cell considers rows and columns.
				list($ignore, $column, $row) = func_get_args();
				
				$_column = $this->_build(PHPTables\TYPE_COLUMN, $column);
				$_row = $this->_build(PHPTables\TYPE_ROW, $row);				
				
				if (!isset($this->_cells[$column][$row]))
				{
					$this->_cells[$column][$row] = new PHPTables\Types\HeaderCell($this->table, $this, $_column, $_row);
				}				
				
				return $this->_cells[$column][$row];
		}
	}
};

class Body extends Section { };

class Header extends HeaderSection
{
	protected $_tag = PHPTables\HTML_TAG_THEAD;
}

class Footer extends HeaderSection
{
	protected $_tag = PHPTables\HTML_TAG_TFOOT;
};

namespace PHPTables\Tables;
use PHPTables;

class Table extends PHPTables\Sections\Section 
{
	protected $_tag = PHPTables\HTML_TAG_TABLE;
};

class Collapsed extends Table
{
	function __construct($columns, $rows)
	{		
		parent::__construct($this, $this->_properties, $columns, $rows);
	}
};

class HBF extends Table
{ 	
	private $_header = null;
	private $_body = null;
	private $_footer = null;
	
	function __construct()
	{
		$this->table = $this;
		
		$this->_tableProperties = $this->_properties;
	}
	
	private function _setSection($section, $arguments)
	{
		list($columns, $rows) = $arguments;
				
		switch ($section)
		{
			case PHPTables\SECTION_HEADER:
				return new PHPTables\Sections\Header($this, $this->_properties, $columns, $rows);
			case PHPTables\SECTION_BODY:
				return new PHPTables\Sections\Body($this, $this->_properties, $columns, $rows);
			case PHPTables\SECTION_FOOTER:
				return new PHPTables\Sections\Footer($this, $this->_properties, $columns, $rows);
		}
		
		return null;
	}
	
	public function header()
	{
		if (($arguments = func_get_args()))
		{
			$this->_header = $this->_setSection(PHPTables\SECTION_HEADER, $arguments);
		}
		
		return $this->_header;
	}
	
	public function body()
	{
		if (($arguments = func_get_args()))
		{
			$this->_body = $this->_setSection(PHPTables\SECTION_BODY, $arguments);
		}
		
		return $this->_body;
	}
	
	public function footer()
	{
		if (($arguments = func_get_args()))
		{
			$this->_footer = $this->_setSection(PHPTables\SECTION_FOOTER, $arguments);
		}
		
		return $this->_footer;
	}
	
	public function render()
	{
		echo $this->beginElement() . PHP_EOL;
		
		if ($this->_header)
		{
			$this->_header->render();
		}
		
		if ($this->_body)
		{
			$this->_body->render();
		}
		
		if ($this->_footer)
		{
			$this->_footer->render();
		}
		
		echo $this->endElement() . PHP_EOL;
	}
};
