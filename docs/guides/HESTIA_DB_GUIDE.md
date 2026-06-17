# Hestia CP Database Configuration Guide - HRMS V2

Follow these steps to set up the database on your DigitalOcean droplet via Hestia Control Panel.

## 1. Create the Database in Hestia CP
1.  Log in to your Hestia CP dashboard.
2.  Navigate to the **DB** (Databases) section in the top navigation bar.
3.  Click the **Add Database** button.
4.  **Database Name**: `anedins_hrms` (Note: Hestia prefix might be added, e.g., `admin_anedins_hrms`).
5.  **Username**: `anedins_hrms_user`.
6.  **Password**: Generate a strong password and **SAVE IT**.
7.  **Type**: `mysql`.
8.  **Charset**: Ensure it is set to `UTF8` or `UTF8MB4`.
9.  Click **Save**.

## 2. Import the HRMS Schema
Once the database is created, you need to import the tables:
1.  Open **phpMyAdmin** from the Hestia CP Database list (there is a "PHPMYADMIN" button next to your new DB).
2.  Select your new database from the left sidebar.
3.  Click the **Import** tab.
4.  Choose the `DATABASE_SCHEMA.sql` file from your local machine (found in the `database/` folder of your deployment zip).
5.  Click **Go**.

## 3. Configure config.php
After creating the database, update your `public_html/config/config.php` on the server:

```php
define('ACTIVE_ENVIRONMENT', 'hevista'); 

// Update the 'hevista' profile in environments.php or directly in config.php
define('DB_NAME', 'admin_anedins_hrms'); // Use the exact name shown in Hestia CP
define('DB_USER', 'admin_anedins_hrms_user');
define('DB_PASS', 'YOUR_NEW_PASSWORD');
```

## 4. Troubleshooting Connection
If you get a connection error:
- Ensure `DB_HOST` is set to `localhost` (Hestia usually uses Unix sockets or localhost for local DBs).
- Verify that the Hestia prefix (e.g. `admin_`) is included in the name and user.
- Check if the MariaDB service is running on the droplet.
