# vakata\database\Query
A simple database query wrapper class.

Do not create manually - the `\vakata\database\DB` class will create instances as needed.
An object of this type is returned by `\vakata\database\DB::prepare()`.
## Methods

| Name | Description |
|------|-------------|
|[execute](#vakata\database\queryexecute)|Execute the query, which was prepared using `\vakata\database\DB::prepare()`.|

---



### vakata\database\Query::execute
Execute the query, which was prepared using `\vakata\database\DB::prepare()`.  


```php
public function execute (  
    array $data  
) : \vakata\database\QueryResult    
```

|  | Type | Description |
|-----|-----|-----|
| `$data` | `array` | optional parameter - the data needed for the query if it has placeholders |
|  |  |  |
| `return` | [`\vakata\database\QueryResult`](QueryResult.md) | the result of the query |

---

