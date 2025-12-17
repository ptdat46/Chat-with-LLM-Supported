
## Laravel Reverb: Real-Time Chat App

How to build real-time chat app using Laravel Reverb.

Reverb is a first-party WebSocket server for Laravel applications, bringing real-time communication between client and server.

In this [freeCodeCamp article](https://www.freecodecamp.org/news/laravel-reverb-realtime-chat-app/) you will learn how to build real-time chat application using Laravel Reverb. With that you can easily implement WebSocket communications between your backend and frontend. As a frontend technology, you  can use anything you want, but in this case we'll use React.js with the Vite.js build tool.

<img src="https://i.imgur.com/kIRE69i.gif" style="width: 100%;">



#### Installation:

- **Pre-requisites**:
    - PHP >= 8.2
    - Composer
    - MySQL >= 5.7
    - Node.js >= 20

- Clone the repository, or download the zip file and extract it.
```shell
git clone git@github.com:boolfalse/laravel-reverb-react-chat.git && cd laravel-reverb-react-chat/
```

- Copy the `.env.example` file to `.env`:
```shell
cp .env.example .env
```

- Install the dependencies.
```shell
composer install
```

- Generate the application key.
```shell
php artisan key:generate
```

- If you're planning to run the app on localhost using the default port, you can leave the `APP_URL` as is in the `.env` file:
```dotenv
APP_URL=http://localhost
```

- If you are planning to run the app on a different port or domain, set the `APP_URL` in the `.env` file:
```dotenv
# a sample
APP_URL=http://virtual-host.site
```

- Create a MySQL database and set the database credentials in the `.env` file:
```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE="<database_name>"
DB_USERNAME="<username>"
DB_PASSWORD="<password>"
```

- Setup Reverb credentials in the `.env` file:
  <br/>**NOTE:** You may already have the credentials ready if you have setup the app step-by-step as described in the [article](https://www.freecodecamp.org/news/laravel-reverb-realtime-chat-app/#heading-how-to-install-laravel-reverb).
  If you don't have them, you can just use the example credentials below or change them to your own. But make sure they are set correctly.
```dotenv
BROADCAST_CONNECTION=reverb

###

REVERB_APP_ID=123456
REVERB_APP_KEY=key
REVERB_APP_SECRET=secret
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

- Optimize the application cache.
  <br/>**NOTE:** This is important to run after setting the `.env` file, so that the app can use the last updated configuration.
```shell
php artisan optimize
```

- Run the migrations.
```shell
php artisan migrate:fresh
```

- Install the NPM dependencies.
```shell
npm install
```

- Build the assets.
```shell
npm run build
```

- **[Optional]** For development, run below command to watch the assets for changes.
```shell
npm run dev
```

- Start WebSocket server.
```shell
php artisan reverb:start
```

- Start listening to Queue jobs.
```shell
php artisan queue:listen
```

- If you want to run the app on localhost, you just run the built-in PHP server:
```shell
php artisan serve
```

In the screenshot below is the case when all commands are running: `npm run dev` for watching assets, `php artisan queue:listen` for listening to queue jobs, `php artisan reverb:start` for starting the WebSocket server, and `php artisan serve` for running the app on localhost.

<img src="https://i.imgur.com/TN0jtAM.png" style="width: 100%;">

- If you are planning to use a custom domain (like `virtual-host.site`), make sure you have setup `APP_URL` in the `.env` file correctly as mentioned a few steps above.

In the screenshot below is the case when two only commands are running: `php artisan queue:listen` and `php artisan reverb:start`.
<br/>
There was no need to run `serve` command, because the app is running on a custom domain, which is already configured in the web server (Apache in this case).
<br/>
There was no need to run `npm run dev` command, because the assets were already built with `npm run build` command.

<img src="https://i.imgur.com/6AwfKEB.png" style="width: 100%;">

- Open the application in two different browser windows (or with normal and incognito mode), register two or more users, and start chatting with each other.



#### Author:

- [BoolFalse](https://boolfalse.com/)
