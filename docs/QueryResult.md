# vakata\database\QueryResult
A wrapper class for the result of a query.

Do not create manually - the `\vakata\database\DB` class will create instances as needed.
An object of this type is returned by `\vakata\database\DB::query()` used for `UPDATE / INSERT / DELETE` queries
## Methods

| Name | Description |
|------|-------------|
|[result](#vakata\database\queryresultresult)|Get an array-like result.|
|[affected](#vakata\database\queryresultaffected)|The number of rows affected by the query|
|[insertId](#vakata\database\queryresultinsertid)|The last inserted ID in the current session.|

---



### vakata\database\QueryResult::result
Get an array-like result.  
Instead of using this method - call `\vakata\database\DB::get()` and `\vakata\database\DB::all()`

```php
public function result (  
    string $key,  
    bool $skip,  
    string $mode,  
    bool $opti  
) : \vakata\database\Result    
```

|  | Type | Description |
|-----|-----|-----|
| `$key` | `string` | column name to use as the array index |
| `$skip` | `bool` | do not include the column used as index in the value (defaults to `false`) |
| `$mode` | `string` | result mode - `"assoc"` by default, could be `"num"`, `"both"`, `"assoc_ci"`, `"assoc_lc"`, `"assoc_uc"` |
| `$opti` | `bool` | if a single column is returned - do not use an array wrapper (defaults to `true`) |
|  |  |  |
| `return` | [`\vakata\database\Result`](Result.md) | the result of the execution - use as a normal array |

---


### vakata\database\QueryResult::affected
The number of rows affected by the query  


```php
public function affected () : int    
```

|  | Type | Description |
|-----|-----|-----|
|  |  |  |
| `return` | `int` | the number of affected rows |

---


### vakata\database\QueryResult::insertId
The last inserted ID in the current session.  


```php
public function insertId (  
    string $name  
) : mixed    
```

|  | Type | Description |
|-----|-----|-----|
| `$name` | `string` | optional parameter for drivers which need a sequence name (oracle for example) |
|  |  |  |
| `return` | `mixed` | the last created ID |

---

