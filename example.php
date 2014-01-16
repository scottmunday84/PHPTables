<?php

// An example setup using MatrixTable.

require_once(dirname(__FILE__) . '/MatrixTable.class.php');

$data = array(
	array(0, 1, 2, 3, 4, 5),
	array(6, 7, 8, 9, 10, 11),
	array(12, 13, 14, 15, 16, 17)
);

$table = new MatrixTable(7, 4); // X x Y

$table->callback(
	MatrixTable::TYPE_CELL, 
	'value',
	function($cell) use ($data)
	{
		return $data[$cell->row->index][$cell->column->index];
	}
);

$table->callback(
	MatrixTable::TYPE_COLUMN, 
	'total',
	function($column)
	{
		$total = 0;
			
		for ($i = 0; $i < ($column->rows - 1); ++$i)
		{
			$total += $column->table->cell($column->index, $i)->value;
		}
			
		return $total;
	}
);

$table->callback(
	MatrixTable::TYPE_ROW, 
	'total',
	function($row)
	{
		$total = 0;
			
		for ($i = 0; $i < ($row->columns - 1); ++$i)
		{
			$total += $row->table->cell($i, $row->index)->value;
		}
			
		return $total;
	}
);

$table->map(
	'*,*', 
	function($cell)
	{
		return $cell->value;
	},
	array(
		MatrixTable::TYPE_TABLE => array(
			'border' => function($table) { return '1'; }
		)
	)
);


$table->map(
	'*,3',
	function($cell)
	{
		$cell->value = $cell->column->total; // Total the column, using callbacks. Guaranteed only once.
	
		return $cell->column->total;
	},
	array(
		MatrixTable::TYPE_ROW => array(
			'style' => function($row) { return 'font-weight: bold;'; }
		)
	)
);

$table->map(
	'6,*',
	function($cell)
	{
		$cell->value = $cell->row->total; // Total the row, using callbacks. Guaranteed to run only once.
	
		return $cell->row->total;
	},
	array(
		MatrixTable::TYPE_COLUMN => array(
			'style' => function($column) { return 'font-weight: bold;'; }
		)
	)
);

$table->map(
	'6,3',
	function($cell)
	{
		return ($cell->row->total + $cell->column->total);
	},
	array(
		MatrixTable::TYPE_CELL => array(
			'style' => function($column) { return 'font-weight: bold;'; }
		)
	)
);

$table->render();
