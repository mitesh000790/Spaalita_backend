# SpaaLita Backend (Laravel + MySQL)

REST API for the SpaaLita website and admin panel. It is a conversion of the
original Node.js / Express / MongoDB backend: every endpoint, request field,
status code, message and JSON response shape is kept, so the existing
frontends work without changes (only the API base URL may change).

- Laravel 12, PHP 8.2+, MySQL 5.7+ / MariaDB 10.3+
- JWT authentication (HS256), Stripe payments, image uploads
- Record ids stay MongoDB-style 24-character strings in `_id`, and existing
  MongoDB data can be imported with the same ids (`php artisan mongo:import`)

## Requirements

| | |
|---|---|
| PHP | **8.2 or newer** with `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `curl`, `zip` (XAMPP builds include them) |
| Composer | 2.x |
| Database | MySQL 5.7+ or MariaDB 10.3+ (XAMPP's MariaDB works) |

> **XAMPP's PHP is too old.** Typing `php ...` in a terminal uses XAMPP's
> PHP 7.1, which fails with *"Your Composer dependencies require a PHP version
> >= 8.2.0"*. This machine has a separate PHP 8.2 in `C:\php82` (Composer
> included) that is used only for this project, so XAMPP and the other
> PHP 7 projects in `htdocs` keep working unchanged.
>
> To set it up on another PC: download the "VS16 x64 Non Thread Safe" zip of
> PHP 8.2 from https://windows.php.net/download, extract it to `C:\php82`,
> copy `php.ini-development` to `php.ini` and enable `extension_dir` and the
> extensions `curl, fileinfo, gd, intl, mbstring, openssl, pdo_mysql,
> pdo_sqlite, sodium, sqlite3, zip`.

### Using PHP 8.2 in a terminal

`start-server.bat` finds PHP 8.2 by itself. For other commands (`composer`,
`artisan`), put PHP 8.2 first on the PATH of the current terminal window
(this only affects that window):

```bat
cd C:\xampp\htdocs\spaalita\Spaalita_backend
set PATH=C:\php82;%PATH%
php -v                          :: should print PHP 8.2.x
```

## Setup

Already done on this machine (dependencies installed, `.env` created, database
migrated and seeded). On a new machine, in a terminal using PHP 8.2:

```bash
cd C:\xampp\htdocs\spaalita\Spaalita_backend
composer install
copy .env.example .env          # cp on macOS/Linux
php artisan key:generate
php artisan jwt:secret          # writes a 64-character JWT_SECRET to .env
```

Create the database (phpMyAdmin or the MySQL console):

```sql
CREATE DATABASE spaalita_database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Check `DB_*` in `.env`, then create the tables:

```bash
php artisan migrate             # tables only
php artisan db:seed             # optional: sample data (see "Default logins")
php artisan db:seed --class=AdminSeeder   # only the admin login
```

Set the Stripe keys in `.env` (`STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET`)
and `FRONTEND_URL` (used in password reset links).

## Running

**Option 1, development server (simplest):** double-click `start-server.bat`, or run

```bat
start-server.bat            :: port 5000
start-server.bat 5001       :: another port
```

(equivalent to `php artisan serve --port=5000` with PHP 8.2). Stop it with
Ctrl+C. The port must be free: if another local server already uses 5000,
stop it first or use another port and point the
website at it (`VITE_API_BASE_URL=http://localhost:5001/api/` in the React
app's `.env`).

The API is then at `http://localhost:5000/api/...` and uploaded images at
`http://localhost:5000/uploads/...`, the same URLs the Node.js server used,
so the frontends need no change at all. The React website
(`SpaaLita_ReactFE`) defaults to `http://localhost:5000/api/`, so it works
against this server out of the box; its API calls were replayed against both
backends during the conversion and give the same responses.

**Option 2, XAMPP Apache (PHP 8.2):** start Apache and use
`http://localhost/spaalita/Spaalita_backend/public/api/...` (images:
`.../public/uploads/...`). For a cleaner URL, create an Apache virtual host
whose `DocumentRoot` is the `public` folder.

> Always serve the app through `php artisan serve`, Apache or PHP-FPM.
> They apply `enable_post_data_reading=Off` (see `app/Console/Commands/ServeCommand.php`,
> `public/.htaccess`, `public/.user.ini`), which lets the app read multipart
> uploads the way multer did: several files sent under the same field name
> (e.g. `productImages`), and uploads sent with `PUT`.

## Default logins (after `php artisan db:seed`)

| Panel | Login | Password |
|---|---|---|
| Admin (`POST /api/admin/login`) | `admin` | `Admin@321` |
| Customer (`POST /api/users/login`) | `user1@example.com` | `User@123` |

Override the admin with `ADMIN_USERNAME`, `ADMIN_PASSWORD`, `ADMIN_EMAIL` in `.env` before seeding.

## Moving existing data from MongoDB

1. Export each collection to JSON, either with MongoDB Compass (*Export
   Collection → JSON*) or with MongoDB Database Tools:

   ```bash
   for c in admins users categories products giftcards coupons orders services bookingpolicies monthlyspecials roles contacts galleries; do
     mongoexport --uri="mongodb://127.0.0.1:27017/spaalita_database" --collection=$c --jsonArray --out=export/$c.json
   done
   ```

2. Import them (ids, timestamps and password hashes are preserved, so users
   and admins keep their passwords):

   ```bash
   php artisan migrate:fresh
   php artisan mongo:import C:\path\to\export
   ```

   Re-running the import replaces matching records. Product categories that
   still hold WooCommerce ids are linked through the category `oId`, as the
   old `updateProductCategories.js` did.

3. Copy the Node project's `uploads` folder into `public/uploads` (rename
   `uploads/category` to `uploads/categories`).

## Configuration (.env)

| Variable | Purpose |
|---|---|
| `APP_URL` | Public URL of the API |
| `DB_*` | MySQL connection |
| `JWT_SECRET` | Token signing key, at least 32 characters (`php artisan jwt:secret`) |
| `JWT_ADMIN_TTL` / `JWT_USER_TTL` | Token lifetimes in seconds (3600 / 86400, as before) |
| `STRIPE_SECRET_KEY` | Stripe secret key |
| `STRIPE_WEBHOOK_SECRET` | Signing secret of the `/api/orders/webhook` endpoint |
| `STRIPE_API_BASE` | Optional: send Stripe calls to a local mock (e.g. stripe-mock) |
| `FRONTEND_URL` | Website URL used in password reset links |
| `CORS_ALLOWED_ORIGINS` | Sites allowed to call the API from a browser (comma separated). Default `*` (any), as before |
| `DB_TIMEZONE` | Database session time zone, default `+00:00` (all dates are stored in UTC) |
| `LOG_LEVEL` | `debug` also logs every incoming request |

The database schema (every table, column, key and how each MongoDB
collection maps to it) is documented in [docs/DATABASE.md](docs/DATABASE.md).

## API

All routes are prefixed with `/api`. 🔒 = requires `Authorization: Bearer <token>`.

| Resource | Endpoints |
|---|---|
| Admin | `POST /admin/login`, `POST /admin/logout` |
| Users | `POST /users/login`, `POST /users/logout`, `POST /users/forgot-password`, `POST /users/reset-password/{token}`; 🔒 `GET /users`, `GET /users/{id}`, `POST /users` (multipart `profilePicture`), `PUT /users/{id}` (multipart `profilePicture`), `PUT /users/{id}/addresses`, `DELETE /users/{id}` |
| Products | `GET /products` (`page`, `limit`, `search`, `sort`), `GET /products/{id}`, `GET /products/{id}/related`, `POST /products/apply-coupon`; 🔒 `POST /products`, `PUT /products/{id}` (multipart, up to 10 `productImages`), `DELETE /products/{id}` |
| Gift cards | `GET /giftcards`, `GET /giftcards/{id}`, `GET /giftcards/{id}/related`; 🔒 `POST /giftcards`, `PUT /giftcards/{id}`, `DELETE /giftcards/{id}` |
| Categories | `GET /category`, `GET /category/{id}`; 🔒 `POST /category`, `PUT /category/{id}` (multipart `image`), `DELETE /category/{id}` |
| Coupons | `POST /coupon/apply`; 🔒 `GET /coupon`, `GET /coupon/{id}`, `POST /coupon`, `PUT /coupon/{id}`, `DELETE /coupon/{id}` |
| Orders | `POST /orders` (guest or logged in), `POST /orders/webhook` (Stripe); 🔒 `GET /orders`, `PUT /orders/{id}/status`, `PUT /orders/{id}`, `DELETE /orders/{id}` |
| Payment | `POST /payment/create-payment-intent` |
| Services | `GET /services`, `GET /services/{id}`; 🔒 `POST`, `PUT /{id}` (JSON, or multipart with an optional `serviceImage` file), `DELETE /{id}` |
| Booking policies | `GET /booking-policies`, `GET /booking-policies/{id}`; 🔒 `POST`, `PUT /{id}`, `DELETE /{id}` |
| Monthly special | `GET /monthly-special`, `GET /monthly-special/{id}`; 🔒 `POST`, `PUT /{id}`, `DELETE /{id}` |
| Roles | 🔒 `GET /roles`, `GET /roles/{id}`, `POST /roles`, `PUT /roles/{id}`, `DELETE /roles/{id}` |
| Gallery | `GET /gallery` (`date=dd-mm-yyyy`), `DELETE /gallery/{id}`; 🔒 `POST /gallery/create` (up to 10 `images`), `PUT /gallery/{id}` (`image`) |
| Contact | `POST /contact` |
| Dashboard | 🔒 `GET /dashboard` |

Run `php artisan route:list --path=api` for the full list (69 routes).

## Differences from the Node.js version

Behaviour is otherwise identical. These changes are deliberate:

- **Security:** password hashes and reset tokens are no longer included in
  user records (`/api/users`, order lists, user creation). Uploaded files
  always keep an image extension and `public/uploads` cannot execute scripts.
  `JWT_SECRET` must be at least 32 characters.
- **Bugs fixed where the Node feature did not work:**
  - The Stripe webhook verified an empty body, so it always answered 400;
    it now verifies real Stripe events and updates the order.
  - Moving a sub-category to the top level failed and deleted the category;
    it now moves it.
  - Category images were never saved (and updating a category with an image
    failed); they are now stored as `{ "src": "/uploads/categories/..." }`.
  - Gallery images were saved to `uploads/gallery/` but recorded as
    `/uploads/<file>` (a broken link); the URL is now `/uploads/gallery/<file>`.
  - The admin panel's service form (multipart with a `serviceImage` file)
    could not create services; `POST`/`PUT /api/services` now accept the
    upload and store it as `/uploads/services/<file>` (a new file replaces
    the image, otherwise it is kept). JSON clients can still send
    `serviceImage` as a URL or base64 string.
  - Updating a user's profile picture recorded `userProfile/<file>` (a
    broken link); it is now `/uploads/userProfile/<file>`, as on creation.
- **Optional fields:** `serviceImage` and `buttonUrl` (services) and
  `buttonUrl` (booking policies) may be empty and are stored as `""`. The
  admin panel marks them optional and the website falls back to its default
  banner and booking links.
- **Database-related:** responses no longer contain MongoDB's internal
  `__v` field; a malformed id returns 404 instead of a 500 cast error;
  `search` parameters are plain text (no regular expressions); unique values
  such as SKU, user name and coupon code are compared case-insensitively;
  money is stored to the cent (an order total that Node stored as
  `59.599999999999994` is `59.6`); deleting a category also removes it from
  the products that used it (Node left the deleted id in their `categories`
  list).
- Unknown routes return JSON (`{"success": false, "message": "Cannot GET /api/x"}`)
  instead of Express's HTML page.

These behaviours of the original code were kept as-is (so the frontends
see no change) and are worth reviewing later:

- `DELETE /api/gallery/{id}` does not require a token.
- `GET /api/products/{id}/related` returns any 4 other active products (not
  the same category). Product lists only show items that have images and
  only their top-level categories.
- `GET /api/orders` does not list guest orders.
- At checkout with `createAccount`, two guests with the same first and last
  name cannot both get accounts (the generated user name is taken), and the
  order then fails.
- `POST /api/products/apply-coupon` only accepts the fixed codes SAVE10,
  SAVE20 and FLAT50 (store coupons use `/api/coupon/apply`).

## Tests

```bash
php artisan test
```

Feature tests cover every resource (authentication, uploads, multipart PUT,
category moves, coupon maths, checkout with a faked Stripe, webhook
signatures). They run on an in-memory SQLite database, so no setup is needed.

## Project structure

```
app/
  Console/Commands/       jwt:secret, mongo:import, users:hash-plain-passwords, serve
  Exceptions/             Cast / schema validation / upload errors
  Http/
    Controllers/Api/      One controller per resource
    Middleware/           JWT auth, image uploads, form-body parser, JSON output
    Requests/             Request validation (400 responses)
    Resources/            JSON response shapes
  Models/                 Eloquent models (BaseModel: ObjectId keys, ms timestamps)
  Models/Builders/        ObjectId-aware query builder
  Rules/                  JavaScript-style validation rules
  Services/               JWT, Stripe, checkout, coupon maths, uploads, MongoDB import
  Support/                JavaScript/Mongoose compatibility helpers, pagination
database/
  migrations/             MySQL schema (documented in docs/DATABASE.md)
  seeders/ factories/     Sample data and test factories
docs/DATABASE.md          Tables, columns, keys and the MongoDB → MySQL mapping
public/uploads/           Uploaded images (products, gallery, categories, userProfile)
routes/api.php            All API routes
server.php                Router for `php artisan serve` (CORS header on uploads)
start-server.bat          Starts the API with PHP 8.2 on Windows
tests/                    Feature and unit tests
```
