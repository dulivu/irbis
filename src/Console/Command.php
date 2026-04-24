<?php
namespace Irbis\Console;

use Exception;

/**
 * Clase base abstracta para todos los comandos CLI
 */
abstract class Command
{
    protected array $arguments = [];
    protected array $options = [];

    /**
     * Nombre del comando (ej: "install", "list", "conn:config")
     */
    abstract public function getName(): string;

    /**
     * Descripción del comando
     */
    abstract public function getDescription(): string;

    /**
     * Sintaxis de uso del comando
     */
    abstract public function getUsage(): string;

    /**
     * Ejecutar el comando
     */
    abstract public function handle(): int;

    /**
     * Establece los argumentos y opciones del comando
     */
    public function setInput(array $arguments, array $options): void
    {
        $this->arguments = $arguments;
        $this->options = $options;
    }

    /**
     * Obtiene un argumento por índice
     */
    protected function argument(int $index, $default = null)
    {
        return $this->arguments[$index] ?? $default;
    }

    /**
     * Obtiene una opción
     */
    protected function option(string $name, $default = false)
    {
        return $this->options[$name] ?? $default;
    }

    /**
     * Verifica si existe un argumento
     */
    protected function hasArgument(int $index): bool
    {
        return isset($this->arguments[$index]);
    }

    /**
     * Verifica si existe una opción
     */
    protected function hasOption(string $name): bool
    {
        return isset($this->options[$name]);
    }

    /**
     * Lanza una excepción con mensaje de error
     */
    protected function fail(string $message): void
    {
        throw new Exception($message);
    }

    /**
     * Muestra la ayuda del comando
     */
    public function showHelp(): void
    {
        Output::title($this->getName());
        Output::line($this->getDescription());
        Output::line();
        Output::line(Output::color('Uso:', 'yellow'));
        Output::line('  ' . $this->getUsage());
        Output::line();
    }
}
