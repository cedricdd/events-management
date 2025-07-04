composer install

copy .env.example .env

php artisan key:generate

php artisan migrate --seed

The API documentation can be found at "/docs", generated with scribe https://scribe.knuckles.wtf/laravel/

You can login with:  
john@basic.com // password -- Basic user  
john@organizer.com // password -- Organizer, can create, edit, ... events  
john@admin.com // password -- Administrator  
