MatrixTables
============

MatrixTables is a PHP implementation of rendering flexible HTML tables. 

What sets this table project apart from other like projects is its use of a technique of dynamic, dynamic programming, or 
(as I prefer to call it) amorphous data classes.

Think of your run-of-the-mill amorphous blob. He may start out small, but as he slides down the street, he will consume all that he touches until (if necessary) he has consumed the entire town, citizens and all.

That is what I think about when I consider amorphous data classes. It is a class that is constructed in such a way
that when you request a member of the class a first time, a callback (a.k.a. a function) will be called and produce an output. If you request the member a second, third, etc. time, since the output has already been generated, the output only needs returning.

Like the blob, the amorphous data class gives you the town, bit-by-bit.

Amorphous data classes have much more application than my little project, but it has obvious potential in the structure of a 2D grid where sums, averages, and other calculations are done from the combination of other smaller calculations. By thinking of your larger problem in terms of its smaller problems and using amorphous data classes, you can effectively be guaranteed a calculation will only calculate once during the rendering, thus minimizing your time domain for calculating the entire table.

I will delve more into the topic once I see the need, have the desire, and see an interest in the project.

## Callbacks

Amorphous data classes, as mentioned, use callbacks to initiate its data. Create a callback to setup a callback on a table, column, row, or cell.

* MatrixTable::TYPE_TABLE
* MatrixTable::TYPE_COLUMN
* MatrixTable::TYPE_ROW
* MatrixTable::TYPE_CELL

### Example

```php
$table->callback(
	MatrixTable::TYPE_CELL, 
	'value',
	function($cell) use ($data)
	{
		return $data[$cell->row->index][$cell->column->index];
	}
);
```

## Mapping

To render cells within the table, you use a mapping function that maps to a selection. The selection is a string, separated by a comma (,) to split the column (x) value by the row (y) starting at 0 for each dimension. For example:

"5,3"

This would select the 6th column and 3rd row assuming they both exist. There is a selection language shorthand available to help ease the selection of your desired mapped cells.

* first: First column/row.
* last: Last column/row.
* even: Even column/row(s).
* odd: Odd column/row(s).
* #-#: A range. Use indices in replace of the pound sign (#).
* #: An index in replace of the pound sign (#).
* ;: A dimension separator. Allows you to group multiple selections within one dimension.

### Example

<table border="1">
	<tr>
		<td colspan="1" rowspan="1">0</td>
		<td colspan="1" rowspan="1" style="background-color: lightgrey !important;"></td>
		<td colspan="1" rowspan="1">2</td>
		<td colspan="1" rowspan="1" style="background-color: lightgrey !important;"></td>
		<td colspan="1" rowspan="1">4</td>
		<td colspan="1" rowspan="1" style="background-color: lightgrey !important;"></td>
		<td colspan="1" rowspan="1" style="font-weight: bold;">15</td>
	</tr>
	<tr>
		<td colspan="1" rowspan="1">6</td>
		<td colspan="1" rowspan="1">7</td>
		<td colspan="1" rowspan="1">8</td>
		<td colspan="1" rowspan="1">9</td>
		<td colspan="2" rowspan="2">10</td>
		<td colspan="1" rowspan="1" style="font-weight: bold !important;">51</td>
	</tr>
	<tr>
		<td colspan="1" rowspan="1">12</td>
		<td colspan="1" rowspan="1" style="background-color: lightgrey !important;"></td>
		<td colspan="1" rowspan="1">14</td>
		<td colspan="1" rowspan="1" style="background-color: lightgrey !important;"></td>
		<td colspan="1" rowspan="1" style="font-weight: bold !important;">87</td>
	</tr>
	<tr style="font-weight: bold !important;">
		<td colspan="1" rowspan="1">18</td>
		<td colspan="1" rowspan="1">21</td>
		<td colspan="1" rowspan="1">24</td>
		<td colspan="1" rowspan="1">27</td>
		<td colspan="1" rowspan="1">30</td>
		<td colspan="1" rowspan="1">33</td>
		<td colspan="1" rowspan="1" style="color: red !important;" style="font-weight: bold;">306</td>
	</tr>
</table>

```php
$table->map(
	'even,odd',
	function($cell)
	{
		return $cell->value;
	},
	array(
		MatrixTable::TYPE_CELL => array(
			'style' => function($cell) { return 'background-color: lightgrey;'; }
		)
	)
);
```

## Example



```php
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
		return @$data[$cell->row->index][$cell->column->index];
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
	'even,odd',
	function($cell)
	{
		$cell->value; // Still calculate.
		
		return '';
	},
	array(
		MatrixTable::TYPE_CELL => array(
			'style' => function($cell) { return 'background-color: lightgrey;'; }
		)
	)
);

$table->map(
	'*,last',
	function($cell)
	{
		$cell->value = $cell->column->total; // Total the column, using callbacks. Guaranteed to run only once.
	
		return $cell->column->total;
	},
	array(
		MatrixTable::TYPE_ROW => array(
			'style' => function($row) { return 'font-weight: bold;'; }
		)
	)
);

$table->map(
	'last,0-2',
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
	'last,last',
	function($cell)
	{
		return ($cell->row->total + $cell->column->total);
	},
	array(
		MatrixTable::TYPE_CELL => array(
			'style' => function($cell) { return 'color: red;'; }
		)
	)
);

// Expand a column.
$table->cell(4, 1)->expand(2, 2);

$table->render();
```
