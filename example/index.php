<?php 

include '../library/ChiperSoft/Karte/Router.php';

use \ChiperSoft\Karte\Router as Karte;

$o = new Karte(__DIR__.'/routes');
//$o->indexPairedArguments();
//$o->pairAllArguments();
$o->parseURL('http://localhost/alpha/beta/delta=0/gamma/?foo=bar');
$o->run();
echo $o->rewriteURL(array(
	'alpha'=>'100',
	'beta'=>false,
	'delta'=>'',
	'gamma'=>null,
	'lima'=>2
));
