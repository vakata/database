# vakata\database\Result
A class wrapping a the result of a SELECT query.

This class implements `\Iterator`, `\ArrayAccess` and `\Countable` so you can just use the instance as an array.
If you need an actual array - call the `get()` method.
## Methods

| Name | Description |
|------|-------------|
|[get](#vakata\database\resultget)|Get the current result set as an array.|

---



### vakata\database\Result::get
Get the current result set as an array.  


```php
public function get () : array    
```

|  | Type | Description |
|-----|-----|-----|
|  |  |  |
| `return` | `array` | the result set as an array |

---

