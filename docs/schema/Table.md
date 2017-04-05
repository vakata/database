# vakata\database\schema\Table
A table definition

## Methods

| Name | Description |
|------|-------------|
|[__construct](#vakata\database\schema\table__construct)|Create a new instance|
|[getComment](#vakata\database\schema\tablegetcomment)|Get the table comment|
|[setComment](#vakata\database\schema\tablesetcomment)|Set the table comment|
|[addColumn](#vakata\database\schema\tableaddcolumn)|Add a column to the definition|
|[addColumns](#vakata\database\schema\tableaddcolumns)|Add columns to the definition|
|[setPrimaryKey](#vakata\database\schema\tablesetprimarykey)|Set the primary key|
|[getName](#vakata\database\schema\tablegetname)|Get the table name|
|[getColumn](#vakata\database\schema\tablegetcolumn)|Get a column definition|
|[getColumns](#vakata\database\schema\tablegetcolumns)|Get all column names|
|[getFullColumns](#vakata\database\schema\tablegetfullcolumns)|Get all column definitions|
|[getPrimaryKey](#vakata\database\schema\tablegetprimarykey)|Get the primary key columns|
|[hasOne](#vakata\database\schema\tablehasone)|Create a relation where each record has zero or one related rows in another table|
|[hasMany](#vakata\database\schema\tablehasmany)|Create a relation where each record has zero, one or more related rows in another table|
|[belongsTo](#vakata\database\schema\tablebelongsto)|Create a relation where each record belongs to another row in another table|
|[manyToMany](#vakata\database\schema\tablemanytomany)|Create a relation where each record has many linked records in another table but using a liking table|
|[addRelation](#vakata\database\schema\tableaddrelation)|Create an advanced relation using the internal array format|
|[hasRelations](#vakata\database\schema\tablehasrelations)|Does the definition have related tables|
|[getRelations](#vakata\database\schema\tablegetrelations)|Get all relation definitions|
|[hasRelation](#vakata\database\schema\tablehasrelation)|Check if a named relation exists|
|[getRelation](#vakata\database\schema\tablegetrelation)|Get a relation by name|
|[renameRelation](#vakata\database\schema\tablerenamerelation)|Rename a relation|

---



### vakata\database\schema\Table::__construct
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


### vakata\database\schema\Table::getComment
Get the table comment  


```php
public function getComment () : string    
```

|  | Type | Description |
|-----|-----|-----|
|  |  |  |
| `return` | `string` | the table comment |

---


### vakata\database\schema\Table::setComment
Set the table comment  


```php
public function setComment (  
    string $comment  
) : $this    
```

|  | Type | Description |
|-----|-----|-----|
| `$comment` | `string` | the table comment |
|  |  |  |
| `return` | `$this` |  |

---


### vakata\database\schema\Table::addColumn
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


### vakata\database\schema\Table::addColumns
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


### vakata\database\schema\Table::setPrimaryKey
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


### vakata\database\schema\Table::getName
Get the table name  


```php
public function getName () : string    
```

|  | Type | Description |
|-----|-----|-----|
|  |  |  |
| `return` | `string` | the table name |

---


### vakata\database\schema\Table::getColumn
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


### vakata\database\schema\Table::getColumns
Get all column names  


```php
public function getColumns () : array    
```

|  | Type | Description |
|-----|-----|-----|
|  |  |  |
| `return` | `array` | array of strings, where each element is a column name |

---


### vakata\database\schema\Table::getFullColumns
Get all column definitions  


```php
public function getFullColumns () : array    
```

|  | Type | Description |
|-----|-----|-----|
|  |  |  |
| `return` | `array` | key - value pairs, where each key is a column name and each value - the column data |

---


### vakata\database\schema\Table::getPrimaryKey
Get the primary key columns  


```php
public function getPrimaryKey () : array    
```

|  | Type | Description |
|-----|-----|-----|
|  |  |  |
| `return` | `array` | array of column names |

---


### vakata\database\schema\Table::hasOne
Create a relation where each record has zero or one related rows in another table  


```php
public function hasOne (  
    \Table $toTable,  
    string|null $name,  
    string|array|null $toTableColumn,  
    string|null $sql,  
    array|null $par  
) : $this    
```

|  | Type | Description |
|-----|-----|-----|
| `$toTable` | `\Table` | the related table definition |
| `$name` | `string`, `null` | the name of the relation (defaults to the related table name) |
| `$toTableColumn` | `string`, `array`, `null` | the remote columns pointing to the PK in the current table |
| `$sql` | `string`, `null` | additional where clauses to use, default to null |
| `$par` | `array`, `null` | parameters for the above statement, defaults to null |
|  |  |  |
| `return` | `$this` |  |

---


### vakata\database\schema\Table::hasMany
Create a relation where each record has zero, one or more related rows in another table  


```php
public function hasMany (  
    \Table $toTable,  
    string|null $name,  
    string|array|null $toTableColumn,  
    string|null $sql,  
    array|null $par  
) : $this    
```

|  | Type | Description |
|-----|-----|-----|
| `$toTable` | `\Table` | the related table definition |
| `$name` | `string`, `null` | the name of the relation (defaults to the related table name) |
| `$toTableColumn` | `string`, `array`, `null` | the remote columns pointing to the PK in the current table |
| `$sql` | `string`, `null` | additional where clauses to use, default to null |
| `$par` | `array`, `null` | parameters for the above statement, defaults to null |
|  |  |  |
| `return` | `$this` |  |

---


### vakata\database\schema\Table::belongsTo
Create a relation where each record belongs to another row in another table  


```php
public function belongsTo (  
    \Table $toTable,  
    string|null $name,  
    string|array|null $localColumn,  
    string|null $sql,  
    array|null $par  
) : $this    
```

|  | Type | Description |
|-----|-----|-----|
| `$toTable` | `\Table` | the related table definition |
| `$name` | `string`, `null` | the name of the relation (defaults to the related table name) |
| `$localColumn` | `string`, `array`, `null` | the local columns pointing to the PK of the related table |
| `$sql` | `string`, `null` | additional where clauses to use, default to null |
| `$par` | `array`, `null` | parameters for the above statement, defaults to null |
|  |  |  |
| `return` | `$this` |  |

---


### vakata\database\schema\Table::manyToMany
Create a relation where each record has many linked records in another table but using a liking table  


```php
public function manyToMany (  
    \Table $toTable,  
    \Table $pivot,  
    string|null $name,  
    string|array|null $toTableColumn,  
    string|array|null $localColumn  
) : $this    
```

|  | Type | Description |
|-----|-----|-----|
| `$toTable` | `\Table` | the related table definition |
| `$pivot` | `\Table` | the pivot table definition |
| `$name` | `string`, `null` | the name of the relation (defaults to the related table name) |
| `$toTableColumn` | `string`, `array`, `null` | the local columns pointing to the pivot table |
| `$localColumn` | `string`, `array`, `null` | the pivot columns pointing to the related table PK |
|  |  |  |
| `return` | `$this` |  |

---


### vakata\database\schema\Table::addRelation
Create an advanced relation using the internal array format  


```php
public function addRelation (  
    \TableRelation $relation,  
    string|null $name  
) : $this    
```

|  | Type | Description |
|-----|-----|-----|
| `$relation` | `\TableRelation` | the relation definition |
| `$name` | `string`, `null` | optional name of the relation (defaults to the related table name) |
|  |  |  |
| `return` | `$this` |  |

---


### vakata\database\schema\Table::hasRelations
Does the definition have related tables  


```php
public function hasRelations () : boolean    
```

|  | Type | Description |
|-----|-----|-----|
|  |  |  |
| `return` | `boolean` |  |

---


### vakata\database\schema\Table::getRelations
Get all relation definitions  


```php
public function getRelations () : \TableRelation[]    
```

|  | Type | Description |
|-----|-----|-----|
|  |  |  |
| `return` | `\TableRelation[]` | the relation definitions |

---


### vakata\database\schema\Table::hasRelation
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


### vakata\database\schema\Table::getRelation
Get a relation by name  


```php
public function getRelation (  
    string $name  
) : \TableRelation, null    
```

|  | Type | Description |
|-----|-----|-----|
| `$name` | `string` | the name to search for |
|  |  |  |
| `return` | `\TableRelation`, `null` | the relation definition |

---


### vakata\database\schema\Table::renameRelation
Rename a relation  


```php
public function renameRelation (  
    string $name,  
    string $new  
) : \TableRelation    
```

|  | Type | Description |
|-----|-----|-----|
| `$name` | `string` | the name to search for |
| `$new` | `string` | the new name for the relation |
|  |  |  |
| `return` | `\TableRelation` | the relation definition |

---

