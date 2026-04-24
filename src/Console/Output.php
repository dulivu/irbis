<?php
namespace Irbis\Console;

/**
 * Helper para output con colores ANSI en la terminal
 */
class Output
{
    // Códigos de color ANSI
    private const COLORS = [
        'reset'   => "\033[0m",
        'black'   => "\033[30m",
        'red'     => "\033[31m",
        'green'   => "\033[32m",
        'yellow'  => "\033[33m",
        'blue'    => "\033[34m",
        'magenta' => "\033[35m",
        'cyan'    => "\033[36m",
        'white'   => "\033[37m",
        'gray'    => "\033[90m",
    ];

    private const STYLES = [
        'bold'      => "\033[1m",
        'dim'       => "\033[2m",
        'underline' => "\033[4m",
    ];

    /**
     * Imprime un mensaje de éxito (verde)
     */
    public static function success(string $message): void
    {
        echo self::COLORS['green'] . '✓ ' . $message . self::COLORS['reset'] . PHP_EOL;
    }

    /**
     * Imprime un mensaje de error (rojo)
     */
    public static function error(string $message): void
    {
        echo self::COLORS['red'] . '✗ ' . $message . self::COLORS['reset'] . PHP_EOL;
    }

    /**
     * Imprime un mensaje de advertencia (amarillo)
     */
    public static function warning(string $message): void
    {
        echo self::COLORS['yellow'] . '⚠ ' . $message . self::COLORS['reset'] . PHP_EOL;
    }

    /**
     * Imprime un mensaje informativo (cyan)
     */
    public static function info(string $message): void
    {
        echo self::COLORS['cyan'] . 'ℹ ' . $message . self::COLORS['reset'] . PHP_EOL;
    }

    /**
     * Imprime texto normal
     */
    public static function line(string $message = ''): void
    {
        echo $message . PHP_EOL;
    }

    /**
     * Imprime un título (bold + cyan)
     */
    public static function title(string $message): void
    {
        echo PHP_EOL . self::STYLES['bold'] . self::COLORS['cyan'] 
             . $message . self::COLORS['reset'] . PHP_EOL . PHP_EOL;
    }

    /**
     * Imprime una tabla simple
     */
    public static function table(array $headers, array $rows): void
    {
        // Calcular anchos de columna
        $widths = array_map('strlen', $headers);
        
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $widths[$i] = max($widths[$i], strlen($cell));
            }
        }

        // Imprimir encabezados
        echo self::STYLES['bold'];
        foreach ($headers as $i => $header) {
            echo str_pad($header, $widths[$i] + 2);
        }
        echo self::COLORS['reset'] . PHP_EOL;

        // Imprimir separador
        foreach ($widths as $width) {
            echo str_repeat('-', $width + 2);
        }
        echo PHP_EOL;

        // Imprimir filas
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                echo str_pad($cell, $widths[$i] + 2);
            }
            echo PHP_EOL;
        }

        echo PHP_EOL;
    }

    /**
     * Colorea un texto
     */
    public static function color(string $text, string $color): string
    {
        return (self::COLORS[$color] ?? '') . $text . self::COLORS['reset'];
    }
}
