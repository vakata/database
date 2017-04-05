# vakata\database\schema\TableRelation
A table definition

## Methods

| Name | Description |
|------|-------------|
|[__construct](#vakata\database\schema\tablerelation__construct)|Create a new instance|

---



### vakata\database\schema\TableRelation::__construct
Create a new instance  


```php
public function __construct (  
    string $name,  
    \Table $table,  
    array $keymap,  
    bool $many,  
    \Table|null $pivot,  
    array|null $keymap,  
    string|null $sql,  
    array $par  
)   
```

|  | Type | Description |
|-----|-----|-----|
| `$name` | `string` | the name of the relation |
| `$table` | `\Table` | the foreign table definition |
| `$keymap` | `array` | the keymap (local => foreign) |
| `$many` | `bool` | is it a one to many rows relation, defaults to false |
| `$pivot` | `\Table`, `null` | the pivot table definition (if exists), defaults to null |
| `$keymap` | `array`, `null` | the keymap (local => foreign), defaults to null |
| `$sql` | `string`, `null` | additional where clauses to use, default to null |
| `$par` | `array` | parameters for the above statement, defaults to null |

---

