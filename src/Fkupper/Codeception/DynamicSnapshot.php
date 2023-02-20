<?php

namespace Fkupper\Codeception;

use Codeception\Exception\ContentNotFound;
use Codeception\Snapshot;
use InvalidArgumentException;
use PHPUnit\Framework\ExpectationFailedException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Throwable;

abstract class DynamicSnapshot extends Snapshot
{
    protected string $leftWrapper = '[';
    protected string $rightWrapper = ']';
    protected string $substitutionPrefix = 'snapshot_';
    protected string $strictSubstitutionPrefix = 'snapshot_strict_';

    protected bool $allowTrailingSpaces = false;
    protected bool $allowSpaceSequences = false;

    /** @var array<string,string> */
    protected array $substitutions = [];
    /** @var array<string,string> */
    protected array $strictSubstitutions = [];
    /** @var array<string> */
    protected array $ignoredLinesPatters = [];


    /**
     * Set what characters will be used to wrap substitution keys.
     * Default is []
     */
    public function setWrappers(string $leftWrapper = '[', string $rightWrapper = ']'): void
    {
        if (count([$leftWrapper, $rightWrapper]) !== count(array_filter([$leftWrapper, $rightWrapper]))) {
            $this->fail('Wrappers cannot be empty strings.');
        }

        $this->leftWrapper = $leftWrapper;
        $this->rightWrapper = $rightWrapper;
    }

    protected function getLeftWrapper(): string
    {
        return $this->leftWrapper;
    }

    protected function getRightWrapper(): string
    {
        return $this->rightWrapper;
    }

    protected function getSubstitutionKey(string $key, bool $strictSubstitutions): string
    {
        return ($strictSubstitutions ? $this->strictSubstitutionPrefix : $this->substitutionPrefix) . $key;
    }

    /**
     * Sets the array of substitutions containing keys as the keys and the
     * replacement as the values.
     * Eg:
     * ['user_id' => '99', 'some_dynamic_path' => '/foo/path/123/']
     * @param array<string,scalar|object> $substitutions
     */
    public function setSubstitutions(array $substitutions): void
    {
        foreach ($substitutions as $key => $value) {
            if (!is_scalar($value) || (is_object($value) && !method_exists($value, '__toString'))) {
                throw new InvalidArgumentException(
                    'Substitutions can only be string values or values that can be casted to string. ' .
                    "You provided substitution `$key` of type " . getType($value)
                );
            }
            $substitutionKey = $this->getSubstitutionKey($key, strictSubstitutions: false);
            $this->substitutions[$substitutionKey] = (string)$value;
        }
    }

    /**
     * Sets the array of strict substitutions containing keys as the keys and the
     * replacement as the values.
     * Strict substitutions are checked using boundaries in the regex.
     * Eg:
     * ['user_id' => 99, 'day_of_the_week' => 6]
     * @param array<string, scalar|object> $strictSubstitutions
     */
    public function setStrictSubstitutions(array $strictSubstitutions): void
    {
        foreach ($strictSubstitutions as $key => $value) {
            if (!is_scalar($value) || (is_object($value) && !method_exists($value, '__toString'))) {
                throw new InvalidArgumentException(
                    'Strict substitutions can only be string values or values that can be casted to string. ' .
                    "You provided substitution `$key` of type " . getType($value)
                );
            }
            $substitutionKey = $this->getSubstitutionKey($key, strictSubstitutions: true);
            $this->strictSubstitutions[$substitutionKey] = (string)$value;
        }
    }

    /**
     * Sets an array of regex patterns that will be used to remove lines that matches them
     * both from expected and actual snapshot value.
     * @param array<string> $patterns
     */
    public function setIgnoredLinesPatterns(array $patterns): void
    {
        $this->ignoredLinesPatters = $patterns;
    }

    /**
     * Allows trailing spaces in snapshots.
     * @param bool $allowTrailingSpaces
     */
    public function shouldAllowTrailingSpaces(bool $allowTrailingSpaces = true): void
    {
        $this->allowTrailingSpaces = $allowTrailingSpaces;
    }

    protected function getAllowTrailingSpaces(): bool
    {
        return $this->allowTrailingSpaces;
    }

    /**
     * Allows whitespace sequences in snapshots.
     * @param bool $allowSpaceSequences
     */
    public function shouldAllowSpaceSequences(bool $allowSpaceSequences = true): void
    {
        $this->allowSpaceSequences = $allowSpaceSequences;
    }

    protected function getAllowSpaceSequences(): bool
    {
        return $this->allowSpaceSequences;
    }

    protected function save(): void
    {
        $this->dataSet = $this->removeIgnoredLines((string)$this->dataSet);
        $this->dataSet = $this->cleanContent($this->dataSet);
        $this->replaceRealValuesWithStrictPlaceholders();
        $this->replaceRealValuesWithPlaceholders();
        parent::save();
    }

    protected function load(): void
    {
        parent::load();
        $this->applyAllSubstitutions();
    }

    protected function wrapAndQuote(string $value): string
    {
        return preg_quote($this->getLeftWrapper() . $value . $this->getRightWrapper(), '/');
    }

    protected function quoteAndWrap(string $value): string
    {
        return $this->getLeftWrapper() . preg_quote($value, '/') . $this->getRightWrapper();
    }

    /**
     * Apply shouldAllowSpaceSequences and shouldAllowTrailingSpaces rules
     */
    protected function cleanContent(string $data): string
    {
        if (!$this->getAllowSpaceSequences()) {
            // clean consecutive whitespaces
            $data = preg_replace('/(\s+(?=\s))/m', '', $data);
        }
        if (!$this->getAllowTrailingSpaces()) {
            // clean trailing spaces
            $data = preg_replace('/(^\s+|\s+$)/m', '', $data);
        }

        return $data;
    }

    /**
     * Replaces placeholders with real values using boundaries.
     * @see setSubstitutions
     */
    protected function applyAllSubstitutions(): void
    {
        foreach (array_merge($this->substitutions, $this->strictSubstitutions) as $placeholder => $value) {
            $placeholder = $this->wrapAndQuote($placeholder);
            $this->dataSet = preg_replace("/$placeholder/", $value, (string)$this->dataSet);
        }
    }

    /**
     * Removes ignored lines defined by setIgnoredLinesPatterns.
     */
    protected function removeIgnoredLines(string $data): string
    {
        foreach ($this->ignoredLinesPatters as $pattern) {
            $data = preg_replace($pattern, '', $data);
        }

        return $data;
    }

    protected function replaceRealValueWithPlaceholder(
        string $value,
        string $placeholder,
        bool $withBoundaries = false
    ): void {
        $value = preg_quote($value, '/');
        $placeholder = $this->quoteAndWrap($placeholder);
        $regex = $withBoundaries ? "/\b$value\b/" : "/$value/";
        $this->dataSet = preg_replace($regex, $placeholder, (string)$this->dataSet);
    }

    /**
     * Replaces the real values in the snapshot with placeholders using boundaries `\b`.
     */
    protected function replaceRealValuesWithStrictPlaceholders(): void
    {
        if (count($this->strictSubstitutions) !== count(array_filter($this->strictSubstitutions))) {
            $this->fail('Error while saving snapshot: one or more strict substitutions is empty.');
        }

        foreach ($this->strictSubstitutions as $placeholder => $value) {
            $this->replaceRealValueWithPlaceholder($value, $placeholder, withBoundaries: true);
        }
    }

    /**
     * Replaces the real values in the snapshot with placeholders.
     */
    protected function replaceRealValuesWithPlaceholders(): void
    {
        if (count($this->substitutions) !== count(array_filter($this->substitutions))) {
            $this->fail('Error while saving snapshot: one or more substitutions is empty.');
        }

        foreach ($this->substitutions as $placeholder => $value) {
            $this->replaceRealValueWithPlaceholder($value, $placeholder);
        }
    }

    protected function fetchData(): array|string|false
    {
        $data = $this->fetchDynamicData();
        if (!$data) {
            throw new ContentNotFound("Fetched dynamic snapshot is empty.");
        }

        $data = $this->removeIgnoredLines($data);
        $data = $this->cleanContent($data);

        return $data;
    }

    protected function getSubstitutionsOutput(): string
    {
        $output = '';
        if (count($this->substitutions) === 0) {
            return $output;
        }
        try {
            $substitutions = [];
            foreach ($this->substitutions as $key => $value) {
                $substitutions[str_replace($this->substitutionPrefix, '', $key)] = OutputFormatter::escape($value);
            }
            $output = 'Substitutions:' . PHP_EOL . print_r($substitutions, true);
        } catch (Throwable $t) {
            $output = 'Count not get substitutions output. Failed with error: ' . $t->getMessage();
        } finally {
            return PHP_EOL . PHP_EOL . $output . PHP_EOL;
        }
    }

    protected function getStrictSubstitutionsOutput(): string
    {
        $output = '';
        if (count($this->strictSubstitutions) === 0) {
            return $output;
        }
        try {
            $substitutions = [];
            foreach ($this->strictSubstitutions as $key => $value) {
                $substitutionKey = str_replace($this->strictSubstitutionPrefix, '', $key);
                $substitutions[$substitutionKey] = OutputFormatter::escape($value);
            }
            $output = 'Strict substitutions:' . PHP_EOL . print_r($substitutions, true);
        } catch (Throwable $t) {
            $output = 'Count not get strict substitutions output. Failed with error: ' . $t->getMessage();
        } finally {
            return PHP_EOL . PHP_EOL . $output . PHP_EOL;
        }
    }

    public function assert(): void
    {
        try {
            parent::assert();
        } catch (ExpectationFailedException $exception) {
            if ($this->showDiff) {
                $substitutionsOutput = $this->getSubstitutionsOutput();
                $strictSubstitutionsOutput = $this->getStrictSubstitutionsOutput();
                $message = $exception->getMessage() . $substitutionsOutput . $strictSubstitutionsOutput;
                throw new ExpectationFailedException(
                    $message,
                    $exception->getComparisonFailure(),
                    $exception,
                );
            }
        }
    }

    /**
     * Should return dynamic data from current test run
     */
    abstract protected function fetchDynamicData(): string;
}
