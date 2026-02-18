<?php

namespace Darvis\UblPeppol\Validation;

class CodelistRegistry
{
    private array $lists;

    public function __construct(array $lists)
    {
        $this->lists = $this->normalizeLists($lists);
    }

    public static function fromJsonFile(string $path): self
    {
        if (!is_file($path)) {
            throw new \InvalidArgumentException("Codelist file not found: {$path}");
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException("Failed to read codelist file: {$path}");
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            throw new \InvalidArgumentException("Invalid codelist JSON file: {$path}");
        }

        return new self($data);
    }

    public function isLoaded(string $listName): bool
    {
        return array_key_exists($listName, $this->lists) && !empty($this->lists[$listName]);
    }

    public function has(string $listName, string $code): bool
    {
        if (!$this->isLoaded($listName)) {
            return false;
        }

        $normalized = $this->normalizeCode($code);
        return isset($this->lists[$listName][$normalized]);
    }

    private function normalizeLists(array $lists): array
    {
        $normalized = [];

        foreach ($lists as $listName => $codes) {
            if (!is_array($codes)) {
                continue;
            }

            $normalized[$listName] = [];
            foreach ($codes as $code) {
                $normalized[$listName][$this->normalizeCode((string)$code)] = true;
            }
        }

        return $normalized;
    }

    private function normalizeCode(string $code): string
    {
        return strtoupper(trim($code));
    }
}
