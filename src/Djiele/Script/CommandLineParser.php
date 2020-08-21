<?php

namespace Djiele\Script;

use stdClass;

class CommandLineParser
{
    const SHORT_OPTION = '-';
    const LONG_OPTION = '--';
    const VALUE_REQUIRED = ':';
    const VALUE_OPTIONAL = '::';
    const REQUIRED_LABEL = 'required';
    const OPTIONAL_LABEL = 'optional';
    const FLAG_LABEL = 'flag';
    const INLINE_CONTENT = '<inline>';
    const CURRENT_OPTION_TEMP_KEY = '@currentOptionName@';

    protected $argc;
    protected $argv;
    protected $customOptions;

    /**
     * CommandLineParser constructor.
     * @param array $customOptions
     */
    public function __construct(array $customOptions)
    {
        global $argc, $argv;

        $this->argc = &$argc;
        $this->argv = &$argv;
        $this->buildCustomOptionsFromArray($customOptions);
    }

    /**
     * Get the parsed options array
     * @return array
     */
    public function parse()
    {
        $ret = [];
        for ($i = 1; $i < $this->argc; $i++) {
            $this->parseToken($i, $ret);
        }
        $this->clearAtKeys($ret);
        $this->dieIfMissingArgument($ret);

        return $ret;
    }

    /**
     * Get the missing mandatory arguments
     * @param array $parsedOptions
     */
    public function dieIfMissingArgument(array $parsedOptions)
    {
        foreach ($this->customOptions as $key => $option) {
            if ($key === $option->longName && self::REQUIRED_LABEL === $option->kind && !array_key_exists($option->longName, $parsedOptions)) {
                die('Required option is missing: ' . self::LONG_OPTION . $option->longName . ' or ' .  self::SHORT_OPTION . $option->shortName);
            }
        }
    }

    /**
     * Find argument name in custom arguments table
     * @param $key
     * @return mixed|null
     */
    protected function lookupCustomOption($key)
    {
        $keyLen = strlen($key);
        $optionNameBuffer = '';
        $foundKey = false;
        for ($i = 0; $i < $keyLen; $i++) {
            if (in_array($key[$i], ['=', '"', '\''], true)) {
                $foundKey = $optionNameBuffer;
                break;
            }
            $optionNameBuffer .= $key[$i];
        }
        if (false === $foundKey) {
            for ($i = $keyLen; $i > -1; $i--) {
                $optionNameBuffer = substr($key, 0, $i);
                if (array_key_exists($optionNameBuffer, $this->customOptions)) {
                    $foundKey = $optionNameBuffer;
                    break;
                }
            }
        }

        return $this->customOptions[$foundKey] ?? null;
    }

    /**
     * Set custom arguments table
     * @param array $customOptions
     */
    protected function buildCustomOptionsFromArray(array $customOptions)
    {
        $this->customOptions = [];
        foreach ($customOptions as $shortName => $longName) {
            $kind = preg_replace('/^[^\'' . self::VALUE_REQUIRED . ']+/', '', $longName);
            if (self::VALUE_REQUIRED === $kind) {
                $kind = self::REQUIRED_LABEL;
            } else if (self::VALUE_OPTIONAL === $kind) {
                $kind = self::OPTIONAL_LABEL;
            } else {
                $kind = self::FLAG_LABEL;
            }

            $this->customOptions[$shortName] = (object)[
                'shortName' => $shortName,
                'longName' => rtrim($longName, self::VALUE_REQUIRED),
                'kind' => $kind
            ];
            $this->customOptions[$this->customOptions[$shortName]->longName] = $this->customOptions[$shortName];
        }
    }

    /**
     * Process the current command line token
     * @param $index
     * @param array $parsedOptions
     */
    protected function parseToken(&$index, array &$parsedOptions)
    {
        if (self::SHORT_OPTION === $this->argv[$index][0]) {

            $this->parseOption($index, $parsedOptions);

        } else {

            $this->setOptionValue($index, $parsedOptions);

        }
    }

    /**
     * Process the current command line token as an option name
     * @param $index
     * @param array $parsedOptions
     */
    protected function parseOption(&$index, array &$parsedOptions)
    {
        $this->clearAtKeys($parsedOptions);
        $token = ltrim($this->argv[$index], self::SHORT_OPTION);
        if (null === ($option = $this->lookupCustomOption($token))) {
            if (self::LONG_OPTION === $this->argv[$index]) {
                $parsedOptions[self::CURRENT_OPTION_TEMP_KEY] = self::INLINE_CONTENT;
                if (array_key_exists(self::INLINE_CONTENT, $parsedOptions) && !is_array($parsedOptions[self::INLINE_CONTENT])) {
                    $parsedOptions[self::INLINE_CONTENT] = [$parsedOptions[self::INLINE_CONTENT], ''];
                }
            } else {
                die('Unknown option: ' . $this->argv[$index]);
            }
        } else {
            $parsedOptions[self::CURRENT_OPTION_TEMP_KEY] = $option->longName;
            $value = self::FLAG_LABEL === $option->kind
                ? true
                : $this->parseMergedValue($option, $token);
            if (null !== $value) {
                $this->setOptionValue($index, $parsedOptions, $value);
            }
            if (true === $value) {
                unset($parsedOptions[self::CURRENT_OPTION_TEMP_KEY]);
            }
        }
    }

    /**
     * Get the merged value of current command line option
     * @param stdClass $option
     * @param $string
     * @return false|string|null
     */
    protected function parseMergedValue(stdClass $option, $string)
    {
        if (false === ($pos = strpos($string, '='))) {
            if (0 === strpos($string, $option->longName)) {
                $string = substr($string, strlen($option->longName));
            } elseif (0 === strpos($string, $option->shortName)) {
                $string = substr($string, strlen($option->shortName));
            } else {
                $string = null;
            }
            if ('' === $string) {
                $string = null;
            }
        } else {
            $string = substr($string, $pos + 1);
        }

        return $string;
    }

    /**
     * Associate the current command line value to the current option
     * @param $index
     * @param array $parsedOptions
     * @param null $value
     */
    protected function setOptionValue(&$index, array &$parsedOptions, $value = null)
    {
        if (null === $value) {
            $value = $this->argv[$index];
        }
        if (isset($parsedOptions[self::CURRENT_OPTION_TEMP_KEY])) {
            $key = $parsedOptions[self::CURRENT_OPTION_TEMP_KEY];
            if ($key === self::INLINE_CONTENT) {
                if (isset($parsedOptions[$key])) {
                    if (is_array($parsedOptions[$key])) {
                        $writeTarget = &$parsedOptions[$key][count($parsedOptions[$key]) - 1];
                    } else {
                        $writeTarget = &$parsedOptions[$key];
                    }
                    if ('' !== $value && ' ' === $value[0]) {
                        $value = substr($value, 1);
                    }
                    if ('' !== $value && ' ' === substr($value, -1)) {
                        $value = substr($value, 0, -1);
                    }
                    if ('' === $writeTarget) {
                        $prepend = '';
                    } else {
                        $prepend = ' ';
                    }
                    $writeTarget .= $prepend . $value;
                } else {
                    $parsedOptions[$key] = $value;
                }
            } else if (isset($parsedOptions[$key])) {
                if (is_array($parsedOptions[$key])) {
                    $parsedOptions[$key][] = $value;
                } else {
                    $parsedOptions[$key] = [$parsedOptions[$key], $value];
                }
            } else {
                $parsedOptions[$key] = $value;
            }
        } else {
            $parsedOptions[$index] = $value;
        }
    }

    /**
     * Clear all temporary parsing keys
     * @param array $parsedOptions
     */
    protected function clearAtKeys(array &$parsedOptions)
    {
        foreach (array_keys($parsedOptions) as $key) {
            $key = (string)$key;
            if ('' !== $key && '@' === $key[0] && '@' === substr($key, -1)) {
                unset($parsedOptions[$key]);
            }
        }
    }
}