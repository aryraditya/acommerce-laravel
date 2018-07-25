# aCommerce API Helper for Laravel
This is library for [aCommerce API](https://url.com)

## Installation
First, install using `composer`

    $ composer require aryraditya/acommerce-laravel

After installation is finish, open `config/app.php` to add the ServiceProvider bellow to `providers` section :

    aryraditya\aCommerceLaravel\aCommerceServiceProvider::class

Publish configuration, the command bellow will copy a config to your project directory `config/acommerce.php` and then you need to set the variable on it.

    $ php artisan vendor:publish --provider="aryraditya\aCommerceLaravel\aCommerceServiceProvider"


## Usage
soon