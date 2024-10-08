# MySQL Column Reorder Script

This repository contains a PHP script that generates SQL queries to reorder columns alphabetically in all tables of a MySQL database. The script also ensures that the `id` column remains the first column in each table. Additionally, the script supports customization for handling specific types of columns, such as `JSON` columns and `timestamp` fields, using the `--custom` option.

## Table of Contents

- [Description](#description)
- [Features](#features)
- [Usage](#usage)
  - [Requirements](#requirements)
  - [Command Line Arguments](#command-line-arguments)
  - [Example Usage](#example-usage)
- [Installation](#installation)
- [Custom](#custom)
- [Contributing](#contributing)

## Description

The purpose of this script is to automatically generate `ALTER TABLE` statements for all tables in a specified MySQL database. It retrieves column information from the MySQL `information_schema` and sorts the columns in alphabetical order while ensuring that the primary key column (typically `id`) remains first in each table. The script can be run directly from the command line and supports options for database connection details and customizations.

This tool is useful for developers or database administrators who need to maintain consistent column order across tables for readability, maintainability, or other organizational purposes.

## Features

- Reorders columns alphabetically in all tables of a MySQL database.
- Ensures the `id` column remains the first column.
- Supports custom column handling for JSON and timestamp fields with the `--custom` option.
- Generates SQL `ALTER TABLE` statements without executing them, allowing for manual review or application.
- Handles default values, `NULL` constraints, comments, and extra attributes (like `AUTO_INCREMENT`).
- Easy to use with command-line arguments for database connection parameters.

## Usage

### Requirements

- PHP (version 7.4 or higher recommended)
- MySQL or MariaDB database
- Access to the command line to run the script

### Command Line Arguments

| Argument            | Description                                                                                              | Required  |
|---------------------|----------------------------------------------------------------------------------------------------------|-----------|
| `--db` or `-d`      | The name of the database to connect to.                                                                   | Yes       |
| `--user` or `-u`    | The MySQL user to connect as. Defaults to `root` or the value from the `MYSQL_USER` environment variable.  | No        |
| `--password` or `-p`| The MySQL user's password. Defaults to an empty string or the value from the `MYSQL_PASSWORD` environment variable. | No        |
| `--host` or `-h`    | The MySQL server host. Defaults to `127.0.0.1` or the value from the `MYSQL_HOST` environment variable.    | No        |
| `--custom`          | Enables custom handling of specific fields such as JSON and timestamps.                                   | No        |
| `--help` or `-h`    | Displays the help message explaining usage.                                                               | No        |

### Example Usage

1. Basic usage to reorder columns in a database:

```bash
php reorder_columns.php --db=my_database
```
2. Usage with custom user credentials and host:

```bash
php reorder_columns.php --db=my_database --user=my_user --password=my_password --host=localhost
```
3. Enable custom handling of `JSON` and `timestamp` columns:

```bash
php reorder_columns.php --db=my_database --custom
```
4. Display the help message:

```bash
php reorder_columns.php --help
```

### Output
The script generates and prints SQL `ALTER TABLE` queries for each table in the database. The queries can then be copied and executed manually to apply the changes.

Example output:

```sql
ALTER TABLE `users` 
MODIFY COLUMN `id` int(11) NOT NULL AUTO_INCREMENT FIRST,
MODIFY COLUMN `created` timestamp NOT NULL DEFAULT current_timestamp() AFTER `id`,
MODIFY COLUMN `email` varchar(255) NOT NULL AFTER `created`,
MODIFY COLUMN `name` varchar(255) DEFAULT NULL AFTER `email`;

ALTER TABLE `orders` 
MODIFY COLUMN `id` int(11) NOT NULL AUTO_INCREMENT FIRST,
MODIFY COLUMN `amount` decimal(10,2) DEFAULT NULL AFTER `id`,
MODIFY COLUMN `created_at` timestamp NOT NULL DEFAULT current_timestamp() AFTER `amount`;
```

## Installation

1. Clone the repository to your local machine:

```bash
git clone https://github.com/yourusername/db-column-reorder.git

cd db-column-reorder
```

2. Ensure you have PHP installed by running:

```bash
php -v
```

3. You can now run the script using the examples provided in the [Usage](#usage) section.



## Custom

The `--custom` option modifies the script's behavior for certain column types:

-   **JSON columns**: If the `--custom` flag is set, columns that have names starting with `json` will be automatically modified to have the `JSON` data type and allow `NULL` values.
-   **Timestamp fields**: The `created` and `updated` fields will be set to use `current_timestamp()` for their default values and, in the case of `updated`, an `ON UPDATE` clause will be added.

This customization feature is useful for databases that follow specific schema conventions and need special handling for these types of columns.

## Contributing
Contributions are welcome! If you'd like to contribute, please fork the repository and submit a pull request with your improvements. Be sure to document any new features or changes in the README.
