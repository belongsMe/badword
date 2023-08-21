TextFilter
=======

Installation
------------

Use composer to manage your dependencies and download `TextFilter\Badword`:

```bash
composer require TextFilter/Badword
```

Ensure that you have installed the Redis extension


Example
----------------------------
```php
use TextFilter\Badword\Badword;


$config = [
    'host'=>'127.0.0.1',
    'port'=>'6379',
    'password'=>'',
    'select'=>0,
    'expire'=>3600,
];
$filter = new Badword($config);
//$filter->loadData(['枪手','电击枪']);
$filter->setDictFile('./file.txt');
$filter->loadDataFromFile(true);
//$filter->match_badword("枪手",0);
$filter->filter("枪手",'*', 0);


```


