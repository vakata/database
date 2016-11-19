# vakata\database\DB
A database abstraction with support for various drivers (mySQL, postgre, oracle, msSQL, sphinx, and even PDO).

## Methods

| Name | Description |
|------|-------------|
|[__construct](#vakata\database\db__construct)|Create an instance.|
|[prepare](#vakata\database\dbprepare)|Prepare a statement.|
|[query](#vakata\database\dbquery)|Run a query (prepare & execute).|
|[get](#vakata\database\dbget)|Run a SELECT query and get an array-like result.|
|[all](#vakata\database\dball)|Run a SELECT query and get an array result.|
|[one](#vakata\database\dbone)|Run a SELECT query and get the first row.|
|[raw](#vakata\database\dbraw)|Run a raw SQL query|
|[driver](#vakata\database\dbdriver)|Get the current driver name (`"mysqli"`, `"postgre"`, etc).|
|[name](#vakata\database\dbname)|Get the current database name.|
|[settings](#vakata\database\dbsettings)|Get the current settings object|
|[begin](#vakata\database\dbbegin)|Begin a transaction.|
|[commit](#vakata\database\dbcommit)|Commit a transaction.|
|[rollback](#vakata\database\dbrollback)|Rollback a transaction.|
|[isTransaction](#vakata\database\dbistransaction)|Check if a transaciton is currently open.|

---



### vakata\database\DB::__construct
Create an instance.  


```php
public function __construct (  
    string $options  
)   
```

|  | Type | Description |
|-----|-----|-----|
| `$options` | `string` | a connection string (like `"mysqli://user:pass@host/database?option=value"`) |

---


### vakata\database\DB::prepare
Prepare a statement.  
Use only if you need a single query to be performed multiple times with different parameters.

```php
public function prepare (  
    string $sql  
) : \vakata\database\Query    
```

|  | Type | Description |
|-----|-----|-----|
| `$sql` | `string` | the query to prepare - use `?` for arguments |
|  |  |  |
| `return` | [`\vakata\database\Query`](Query.md) | the prepared statement |

---


### vakata\database\DB::query
Run a query (prepare & execute).  


```php
public function query (  
    string $sql,  
    array $data  
) : \vakata\database\QueryResult    
```

|  | Type | Description |
|-----|-----|-----|
| `$sql` | `string` | SQL query |
| `$data` | `array` | parameters |
|  |  |  |
| `return` | [`\vakata\database\QueryResult`](QueryResult.md) | the result of the execution |

---


### vakata\database\DB::get
Run a SELECT query and get an array-like result.  
When using `get` the data is kept in the database client and fetched as needed (not in PHP memory as with `all`)

```php
public function get (  
    string $sql,  
    array $data,  
    string $key,  
    bool $skip,  
    string $mode,  
    bool $opti  
) : \vakata\database\Result    
```

|  | Type | Description |
|-----|-----|-----|
| `$sql` | `string` | SQL query |
| `$data` | `array` | parameters |
| `$key` | `string` | column name to use as the array index |
| `$skip` | `bool` | do not include the column used as index in the value (defaults to `false`) |
| `$mode` | `string` | result mode - `"assoc"` by default, could be `"num"`, `"both"`, `"assoc_ci"`, `"assoc_lc"`, `"assoc_uc"` |
| `$opti` | `bool` | if a single column is returned - do not use an array wrapper (defaults to `true`) |
|  |  |  |
| `return` | [`\vakata\database\Result`](Result.md) | the result of the execution - use as a normal array |

---


### vakata\database\DB::all
Run a SELECT query and get an array result.  


```php
public function all (  
    string $sql,  
    array $data,  
    string $key,  
    bool $skip,  
    string $mode,  
    bool $opti  
) : array    
```

|  | Type | Description |
|-----|-----|-----|
| `$sql` | `string` | SQL query |
| `$data` | `array` | parameters |
| `$key` | `string` | column name to use as the array index |
| `$skip` | `bool` | do not include the column used as index in the value (defaults to `false`) |
| `$mode` | `string` | result mode - `"assoc"` by default, could be `"num"`, `"both"`, `"assoc_ci"`, `"assoc_lc"`, `"assoc_uc"` |
| `$opti` | `bool` | if a single column is returned - do not use an array wrapper (defaults to `true`) |
|  |  |  |
| `return` | `array` | the result of the execution |

---


### vakata\database\DB::one
Run a SELECT query and get the first row.  


```php
public function one (  
    string $sql,  
    array $data,  
    string $mode,  
    bool $opti  
) : mixed    
```

|  | Type | Description |
|-----|-----|-----|
| `$sql` | `string` | SQL query |
| `$data` | `array` | parameters |
| `$mode` | `string` | result mode - `"assoc"` by default, could be `"num"`, `"both"`, `"assoc_ci"`, `"assoc_lc"`, `"assoc_uc"` |
| `$opti` | `bool` | if a single column is returned - do not use an array wrapper (defaults to `true`) |
|  |  |  |
| `return` | `mixed` | the result of the execution |

---


### vakata\database\DB::raw
Run a raw SQL query  


```php
public function raw (  
    string $sql  
) : mixed    
```

|  | Type | Description |
|-----|-----|-----|
| `$sql` | `string` | SQL query |
|  |  |  |
| `return` | `mixed` | the result of the execution |

---


### vakata\database\DB::driver
Get the current driver name (`"mysqli"`, `"postgre"`, etc).  


```php
public function driver () : string    
```

|  | Type | Description |
|-----|-----|-----|
|  |  |  |
| `return` | `string` | the current driver name |

---


### vakata\database\DB::name
Get the current database name.  


```php
public function name () : string    
```

|  | Type | Description |
|-----|-----|-----|
|  |  |  |
| `return` | `string` | the current database name |

---


### vakata\database\DB::settings
Get the current settings object  


```php
public function settings () : \vakata\database\Settings    
```

|  | Type | Description |
|-----|-----|-----|
|  |  |  |
| `return` | `\vakata\database\Settings` | the current settings |

---


### vakata\database\DB::begin
Begin a transaction.  


```php
public function begin () : bool    
```

|  | Type | Description |
|-----|-----|-----|
|  |  |  |
| `return` | `bool` | `true` if a transaction was opened, `false` otherwise |

---


### vakata\database\DB::commit
Commit a transaction.  


```php
public function commit () : bool    
```

|  | Type | Description |
|-----|-----|-----|
|  |  |  |
| `return` | `bool` | was the commit successful |

---


### vakata\database\DB::rollback
Rollback a transaction.  


```php
public function rollback () : bool    
```

|  | Type | Description |
|-----|-----|-----|
|  |  |  |
| `return` | `bool` | was the rollback successful |

---


### vakata\database\DB::isTransaction
Check if a transaciton is currently open.  


```php
public function isTransaction () : bool    
```

|  | Type | Description |
|-----|-----|-----|
|  |  |  |
| `return` | `bool` | is a transaction currently open |

---

