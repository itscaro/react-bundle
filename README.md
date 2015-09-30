# Introduction

*Run Symfony apps under [React-PHP](https://github.com/reactphp/react).*

This module adds enable the usage of ReactPHP server for Symfony.

No configuration is needed. Follow the **Installation** instructions and read **Usage** section to know how to start using ReactPHP with your Symfony APP.

# Installation

### Composer

To install the bundle through Composer, run the following command in console at your project base path:

```
php composer.phar require itscaro/react-bundle
```

### Register bundle

Then register the new bundle in your AppKernel.

```php
<?php
    
    // #app/AppKernel.php
    $bundles = array(
        ...
        new Itscaro\ReactBundle\JogaramReactBundle(),
        ...
    );
    
```

# Usage

To start using ReactPHP with Symfony, open console, go to your project root path and execute the following command:

```
php app/console react:server:run --standalone
```

### Available options

**--host=127.0.0.1** Selects IP to run server at. Defaults to 127.0.0.1.
**--port=1337** Selects port to run server at, use comma to separate ports. Defaults to 1337.
**--standalone** If passed, React server will serve static files directly. (Use this if you don`t have Apache or Nginx running in you local machine. Static file serving is not designed for production environments)
**--cache** If passed, class loader will be enabled.
**--apc** If passed, APC class loader will be enabled. This option requires **--cache** option.
**--sessionleader** Available for background server, promote the forked process to be session leader

### Background server

* To start the server execute the following:

```
php app/console react:server:start
```

* To stop the server, run:

```
php app/console react:server:stop
```

Note: If host and port are specified when starting the server, they must be specified for the stop command.

* To restart the server:

```
php app/console react:server:restart
```

# Using with web server as load balancer

### Apache

```
<Proxy balancer://mycluster>
BalancerMember http://<ip:port of ReactPHP server>
BalancerMember http://<ip:port of ReactPHP server>
</Proxy>
ProxyPass / balancer://mycluster
```

[Apache Document](http://httpd.apache.org/docs/2.4/mod/mod_proxy_balancer.html)

### Nginx

```
http {
    upstream mycluster {
        server <ip:port of ReactPHP server>;
        server <ip:port of ReactPHP server>;
    }

    server {
        listen 80;

        location / {
            proxy_pass http://mycluster;
        }
    }
}
```

[Nginx Document](http://nginx.org/en/docs/http/load_balancing.html)

##### Credits

[Blackshawk](https://github.com/Blackshawk/SymfonyReactorBundle)
