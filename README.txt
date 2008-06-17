With this tool you can define SQL queries that can later be pointed to by a "datadisplay" content element to render to HTML.

To prepare a SQL query, create a new record and choose the type "Data Query". You can then enter SQL query in the text field and check various options. Currently only the "Use deleted flag" and "Use enable fields" are working. As their names imply checking those options will automatically add delete and enable fields check to your SQL statement. Languge overlay and workspace support are not implemented now.

The SQL query must follow a number of rules:

1) use only those SQL keywords: SELECT, FROM, INNER JOIN, LEFT JOIN, RIGHT JOIN, WHERE, GROUP BY, ORDER BY, LIMIT, OFFSET

2) set only a single table in the FROM statement. All other tables must be joined using JOIN statement

3) table in FROM statement must have an alias (AS) (Note: the reason for this is explained in the "datadisplay" extension; but it will probably be changed in the future)

4) fields in the SELECT statement *must* be prefixed with their respective table names (or aliases), i.e. mytable.field1

There's a link to a wizard, but it's not working (and may never).

When this Data Query record is used by the "datadisplay" extension, it will perform the query and return the resulting record set in a specific PHP array structure, which "datadisplay" then renders. The format is the following:

array(
	'name' => 'maintable',
	'records' => array(
		...
		'subtables' => array(
			'name' => 'subtable',
			'records' => array(
				...
			)
		)
	)
)

The "name" properties are currently hard-code which is not good, since it prevents having multiple joined tables (the "maintable" label is actually not important). In general that structure is not quite definite yet, as it hasn't been used in enough scenarios yet to be sure of all the needs that must be covered. Given those limitations, it works quite fine.
