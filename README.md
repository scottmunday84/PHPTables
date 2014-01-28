PHPTables
============

PHPTables is a PHP implementation of rendering flexible HTML tables. 

What sets this table project apart from other like projects is its use of a technique of dynamic, dynamic programming, or 
(as I prefer to call it) amorphous data classes.

Think of your run-of-the-mill amorphous blob. He may start out small, but as he slides down the street, he will consume all that he touches until (if necessary) he has consumed the entire town, citizens and all.

That is what I think about when I consider amorphous data classes. It is a class that is constructed in such a way
that when you request a member of the class a first time, a callback (a.k.a. a function) will be called and produce an output. If you request the member a second, third, etc. time, since the output has already been generated, the output only needs returning.

Like the blob, the amorphous data class gives you the town, bit-by-bit.

Amorphous data classes have much more application than my little project, but it has obvious potential in the structure of a 2D grid where sums, averages, and other calculations are done from the combination of other smaller calculations. By thinking of your larger problem in terms of its smaller problems and using amorphous data classes, you can effectively be guaranteed a calculation will only calculate once during the rendering, thus minimizing your time domain for calculating the entire table.

I will delve more into the topic once I see the need, have the desire, and see an interest in the project.

## Properties

Amorphous data classes, as mentioned, use callbacks to initiate its data. Create a property with a callback on a table, section, column, row, or cell.

* PHPTables\TYPE_TABLE
* PHPTables\TYPE_SECTION
* PHPTables\TYPE_COLUMN
* PHPTables\TYPE_ROW
* PHPTables\TYPE_CELL

### Example

```php
$table->property(
	PHPTables\TYPE_CELL, 
	'value',
	function($cell)
	{
		return 'foobar';
	}
);
```

Requesting the member "value" from any cell within the table will then produce a result.

```php
echo $table->cell(3, 3)->value;
```

## Mapping

To render cells within the table, you map to a cell selection. The selection is a string separated by a comma (,) to split the column (x) selection by the row (y) selection. Indices start at 0 in either dimension. For example the selection "5,3" selects the cell at index 5 (6th column) and index 3 (4th row), assuming the cell exists on the table. A selection language shorthand has been built for convenience.

* first: First column/row.
* last: Last column/row.
* even: Even column/row(s).
* odd: Odd column/row(s).
* #-#: A range, using indices in replace of the pound sign (#).
* #: An index in replace of the pound sign (#).
* ;: A selection separator. Allows you to group multiple selections into one selection.

Return a false or a PHPTables\SKIP to skip the rendering of the cell.

### Example

```php
$table->map(
	'*,last',
	function($cell)
	{
		return $cell->column->index . ', ' . $cell->row->index;
	},
	array(
		PHPTables\TYPE_CELL => array(
			'style' => function($row) { return 'font-weight: bold;'; }
		)
	)
);
```

Mapping render/attribute callbacks cascade downward, so effectively you can map one selection, map over it, and return the combination of their results. As well cell attributes override any of the same column attributes applied. When in doubt use PHPTables\TYPE_CELL.

## Selecting Columns, Rows, and Cells

Columns, rows, and cells can be selected at any time.

* column($column): Get the column.
* row($row): Get the row.
* cell($column, $row): Get the cell.
 
```php
$cell = $table->cell(5, 3);
```

## Expanding Cells

You are able to expand a cell on the rendering of the cell. Note that selecting a cell will return the underlying cell and not the cell that expanded on top of it.

### Example

On rendering this cell covers (4, 1), (5, 1), (4, 2), and (5, 2). Selecting the cell at (5, 2) will not return the expanded cell, but the underlying cell on the grid. This is built for convenience, so you can calculate sums and averages on straight columns and rows (i.e. the underlying structure) and not on the rendered layout.

```php
$expandedCell = $table->cell(4, 1)->expand(2, 2);

// Selects the cell on the grid, not the expanded cell.
$differentCell = $table->cell(5, 2);
```

## Example

GitHub strips out all styling associated with HTML tags. Run the example on your own machine to see the styling associated with the cells.

<table border="1">
	<tr>
		<td colspan="1" rowspan="1">0</td>
		<td colspan="1" rowspan="1" style="background-color: lightgrey;">1</td>
		<td colspan="1" rowspan="1">2</td>
		<td colspan="1" rowspan="1" style="background-color: lightgrey;">3</td>
		<td colspan="1" rowspan="1">4</td>
		<td colspan="1" rowspan="1" style="background-color: lightgrey;">5</td>
		<td colspan="1" rowspan="1" style="font-weight: bold;">15</td>
	</tr>
	<tr>
		<td colspan="1" rowspan="1">6</td>
		<td colspan="1" rowspan="1">7</td>
		<td colspan="1" rowspan="1">8</td>
		<td colspan="1" rowspan="1">9</td>
		<td colspan="2" rowspan="2">10</td>
		<td colspan="1" rowspan="1" style="font-weight: bold;">51</td>
	</tr>
	<tr>
		<td colspan="1" rowspan="1">12</td>
		<td colspan="1" rowspan="1" style="background-color: lightgrey;">13</td>
		<td colspan="1" rowspan="1">14</td>
		<td colspan="1" rowspan="1" style="background-color: lightgrey;">15</td>
		<td colspan="1" rowspan="1" style="font-weight: bold;">87</td>
	</tr>
	<tr>
		<td colspan="1" rowspan="1" style="font-weight: bold;">18</td>
		<td colspan="1" rowspan="1" style="font-weight: bold;">21</td>
		<td colspan="1" rowspan="1" style="font-weight: bold;">24</td>
		<td colspan="1" rowspan="1" style="font-weight: bold;">27</td>
		<td colspan="1" rowspan="1" style="font-weight: bold;">30</td>
		<td colspan="1" rowspan="1" style="font-weight: bold;">33</td>
		<td colspan="1" rowspan="1" style="font-weight: bold; color: red;">306</td>
	</tr>
</table>

```php

// An example setup using PHPTables.
require_once(dirname(__FILE__) . '/PHPTables/include.php');

$data = array(
	array(0, 1, 2, 3, 4, 5),
	array(6, 7, 8, 9, 10, 11),
	array(12, 13, 14, 15, 16, 17)
);

$table = new PHPTables\Tables\Collapsed(7, 4); // X x Y.

$table->property(
	PHPTables\TYPE_CELL, 
	'value',
	function($cell) use ($data)
	{
		return @$data[$cell->row->index][$cell->column->index];
	}
);

$table->property(
	PHPTables\TYPE_COLUMN, 
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

$table->property(
	PHPTables\TYPE_ROW, 
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
		PHPTables\TYPE_TABLE => array(
			'border' => function($table) { return '1'; }
		)
	)
);

$table->map(
	'even,odd',
	null,
	array(
		PHPTables\TYPE_CELL => array(
			'style' => function($cell) { return 'background-color: lightgrey;'; }
		)
	)
);

$table->map(
	'*,last',
	function($cell)
	{
		return ($cell->value = $cell->column->total); // Total the column, using callbacks. Guaranteed to run only once.
	},
	array(
		PHPTables\TYPE_CELL => array(
			'style' => function($row) { return 'font-weight: bold;'; }
		)
	)
);

$table->map(
	'last,*',
	function($cell)
	{
		return ($cell->value = $cell->row->total); // Total the row, using callbacks. Guaranteed to run only once.
	},
	array(
		PHPTables\TYPE_CELL => array(
			'style' => function($cell) { return 'font-weight: bold;'; }
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
		PHPTables\TYPE_CELL => array(
			'style' => function($cell, $render) { return $render . ' color: red;'; }
		)
	)
);

// Expand a cell.
$table->cell(4, 1)->expand(2, 2);

$table->render();
```
