# datapatch

A tool for execute databases migrations into multiples databases across multiple
servers.

## Why another migration tool?

Database migration tools are made for applications that have a single database
schema on a single database server.

`datapatch` was made with a multi-schemas setup in mind.

It is not unusual to encounter large and legacy applications with diferent
schemas.


## Requirements

To install and run **datapatch** you must have:

 * PHP >= 5.6 with PDO extension
 * `cat` command on path
 * `mysql` client on path
 * [Composer](https://getcomposer.org/) dependency manager

## Installing

Install it as a regular package via composer:

    composer require jairocgr/datapatch

## Usage

You can call it as a regular php command line tool:

    php vendor/bin/datapatch list

## Getting Started

Inside your project folder run:

    php vendor/bin/datapatch init

The `init` command will setup the basic structure required.

```
project-dir/
  |- db/
  |  |- bundles/
  |  |- patches/
  +- datapatch.config.php
```

## `datapatch.config.php`

The configuration file where you set the environments, database hosts,
passwords and how to apply the database patches.

> For a commented and more complete configuration file see the sample
> `datashot.config.php` file inside this repository root directory

## Patches

With *datapatch* you don't write _migrations_, you write _*patches*_.

Patches are the basic unit-of-change and are made by a directory with one or more
native SQL scripts.

To generate a new patch named `201912231531` you can run the command:

    php vendor/bin/datapatch gen:patch 201912231531

Then the patch directory will be create inside the patches folder:

```
project-dir/
  |- db/
     |- patches/
        |- 201911011819/
        |- 201912231531/
           +- change_on_schema1.sql
           +- change_on_schema2.sql
           +- views.sql
        |- 201912270907/
```

In the example above, you have a patch named `201912231531` containing 3 scripts.

To apply the forementioned patch you can run the following command and optionaly
informing the target environments

    php vendor/bin/datapatch apply 201912231531 --env staging

The `apply` command know how to apply a patch by reading the
`datapatch.config.php` file.

### Patch naming

You can addopt a naming scheme of your choose but it should be a cannonical date
time string (ex: 201912122520) or a project management tool task number
(ex: TASK-21445, BUG-231).

## Bundles

Bundles are YAML files listing a group of patches that should be applied
together in a particular order.

Bundles are very usefull when you whant to bundle up a group of patches for a
particular version release:

```yaml
#
# Bundle v1.7.23.yml for the version release 1.7.23
#
# The listed patches will be applyed in this order
#

TASK-2123
TASK-3010
TASK-2125

BUG-212
BUG-209
```

To deploy the bundle `v1.7.23` you must run

    php vendor/bin/datapatch deploy v1.7.23 --env production

## More

To list all the available commands run

    php vendor/bin/datapatch list

## License

This project is licensed under the MIT License - see the
[LICENSE](LICENSE) file for details
