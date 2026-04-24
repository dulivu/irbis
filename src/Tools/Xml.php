<?php
namespace Irbis\Tools;
use SimpleXMLElement;
use Irbis\Tools\Json;


class Xml {
    private static function arrayToXml(array $data, SimpleXMLElement &$xml): void {
        foreach ($data as $key => $value) {
            if (is_numeric($key)) {
                $key = 'item';
            }

            if (is_array($value)) {
                $child = $xml->addChild($key);
                self::arrayToXml($value, $child);
            } else {
                $xml->addChild($key, htmlspecialchars((string)$value));
            }
        }
    }

    public static function encode(array $data, string $root = 'root'): string {
        $xml = new SimpleXMLElement("<$root/>");
        self::arrayToXml($data, $xml);
        return $xml->asXML();
    }

    public static function decode(string $xml): array {
        return Json::decode(Json::encode(simplexml_load_string($xml)));
    }
}