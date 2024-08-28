# Database Schema Export Command

This Symfony command allows you to export the schema of all tables in your databases in either a console-friendly format or as Markdown tables.

## Installation

1. Place the `DatabaseExportAllSchemasCommand.php` file in your `src/Command/` directory.
2. Register the command in your `services.yaml`:

   ```yaml
   services:
       App\Command\DatabaseExportAllSchemasCommand:
           arguments:
               $container: '@service_container'
           tags:
               - { name: 'console.command' }
   ```

## Usage

You can run the command using the Symfony console. It supports two output formats: human-readable for the console and Markdown. Additionally, you can specify a particular database to export or export all configured databases.

### Console-Friendly Output (Default)

```bash
php bin/console database:export-all-schemas
```

This will output the schema of all configured databases in a format that is easy to read in the console.

### Markdown Output

```bash
php bin/console database:export-all-schemas md
```

This will output the schema of all configured databases as Markdown tables, which can be used in documentation.

### Specific Database Export

You can specify a particular database to export by providing the database name as the second argument. For example:

```bash
php bin/console database:export-all-schemas console my_database
```

or

```bash
php bin/console database:export-all-schemas md my_database
```

This will export only the schema of the specified database.

## Error Handling

- **Invalid Format**: If you provide an invalid format as the first argument, the command will display an error message:
  
  ```bash
  php bin/console database:export-all-schemas invalidFormat
  ```

  Output:

  ```plaintext
  <error>Invalid format. Please use "md" for Markdown or "console" for human-readable console output.</error>
  ```

- **Non-Existent Database**: If you specify a database that does not exist, the command will display an error message:

  ```bash
  php bin/console database:export-all-schemas console nonExistentDatabase
  ```

  Output:

  ```plaintext
  <error>The specified database "nonExistentDatabase" does not exist.</error>
  ```

## Example Output

### Console Output

```plaintext
## Database: my_database
### Table: users
Column               Type            Length     Precision  Nullable   Default    Extra          
------               ----            ------     ---------  --------   -------    -----          
id                   IntegerType     -          -          No         -          AUTO_INCREMENT
name                 StringType      255        -          No         -          -             
email                StringType      255        -          Yes        -          -             
created_at           DateTimeType    -          -          No         -          -             
updated_at           DateTimeType    -          -          No         -          -             
```

### Markdown Output

```markdown
## Database: my_database
### Table: users

| Column     | Type        | Length | Precision | Nullable | Default | Extra          |
|------------|-------------|--------|-----------|----------|---------|----------------|
| id         | IntegerType | -      | -         | No       | -       | AUTO_INCREMENT |
| name       | StringType  | 255    | -         | No       | -       | -              |
| email      | StringType  | 255    | -         | Yes      | -       | -              |
| created_at | DateTimeType| -      | -         | No       | -       | -              |
| updated_at | DateTimeType| -      | -         | No       | -       | -              |
```

## License

This project is licensed under the MIT License.
