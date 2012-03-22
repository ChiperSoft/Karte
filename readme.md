#Karte

Created and Copyright 2012 by Jarvis Badgley, chiper at chipersoft dot com.

Karte is a Clean URL routing class for PHP 5.3 and later, built on four principles:

1. The forward-slash is an argument delimiter, not a hierarchy
2. The route that matches the most arguments wins.
3. Every route is a file
4. Any argument can have a paired value

Lets take the following URL as an example:

    http://localhost/alpha/beta/delta/gamma
    
The above request is parsed into a list of arguments.  Karte then works backwards, scanning your routes folder for the first file that matches the arguments list (delimiting the file names with periods).  In this case it will scan for the following files in order:

1. `alpha.beta.delta.gamma.php`
2. `alpha.beta.delta.php`
3. `alpha.beta.php`
4. `alpha.php`
5. `_catchall.php`
6. `404.php`

*Note that the last two routes can be changed using the `setNotFound()` and `setCatchAll()` functions.*

All arguments that follow the matched route name are passed to the route when it is called, indexed by their order.  So if the route matched on `alpha.beta.php`, the route would received the following arguments array:

    Array
    (
        [0] => delta
        [1] => gamma
    )
    
Now lets look at principle #4, every argument can have a paired value.  Examine the following url:

    http://localhost/alpha=1/beta=foo/delta=0/gamma/

This url will still match to the same routes, but the arguments array will now look like this:

    Array
    (
        [0] => gamma
        [alpha] => 1
        [beta] => foo
        [delta] => 0
    )


### `indexPairedArguments(boolean)`

By default, Karte will filter out any any value paired arguments from the integer indexes.  This is why in the above example, the arguments list starts with "gamma" instead of "delta".  If this function is called before parsing the url

    Array
    (
        [0] => delta
        [1] => gamma
        [alpha] => 1
        [beta] => foo
        [gamma] => 0
    )

### `pairAllArguments(boolean)`

When used in conjunction with `indexPairedArguments()`, `pairAllArguments()` will result in the arguments collection containing every argument as a keyed value, even if the value is not defined:

    Array
    (
        [0] => delta
        [1] => gamma
        [alpha] => 1
        [beta] => foo
        [delta] => 0
        [gamma] => 
    )

On it's own the `pairAllArguments` function will result in an arguments array containing _only_ arguments as keys to their paired values, omitting the indexed values.

    Array
    (
        [alpha] => 1
        [beta] => foo
        [delta] => 0
        [gamma] => 
    )


###Site Index

The url `/` or `http://my.domain.com/` is interpreted by Karte as a call to the site index.  Karte will attempt to route to "index" (call `setSiteIndex()` to change this value) before passing to the file not found route ("404").  The site index cannot receive arguments unless the url begins with the site index name.  Example: 

    http://my.domain.com/index/foo=bar


##Usage

###Namespacing

The Karte base routing class is namespaced as `\ChiperSoft\Karte\Router` following the PSR-0 standard.  For the purposes of these examples we've imported the `Router` class under the alias of `Karte`, like so:

```php
use \ChiperSoft\Karte\Router as Karte;
```

If your server is configured with APC, you may wish to use the `CachedRouter` subclass in place of `Router`, which will catalog your routes folder and save the catalog in APC for faster access. This significantly reduces the number of I/O operations performed when routing a url.

###Examples

The simplest way to run Karte is using the static initialization function, like so:

```php
Karte::Execute('/path/to/routes', true);
```

This will create a Karte object and tell it to parse and execute the page request URL.  You can also chain off of this call:

```php
Karte::Execute('/path/to/routes')
    ->indexPairedArguments()
    ->parseCurrentRequest()
    ->run();
```

If you prefer the long-form method:

```php
$kroute = new Karte();
$kroute->setRoutesPath('/path/to/routes');
$kroute->parseURL('/foo=2/bar');
$kroute->run();
```

After calling `parseURL`, the matched route and defined arguments can be accessed with the following member variables:

- `route_name`: Contains the filename of the route, minus the PHP extension
- `route_file`: Contains the full path to the route file.
- `arguments`: Contains the arguments collection.

When `run` is called, the matched route is executed with two variables in scope:

- `$route`: The Karte instance that triggered the execution
- `$arguments`: The arguments collection.

##Server Forwarding

In a standard configuration, Apache and Nginx will only call the file containing the Karte code if you directly access it.  The following configurations expect that `index.php` contains the Karte initialization code.

###Apache

Place the following into the virtualhost Directory definition, or into a `.htaccess` file at the root of your website.

    <IfModule mod_rewrite.c>
    RewriteEngine on
    RewriteBase /
    RewriteRule ^$ index.php	[L]

    # forward all requests to /
    RewriteRule ^$ index.php [L]
            
    # send all other requests to index.php
    RewriteCond %{REQUEST_FILENAME} !-f [OR]
    RewriteRule ^/?.*$ index.php [L]
    </IfModule>
    
Note that Apache must be configured with mod_rewrite for this to work.
    
###Nginx

Place the following into the virtualhost `server` block.

    location = / {
        rewrite ^ /index.php last;
    }
    
    location / {
        if (!-e $request_filename) {
            rewrite ^ /index.php last;
        }
    }
    
Note that this configuration assumes that you have PHP configured through a FastCGI interface in the traditional method.  It may also be necessary to add the following to your PHP location directive:

    try_files $uri $uri/ $uri/index.php /index.php;


##URL Rewriting

After the url has been parsed you can alter the url's arguments by calling the `rewriteURL` function, passing the new values as an array.  Any values defined as `false` are removed from the url, `null` values become simple parameters.

A route mapped from `http://localhost/alpha=1/beta=foo/delta=0/gamma/` could be rewritten like so:

```php
$newurl = $route->rewriteURL(array(
	'alpha'=>'100',
	'beta'=>false,
	'delta'=>'',
	'gamma'=>null,
	'lima'=>2
));
```

This results in `$newurl` containing `/alpha=100/delta=/gamma/lima=2`

##License

Karte is released under an MIT license.  No attribution is required.  For details see the enclosed LICENSE file.




