<?php

if (in_array('--help', $argv) || in_array('-h', $argv)) {
    displayHelp();
    exit(0);
}

// Gets the database connection parameters
list($host, $db, $user, $password, $custom) = getDbConnectionParams($argv);
// Create connection
$conn = new mysqli($host, $user, $password, $db);
// Check connection
if ($conn->connect_error)
{
    die("Connection failed: " . $conn->connect_error);
}

// Gets all columns from all tables
$all_columns = getAllColumns($conn, $db);
// Initializes a variable to store the generated queries
$queries = "";

foreach ($all_columns as $table_name => $columns) {
    // Generates the ALTER TABLE query for each table
    $alter_query = generateAlterTable($table_name, $columns, $custom);
    $queries .= $alter_query . "\n";
}

// Display all generated queries
echo $queries;
// Close connection
$conn->close();

// Function to get the command line parameters or use default values
function getDbConnectionParams($argv) {
    // Set default values
    $db = '';
    $user = getenv('MYSQL_USER') ?: 'root';
    $password = getenv('MYSQL_PASSWORD') ?: '';
    $host = getenv('MYSQL_HOST') ?: '127.0.0.1';
    $custom = false;

    // Parse the arguments provided by the user
    foreach ($argv as $arg) {
        if (preg_match('/-([^=]+)(?=(.*))/', $arg, $reg))
        {
            switch ($reg[1]) {
                case 'd':
                case '-db':
                    $db = ltrim($reg[2], "=");
                    break;
                case 'u':
                case '-user':
                    $user = ltrim($reg[2], "=");
                    break;
                case 'p':
                case '-password':
                    $password = ltrim($reg[2], "=");
                    break;
                case 'h':
                case '-host':
                    $host = ltrim($reg[2], "=");
                    break;
                case '-custom':
                    $custom = true;
                    break;
            }
        }
    }

    if (empty($db))
    {
        die("\nUsage: php sort_tables.php --db=<database> [OPTIONS]\nFor more information, use php sort_tables.php --help \n");
    }

    return [$host, $db, $user, $password, $custom];
}

// Function to get the columns of all tables in a single query
function getAllColumns($conn, $db) {
    $columns = [];
    $query = "
        SELECT 
            information_schema.COLUMNS.TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_COMMENT, EXTRA, CHECK_CLAUSE
        FROM 
            information_schema.COLUMNS
        LEFT JOIN 
            information_schema.CHECK_CONSTRAINTS ON information_schema.COLUMNS.TABLE_SCHEMA = information_schema.CHECK_CONSTRAINTS.CONSTRAINT_SCHEMA AND
            information_schema.CHECK_CONSTRAINTS.CONSTRAINT_NAME = information_schema.COLUMNS.COLUMN_NAME
        WHERE 
            TABLE_SCHEMA = '$db'
        ORDER BY 
            information_schema.COLUMNS.TABLE_NAME, ORDINAL_POSITION";
    
    $result = $conn->query($query);

    if (!$result)
    {
        die("Error retrieving columns: " . $conn->error);
    }

    while ($row = $result->fetch_assoc()) {
        $table_name = $row['TABLE_NAME'];
        $columns[$table_name][] = $row;
    }

    return $columns;
}

// Function to generate the ALTER TABLE query for a table
function generateAlterTable($table_name, $columns, $custom) {
    $column_definitions = [];

    foreach ($columns as $row) {
        $definition = "MODIFY COLUMN `" . $row['COLUMN_NAME'] . "` " . $row['COLUMN_TYPE'];
        
        // If the column does not allow NULL
        if ($row['IS_NULLABLE'] == 'NO')
        {
            $definition .= " NOT NULL";
        } else
        {
            $definition .= " NULL";
        }

        // Handle default values
        if (is_null($row['COLUMN_DEFAULT']))
        {
            if ($row['IS_NULLABLE'] == 'YES')
            {
                $definition .= " DEFAULT NULL";
            }
        } elseif ($row['COLUMN_DEFAULT'] == "current_timestamp()")
        {
            $definition .= " DEFAULT " . $row['COLUMN_DEFAULT'];
        } else
        {
            $definition .= " DEFAULT " . $row['COLUMN_DEFAULT'];
        }

        // Include column comments
        if (!empty($row['COLUMN_COMMENT']))
        {
            $definition .= " COMMENT '" . trim($row['COLUMN_COMMENT']) . "'";
        }

        // If there is any extra, such as auto_increment or ON UPDATE CURRENT_TIMESTAMP
        if (!empty($row['EXTRA']))
        {
            $definition .= " " . $row['EXTRA'];
        }
        
        // If there is any check_clause
        if (!empty($row['CHECK_CLAUSE']) && $custom == false)
        {
            $definition .= " CHECK (". $row['CHECK_CLAUSE'] . ")";
        }

        $column_definitions[$row['COLUMN_NAME']] = $definition;
    }

    // Sort columns alphabetically, keeping "id" first
    $column_names = array_keys($column_definitions);
    usort($column_names, "cmp");

    // Generate the ALTER TABLE query
    $alter = "ALTER TABLE `$table_name` \n";
    foreach ($column_names as $index => $column) {
        $position_string = $index == 0 ? " FIRST" : " AFTER `" . $column_names[$index - 1] . "`";

        if ($column == 'created' && $custom)
        {
            $alter .= "MODIFY COLUMN `created` timestamp NOT NULL DEFAULT current_timestamp() " . $position_string;
        } elseif ($column == 'updated' && $custom)
        {
            $alter .= "MODIFY COLUMN `updated` timestamp NOT NULL DEFAULT current_timestamp() on update current_timestamp() " . $position_string;
        } elseif (strpos($column, "json") === 0  && $custom)
        {
            $alter .= "MODIFY COLUMN `" . $column . "` JSON NULL " . $position_string;
        } else
        {
            $alter .= $column_definitions[$column] . $position_string;
        }

        if ($index == count($column_names) - 1)
        {
            $alter .= ";\n";
        } else
        {
            $alter .= ",\n";
        }
    }

    return $alter;
}

// Function to compare columns (keep "id" first)
function cmp($a, $b) {
    if ($a == "id")
    {
        return -1;
    }
    if ($b == "id")
    {
        return 1;
    }
    return strcmp($a, $b);
}

// Function to display the help message
function displayHelp() {
    echo "\nUsage: php sort_tables.php --db=<database> [options]\n";
    echo "\n";
    echo "Parameters:\n";
    echo "  --db, -d         (required) Name of the database.\n";
    echo "  --user, -u       (optional) Username. Default: root or the value from the MYSQL_USER environment variable.\n";
    echo "  --password, -p   (optional) User password. Default: empty or the value from the MYSQL_PASSWORD environment variable.\n";
    echo "  --host, -h       (optional) MySQL server address. Default: 127.0.0.1 or the value from the MYSQL_HOST environment variable.\n";
    echo "  --custom         (optional) Enables a specific format for JSON data (JSON NULL).\n";
    echo "  --help, -h       Displays this help message.\n";
    echo "\n";
    echo "Description:\n";
    echo "  This script generates SQL queries to modify the columns of all tables in a database, ordering them alphabetically.\n";
    echo "  The only required parameter is the database name (--db or -d). The other parameters can be taken from the environment variables\n";
    echo "  (MYSQL_USER, MYSQL_PASSWORD, MYSQL_HOST) or use default values.\n";
    echo "\n";
    echo "Example usage:\n";
    echo "  php sort_tables.php --db=my_database --user=my_user --password=my_password --host=localhost\n";
}
