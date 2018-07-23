## Table of contents

- [\vakata\database\DB](#class-vakatadatabasedb)
- [\vakata\database\DBException](#class-vakatadatabasedbexception)
- [\vakata\database\DBInterface (interface)](#interface-vakatadatabasedbinterface)
- [\vakata\database\DriverAbstract (abstract)](#class-vakatadatabasedriverabstract-abstract)
- [\vakata\database\DriverInterface (interface)](#interface-vakatadatabasedriverinterface)
- [\vakata\database\ResultInterface (interface)](#interface-vakatadatabaseresultinterface)
- [\vakata\database\StatementInterface (interface)](#interface-vakatadatabasestatementinterface)
- [\vakata\database\driver\ibase\Driver](#class-vakatadatabasedriveribasedriver)
- [\vakata\database\driver\ibase\Result](#class-vakatadatabasedriveribaseresult)
- [\vakata\database\driver\ibase\Statement](#class-vakatadatabasedriveribasestatement)
- [\vakata\database\driver\mysql\Driver](#class-vakatadatabasedrivermysqldriver)
- [\vakata\database\driver\mysql\Result](#class-vakatadatabasedrivermysqlresult)
- [\vakata\database\driver\mysql\Statement](#class-vakatadatabasedrivermysqlstatement)
- [\vakata\database\driver\odbc\Driver](#class-vakatadatabasedriverodbcdriver)
- [\vakata\database\driver\odbc\Result](#class-vakatadatabasedriverodbcresult)
- [\vakata\database\driver\odbc\Statement](#class-vakatadatabasedriverodbcstatement)
- [\vakata\database\driver\oracle\Driver](#class-vakatadatabasedriveroracledriver)
- [\vakata\database\driver\oracle\Result](#class-vakatadatabasedriveroracleresult)
- [\vakata\database\driver\oracle\Statement](#class-vakatadatabasedriveroraclestatement)
- [\vakata\database\driver\pdo\Driver](#class-vakatadatabasedriverpdodriver)
- [\vakata\database\driver\pdo\Result](#class-vakatadatabasedriverpdoresult)
- [\vakata\database\driver\pdo\Statement](#class-vakatadatabasedriverpdostatement)
- [\vakata\database\driver\postgre\Driver](#class-vakatadatabasedriverpostgredriver)
- [\vakata\database\driver\postgre\Result](#class-vakatadatabasedriverpostgreresult)
- [\vakata\database\driver\postgre\Statement](#class-vakatadatabasedriverpostgrestatement)
- [\vakata\database\driver\sqlite\Driver](#class-vakatadatabasedriversqlitedriver)
- [\vakata\database\driver\sqlite\Result](#class-vakatadatabasedriversqliteresult)
- [\vakata\database\driver\sqlite\Statement](#class-vakatadatabasedriversqlitestatement)
- [\vakata\database\schema\Table](#class-vakatadatabaseschematable)
- [\vakata\database\schema\TableColumn](#class-vakatadatabaseschematablecolumn)
- [\vakata\database\schema\TableQuery](#class-vakatadatabaseschematablequery)
- [\vakata\database\schema\TableQueryIterator](#class-vakatadatabaseschematablequeryiterator)
- [\vakata\database\schema\TableRelation](#class-vakatadatabaseschematablerelation)

<hr />

### Class: \vakata\database\DB

> A database abstraction with support for various drivers (mySQL, postgre, oracle, msSQL, sphinx, and even PDO).

| Visibility | Function |
|:-----------|:---------|
| public | <strong>__call(</strong><em>mixed</em> <strong>$method</strong>, <em>mixed</em> <strong>$args</strong>)</strong> : <em>void</em> |
| public | <strong>__construct(</strong><em>[\vakata\database\DriverInterface](#interface-vakatadatabasedriverinterface)/string</em> <strong>$driver</strong>)</strong> : <em>void</em><br /><em>Create an instance.</em> |
| public | <strong>all(</strong><em>\string</em> <strong>$sql</strong>, <em>array</em> <strong>$par=null</strong>, <em>\string</em> <strong>$key=null</strong>, <em>\boolean</em> <strong>$skip=false</strong>, <em>\boolean</em> <strong>$opti=true</strong>)</strong> : <em>Collection the result of the execution</em><br /><em>Run a SELECT query and get an array</em> |
| public | <strong>begin()</strong> : <em>\vakata\database\$this</em><br /><em>Begin a transaction.</em> |
| public | <strong>commit()</strong> : <em>\vakata\database\$this</em><br /><em>Commit a transaction.</em> |
| public | <strong>definition(</strong><em>\string</em> <strong>$table</strong>, <em>\boolean</em> <strong>$detectRelations=true</strong>)</strong> : <em>void</em> |
| public | <strong>driverName()</strong> : <em>string the current driver name</em><br /><em>Get the current driver name (`"mysql"`, `"postgre"`, etc).</em> |
| public | <strong>driverOption(</strong><em>\string</em> <strong>$key</strong>, <em>mixed</em> <strong>$default=null</strong>)</strong> : <em>mixed the option value</em><br /><em>Get an option from the driver</em> |
| public | <strong>get(</strong><em>\string</em> <strong>$sql</strong>, <em>array</em> <strong>$par=null</strong>, <em>\string</em> <strong>$key=null</strong>, <em>\boolean</em> <strong>$skip=false</strong>, <em>\boolean</em> <strong>$opti=true</strong>)</strong> : <em>Collection the result of the execution</em><br /><em>Run a SELECT query and get an array-like result. When using `get` the data is kept in the database client and fetched as needed (not in PHP memory as with `all`)</em> |
| public static | <strong>getDriver(</strong><em>\string</em> <strong>$connectionString</strong>)</strong> : <em>[\vakata\database\DriverInterface](#interface-vakatadatabasedriverinterface)</em><br /><em>Create a driver instance from a connection string</em> |
| public | <strong>getSchema(</strong><em>bool</em> <strong>$asPlainArray=true</strong>)</strong> : <em>array</em><br /><em>Get the full schema as an array that you can serialize and store</em> |
| public | <strong>one(</strong><em>\string</em> <strong>$sql</strong>, <em>array</em> <strong>$par=null</strong>, <em>\boolean</em> <strong>$opti=true</strong>)</strong> : <em>Collection the result of the execution</em><br /><em>Run a SELECT query and get a single row</em> |
| public | <strong>parseSchema()</strong> : <em>\vakata\database\$this</em><br /><em>Parse all tables from the database.</em> |
| public | <strong>prepare(</strong><em>\string</em> <strong>$sql</strong>)</strong> : <em>StatementInterface the prepared statement</em><br /><em>Prepare a statement. Use only if you need a single query to be performed multiple times with different parameters.</em> |
| public | <strong>query(</strong><em>\string</em> <strong>$sql</strong>, <em>array/null</em> <strong>$par=null</strong>)</strong> : <em>ResultInterface the result of the execution</em><br /><em>Run a query (prepare & execute).</em> |
| public | <strong>rollback()</strong> : <em>\vakata\database\$this</em><br /><em>Rollback a transaction.</em> |
| public | <strong>setSchema(</strong><em>array</em> <strong>$data</strong>)</strong> : <em>\vakata\database\$this</em><br /><em>Load the schema data from a schema definition array (obtained from getSchema)</em> |
| public | <strong>table(</strong><em>string</em> <strong>$table</strong>)</strong> : <em>[\vakata\database\schema\TableQuery](#class-vakatadatabaseschematablequery)</em><br /><em>Initialize a table query</em> |
| public | <strong>test()</strong> : <em>bool</em><br /><em>Test the connection</em> |
| protected | <strong>expand(</strong><em>\string</em> <strong>$sql</strong>, <em>mixed</em> <strong>$par=null</strong>)</strong> : <em>void</em> |

*This class implements [\vakata\database\DBInterface](#interface-vakatadatabasedbinterface)*

<hr />

### Class: \vakata\database\DBException

| Visibility | Function |
|:-----------|:---------|

*This class extends \Exception*

*This class implements \Throwable*

<hr />

### Interface: \vakata\database\DBInterface

| Visibility | Function |
|:-----------|:---------|
| public | <strong>all(</strong><em>\string</em> <strong>$sql</strong>, <em>mixed</em> <strong>$par=null</strong>, <em>\string</em> <strong>$key=null</strong>, <em>\boolean</em> <strong>$skip=false</strong>, <em>\boolean</em> <strong>$opti=true</strong>)</strong> : <em>void</em> |
| public | <strong>begin()</strong> : <em>void</em> |
| public | <strong>commit()</strong> : <em>void</em> |
| public | <strong>definition(</strong><em>\string</em> <strong>$table</strong>, <em>\boolean</em> <strong>$detectRelations=true</strong>)</strong> : <em>void</em> |
| public | <strong>driverName()</strong> : <em>void</em> |
| public | <strong>driverOption(</strong><em>\string</em> <strong>$key</strong>, <em>mixed</em> <strong>$default=null</strong>)</strong> : <em>void</em> |
| public | <strong>get(</strong><em>\string</em> <strong>$sql</strong>, <em>mixed</em> <strong>$par=null</strong>, <em>\string</em> <strong>$key=null</strong>, <em>\boolean</em> <strong>$skip=false</strong>, <em>\boolean</em> <strong>$opti=true</strong>)</strong> : <em>mixed</em> |
| public static | <strong>getDriver(</strong><em>\string</em> <strong>$connectionString</strong>)</strong> : <em>mixed</em> |
| public | <strong>getSchema(</strong><em>bool</em> <strong>$asPlainArray=true</strong>)</strong> : <em>mixed</em> |
| public | <strong>one(</strong><em>\string</em> <strong>$sql</strong>, <em>mixed</em> <strong>$par=null</strong>, <em>\boolean</em> <strong>$opti=true</strong>)</strong> : <em>void</em> |
| public | <strong>parseSchema()</strong> : <em>void</em> |
| public | <strong>prepare(</strong><em>\string</em> <strong>$sql</strong>)</strong> : <em>void</em> |
| public | <strong>query(</strong><em>\string</em> <strong>$sql</strong>, <em>mixed</em> <strong>$par=null</strong>)</strong> : <em>void</em> |
| public | <strong>rollback()</strong> : <em>void</em> |
| public | <strong>setSchema(</strong><em>array</em> <strong>$data</strong>)</strong> : <em>void</em> |
| public | <strong>table(</strong><em>mixed</em> <strong>$table</strong>)</strong> : <em>void</em> |

<hr />

### Class: \vakata\database\DriverAbstract (abstract)

| Visibility | Function |
|:-----------|:---------|
| public | <strong>begin()</strong> : <em>void</em> |
| public | <strong>commit()</strong> : <em>void</em> |
| public | <strong>name()</strong> : <em>void</em> |
| public | <strong>option(</strong><em>\string</em> <strong>$key</strong>, <em>mixed</em> <strong>$default=null</strong>)</strong> : <em>void</em> |
| public | <strong>abstract prepare(</strong><em>\string</em> <strong>$sql</strong>)</strong> : <em>void</em> |
| public | <strong>query(</strong><em>\string</em> <strong>$sql</strong>, <em>mixed</em> <strong>$par=null</strong>)</strong> : <em>ResultInterface the result of the execution</em><br /><em>Run a query (prepare & execute).</em> |
| public | <strong>rollback()</strong> : <em>void</em> |
| public | <strong>table(</strong><em>\string</em> <strong>$table</strong>, <em>\boolean</em> <strong>$detectRelations=true</strong>)</strong> : <em>void</em> |
| public | <strong>tables()</strong> : <em>void</em> |
| public | <strong>abstract test()</strong> : <em>void</em> |
| protected | <strong>expand(</strong><em>\string</em> <strong>$sql</strong>, <em>mixed</em> <strong>$par=null</strong>)</strong> : <em>void</em> |

*This class implements [\vakata\database\DriverInterface](#interface-vakatadatabasedriverinterface)*

<hr />

### Interface: \vakata\database\DriverInterface

| Visibility | Function |
|:-----------|:---------|
| public | <strong>begin()</strong> : <em>void</em> |
| public | <strong>commit()</strong> : <em>void</em> |
| public | <strong>name()</strong> : <em>void</em> |
| public | <strong>option(</strong><em>\string</em> <strong>$key</strong>, <em>mixed</em> <strong>$default=null</strong>)</strong> : <em>void</em> |
| public | <strong>prepare(</strong><em>\string</em> <strong>$sql</strong>)</strong> : <em>void</em> |
| public | <strong>query(</strong><em>\string</em> <strong>$sql</strong>, <em>mixed</em> <strong>$par=null</strong>)</strong> : <em>void</em> |
| public | <strong>rollback()</strong> : <em>void</em> |
| public | <strong>table(</strong><em>\string</em> <strong>$table</strong>, <em>\boolean</em> <strong>$detectRelations=true</strong>)</strong> : <em>void</em> |
| public | <strong>tables()</strong> : <em>void</em> |
| public | <strong>test()</strong> : <em>void</em> |

<hr />

### Interface: \vakata\database\ResultInterface

| Visibility | Function |
|:-----------|:---------|
| public | <strong>affected()</strong> : <em>void</em> |
| public | <strong>insertID()</strong> : <em>void</em> |
| public | <strong>toArray()</strong> : <em>void</em> |

*This class implements \Iterator, \Traversable, \Countable*

<hr />

### Interface: \vakata\database\StatementInterface

| Visibility | Function |
|:-----------|:---------|
| public | <strong>execute(</strong><em>array</em> <strong>$par=array()</strong>)</strong> : <em>void</em> |

<hr />

### Class: \vakata\database\driver\ibase\Driver

| Visibility | Function |
|:-----------|:---------|
| public | <strong>__construct(</strong><em>array</em> <strong>$connection</strong>)</strong> : <em>void</em> |
| public | <strong>__destruct()</strong> : <em>void</em> |
| public | <strong>begin()</strong> : <em>void</em> |
| public | <strong>commit()</strong> : <em>void</em> |
| public | <strong>isTransaction()</strong> : <em>bool</em> |
| public | <strong>prepare(</strong><em>\string</em> <strong>$sql</strong>)</strong> : <em>void</em> |
| public | <strong>rollback()</strong> : <em>void</em> |
| public | <strong>test()</strong> : <em>void</em> |
| protected | <strong>connect()</strong> : <em>void</em> |
| protected | <strong>disconnect()</strong> : <em>void</em> |

*This class extends [\vakata\database\DriverAbstract](#class-vakatadatabasedriverabstract-abstract)*

*This class implements [\vakata\database\DriverInterface](#interface-vakatadatabasedriverinterface)*

<hr />

### Class: \vakata\database\driver\ibase\Result

| Visibility | Function |
|:-----------|:---------|
| public | <strong>__construct(</strong><em>mixed</em> <strong>$result</strong>, <em>array</em> <strong>$data</strong>, <em>mixed</em> <strong>$driver</strong>)</strong> : <em>void</em> |
| public | <strong>__destruct()</strong> : <em>void</em> |
| public | <strong>affected()</strong> : <em>void</em> |
| public | <strong>count()</strong> : <em>void</em> |
| public | <strong>current()</strong> : <em>void</em> |
| public | <strong>insertID()</strong> : <em>void</em> |
| public | <strong>key()</strong> : <em>void</em> |
| public | <strong>next()</strong> : <em>void</em> |
| public | <strong>rewind()</strong> : <em>void</em> |
| public | <strong>toArray()</strong> : <em>void</em> |
| public | <strong>valid()</strong> : <em>void</em> |

*This class implements [\vakata\database\ResultInterface](#interface-vakatadatabaseresultinterface), \Countable, \Traversable, \Iterator*

<hr />

### Class: \vakata\database\driver\ibase\Statement

| Visibility | Function |
|:-----------|:---------|
| public | <strong>__construct(</strong><em>mixed</em> <strong>$statement</strong>, <em>mixed</em> <strong>$driver</strong>)</strong> : <em>void</em> |
| public | <strong>execute(</strong><em>array</em> <strong>$data=array()</strong>)</strong> : <em>void</em> |

*This class implements [\vakata\database\StatementInterface](#interface-vakatadatabasestatementinterface)*

<hr />

### Class: \vakata\database\driver\mysql\Driver

| Visibility | Function |
|:-----------|:---------|
| public | <strong>__construct(</strong><em>array</em> <strong>$connection</strong>)</strong> : <em>void</em> |
| public | <strong>__destruct()</strong> : <em>void</em> |
| public | <strong>begin()</strong> : <em>void</em> |
| public | <strong>commit()</strong> : <em>void</em> |
| public | <strong>prepare(</strong><em>\string</em> <strong>$sql</strong>)</strong> : <em>void</em> |
| public | <strong>rollback()</strong> : <em>void</em> |
| public | <strong>table(</strong><em>\string</em> <strong>$table</strong>, <em>\boolean</em> <strong>$detectRelations=true</strong>)</strong> : <em>void</em> |
| public | <strong>tables()</strong> : <em>void</em> |
| public | <strong>test()</strong> : <em>void</em> |
| protected | <strong>connect()</strong> : <em>void</em> |
| protected | <strong>disconnect()</strong> : <em>void</em> |

*This class extends [\vakata\database\DriverAbstract](#class-vakatadatabasedriverabstract-abstract)*

*This class implements [\vakata\database\DriverInterface](#interface-vakatadatabasedriverinterface)*

<hr />

### Class: \vakata\database\driver\mysql\Result

| Visibility | Function |
|:-----------|:---------|
| public | <strong>__construct(</strong><em>[\mysqli_stmt](http://php.net/manual/en/class.mysqli_stmt.php)</em> <strong>$statement</strong>)</strong> : <em>void</em> |
| public | <strong>affected()</strong> : <em>void</em> |
| public | <strong>count()</strong> : <em>void</em> |
| public | <strong>current()</strong> : <em>void</em> |
| public | <strong>insertID()</strong> : <em>void</em> |
| public | <strong>key()</strong> : <em>void</em> |
| public | <strong>next()</strong> : <em>void</em> |
| public | <strong>rewind()</strong> : <em>void</em> |
| public | <strong>toArray()</strong> : <em>void</em> |
| public | <strong>valid()</strong> : <em>void</em> |

*This class implements [\vakata\database\ResultInterface](#interface-vakatadatabaseresultinterface), \Countable, \Traversable, \Iterator*

<hr />

### Class: \vakata\database\driver\mysql\Statement

| Visibility | Function |
|:-----------|:---------|
| public | <strong>__construct(</strong><em>[\mysqli_stmt](http://php.net/manual/en/class.mysqli_stmt.php)</em> <strong>$statement</strong>)</strong> : <em>void</em> |
| public | <strong>__destruct()</strong> : <em>void</em> |
| public | <strong>execute(</strong><em>array</em> <strong>$data=array()</strong>)</strong> : <em>void</em> |

*This class implements [\vakata\database\StatementInterface](#interface-vakatadatabasestatementinterface)*

<hr />

### Class: \vakata\database\driver\odbc\Driver

| Visibility | Function |
|:-----------|:---------|
| public | <strong>__construct(</strong><em>array</em> <strong>$connection</strong>)</strong> : <em>void</em> |
| public | <strong>__destruct()</strong> : <em>void</em> |
| public | <strong>begin()</strong> : <em>void</em> |
| public | <strong>commit()</strong> : <em>void</em> |
| public | <strong>isTransaction()</strong> : <em>bool</em> |
| public | <strong>prepare(</strong><em>\string</em> <strong>$sql</strong>)</strong> : <em>void</em> |
| public | <strong>rollback()</strong> : <em>void</em> |
| public | <strong>test()</strong> : <em>void</em> |
| protected | <strong>connect()</strong> : <em>void</em> |
| protected | <strong>disconnect()</strong> : <em>void</em> |

*This class extends [\vakata\database\DriverAbstract](#class-vakatadatabasedriverabstract-abstract)*

*This class implements [\vakata\database\DriverInterface](#interface-vakatadatabasedriverinterface)*

<hr />

### Class: \vakata\database\driver\odbc\Result

| Visibility | Function |
|:-----------|:---------|
| public | <strong>__construct(</strong><em>mixed</em> <strong>$statement</strong>, <em>mixed</em> <strong>$data</strong>, <em>mixed</em> <strong>$iid</strong>, <em>mixed</em> <strong>$charIn=null</strong>, <em>mixed</em> <strong>$charOut=null</strong>)</strong> : <em>void</em> |
| public | <strong>__destruct()</strong> : <em>void</em> |
| public | <strong>affected()</strong> : <em>void</em> |
| public | <strong>count()</strong> : <em>void</em> |
| public | <strong>current()</strong> : <em>void</em> |
| public | <strong>insertID()</strong> : <em>void</em> |
| public | <strong>key()</strong> : <em>void</em> |
| public | <strong>next()</strong> : <em>void</em> |
| public | <strong>rewind()</strong> : <em>void</em> |
| public | <strong>toArray()</strong> : <em>void</em> |
| public | <strong>valid()</strong> : <em>void</em> |
| protected | <strong>convert(</strong><em>mixed</em> <strong>$data</strong>)</strong> : <em>void</em> |

*This class implements [\vakata\database\ResultInterface](#interface-vakatadatabaseresultinterface), \Countable, \Traversable, \Iterator*

<hr />

### Class: \vakata\database\driver\odbc\Statement

| Visibility | Function |
|:-----------|:---------|
| public | <strong>__construct(</strong><em>\string</em> <strong>$statement</strong>, <em>mixed</em> <strong>$driver</strong>, <em>mixed</em> <strong>$charIn=null</strong>, <em>mixed</em> <strong>$charOut=null</strong>)</strong> : <em>void</em> |
| public | <strong>execute(</strong><em>array</em> <strong>$data=array()</strong>)</strong> : <em>void</em> |
| protected | <strong>convert(</strong><em>mixed</em> <strong>$data</strong>)</strong> : <em>void</em> |

*This class implements [\vakata\database\StatementInterface](#interface-vakatadatabasestatementinterface)*

<hr />

### Class: \vakata\database\driver\oracle\Driver

| Visibility | Function |
|:-----------|:---------|
| public | <strong>__construct(</strong><em>array</em> <strong>$connection</strong>)</strong> : <em>void</em> |
| public | <strong>__destruct()</strong> : <em>void</em> |
| public | <strong>begin()</strong> : <em>void</em> |
| public | <strong>commit()</strong> : <em>void</em> |
| public | <strong>isTransaction()</strong> : <em>bool</em> |
| public | <strong>lob()</strong> : <em>void</em> |
| public | <strong>prepare(</strong><em>\string</em> <strong>$sql</strong>)</strong> : <em>void</em> |
| public | <strong>rollback()</strong> : <em>void</em> |
| public | <strong>table(</strong><em>\string</em> <strong>$table</strong>, <em>\boolean</em> <strong>$detectRelations=true</strong>)</strong> : <em>void</em> |
| public | <strong>tables()</strong> : <em>void</em> |
| public | <strong>test()</strong> : <em>void</em> |
| protected | <strong>connect()</strong> : <em>void</em> |
| protected | <strong>disconnect()</strong> : <em>void</em> |

*This class extends [\vakata\database\DriverAbstract](#class-vakatadatabasedriverabstract-abstract)*

*This class implements [\vakata\database\DriverInterface](#interface-vakatadatabasedriverinterface)*

<hr />

### Class: \vakata\database\driver\oracle\Result

| Visibility | Function |
|:-----------|:---------|
| public | <strong>__construct(</strong><em>mixed</em> <strong>$statement</strong>)</strong> : <em>void</em> |
| public | <strong>__destruct()</strong> : <em>void</em> |
| public | <strong>affected()</strong> : <em>void</em> |
| public | <strong>count()</strong> : <em>void</em> |
| public | <strong>current()</strong> : <em>void</em> |
| public | <strong>insertID()</strong> : <em>void</em> |
| public | <strong>key()</strong> : <em>void</em> |
| public | <strong>next()</strong> : <em>void</em> |
| public | <strong>rewind()</strong> : <em>void</em> |
| public | <strong>toArray()</strong> : <em>void</em> |
| public | <strong>valid()</strong> : <em>void</em> |

*This class implements [\vakata\database\ResultInterface](#interface-vakatadatabaseresultinterface), \Countable, \Traversable, \Iterator*

<hr />

### Class: \vakata\database\driver\oracle\Statement

| Visibility | Function |
|:-----------|:---------|
| public | <strong>__construct(</strong><em>mixed</em> <strong>$statement</strong>, <em>[\vakata\database\driver\oracle\Driver](#class-vakatadatabasedriveroracledriver)</em> <strong>$driver</strong>)</strong> : <em>void</em> |
| public | <strong>execute(</strong><em>array</em> <strong>$data=array()</strong>)</strong> : <em>void</em> |

*This class implements [\vakata\database\StatementInterface](#interface-vakatadatabasestatementinterface)*

<hr />

### Class: \vakata\database\driver\pdo\Driver

| Visibility | Function |
|:-----------|:---------|
| public | <strong>__construct(</strong><em>array</em> <strong>$connection</strong>)</strong> : <em>void</em> |
| public | <strong>__destruct()</strong> : <em>void</em> |
| public | <strong>begin()</strong> : <em>void</em> |
| public | <strong>commit()</strong> : <em>void</em> |
| public | <strong>prepare(</strong><em>\string</em> <strong>$sql</strong>)</strong> : <em>void</em> |
| public | <strong>rollback()</strong> : <em>void</em> |
| public | <strong>test()</strong> : <em>void</em> |
| protected | <strong>connect()</strong> : <em>void</em> |
| protected | <strong>disconnect()</strong> : <em>void</em> |

*This class extends [\vakata\database\DriverAbstract](#class-vakatadatabasedriverabstract-abstract)*

*This class implements [\vakata\database\DriverInterface](#interface-vakatadatabasedriverinterface)*

<hr />

### Class: \vakata\database\driver\pdo\Result

| Visibility | Function |
|:-----------|:---------|
| public | <strong>__construct(</strong><em>[\PDOStatement](http://php.net/manual/en/class.pdostatement.php)</em> <strong>$statement</strong>, <em>[\PDO](http://php.net/manual/en/class.pdo.php)</em> <strong>$driver</strong>)</strong> : <em>void</em> |
| public | <strong>affected()</strong> : <em>void</em> |
| public | <strong>count()</strong> : <em>void</em> |
| public | <strong>current()</strong> : <em>void</em> |
| public | <strong>insertID()</strong> : <em>void</em> |
| public | <strong>key()</strong> : <em>void</em> |
| public | <strong>next()</strong> : <em>void</em> |
| public | <strong>rewind()</strong> : <em>void</em> |
| public | <strong>toArray()</strong> : <em>void</em> |
| public | <strong>valid()</strong> : <em>void</em> |

*This class implements [\vakata\database\ResultInterface](#interface-vakatadatabaseresultinterface), \Countable, \Traversable, \Iterator*

<hr />

### Class: \vakata\database\driver\pdo\Statement

| Visibility | Function |
|:-----------|:---------|
| public | <strong>__construct(</strong><em>[\PDOStatement](http://php.net/manual/en/class.pdostatement.php)</em> <strong>$statement</strong>, <em>[\PDO](http://php.net/manual/en/class.pdo.php)</em> <strong>$driver</strong>)</strong> : <em>void</em> |
| public | <strong>execute(</strong><em>array</em> <strong>$data=array()</strong>)</strong> : <em>void</em> |

*This class implements [\vakata\database\StatementInterface](#interface-vakatadatabasestatementinterface)*

<hr />

### Class: \vakata\database\driver\postgre\Driver

| Visibility | Function |
|:-----------|:---------|
| public | <strong>__construct(</strong><em>array</em> <strong>$connection</strong>)</strong> : <em>void</em> |
| public | <strong>__destruct()</strong> : <em>void</em> |
| public | <strong>begin()</strong> : <em>void</em> |
| public | <strong>commit()</strong> : <em>void</em> |
| public | <strong>isTransaction()</strong> : <em>bool</em> |
| public | <strong>prepare(</strong><em>\string</em> <strong>$sql</strong>)</strong> : <em>void</em> |
| public | <strong>rollback()</strong> : <em>void</em> |
| public | <strong>test()</strong> : <em>void</em> |
| protected | <strong>connect()</strong> : <em>void</em> |
| protected | <strong>disconnect()</strong> : <em>void</em> |

*This class extends [\vakata\database\DriverAbstract](#class-vakatadatabasedriverabstract-abstract)*

*This class implements [\vakata\database\DriverInterface](#interface-vakatadatabasedriverinterface)*

<hr />

### Class: \vakata\database\driver\postgre\Result

| Visibility | Function |
|:-----------|:---------|
| public | <strong>__construct(</strong><em>mixed</em> <strong>$statement</strong>, <em>mixed</em> <strong>$iid</strong>, <em>mixed</em> <strong>$aff</strong>)</strong> : <em>void</em> |
| public | <strong>__destruct()</strong> : <em>void</em> |
| public | <strong>affected()</strong> : <em>void</em> |
| public | <strong>count()</strong> : <em>void</em> |
| public | <strong>current()</strong> : <em>void</em> |
| public | <strong>insertID()</strong> : <em>void</em> |
| public | <strong>key()</strong> : <em>void</em> |
| public | <strong>next()</strong> : <em>void</em> |
| public | <strong>rewind()</strong> : <em>void</em> |
| public | <strong>toArray()</strong> : <em>void</em> |
| public | <strong>valid()</strong> : <em>void</em> |

*This class implements [\vakata\database\ResultInterface](#interface-vakatadatabaseresultinterface), \Countable, \Traversable, \Iterator*

<hr />

### Class: \vakata\database\driver\postgre\Statement

| Visibility | Function |
|:-----------|:---------|
| public | <strong>__construct(</strong><em>\string</em> <strong>$statement</strong>, <em>mixed</em> <strong>$driver</strong>)</strong> : <em>void</em> |
| public | <strong>execute(</strong><em>array</em> <strong>$data=array()</strong>)</strong> : <em>void</em> |

*This class implements [\vakata\database\StatementInterface](#interface-vakatadatabasestatementinterface)*

<hr />

### Class: \vakata\database\driver\sqlite\Driver

| Visibility | Function |
|:-----------|:---------|
| public | <strong>__construct(</strong><em>array</em> <strong>$connection</strong>)</strong> : <em>void</em> |
| public | <strong>__destruct()</strong> : <em>void</em> |
| public | <strong>begin()</strong> : <em>void</em> |
| public | <strong>commit()</strong> : <em>void</em> |
| public | <strong>isTransaction()</strong> : <em>bool</em> |
| public | <strong>prepare(</strong><em>\string</em> <strong>$sql</strong>)</strong> : <em>void</em> |
| public | <strong>rollback()</strong> : <em>void</em> |
| public | <strong>test()</strong> : <em>void</em> |
| protected | <strong>connect()</strong> : <em>void</em> |
| protected | <strong>disconnect()</strong> : <em>void</em> |

*This class extends [\vakata\database\DriverAbstract](#class-vakatadatabasedriverabstract-abstract)*

*This class implements [\vakata\database\DriverInterface](#interface-vakatadatabasedriverinterface)*

<hr />

### Class: \vakata\database\driver\sqlite\Result

| Visibility | Function |
|:-----------|:---------|
| public | <strong>__construct(</strong><em>\SQLite3Result</em> <strong>$statement</strong>, <em>mixed</em> <strong>$iid</strong>, <em>mixed</em> <strong>$aff</strong>)</strong> : <em>void</em> |
| public | <strong>__destruct()</strong> : <em>void</em> |
| public | <strong>affected()</strong> : <em>void</em> |
| public | <strong>count()</strong> : <em>void</em> |
| public | <strong>current()</strong> : <em>void</em> |
| public | <strong>insertID()</strong> : <em>void</em> |
| public | <strong>key()</strong> : <em>void</em> |
| public | <strong>next()</strong> : <em>void</em> |
| public | <strong>rewind()</strong> : <em>void</em> |
| public | <strong>toArray()</strong> : <em>void</em> |
| public | <strong>valid()</strong> : <em>void</em> |

*This class implements [\vakata\database\ResultInterface](#interface-vakatadatabaseresultinterface), \Countable, \Traversable, \Iterator*

<hr />

### Class: \vakata\database\driver\sqlite\Statement

| Visibility | Function |
|:-----------|:---------|
| public | <strong>__construct(</strong><em>\SQLite3Stmt</em> <strong>$statement</strong>, <em>\SQLite3</em> <strong>$driver</strong>)</strong> : <em>void</em> |
| public | <strong>execute(</strong><em>array</em> <strong>$data=array()</strong>)</strong> : <em>void</em> |

*This class implements [\vakata\database\StatementInterface](#interface-vakatadatabasestatementinterface)*

<hr />

### Class: \vakata\database\schema\Table

> A table definition

| Visibility | Function |
|:-----------|:---------|
| public | <strong>__construct(</strong><em>\string</em> <strong>$name</strong>)</strong> : <em>void</em><br /><em>Create a new instance</em> |
| public | <strong>addColumn(</strong><em>\string</em> <strong>$column</strong>, <em>array</em> <strong>$definition=array()</strong>)</strong> : <em>\vakata\database\schema\self</em><br /><em>Add a column to the definition</em> |
| public | <strong>addColumns(</strong><em>array</em> <strong>$columns</strong>)</strong> : <em>\vakata\database\schema\self</em><br /><em>Add columns to the definition</em> |
| public | <strong>addRelation(</strong><em>[\vakata\database\schema\TableRelation](#class-vakatadatabaseschematablerelation)</em> <strong>$relation</strong>, <em>\string</em> <strong>$name=null</strong>)</strong> : <em>\vakata\database\schema\$this</em><br /><em>Create an advanced relation using the internal array format</em> |
| public | <strong>belongsTo(</strong><em>[\vakata\database\schema\Table](#class-vakatadatabaseschematable)</em> <strong>$toTable</strong>, <em>\string</em> <strong>$name=null</strong>, <em>string/array/null</em> <strong>$localColumn=null</strong>, <em>string/null</em> <strong>$sql=null</strong>, <em>array/array/null/array</em> <strong>$par=array()</strong>)</strong> : <em>\vakata\database\schema\$this</em><br /><em>Create a relation where each record belongs to another row in another table</em> |
| public | <strong>getColumn(</strong><em>string</em> <strong>$column</strong>)</strong> : <em>array/null the column details or `null` if the column does not exist</em><br /><em>Get a column definition</em> |
| public | <strong>getColumns()</strong> : <em>array array of strings, where each element is a column name</em><br /><em>Get all column names</em> |
| public | <strong>getComment()</strong> : <em>string the table comment</em><br /><em>Get the table comment</em> |
| public | <strong>getFullColumns()</strong> : <em>array key - value pairs, where each key is a column name and each value - the column data</em><br /><em>Get all column definitions</em> |
| public | <strong>getName()</strong> : <em>string the table name</em><br /><em>Get the table name</em> |
| public | <strong>getPrimaryKey()</strong> : <em>array array of column names</em><br /><em>Get the primary key columns</em> |
| public | <strong>getRelation(</strong><em>\string</em> <strong>$name</strong>)</strong> : <em>[\vakata\database\schema\TableRelation](#class-vakatadatabaseschematablerelation)/null the relation definition</em><br /><em>Get a relation by name</em> |
| public | <strong>getRelations()</strong> : <em>TableRelation[] the relation definitions</em><br /><em>Get all relation definitions</em> |
| public | <strong>hasMany(</strong><em>[\vakata\database\schema\Table](#class-vakatadatabaseschematable)</em> <strong>$toTable</strong>, <em>\string</em> <strong>$name=null</strong>, <em>string/array/null</em> <strong>$toTableColumn=null</strong>, <em>string/null</em> <strong>$sql=null</strong>, <em>array/array/null/array</em> <strong>$par=array()</strong>)</strong> : <em>\vakata\database\schema\$this</em><br /><em>Create a relation where each record has zero, one or more related rows in another table</em> |
| public | <strong>hasOne(</strong><em>[\vakata\database\schema\Table](#class-vakatadatabaseschematable)</em> <strong>$toTable</strong>, <em>\string</em> <strong>$name=null</strong>, <em>string/array/null</em> <strong>$toTableColumn=null</strong>, <em>\string</em> <strong>$sql=null</strong>, <em>array/array/null/array</em> <strong>$par=array()</strong>)</strong> : <em>\vakata\database\schema\$this</em><br /><em>Create a relation where each record has zero or one related rows in another table</em> |
| public | <strong>hasRelation(</strong><em>\string</em> <strong>$name</strong>)</strong> : <em>boolean does the relation exist</em><br /><em>Check if a named relation exists</em> |
| public | <strong>hasRelations()</strong> : <em>boolean</em><br /><em>Does the definition have related tables</em> |
| public | <strong>manyToMany(</strong><em>[\vakata\database\schema\Table](#class-vakatadatabaseschematable)</em> <strong>$toTable</strong>, <em>[\vakata\database\schema\Table](#class-vakatadatabaseschematable)</em> <strong>$pivot</strong>, <em>string/null</em> <strong>$name=null</strong>, <em>string/array/null</em> <strong>$toTableColumn=null</strong>, <em>string/array/null</em> <strong>$localColumn=null</strong>)</strong> : <em>\vakata\database\schema\$this</em><br /><em>Create a relation where each record has many linked records in another table but using a liking table</em> |
| public | <strong>renameRelation(</strong><em>\string</em> <strong>$name</strong>, <em>\string</em> <strong>$new</strong>)</strong> : <em>TableRelation the relation definition</em><br /><em>Rename a relation</em> |
| public | <strong>setComment(</strong><em>\string</em> <strong>$comment</strong>)</strong> : <em>\vakata\database\schema\$this</em><br /><em>Set the table comment</em> |
| public | <strong>setPrimaryKey(</strong><em>array/string</em> <strong>$column</strong>)</strong> : <em>\vakata\database\schema\self</em><br /><em>Set the primary key</em> |
| public | <strong>toLowerCase()</strong> : <em>void</em> |

<hr />

### Class: \vakata\database\schema\TableColumn

> A column definition

| Visibility | Function |
|:-----------|:---------|
| public | <strong>__construct(</strong><em>\string</em> <strong>$name</strong>)</strong> : <em>void</em> |
| public static | <strong>fromArray(</strong><em>\string</em> <strong>$name</strong>, <em>array</em> <strong>$data=array()</strong>)</strong> : <em>void</em> |
| public | <strong>getBasicType()</strong> : <em>mixed</em> |
| public | <strong>getComment()</strong> : <em>mixed</em> |
| public | <strong>getDefault()</strong> : <em>mixed</em> |
| public | <strong>getLength()</strong> : <em>mixed</em> |
| public | <strong>getName()</strong> : <em>mixed</em> |
| public | <strong>getType()</strong> : <em>mixed</em> |
| public | <strong>getValues()</strong> : <em>mixed</em> |
| public | <strong>hasLength()</strong> : <em>bool</em> |
| public | <strong>isNullable()</strong> : <em>bool</em> |
| public | <strong>setComment(</strong><em>\string</em> <strong>$comment</strong>)</strong> : <em>void</em> |
| public | <strong>setDefault(</strong><em>mixed</em> <strong>$default=null</strong>)</strong> : <em>void</em> |
| public | <strong>setLength(</strong><em>mixed</em> <strong>$length</strong>)</strong> : <em>void</em> |
| public | <strong>setName(</strong><em>\string</em> <strong>$name</strong>)</strong> : <em>void</em> |
| public | <strong>setNullable(</strong><em>\boolean</em> <strong>$nullable</strong>)</strong> : <em>void</em> |
| public | <strong>setType(</strong><em>\string</em> <strong>$type</strong>)</strong> : <em>void</em> |
| public | <strong>setValues(</strong><em>array</em> <strong>$values</strong>)</strong> : <em>void</em> |

<hr />

### Class: \vakata\database\schema\TableQuery

> A database query class

| Visibility | Function |
|:-----------|:---------|
| public | <strong>__call(</strong><em>mixed</em> <strong>$name</strong>, <em>mixed</em> <strong>$data</strong>)</strong> : <em>void</em> |
| public | <strong>__clone()</strong> : <em>void</em> |
| public | <strong>__construct(</strong><em>[\vakata\database\DBInterface](#interface-vakatadatabasedbinterface)</em> <strong>$db</strong>, <em>[\vakata\database\schema\Table](#class-vakatadatabaseschematable)/string</em> <strong>$table</strong>)</strong> : <em>void</em><br /><em>Create an instance</em> |
| public | <strong>all(</strong><em>array</em> <strong>$criteria</strong>)</strong> : <em>\vakata\database\schema\$this</em><br /><em>Filter the results matching all of the criteria</em> |
| public | <strong>any(</strong><em>array</em> <strong>$criteria</strong>)</strong> : <em>\vakata\database\schema\$this</em><br /><em>Filter the results matching any of the criteria</em> |
| public | <strong>collection(</strong><em>array</em> <strong>$fields=null</strong>)</strong> : <em>void</em> |
| public | <strong>columns(</strong><em>array</em> <strong>$fields</strong>)</strong> : <em>\vakata\database\schema\$this</em><br /><em>Specify which columns to fetch (be default all table columns are fetched)</em> |
| public | <strong>count()</strong> : <em>int the total number of records (does not respect pagination)</em><br /><em>Get the number of records</em> |
| public | <strong>delete()</strong> : <em>int the number of deleted rows</em><br /><em>Delete the filtered rows from the DB</em> |
| public | <strong>filter(</strong><em>\string</em> <strong>$column</strong>, <em>mixed</em> <strong>$value</strong>, <em>\boolean</em> <strong>$negate=false</strong>)</strong> : <em>\vakata\database\schema\$this</em><br /><em>Filter the results by a column and a value</em> |
| public | <strong>getDefinition()</strong> : <em>Table the definition</em><br /><em>Get the table definition of the queried table</em> |
| public | <strong>getIterator()</strong> : <em>mixed</em> |
| public | <strong>group(</strong><em>string/array</em> <strong>$column</strong>)</strong> : <em>\vakata\database\schema\$this</em><br /><em>Group by a column (or columns)</em> |
| public | <strong>groupBy(</strong><em>\string</em> <strong>$sql</strong>, <em>array</em> <strong>$params=array()</strong>)</strong> : <em>\vakata\database\schema\$this</em><br /><em>Apply advanced grouping</em> |
| public | <strong>having(</strong><em>\string</em> <strong>$sql</strong>, <em>array</em> <strong>$params=array()</strong>)</strong> : <em>\vakata\database\schema\$this</em><br /><em>Apply an advanced HAVING filter (can be called multiple times)</em> |
| public | <strong>ids()</strong> : <em>void</em> |
| public | <strong>insert(</strong><em>array</em> <strong>$data</strong>)</strong> : <em>array the inserted ID where keys are column names and values are column values</em><br /><em>Insert a new row in the table</em> |
| public | <strong>iterator(</strong><em>array/null/array</em> <strong>$fields=null</strong>)</strong> : <em>TableQueryIterator the query result as an iterator</em><br /><em>Perform the actual fetch</em> |
| public | <strong>join(</strong><em>[\vakata\database\schema\Table](#class-vakatadatabaseschematable)/string</em> <strong>$table</strong>, <em>array</em> <strong>$fields</strong>, <em>\string</em> <strong>$name=null</strong>, <em>\boolean</em> <strong>$multiple=true</strong>)</strong> : <em>\vakata\database\schema\$this</em><br /><em>Join a table to the query (no need to do this for relations defined with foreign keys)</em> |
| public | <strong>limit(</strong><em>int/\integer</em> <strong>$limit</strong>, <em>int/\integer</em> <strong>$offset</strong>, <em>\boolean</em> <strong>$limitOnMainTable=false</strong>)</strong> : <em>\vakata\database\schema\$this</em><br /><em>Apply an advanced limit</em> |
| public | <strong>offsetExists(</strong><em>mixed</em> <strong>$offset</strong>)</strong> : <em>void</em> |
| public | <strong>offsetGet(</strong><em>mixed</em> <strong>$offset</strong>)</strong> : <em>void</em> |
| public | <strong>offsetSet(</strong><em>mixed</em> <strong>$offset</strong>, <em>mixed</em> <strong>$value</strong>)</strong> : <em>void</em> |
| public | <strong>offsetUnset(</strong><em>mixed</em> <strong>$offset</strong>)</strong> : <em>void</em> |
| public | <strong>order(</strong><em>\string</em> <strong>$sql</strong>, <em>array</em> <strong>$params=array()</strong>)</strong> : <em>\vakata\database\schema\$this</em><br /><em>Apply advanced sorting</em> |
| public | <strong>paginate(</strong><em>\integer</em> <strong>$page=1</strong>, <em>\integer</em> <strong>$perPage=25</strong>)</strong> : <em>\vakata\database\schema\$this</em><br /><em>Get a part of the data</em> |
| public | <strong>reset()</strong> : <em>\vakata\database\schema\$this</em><br /><em>Remove all filters, sorting, etc</em> |
| public | <strong>select(</strong><em>array/null/array</em> <strong>$fields=null</strong>)</strong> : <em>array the query result as an array</em><br /><em>Perform the actual fetch</em> |
| public | <strong>sort(</strong><em>\string</em> <strong>$column</strong>, <em>\boolean</em> <strong>$desc=false</strong>)</strong> : <em>\vakata\database\schema\$this</em><br /><em>Sort by a column</em> |
| public | <strong>update(</strong><em>array</em> <strong>$data</strong>)</strong> : <em>int the number of affected rows</em><br /><em>Update the filtered rows with new data</em> |
| public | <strong>where(</strong><em>\string</em> <strong>$sql</strong>, <em>array</em> <strong>$params=array()</strong>)</strong> : <em>\vakata\database\schema\$this</em><br /><em>Apply an advanced filter (can be called multiple times)</em> |
| public | <strong>with(</strong><em>\string</em> <strong>$relation</strong>)</strong> : <em>\vakata\database\schema\$this</em><br /><em>Solve the n+1 queries problem by prefetching a relation by name</em> |
| protected | <strong>filterSQL(</strong><em>\string</em> <strong>$column</strong>, <em>mixed</em> <strong>$value</strong>, <em>\boolean</em> <strong>$negate=false</strong>)</strong> : <em>void</em> |
| protected | <strong>getColumn(</strong><em>mixed</em> <strong>$column</strong>)</strong> : <em>mixed</em> |
| protected | <strong>normalizeValue(</strong><em>[\vakata\database\schema\TableColumn](#class-vakatadatabaseschematablecolumn)</em> <strong>$col</strong>, <em>mixed</em> <strong>$value</strong>)</strong> : <em>void</em> |

*This class implements \IteratorAggregate, \Traversable, \ArrayAccess, \Countable*

<hr />

### Class: \vakata\database\schema\TableQueryIterator

> A table query iterator

| Visibility | Function |
|:-----------|:---------|
| public | <strong>__construct(</strong><em>\vakata\collection\Collection</em> <strong>$result</strong>, <em>array</em> <strong>$pkey</strong>, <em>array</em> <strong>$relations=array()</strong>, <em>array</em> <strong>$aliases=array()</strong>)</strong> : <em>void</em> |
| public | <strong>current()</strong> : <em>void</em> |
| public | <strong>key()</strong> : <em>void</em> |
| public | <strong>next()</strong> : <em>void</em> |
| public | <strong>offsetExists(</strong><em>mixed</em> <strong>$offset</strong>)</strong> : <em>void</em> |
| public | <strong>offsetGet(</strong><em>mixed</em> <strong>$offset</strong>)</strong> : <em>void</em> |
| public | <strong>offsetSet(</strong><em>mixed</em> <strong>$offset</strong>, <em>mixed</em> <strong>$value</strong>)</strong> : <em>void</em> |
| public | <strong>offsetUnset(</strong><em>mixed</em> <strong>$offset</strong>)</strong> : <em>void</em> |
| public | <strong>rewind()</strong> : <em>void</em> |
| public | <strong>valid()</strong> : <em>void</em> |
| protected | <strong>values(</strong><em>array</em> <strong>$data</strong>)</strong> : <em>void</em> |

*This class implements \Iterator, \Traversable, \ArrayAccess*

<hr />

### Class: \vakata\database\schema\TableRelation

> A table definition

| Visibility | Function |
|:-----------|:---------|
| public | <strong>__construct(</strong><em>\string</em> <strong>$name</strong>, <em>[\vakata\database\schema\Table](#class-vakatadatabaseschematable)</em> <strong>$table</strong>, <em>array/null/array</em> <strong>$keymap</strong>, <em>\boolean</em> <strong>$many=false</strong>, <em>[\vakata\database\schema\Table](#class-vakatadatabaseschematable)/null/[\vakata\database\schema\Table](#class-vakatadatabaseschematable)</em> <strong>$pivot=null</strong>, <em>array</em> <strong>$pivot_keymap=null</strong>, <em>\string</em> <strong>$sql=null</strong>, <em>array</em> <strong>$par=null</strong>)</strong> : <em>void</em><br /><em>Create a new instance</em> |

