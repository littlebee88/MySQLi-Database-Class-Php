To utilize this class, first import Mysqldbi.php into your project, and require it.

```php
require_once('Mysqlidb.php');
```

After that, create a new instance of the class.

```php
$db = new Mysqlidb('host', 'username', 'password', 'databaseName');
```

Next, prepare your data, and call the necessary methods. 

### Insert Query

```php
$data = array(
	'login' => 'admin',
	'firstName' => 'John',
	'lastName' => 'Doe',
);

$id = $db->insert('users', $data)
if($id)
    echo 'user was created. Id='.$id;
```

### Select Query

```php
$users = $db->get('users'); //contains an array of user objects for all users
$users = $db->output('array')->get('users'); //contains an array of associative arrays for all users
$users = $db->limit(10)->get('users'); //contains an array 10 users
$users = $db->offset(10)->limit(10)->get('users'); //contains an array 10 users 11-20
```

or select with custom columns set. Functions also could be used

```php
$stats = $db->getOne("users", "sum(id), count(*) as cnt");
echo "total ".$stats->cnt. "users found";
```

or even better just get the count as a field

```php
$cnt = $db->getVar("users", "count(id) as cnt");
echo $cnt
```

or get a single users name by id

```php
$db->where("id", 1);
$username = $db->getVar("users", "name");
echo $username;
```

```php
$cols = array("id, name, email");
$users = $db->output('array')->get("users", $cols);
foreach ($users as $user) { 
    print_r($user);
}
```

or select just one row

```php
$db->where("id", 1);
$user = $db->getOne("users");
echo $user->id;
```

or select just one column

```php
$user = $db->getCol("users", "name");
foreach ($users as $username) {
    echo $username;
}
```

### Update Query
```php
$data = array (
	'firstName' => 'Bobby',
	'lastName' => 'Tables'
);
$db->where('id', 1);
if($db->update('users', $data)) echo 'successfully updated'; 
```

### Delete Query
```php
$db->where('id', 1);
if($db->delete('posts')) echo 'successfully deleted'; 
```

### Generic Query Method
```php
$users = $db->output('array')->query('SELECT * from users');
foreach ($users as $user) {
    print_r ($user);
}
```

### Raw Query Method
```php
$params = array(1, 'admin');
$users = $db->rawQuery("SELECT id, firstName, lastName FROM users WHERE id = ? AND login = ?", $params);
print_r($users); // contains array of returned rows

// will handle any SQL query
$params = array(10, 1, 10, 11, 2, 10);
$resutls = $db->rawQuery("(SELECT a FROM t1 WHERE a = ? AND B = ? ORDER BY a LIMIT ?) UNION(SELECT a FROM t2 WHERE a = ? AND B = ? ORDER BY a LIMIT ?)", $params);
print_r($results); // contains array of returned rows
```


### Where Method
This method allows you to specify the parameters of the query.

Regular == operator:
```php
$db->where('id', 1);
$db->where('login', 'admin');
$results = $db->get('users');
// Gives: SELECT * FROM users WHERE id=1 AND login='admin';
```

Custom Operators:
```php
$db->where('id', array('>=' => 50));
$results = $db->get('users');
// Gives: SELECT * FROM users WHERE id >= 50;
```

BETWEEN:
```php
$db->where('id', array('between' => array(4, 20) ) );
$results = $db->get('users');
// Gives: SELECT * FROM users WHERE id BETWEEN 4 AND 20
```

IN:
```php
$db->where('id', array( 'in' => array(1, 5, 27, -1, 'd') ) );
$results = $db->get('users');
// Gives: SELECT * FROM users WHERE id IN (1, 5, 27, -1, 'd');
```

Optionally you can use method chaining to call where multiple times without referencing your object over an over:

```php
$results = $db
	->where('id', 1)
	->where('title', 'MyTitle')
	->get('users');
```

### Ordering method
```php
$db->orderBy("id","asc");
$db->orderBy("login","Desc");
$results = $db->get('users');
// Gives: SELECT * FROM users ORDER BY id ASC,login DESC;
```

### Grouping method
```php
$db->groupBy("name");
$results = $db->get('users');
// Gives: SELECT * FROM users GROUP BY name;
```

Join table products with table users with LEFT JOIN by tenantID
### JOIN method
```php
$db->join("users u", "p.tenantID=u.tenantID", "LEFT");
$db->where("u.id", 6);
$products = $db->get ("products p", "u.name, p.productName");
print_r ($products);
```

### Limit method
```php
$db->limit(10);
$results = $db->get('users');
// Gives: SELECT * FROM users LIMIT 10;
```

### Offset method plus Limit method
```php
$db->limit(10);
$results = $db->get('users');
// Gives: SELECT * FROM users LIMIT 10, 10;
```

### Transaction Wrappers
```php
$this->beginTransaction();

$data = array (
	'firstName' => 'Bobby',
	'lastName' => 'Tables'
);
$db->where('id', 1);
$res = $db->insert('users', $data)

if(!$res) {
    //rollback
    echo 'inserted failed';
    $this->rollbackTransaction();
} else {
    //end
    echo 'successfully inserted';
    $this->endTransaction();
}
```
