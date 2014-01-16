MatrixTables
============

MatrixTables

MatrixTables is a PHP implementation of rendering flexible HTML tables. 

What sets this table project apart from other like projects is its use of a technique of dynamic, dynamic programming, or 
(as I prefer to call it) amorphous data classes.

Think of your run-of-the-mill amorphous blob. He may start out small, but as he slides down the street, he will consume
all that he touches until (if necessary) he has consumed the entire town, citizens and all.

That is what I think about when I consider amorphous data classes. It is a class that is constructed in such a way
that when you request a member of the class a first time, a callback (a.k.a. a function) will be called and produce an output. 
If you request the member a second, third, etc. time, since the output has already been generated, the output only needs returning.

Like the blob, the amorphous data class gives you the town, bit-by-bit.

Amorphous data classes have much more application that my little project, but it has obvious potential in structure of a
2D grid, where sums, averages, and other calculations are done from the calculation of other smaller calculations. By thinking of
your larger problem in terms of smaller problems and using amorphous data classes, you can effectively be guaranteed a calculation
will only occur once during the rendering.

I will explain more and expand this more once I see the need, have the desire, and see the interest in the project.
