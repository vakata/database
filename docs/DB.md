# vakata\database\DB
A database abstraction with support for various drivers (mySQL, postgre, oracle, msSQL, sphinx, and even PDO).

## Methods

| Name | Description |
|------|-------------|
|[__construct](#vakata\database\db__construct)|Create an instance.|
|[getDriver](#vakata\database\dbgetdriver)|Create a driver instance from a connection string|
|[prepare](#vakata\database\dbprepare)|Prepare a statement.|
|[query](#vakata\database\dbquery)|Run a query (prepare & execute).|
|[get](#vakata\database\dbget)|Run a SELECT query and get an array-like result.|
|[one](#vakata\database\dbone)|Run a SELECT query and get a single row|
|[all](#vakata\database\dball)|Run a SELECT query and get an array|
|[begin](#vakata\database\dbbegin)|Begin a transaction.|
|[commit](#vakata\database\dbcommit)|Commit a transaction.|
|[rollback](#vakata\database\dbrollback)|Rollback a transaction.|
|[driver](#vakata\database\dbdriver)|Get the current driver name (`"mysql"`, `"postgre"`, etc).|
|[parseSchema](#vakata\database\dbparseschema)|Parse all tables from the database.|
|[getSchema](#vakata\database\dbgetschema)|Get the full schema as an array that you can serialize and store|
|[setSchema](#vakata\database\dbsetschema)|Load the schema data from a schema definition array (obtained from getSchema)|
|[table](#vakata\database\dbtable)|Initialize a table query|

---



### vakata\database\DB::__construct
Create an instance.  


```php
public function __construct (  
    \DriverInterface|string $driver  
)   
```

|  | Type | Description |
|-----|-----|-----|
| `$driver` | `\DriverInterface`, `string` | a driver instance or a connection string |

---


### vakata\database\DB::getDriver
Create a driver instance from a connection string  


```php
public static function getDriver (  
    string $connectionString  
) : \DriverInterface    
```

|  | Type | Description |
|-----|-----|-----|
| `$connectionString` | `string` | the connection string |
|  |  |  |
| `return` | `\DriverInterface` |  |

---


### vakata\database\DB::prepare
Prepare a statement.  
Use only if you need a single query to be performed multiple times with different parameters.

```php
public function prepare (  
    string $sql  
) : \StatementInterface    
```

|  | Type | Description |
|-----|-----|-----|
| `$sql` | `string` | the query to prepare - use `?` for arguments |
|  |  |  |
| `return` | `\StatementInterface` | the prepared statement |

---


### vakata\database\DB::query
Run a query (prepare & execute).  


```php
public function query (  
    string $sql,  
    array $data  
) : \ResultInterface    
```

|  | Type | Description |
|-----|-----|-----|
| `$sql` | `string` | SQL query |
| `$data` | `array` | parameters (optional) |
|  |  |  |
| `return` | `\ResultInterface` | the result of the execution |

---


### vakata\database\DB::get
Run a SELECT query and get an array-like result.  
When using `get` the data is kept in the database client and fetched as needed (not in PHP memory as with `all`)

```php
public function get (  
    string $sql,  
    array $par,  
    string $key,  
    bool $skip,  
    bool $opti  
) : \Collection    
```

|  | Type | Description |
|-----|-----|-----|
| `$sql` | `string` | SQL query |
| `$par` | `array` | parameters |
| `$key` | `string` | column name to use as the array index |
| `$skip` | `bool` | do not include the column used as index in the value (defaults to `false`) |
| `$opti` | `bool` | if a single column is returned - do not use an array wrapper (defaults to `true`) |
|  |  |  |
| `return` | `\Collection` | the result of the execution |

---


### vakata\database\DB::one
Run a SELECT query and get a single row  


```php
public function one (  
    string $sql,  
    array $par,  
    callable $keys,  
    bool $opti  
) : \Collection    
```

|  | Type | Description |
|-----|-----|-----|
| `$sql` | `string` | SQL query |
| `$par` | `array` | parameters |
| `$keys` | `callable` | an optional mutator to pass each row's keys through (the column names) |
| `$opti` | `bool` | if a single column is returned - do not use an array wrapper (defaults to `true`) |
|  |  |  |
| `return` | `\Collection` | the result of the execution |

---


### vakata\database\DB::all
Run a SELECT query and get an array  


```php
public function all (  
    string $sql,  
    array $par,  
    string $key,  
    bool $skip,  
    callable $keys,  
    bool $opti  
) : \Collection    
```

|  | Type | Description |
|-----|-----|-----|
| `$sql` | `string` | SQL query |
| `$par` | `array` | parameters |
| `$key` | `string` | column name to use as the array index |
| `$skip` | `bool` | do not include the column used as index in the value (defaults to `false`) |
| `$keys` | `callable` | an optional mutator to pass each row's keys through (the column names) |
| `$opti` | `bool` | if a single column is returned - do not use an array wrapper (defaults to `true`) |
|  |  |  |
| `return` | `\Collection` | the result of the execution |

---


### vakata\database\DB::begin
Begin a transaction.  


```php
public function begin () : $this    
```

|  | Type | Description |
|-----|-----|-----|
|  |  |  |
| `return` | `$this` |  |

---


### vakata\database\DB::commit
Commit a transaction.  


```php
public function commit () : $this    
```

|  | Type | Description |
|-----|-----|-----|
|  |  |  |
| `return` | `$this` |  |

---


### vakata\database\DB::rollback
Rollback a transaction.  


```php
public function rollback () : $this    
```

|  | Type | Description |
|-----|-----|-----|
|  |  |  |
| `return` | `$this` |  |

---


### vakata\database\DB::driver
Get the current driver name (`"mysql"`, `"postgre"`, etc).  


```php
public function driver () : string    
```

|  | Type | Description |
|-----|-----|-----|
|  |  |  |
| `return` | `string` | the current driver name |

---


### vakata\database\DB::parseSchema
Parse all tables from the database.  


```php
public function parseSchema () : $this    
```

|  | Type | Description |
|-----|-----|-----|
|  |  |  |
| `return` | `$this` |  |

---


### vakata\database\DB::getSchema
Get the full schema as an array that you can serialize and store  


```php
public function getSchema () : array    
```

|  | Type | Description |
|-----|-----|-----|
|  |  |  |
| `return` | `array` |  |

---


### vakata\database\DB::setSchema
Load the schema data from a schema definition array (obtained from getSchema)  


```php
public function setSchema (  
    array $data  
) : $this    
```

|  | Type | Description |
|-----|-----|-----|
| `$data` | `array` | the schema definition |
|  |  |  |
| `return` | `$this` |  |

---


### vakata\database\DB::table
Initialize a table query  


```php
public function table (  
    string $table  
) : \TableQuery    
```

|  | Type | Description |
|-----|-----|-----|
| `$table` | `string` | the table to query |
|  |  |  |
| `return` | `\TableQuery` |  |

---

