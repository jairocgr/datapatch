# datapatch

A tool for execute databases migrations into multiples databases across multiple
servers.

## Requirements

To install and run **datapatch** you must have:

 * PHP >= 5.6 with PDO extension
 * `zlib` PHP extension for gzip compression
 * [Composer](https://getcomposer.org/) dependency manager

## Installing

Install it as a regular package via composer:

    composer require jairocgr/datapatch

## Usage

After requiring **datapatch**, you can call it as a php command line tool:

   php vendor/bin/datapatch list

## License

This project is licensed under the MIT License - see the
[LICENSE](LICENSE) file for details
