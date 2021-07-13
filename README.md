# Codeception Dynamic Snapshots Module

![Build Status](https://github.com/fkupper/module-dynamic-snapshots/actions/workflows/php.yml/badge.svg?branch=master)
[![Coverage Status](https://coveralls.io/repos/github/fkupper/module-dynamic-snapshots/badge.svg?branch=master)](https://coveralls.io/github/fkupper/module-dynamic-snapshots?branch=master)
[![Total Downloads](https://poser.pugx.org/fkupper/module-dynamic-snapshots/downloads)](//packagist.org/packages/fkupper/module-dynamic-snapshots)
[![Monthly Downloads](https://poser.pugx.org/fkupper/module-dynamic-snapshots/d/monthly)](//packagist.org/packages/fkupper/module-dynamic-snapshots)


This is a module that can be used together with [Codeception](https://github.com/Codeception/Codeception) to test Snapshots with dynamic data.

# Installation

Using composer:
``` shell
composer require "fkupper/module-dynamic-snapshots"
```

On your `codeception.yml` file, add:
``` yml
extensions:
    commands:
        - Fkupper\Command\GenerateDynamicSnapshot
```

# Usage

## Creating Snapshots
New snapshots cna be created using codeception client with the custom command in this package. Eg:
``` shell
php ./vendor/bin/codecept generate:dynamicsnapshot Acceptance Products
```

## Fetching dynamic snapshot data
Similar to vanilla Codeception snapshots, the DynamicSnapshots classes will fetch data using the method `fetchDynamicData`. So, in your snapshots you will have to implement this method:
```php
class FooSnapshot extends DynamicSnapshot
{
    /**
     * @var Tester
     */
    protected $tester;

    public function fetchDynamicData()
    {
        // fetch snapshot from a helper method or snomething and return
        return $this->tester->fetchDataFromSomewhere();
    }
}
```

## Substitutions
This is the main feature of this package.
When dealing with variable data in snapshots, they can be replaced with placeholders and replaced back in runtime everytime the snapshot tests are executed.

For example, if you want to test a XML API response containing static data and variable data:
```xml
<xml>
    <appVersion value="v8.8.9" />
    <someOtherNonDynamicData value="foo" />
    <bar value="baz" />
</xml>
```

The property `"appVersion"` can change anytime and to avoid updating it every time, use `setSubstitutions`:

```php
class FooSnapshot extends DynamicSnapshot
{
    /**
     * @var Tester
     */
    protected $tester;

    public function fetchDynamicData()
    {
        $this->setSubstitutions(
            // $this->tester->getAppVersion() returns "v8.8.9"
            'app_version' => $this->tester->getAppVersion()
        );
        // fetch snapshot from a helper method or snomething and return
        return $this->tester->fetchDataFromXml();
    }
}
```

The first time the dynamic snapshot test is executed, a snapshot **data** file will be created like:
```xml
<xml>
    <appVersion value="[snapshot_app_version]" />
    <someOtherNonDynamicData value="foo" />
    <bar value="baz" />
</xml>
```
Note that placeholders are wrapped in `[ ]`, and from now on, whenever the app version changes, the snapshot will not break or require an update.

## Using the dynamic snapshot classes in tests
Please refer to [Codepcetion's standard Snapshot documentation](https://codeception.com/docs/09-Data#Testing-Dynamic-Data-with-Snapshots).

## Custom placeholder wrappers
By default, placeholders are wrapped in brackets `[ ]`, but it is possible to change what character or sequence of charaters it should use.

For example, if brackets are sensible part of your snapshot data, you can change it to something else using `setWrappers`:

```php
class FooSnapshot extends DynamicSnapshot
{
    protected $tester;

    public function __constructor(Tester $I)
    {
        $this->tester = $I;
        $this->setWrappers('{', '}');
        // $this->setWrappers('(', ')');
        // $this->setWrappers('<', '>');
    }
}
```

## Ignoring parts of the snapshot data
If your snapshot have variable data that cannot be tested or that you just want to igore, it is possible to provide a list of regular expression patterns that will be removed from the data when asserting.

For example, in the snapshot data below, the current timestamp in the favicon href property have to be ignored:
```html
<html>
    <head>
        <!-- the favicon will always have the current timestamp suffix -->
        <link rel="shortcut icon" href="/favicon.ico?v=1588930951">
    </head>
</html>
```
So you can ignore this line by calling `setIgnoredLinesPatterns` from your snapshot object:
```php
$this->setIgnoredLinesPatterns(['/^.*favicon.*$/m']);
```

## Handling space sequences
Sometimes your snapshot data can have variable amounts of space characters in sequence that change out of your control.

To toggle your snapshot behavior to ignore these spaces or not, use `shouldAllowSpaceSequences(true|false)`. Setting it to true will compact these space sequences to a single space character.

Note that the default is `true`, so these sequences will never be reduced to one space if you do not specify them to.

## Handling trailing spaces
If you want to clear every line of your snapshot data of trailing spaces, use `shouldAllowTrailingSpaces(false)`.

Note that the default is `true`, so trailing spaces in your snapshot data will never be removed.
