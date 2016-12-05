# vakata\database\Table
A table definition

## Methods

| Name | Description |
|------|-------------|
|[__construct](#vakata\database\table__construct)|Create a new instance|
|[getComment](#vakata\database\tablegetcomment)|Get the table comment|
|[setComment](#vakata\database\tablesetcomment)|Set the table comment|
|[addColumn](#vakata\database\tableaddcolumn)|Add a column to the definition|
|[addColumns](#vakata\database\tableaddcolumns)|Add columns to the definition|
|[setPrimaryKey](#vakata\database\tablesetprimarykey)|Set the primary key|
|[getName](#vakata\database\tablegetname)|Get the table name|
|[getColumn](#vakata\database\tablegetcolumn)|Get a column definition|
|[getColumns](#vakata\database\tablegetcolumns)|Get all column names|
|[getFullColumns](#vakata\database\tablegetfullcolumns)|Get all column definitions|
|[getPrimaryKey](#vakata\database\tablegetprimarykey)|Get the primary key columns|
|[hasOne](#vakata\database\tablehasone)|Create a relation where each record has zero or one related rows in another table|
|[hasMany](#vakata\database\tablehasmany)|Create a relation where each record has zero, one or more related rows in another table|
|[belongsTo](#vakata\database\tablebelongsto)|Create a relation where each record belongs to another row in another table|
|[manyToMany](#vakata\database\tablemanytomany)|Create a relation where each record has many linked records in another table but using a liking table|
|[addRelation](#vakata\database\tableaddrelation)|Create an advanced relation using the internal array format|
|[hasRelations](#vakata\database\tablehasrelations)|Does the definition have related tables|
|[getRelations](#vakata\database\tablegetrelations)|Get all relation definitions|
|[hasRelation](#vakata\database\tablehasrelation)|Check if a named relation exists|
|[getRelation](#vakata\database\tablegetrelation)|Get a relation by name|
|[renameRelation](#vakata\database\tablerenamerelation)|Rename a relation|

---



### vakata\database\Table::__construct
Create a new instance  


```php
public function __construct (  
    string $name  
)   
```

|  | Type | Description |
|-----|-----|-----|
| `$name` | `string` | the table name |

---


### vakata\database\Table::getComment
Get the table comment  


```php
public function getComment () : string    
```

|  | Type | Description |
|-----|-----|-----|
|  |  |  |
| `return` | `string` | the table comment |

---


### vakata\database\Table::setComment
Set the table comment  


```php
public function setComment (  
    string $comment  
) : self    
```

|  | Type | Description |
|-----|-----|-----|
| `$comment` | `string` | the table comment |
|  |  |  |
| `return` | `self` |  |

---


### vakata\database\Table::addColumn
Add a column to the definition  


```php
public function addColumn (  
    string $column,  
    array $definition  
) : self    
```

|  | Type | Description |
|-----|-----|-----|
| `$column` | `string` | the column name |
| `$definition` | `array` | optional array of data associated with the column |
|  |  |  |
| `return` | `self` |  |

---


### vakata\database\Table::addColumns
Add columns to the definition  


```php
public function addColumns (  
    array $columns  
) : self    
```

|  | Type | Description |
|-----|-----|-----|
| `$columns` | `array` | key - value pairs, where each key is a column name and each value - array of info |
|  |  |  |
| `return` | `self` |  |

---


### vakata\database\Table::setPrimaryKey
Set the primary key  


```php
public function setPrimaryKey (  
    array|string $column  
) : self    
```

|  | Type | Description |
|-----|-----|-----|
| `$column` | `array`, `string` | either a single column name or an array of column names |
|  |  |  |
| `return` | `self` |  |

---


### vakata\database\Table::getName
Get the table name  


```php
public function getName () : string    
```

|  | Type | Description |
|-----|-----|-----|
|  |  |  |
| `return` | `string` | the table name |

---


### vakata\database\Table::getColumn
Get a column definition  


```php
public function getColumn (  
    string $column  
) : array, null    
```

|  | Type | Description |
|-----|-----|-----|
| `$column` | `string` | the column name to search for |
|  |  |  |
| `return` | `array`, `null` | the column details or `null` if the column does not exist |

---


### vakata\database\Table::getColumns
Get all column names  


```php
public function getColumns () : array    
```

|  | Type | Description |
|-----|-----|-----|
|  |  |  |
| `return` | `array` | array of strings, where each element is a column name |

---


### vakata\database\Table::getFullColumns
Get all column definitions  


```php
public function getFullColumns () : array    
```

|  | Type | Description |
|-----|-----|-----|
|  |  |  |
| `return` | `array` | key - value pairs, where each key is a column name and each value - the column data |

---


### vakata\database\Table::getPrimaryKey
Get the primary key columns  


```php
public function getPrimaryKey () : array    
```

|  | Type | Description |
|-----|-----|-----|
|  |  |  |
| `return` | `array` | array of column names |

---


### vakata\database\Table::hasOne
Create a relation where each record has zero or one related rows in another table  


```php
public function hasOne (  
    \Table $toTable,  
    string|null $name,  
    string|array|null $toTableColumn,  
    string|null $sql,  
    array $par  
) : self    
```

|  | Type | Description |
|-----|-----|-----|
| `$toTable` | `\Table` | the related table definition |
| `$name` | `string`, `null` | the name of the relation (defaults to the related table name) |
| `$toTableColumn` | `string`, `array`, `null` | the remote columns pointing to the PK in the current table |
| `$sql` | `string`, `null` | additional where clauses to use, default to null |
| `$par` | `array` | parameters for the above statement, defaults to an empty array |
|  |  |  |
| `return` | `self` |  |

---


### vakata\database\Table::hasMany
Create a relation where each record has zero, one or more related rows in another table  


```php
public function hasMany (  
    \Table $toTable,  
    string|null $name,  
    string|array|null $toTableColumn,  
    string|null $sql,  
    array $par  
) : self    
```

|  | Type | Description |
|-----|-----|-----|
| `$toTable` | `\Table` | the related table definition |
| `$name` | `string`, `null` | the name of the relation (defaults to the related table name) |
| `$toTableColumn` | `string`, `array`, `null` | the remote columns pointing to the PK in the current table |
| `$sql` | `string`, `null` | additional where clauses to use, default to null |
| `$par` | `array` | parameters for the above statement, defaults to an empty array |
|  |  |  |
| `return` | `self` |  |

---


### vakata\database\Table::belongsTo
Create a relation where each record belongs to another row in another table  


```php
public function belongsTo (  
    \Table $toTable,  
    string|null $name,  
    string|array|null $localColumn,  
    string|null $sql,  
    array $par  
) : self    
```

|  | Type | Description |
|-----|-----|-----|
| `$toTable` | `\Table` | the related table definition |
| `$name` | `string`, `null` | the name of the relation (defaults to the related table name) |
| `$localColumn` | `string`, `array`, `null` | the local columns pointing to the PK of the related table |
| `$sql` | `string`, `null` | additional where clauses to use, default to null |
| `$par` | `array` | parameters for the above statement, defaults to an empty array |
|  |  |  |
| `return` | `self` |  |

---


### vakata\database\Table::manyToMany
Create a relation where each record has many linked records in another table but using a liking table  


```php
public function manyToMany (  
    \Table $toTable,  
    \Table $pivot,  
    string|null $name,  
    string|array|null $toTableColumn,  
    string|array|null $localColumn  
) : self    
```

|  | Type | Description |
|-----|-----|-----|
| `$toTable` | `\Table` | the related table definition |
| `$pivot` | `\Table` | the pivot table definition |
| `$name` | `string`, `null` | the name of the relation (defaults to the related table name) |
| `$toTableColumn` | `string`, `array`, `null` | the local columns pointing to the pivot table |
| `$localColumn` | `string`, `array`, `null` | the pivot columns pointing to the related table PK |
|  |  |  |
| `return` | `self` |  |

---


### vakata\database\Table::addRelation
Create an advanced relation using the internal array format  


```php
public function addRelation (  
    string $name,  
    array $relation  
) : self    
```

|  | Type | Description |
|-----|-----|-----|
| `$name` | `string` | the name of the relation (defaults to the related table name) |
| `$relation` | `array` | the relation definition |
|  |  |  |
| `return` | `self` |  |

---


### vakata\database\Table::hasRelations
Does the definition have related tables  


```php
public function hasRelations () : boolean    
```

|  | Type | Description |
|-----|-----|-----|
|  |  |  |
| `return` | `boolean` |  |

---


### vakata\database\Table::getRelations
Get all relation definitions  


```php
public function getRelations () : array    
```

|  | Type | Description |
|-----|-----|-----|
|  |  |  |
| `return` | `array` | the relation definitions |

---


### vakata\database\Table::hasRelation
Check if a named relation exists  


```php
public function hasRelation (  
    string $name  
) : boolean    
```

|  | Type | Description |
|-----|-----|-----|
| `$name` | `string` | the name to search for |
|  |  |  |
| `return` | `boolean` | does the relation exist |

---


### vakata\database\Table::getRelation
Get a relation by name  


```php
public function getRelation (  
    string $name  
) : array    
```

|  | Type | Description |
|-----|-----|-----|
| `$name` | `string` | the name to search for |
|  |  |  |
| `return` | `array` | the relation definition |

---


### vakata\database\Table::renameRelation
Rename a relation  


```php
public function renameRelation (  
    string $name,  
    string $new  
) : array    
```

|  | Type | Description |
|-----|-----|-----|
| `$name` | `string` | the name to search for |
| `$new` | `string` | the new name for the relation |
|  |  |  |
| `return` | `array` | the relation definition |

---

