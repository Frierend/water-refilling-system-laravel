<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'inventory_items';
    private const COLUMN = 'type';
    private const UP_BASE_ORDER = ['water', 'container', 'empty', 'cap', 'seal', 'other'];
    private const DOWN_BASE_ORDER = ['water', 'container', 'cap', 'seal', 'other'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if ($this->shouldSkip()) {
            return;
        }

        $column = $this->getTypeColumnMetadata();

        if ($column === null) {
            return;
        }

        $currentValues = $this->parseEnumValues($column['type']);

        if ($currentValues === [] || in_array('empty', $currentValues, true)) {
            return;
        }

        $targetValues = $this->reorderValues(
            $this->appendIfMissing($currentValues, 'empty'),
            self::UP_BASE_ORDER
        );

        $this->alterTypeEnum($targetValues, $column['nullable'], $column['default']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->shouldSkip()) {
            return;
        }

        $column = $this->getTypeColumnMetadata();

        if ($column === null) {
            return;
        }

        $currentValues = $this->parseEnumValues($column['type']);

        if ($currentValues === [] || ! in_array('empty', $currentValues, true)) {
            return;
        }

        $replacementValue = in_array('container', $currentValues, true)
            ? 'container'
            : $this->firstNonEmptyEnumValue($currentValues);

        if ($replacementValue === null) {
            return;
        }

        DB::table(self::TABLE)
            ->where(self::COLUMN, 'empty')
            ->update([self::COLUMN => $replacementValue]);

        $targetValues = array_values(array_filter(
            $currentValues,
            static fn (string $value): bool => $value !== 'empty'
        ));

        if ($targetValues === []) {
            return;
        }

        $targetValues = $this->reorderValues($targetValues, self::DOWN_BASE_ORDER);

        $this->alterTypeEnum($targetValues, $column['nullable'], $column['default']);
    }

    private function shouldSkip(): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return true;
        }

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return true;
        }

        return ! Schema::hasTable(self::TABLE) || ! Schema::hasColumn(self::TABLE, self::COLUMN);
    }

    /**
     * @return array{type: string, nullable: bool, default: string|null}|null
     */
    private function getTypeColumnMetadata(): ?array
    {
        $row = DB::selectOne("SHOW COLUMNS FROM `" . self::TABLE . "` LIKE '" . self::COLUMN . "'");

        if ($row === null) {
            return null;
        }

        $column = (array) $row;
        $type = $this->readCaseInsensitiveKey($column, 'Type');

        if (! is_string($type)) {
            return null;
        }

        $nullable = strtoupper((string) $this->readCaseInsensitiveKey($column, 'Null')) === 'YES';
        $default = $this->readCaseInsensitiveKey($column, 'Default');

        return [
            'type' => $type,
            'nullable' => $nullable,
            'default' => is_string($default) ? $default : null,
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function readCaseInsensitiveKey(array $row, string $key): mixed
    {
        foreach ($row as $currentKey => $value) {
            if (strcasecmp((string) $currentKey, $key) === 0) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function parseEnumValues(string $columnType): array
    {
        if (! preg_match('/^enum\((.*)\)$/i', $columnType, $matches)) {
            return [];
        }

        preg_match_all("/'((?:''|[^'])*)'/", $matches[1], $values);

        return array_values(array_unique(array_map(
            static fn (string $value): string => str_replace("''", "'", $value),
            $values[1]
        )));
    }

    /**
     * @param list<string> $values
     * @param list<string> $order
     * @return list<string>
     */
    private function reorderValues(array $values, array $order): array
    {
        $values = array_values(array_unique($values));
        $ordered = [];

        foreach ($order as $value) {
            if (in_array($value, $values, true)) {
                $ordered[] = $value;
            }
        }

        foreach ($values as $value) {
            if (! in_array($value, $ordered, true)) {
                $ordered[] = $value;
            }
        }

        return $ordered;
    }

    /**
     * @param list<string> $values
     * @return list<string>
     */
    private function appendIfMissing(array $values, string $value): array
    {
        if (! in_array($value, $values, true)) {
            $values[] = $value;
        }

        return $values;
    }

    /**
     * @param list<string> $values
     */
    private function firstNonEmptyEnumValue(array $values): ?string
    {
        foreach ($values as $value) {
            if ($value !== 'empty') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param list<string> $values
     */
    private function alterTypeEnum(array $values, bool $nullable, ?string $default): void
    {
        $enumList = implode(', ', array_map(
            fn (string $value): string => "'" . str_replace("'", "''", $value) . "'",
            $values
        ));

        $nullSql = $nullable ? 'NULL' : 'NOT NULL';

        if ($default !== null && ! in_array($default, $values, true)) {
            $default = $values[0] ?? null;
        }

        $defaultSql = '';

        if ($default !== null) {
            $defaultSql = " DEFAULT '" . str_replace("'", "''", $default) . "'";
        } elseif ($nullable) {
            $defaultSql = ' DEFAULT NULL';
        }

        DB::statement(
            "ALTER TABLE `" . self::TABLE . "` MODIFY COLUMN `" . self::COLUMN . "` ENUM(" . $enumList . ") " . $nullSql . $defaultSql
        );
    }
};
