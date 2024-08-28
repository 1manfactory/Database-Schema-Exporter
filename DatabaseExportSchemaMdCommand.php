<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Schema\Table;
use Symfony\Component\Console\Input\InputArgument;

class DatabaseExportAllSchemasCommand extends Command
{
    protected static string $defaultName = 'database:export-all-schemas';

    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct();
        $this->container = $container;
    }

    protected function configure()
    {
        $this
            ->setName('database:export-all-schemas')
            ->setDescription('Exports the structure of all database tables.')
            ->addArgument('format', InputArgument::OPTIONAL, 'Output format: "md" for Markdown or "console" for human-readable console output', 'console')
            ->addArgument('database', InputArgument::OPTIONAL, 'The specific database to export. Default is to export all configured databases.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $format = $input->getArgument('format');
        $database = $input->getArgument('database');

        // Validate the format argument
        if (!in_array($format, ['md', 'console'])) {
            $output->writeln('<error>Invalid format. Please use "md" for Markdown or "console" for human-readable console output.</error>');
            return Command::FAILURE;
        }

        // Get all configured database connections
        $connectionNames = $this->container->getParameter('doctrine.connections');
        $databaseExists = false;

        foreach ($connectionNames as $connectionServiceId) {
            /** @var Connection $connection */
            $connection = $this->container->get($connectionServiceId);

            // Check if the specific database exists
            if ($database && $connection->getDatabase() === $database) {
                $databaseExists = true;
                $output->writeln('## Database: ' . $connection->getDatabase());
                $this->exportDatabaseSchema($connection, $output, $format);
                break;
            }

            // Export all databases if no specific database is provided
            if (!$database) {
                $output->writeln('## Database: ' . $connection->getDatabase());
                $this->exportDatabaseSchema($connection, $output, $format);
            }
        }

        // If a specific database was requested but not found
        if ($database && !$databaseExists) {
            $output->writeln('<error>The specified database "' . $database . '" does not exist.</error>');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function exportDatabaseSchema(Connection $connection, OutputInterface $output, string $format): void
    {
        $schemaManager = $connection->createSchemaManager();
        $tables = $schemaManager->listTables();

        foreach ($tables as $table) {
            $output->writeln('### Table: ' . $table->getName());

            if ($format === 'md') {
                $output->writeln('| Column | Type | Length | Precision | Nullable | Default | Extra |');
                $output->writeln('|--------|------|--------|-----------|----------|---------|-------|');
            } else {
                $output->writeln($this->formatLine(['Column', 'Type', 'Length', 'Precision', 'Nullable', 'Default', 'Extra']));
                $output->writeln($this->formatLine(['------', '----', '------', '---------', '--------', '-------', '-----']));
            }

            $this->exportTableColumns($table, $output, $format);
            $this->exportPrimaryKey($table, $output, $format);
            $this->exportForeignKeys($table, $output, $format);
            $this->exportIndexes($table, $output, $format);

            $output->writeln('');
        }
    }

    private function exportTableColumns(Table $table, OutputInterface $output, string $format): void
    {
        foreach ($table->getColumns() as $column) {
            $columnType = $this->getColumnTypeName($column->getType());
            $extra = $column->getAutoincrement() ? 'AUTO_INCREMENT' : '';

            if ($format === 'md') {
                $output->writeln(sprintf(
                    '| %s | %s | %s | %s | %s | %s | %s |',
                    $column->getName(),
                    $columnType,
                    $column->getLength() ?: '-',
                    $column->getPrecision() ?: '-',
                    $column->getNotnull() ? 'No' : 'Yes',
                    $column->getDefault() ?: '-',
                    $extra
                ));
            } else {
                $output->writeln($this->formatLine([
                    $column->getName(),
                    $columnType,
                    $column->getLength() ?: '-',
                    $column->getPrecision() ?: '-',
                    $column->getNotnull() ? 'No' : 'Yes',
                    $column->getDefault() ?: '-',
                    $extra
                ]));
            }
        }
    }

    private function exportPrimaryKey(Table $table, OutputInterface $output, string $format): void
    {
        $primaryKey = $table->getPrimaryKey();
        if ($primaryKey) {
            $output->writeln('');
            $output->writeln('#### Primary Key');
            foreach ($primaryKey->getColumns() as $column) {
                if ($format === 'md') {
                    $output->writeln(sprintf('| %s |', $column));
                } else {
                    $output->writeln($this->formatLine([$column, '', '', '', '', '', '']));
                }
            }
        }
    }

    private function exportForeignKeys(Table $table, OutputInterface $output, string $format): void
    {
        $foreignKeys = $table->getForeignKeys();
        if (!empty($foreignKeys)) {
            $output->writeln('');
            $output->writeln('#### Foreign Keys');
            foreach ($foreignKeys as $foreignKey) {
                foreach ($foreignKey->getLocalColumns() as $key => $localColumn) {
                    if ($format === 'md') {
                        $output->writeln(sprintf(
                            '| %s | %s | %s |',
                            $localColumn,
                            $foreignKey->getForeignTableName(),
                            $foreignKey->getForeignColumns()[$key]
                        ));
                    } else {
                        $output->writeln($this->formatLine([
                            $localColumn,
                            $foreignKey->getForeignTableName(),
                            $foreignKey->getForeignColumns()[$key],
                            '', '', '', ''
                        ]));
                    }
                }
            }
        }
    }

    private function exportIndexes(Table $table, OutputInterface $output, string $format): void
    {
        $indexes = $table->getIndexes();
        if (!empty($indexes)) {
            $output->writeln('');
            $output->writeln('#### Indexes');
            foreach ($indexes as $index) {
                if ($format === 'md') {
                    $output->writeln(sprintf(
                        '| %s | %s | %s |',
                        $index->getName(),
                        implode(', ', $index->getColumns()),
                        $index->isUnique() ? 'Yes' : 'No'
                    ));
                } else {
                    $output->writeln($this->formatLine([
                        $index->getName(),
                        implode(', ', $index->getColumns()),
                        $index->isUnique() ? 'Yes' : 'No',
                        '', '', '', ''
                    ]));
                }
            }
        }
    }

    private function getColumnTypeName(Type $type): string
    {
        return (new \ReflectionClass($type))->getShortName();
    }

    private function formatLine(array $columns): string
    {
        return sprintf(
            '%-20s %-20s %-15s %-10s %-10s %-10s %-20s',
            ...$columns
        );
    }
}
