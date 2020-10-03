<?php

namespace Codeception;

use Codeception\Exception\ContentNotFound;

abstract class DynamicSnapshot extends Snapshot
{
    /** @var string */
    protected $leftWrapper = '[';

    /** @var string */
    protected $rightWrapper = ']';

    /** @var string */
    protected $substitutionPrefix = 'snapshot_';

    /** @var array<string,string> */
    protected $substitutions = [];

    /** @var array<string> */
    protected $ignoredLinesPatters = [];

    /** @var bool */
    protected $allowTrailingSpaces = false;

    /** @var bool */
    protected $allowSpaceSequences = false;

    /**
     * Set what charaters will be used to wrap substitution keys.
     * Default is []
     *
     * @param string $leftWrapper = '['
     * @param string $rightWrapper = ']'
     * @return void
     */
    public function setWrappers(string $leftWrapper = '[', string $rightWrapper = ']'): void
    {
        if (count([$leftWrapper, $rightWrapper]) !== count(array_filter([$leftWrapper, $rightWrapper]))) {
            $this->fail('Wrappers cannot be empty strings.');
        }

        $this->leftWrapper = $leftWrapper;
        $this->rightWrapper = $rightWrapper;
    }

    /**
     * @return string
     */
    protected function getLeftWrapper(): string
    {
        return $this->leftWrapper;
    }

    /**
     * @return string
     */
    protected function getRightWrapper(): string
    {
        return $this->rightWrapper;
    }

    /**
     * Sets the array of substitutions containing keys as the keys and the
     * replacement as the values.
     * Eg:
     * ['user_id' => '99', 'some_dynamic_path' => '/foo/path/123/']
     *
     * @param array<string,string> $substitutions
     * @return void
     */
    public function setSubstitutions(array $substitutions): void
    {
        foreach ($substitutions as $key => $value) {
            $this->substitutions[$this->substitutionPrefix . $key] = $value;
        }
    }

    /**
     * Sets an array of regex patterns that will be used to remove lines that matches them
     * both from expected and actual snapshot value.
     *
     * @param array<string> $patterns
     * @return void
     */
    public function setIgnoredLinesPatterns(array $patterns): void
    {
        $this->ignoredLinesPatters = $patterns;
    }

    /**
     * Allows trailing spaces in snapshots.
     *
     * @param bool $allowTrailingSpaces
     * @return void
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
     *
     * @param bool $allowSpaceSequences
     * @return void
     */
    public function shouldAllowSpaceSequences(bool $allowSpaceSequences = true): void
    {
        $this->allowSpaceSequences = $allowSpaceSequences;
    }

    protected function getAllowSpaceSequences(): bool
    {
        return $this->allowSpaceSequences;
    }

    /**
     * @return void
     */
    protected function save()
    {
        $this->dataSet = $this->removeIgnoredLines($this->dataSet);
        $this->dataSet = $this->cleanContent($this->dataSet);
        $this->replaceRealValues();
        parent::save();
    }

    /**
     * @return void
     */
    protected function load()
    {
        parent::load();
        $this->applySubstitutions();
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
     *
     * @param string $data
     * @return string
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
     * Replaces values with placeholder keys.
     *
     * @see setSubstitutions
     * @return void
     */
    protected function applySubstitutions(): void
    {
        foreach ($this->substitutions as $pattern => $replacement) {
            $pattern = $this->wrapAndQuote($pattern);
            $this->dataSet = preg_replace("/$pattern/", $replacement, $this->dataSet);
        }
    }

    /**
     * Removes ignored lines defined by setIgnoredLinesPatterns.
     *
     * @return string
     */
    protected function removeIgnoredLines(string $data): string
    {
        foreach ($this->ignoredLinesPatters as $pattern) {
            $data = preg_replace($pattern, '', $data);
        }

        return $data;
    }

    /**
     * Replaces the real values in the snpashot by the keys.
     *
     * @return void
     */
    protected function replaceRealValues(): void
    {
        if (count($this->substitutions) !== count(array_filter($this->substitutions))) {
            $this->fail('Error while saving snapshot: one or more substitutions is empty.');
        }

        foreach ($this->substitutions as $pattern => $replacement) {
            $replacement = preg_quote($replacement, '/');
            $pattern = $this->quoteAndWrap($pattern);
            $this->dataSet = preg_replace("/$replacement/", $pattern, $this->dataSet);
        }
    }

    protected function fetchData()
    {
        $data = $this->fetchDynamicData();
        if (!$data) {
            throw new ContentNotFound("Fetched dynamic snapshot is empty.");
        }

        $data = $this->removeIgnoredLines($data);
        $data = $this->cleanContent($data);

        return $data;
    }

    /**
     * Should return dynamic data from current test run
     *
     * @return string
     */
    abstract protected function fetchDynamicData();
}
