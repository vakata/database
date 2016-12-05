# vakata\database\TableQuery
A database query class

## Methods

| Name | Description |
|------|-------------|
|[__construct](#vakata\database\tablequery__construct)|Create an instance|
|[getDefinition](#vakata\database\tablequerygetdefinition)|Get the table definition of the queried table|
|[filter](#vakata\database\tablequeryfilter)|Filter the results by a column and a value|
|[sort](#vakata\database\tablequerysort)|Sort by a column|
|[group](#vakata\database\tablequerygroup)|Group by a column (or columns)|
|[paginate](#vakata\database\tablequerypaginate)|Get a part of the data|
|[reset](#vakata\database\tablequeryreset)|Remove all filters, sorting, etc|
|[groupBy](#vakata\database\tablequerygroupby)|Apply advanced grouping|
|[join](#vakata\database\tablequeryjoin)|Join a table to the query (no need to do this for relations defined with foreign keys)|
|[where](#vakata\database\tablequerywhere)|Apply an advanced filter (can be called multiple times)|
|[having](#vakata\database\tablequeryhaving)|Apply an advanced HAVING filter (can be called multiple times)|
|[order](#vakata\database\tablequeryorder)|Apply advanced sorting|
|[limit](#vakata\database\tablequerylimit)|Apply an advanced limit|
|[count](#vakata\database\tablequerycount)|Get the number of records|
|[iterator](#vakata\database\tablequeryiterator)|Perform the actual fetch|
|[select](#vakata\database\tablequeryselect)|Perform the actual fetch|
|[insert](#vakata\database\tablequeryinsert)|Insert a new row in the table|
|[update](#vakata\database\tablequeryupdate)|Update the filtered rows with new data|
|[delete](#vakata\database\tablequerydelete)|Delete the filtered rows from the DB|
|[with](#vakata\database\tablequerywith)|Solve the n+1 queries problem by prefetching a relation by name|

---



### vakata\database\TableQuery::__construct
Create an instance  


```php
public function __construct (  
    \DatabaseInterface $db,  
    \Table|string $definition  
)   
```

|  | Type | Description |
|-----|-----|-----|
| `$db` | `\DatabaseInterface` | the database connection |
| `$definition` | `\Table`, `string` | the name or definition of the main table in the query |

---


### vakata\database\TableQuery::getDefinition
Get the table definition of the queried table  


```php
public function getDefinition () : \Table    
```

|  | Type | Description |
|-----|-----|-----|
|  |  |  |
| `return` | `\Table` | the definition |

---


### vakata\database\TableQuery::filter
Filter the results by a column and a value  


```php
public function filter (  
    string $column,  
    mixed $value  
) : self    
```

|  | Type | Description |
|-----|-----|-----|
| `$column` | `string` | the column name to filter by (related columns can be used - for example: author.name) |
| `$value` | `mixed` | a required value, array of values or range of values (range example: ['beg'=>1,'end'=>3]) |
|  |  |  |
| `return` | `self` |  |

---


### vakata\database\TableQuery::sort
Sort by a column  


```php
public function sort (  
    string $column,  
    bool|boolean $desc  
) : self    
```

|  | Type | Description |
|-----|-----|-----|
| `$column` | `string` | the column name to sort by (related columns can be used - for example: author.name) |
| `$desc` | `bool`, `boolean` | should the sorting be in descending order, defaults to `false` |
|  |  |  |
| `return` | `self` |  |

---


### vakata\database\TableQuery::group
Group by a column (or columns)  


```php
public function group (  
    string|array $column  
) : self    
```

|  | Type | Description |
|-----|-----|-----|
| `$column` | `string`, `array` | the column name (or names) to group by |
|  |  |  |
| `return` | `self` |  |

---


### vakata\database\TableQuery::paginate
Get a part of the data  


```php
public function paginate (  
    int|integer $page,  
    int|integer $perPage  
) : self    
```

|  | Type | Description |
|-----|-----|-----|
| `$page` | `int`, `integer` | the page number to get (1-based), defaults to 1 |
| `$perPage` | `int`, `integer` | the number of records per page - defaults to 25 |
|  |  |  |
| `return` | `self` |  |

---


### vakata\database\TableQuery::reset
Remove all filters, sorting, etc  


```php
public function reset () : self    
```

|  | Type | Description |
|-----|-----|-----|
|  |  |  |
| `return` | `self` |  |

---


### vakata\database\TableQuery::groupBy
Apply advanced grouping  


```php
public function groupBy (  
    string $sql,  
    array $params  
) : self    
```

|  | Type | Description |
|-----|-----|-----|
| `$sql` | `string` | SQL statement to use in the GROUP BY clause |
| `$params` | `array` | optional params for the statement (defaults to an empty array) |
|  |  |  |
| `return` | `self` |  |

---


### vakata\database\TableQuery::join
Join a table to the query (no need to do this for relations defined with foreign keys)  


```php
public function join (  
    \Table|string $table,  
    array $fields,  
    string|null $name,  
    bool $multiple  
) : self    
```

|  | Type | Description |
|-----|-----|-----|
| `$table` | `\Table`, `string` | the table to join |
| `$fields` | `array` | what to join on (joined_table_field => other_field) |
| `$name` | `string`, `null` | alias for the join, defaults to the table name |
| `$multiple` | `bool` | are multiple rows joined (results in a LEFT JOIN), default to true |
|  |  |  |
| `return` | `self` |  |

---


### vakata\database\TableQuery::where
Apply an advanced filter (can be called multiple times)  


```php
public function where (  
    string $sql,  
    array $params  
) : self    
```

|  | Type | Description |
|-----|-----|-----|
| `$sql` | `string` | SQL statement to be used in the where clause |
| `$params` | `array` | parameters for the SQL statement (defaults to an empty array) |
|  |  |  |
| `return` | `self` |  |

---


### vakata\database\TableQuery::having
Apply an advanced HAVING filter (can be called multiple times)  


```php
public function having (  
    string $sql,  
    array $params  
) : self    
```

|  | Type | Description |
|-----|-----|-----|
| `$sql` | `string` | SQL statement to be used in the HAING clause |
| `$params` | `array` | parameters for the SQL statement (defaults to an empty array) |
|  |  |  |
| `return` | `self` |  |

---


### vakata\database\TableQuery::order
Apply advanced sorting  


```php
public function order (  
    string $sql,  
    array $params  
) : self    
```

|  | Type | Description |
|-----|-----|-----|
| `$sql` | `string` | SQL statement to use in the ORDER clause |
| `$params` | `array` | optional params for the statement (defaults to an empty array) |
|  |  |  |
| `return` | `self` |  |

---


### vakata\database\TableQuery::limit
Apply an advanced limit  


```php
public function limit (  
    int $limit,  
    int $offset  
) : self    
```

|  | Type | Description |
|-----|-----|-----|
| `$limit` | `int` | number of rows to return |
| `$offset` | `int` | number of rows to skip from the beginning (defaults to 0) |
|  |  |  |
| `return` | `self` |  |

---


### vakata\database\TableQuery::count
Get the number of records  


```php
public function count () : int    
```

|  | Type | Description |
|-----|-----|-----|
|  |  |  |
| `return` | `int` | the total number of records (does not respect pagination) |

---


### vakata\database\TableQuery::iterator
Perform the actual fetch  


```php
public function iterator (  
    array|null $fields  
) : \TableQueryIterator    
```

|  | Type | Description |
|-----|-----|-----|
| `$fields` | `array`, `null` | optional array of columns to select (related columns can be used too) |
|  |  |  |
| `return` | `\TableQueryIterator` | the query result as an iterator |

---


### vakata\database\TableQuery::select
Perform the actual fetch  


```php
public function select (  
    array|null $fields  
) : array    
```

|  | Type | Description |
|-----|-----|-----|
| `$fields` | `array`, `null` | optional array of columns to select (related columns can be used too) |
|  |  |  |
| `return` | `array` | the query result as an array |

---


### vakata\database\TableQuery::insert
Insert a new row in the table  


```php
public function insert (  
    array $data  
) : array    
```

|  | Type | Description |
|-----|-----|-----|
| `$data` | `array` | key value pairs, where each key is the column name and the value is the value to insert |
|  |  |  |
| `return` | `array` | the inserted ID where keys are column names and values are column values |

---


### vakata\database\TableQuery::update
Update the filtered rows with new data  


```php
public function update (  
    array $data  
) : int    
```

|  | Type | Description |
|-----|-----|-----|
| `$data` | `array` | key value pairs, where each key is the column name and the value is the value to insert |
|  |  |  |
| `return` | `int` | the number of affected rows |

---


### vakata\database\TableQuery::delete
Delete the filtered rows from the DB  


```php
public function delete () : int    
```

|  | Type | Description |
|-----|-----|-----|
|  |  |  |
| `return` | `int` | the number of deleted rows |

---


### vakata\database\TableQuery::with
Solve the n+1 queries problem by prefetching a relation by name  


```php
public function with (  
    string $relation  
) : self    
```

|  | Type | Description |
|-----|-----|-----|
| `$relation` | `string` | the relation name to fetch along with the data |
|  |  |  |
| `return` | `self` |  |

---

