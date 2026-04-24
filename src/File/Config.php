<?php
namespace Irbis\File;
use Irbis\File\File;


class Config extends File
{
    private array $data;
    private bool $has_changes = false;
    
    public static function fromDefault(string $path): self 
    {
        $state = new self($path);
        if ($state->empty()) {
            $defaults = [
                'server' => [
                    'debug' => 'on',
                    'terminal' => 'on'
                ],
                'database' => [
                    'dsn' => 'sqlite:database.db3',
                    'user' => '',
                    'pass' => ''
                ]
            ];
            foreach ($defaults as $section => $values)
                foreach ($values as $k => $v)
                    $state->set("{$section}.{$k}", $v);
        }
        return $state;
    }

    public function __construct(string $path) 
    {
        parent::__construct($path);

        if (!$this->exists()) {
            $this->truncate();
        }

        $this->data = parse_ini_file(
            $this->path(),
            true,
            INI_SCANNER_TYPED
        ) ?: [];
    }

    public function get(string $key) 
    {
        $value = array_get($this->data, $key);

        if (is_string($value)) {
            $lower = strtolower($value);
            if ($lower === 'on') return true;
            if ($lower === 'off') return false;
        }

        if (is_numeric($value)) {
            if (str_contains($value, '.')) 
                return floatval($value);
            return intval($value);
        }

        return $value;
    }

    public function set(string $key, $value): void 
    {
        $this->has_changes = true;

        if ($value === null) {
            array_unset($this->data, $key);
            return;
        }

        array_set($this->data, $key, $value);
    }

    public function empty(): bool 
    {
        return empty($this->data);
    }

    public function save(): void 
    {
        if (!$this->has_changes) {
            return;
        }

        $lines = [];

        foreach ($this->data as $section => $values) {

            if (is_array($values)) {

                $lines[] = "[$section]";

                foreach ($values as $key => $value) {
                    $lines[] = $this->formatLine($key, $value);
                }

                $lines[] = ''; // espacio entre secciones

            } else {

                $lines[] = $this->formatLine($section, $values);
            }
        }

        $this->truncate(); // importante
        $this->write(implode(PHP_EOL, $lines));

        $this->has_changes = false;
    }

    private function formatLine(string $key, $value): string 
    {
        if (is_bool($value)) {
            $value = $value ? 'on' : 'off';
        } elseif (is_string($value)) {
            $value = '"' . addslashes($value) . '"';
        }

        return "{$key} = {$value}";
    }
}