# OSFX
Wordpress plugin for (non breaking) OSF Shownotes.

## Dev-Setup
Install [composer](https://getcomposer.org/) to download the dependencies, then execute `composer install`.

## CLI-Version
This plugin offers a commandline version via `cli.php`. It reads OSF form stdin and returns the parsed document
as JSON via stdout. It is not possible to invoke this script via the browser. All php-level errors or warnings
which might occur while executing it are silenced, so the output is always valid json.

## License
MIT
