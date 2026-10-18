<?php

namespace App\Services\Disc;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

class DiscExcelReader
{
    private const SPREADSHEET_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    /** @return array<int, array<string, string>> */
    public function read(string $path, string $sheetName = 'Suivi Courrier'): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Le fichier Excel ne peut pas être ouvert.');
        }

        try {
            $sharedStrings = $this->sharedStrings($zip);
            $sheetPath = $this->sheetPath($zip, $sheetName);
            $sheetXml = $zip->getFromName($sheetPath);
            if ($sheetXml === false) {
                throw new RuntimeException("L’onglet {$sheetName} est introuvable.");
            }

            $document = $this->document($sheetXml);
            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('s', self::SPREADSHEET_NS);
            $rows = [];

            foreach ($xpath->query('//s:sheetData/s:row') as $row) {
                if (! $row instanceof DOMElement || (int) $row->getAttribute('r') < 17) {
                    continue;
                }

                $values = [];
                foreach ($xpath->query('./s:c', $row) as $cell) {
                    if (! $cell instanceof DOMElement) {
                        continue;
                    }

                    preg_match('/^[A-Z]+/', $cell->getAttribute('r'), $matches);
                    $column = $matches[0] ?? '';
                    $type = $cell->getAttribute('t');
                    $valueNode = $xpath->query('./s:v', $cell)->item(0);
                    $value = $valueNode?->textContent ?? '';

                    if ($type === 's' && $value !== '') {
                        $value = $sharedStrings[(int) $value] ?? '';
                    } elseif ($type === 'inlineStr') {
                        $parts = [];
                        foreach ($xpath->query('.//s:t', $cell) as $text) {
                            $parts[] = $text->textContent;
                        }
                        $value = implode('', $parts);
                    }

                    $values[$column] = trim($value);
                }

                if ($this->isDataRow($values)) {
                    $values['_row'] = (string) $row->getAttribute('r');
                    $rows[] = $values;
                }
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    /** @return array<int, string> */
    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $document = $this->document($xml);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('s', self::SPREADSHEET_NS);
        $strings = [];

        foreach ($xpath->query('//s:si') as $item) {
            $parts = [];
            foreach ($xpath->query('.//s:t', $item) as $text) {
                $parts[] = $text->textContent;
            }
            $strings[] = implode('', $parts);
        }

        return $strings;
    }

    private function sheetPath(ZipArchive $zip, string $sheetName): string
    {
        $workbook = $this->document($this->requiredEntry($zip, 'xl/workbook.xml'));
        $xpath = new DOMXPath($workbook);
        $xpath->registerNamespace('s', self::SPREADSHEET_NS);
        $relationshipId = null;

        foreach ($xpath->query('//s:sheets/s:sheet') as $sheet) {
            if ($sheet instanceof DOMElement && $sheet->getAttribute('name') === $sheetName) {
                $relationshipId = $sheet->getAttributeNS(
                    'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
                    'id',
                );
                break;
            }
        }

        if (! $relationshipId) {
            throw new RuntimeException("L’onglet {$sheetName} est introuvable.");
        }

        $relationships = $this->document($this->requiredEntry($zip, 'xl/_rels/workbook.xml.rels'));
        $relationshipXpath = new DOMXPath($relationships);
        foreach ($relationshipXpath->query('//*[local-name()="Relationship"]') as $relationship) {
            if ($relationship instanceof DOMElement && $relationship->getAttribute('Id') === $relationshipId) {
                $target = ltrim($relationship->getAttribute('Target'), '/');

                return str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
            }
        }

        throw new RuntimeException("La définition de l’onglet {$sheetName} est invalide.");
    }

    private function requiredEntry(ZipArchive $zip, string $name): string
    {
        $contents = $zip->getFromName($name);
        if ($contents === false) {
            throw new RuntimeException('Le fichier ne respecte pas le format XLSX attendu.');
        }

        return $contents;
    }

    private function document(string $xml): DOMDocument
    {
        $document = new DOMDocument;
        if (! @$document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new RuntimeException('Une partie du fichier Excel est illisible.');
        }

        return $document;
    }

    /** @param array<string, string> $values */
    private function isDataRow(array $values): bool
    {
        foreach (['B', 'C', 'D', 'E', 'G', 'H', 'I', 'M', 'N', 'S'] as $column) {
            if (($values[$column] ?? '') !== '') {
                return true;
            }
        }

        return false;
    }
}
