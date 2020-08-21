# command-line-parser
A flexible command line parser inspired by PHP getopt parser. Supports short and long options, compact and inline options.

##### Installation

You can install the package via composer:

```
composer require djiele/command-line-parser dev-master
```
##### Usage
```
./demo.php -dw ./logs --script ./bin/run.php --dont-watch ./run.pid -k9 -ak -- inline option
```
```php
require_once __DIR__'./vendor/autoload.php';
use Djiele\Script\CommandLineParser;
$opts = [
	'dw' => 'dont-watch:', // required option
	's:' => 'script:', // required option
	'k' => 'kill-signal::', // optional
	'ak' => 'auto-kill', // === true if set
	'h' => 'help' // === true if set
];
$args = new CommandLineParser($opts);
var_export($args->parse());
```
```
returns:
array (
  'dont-watch' =>
  array (
    0 => './logs',
    1 => './run.pid',
  ),
  'script' => './bin/run.php',
  'kill-signal' => '9',
  'auto-kill' => true,
  '<inline>' => 'inline option',
)
```