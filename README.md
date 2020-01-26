# datapatch

A tool for execute databases migrations into multiples databases across multiple
servers.

## Why another migration tool?

Database migration tools are made for applications that have a single database
schema deployed on a single database server.

`datapatch` was made with a multi-schema setup in mind.

It is not unusual for legacy applications to have diferent schemas across
multiple servers and regions.

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

The `init` command will setup the basic structure required:

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

With `datapatch` you don't write _migrations_, you write _*patches*_.

Patches are the basic unit-of-change and are made by a directory with one or
more native SQL scripts.

#### Creating Patches

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
        |- ...
```

In the example above, you have a patch named `201912231531` containing 3 scripts.

#### Scripts

Patch scripts it's where you write your database scripts

```SQL
-- 201912231531/change_on_schema1.sql

ALTER TABLE employee MODIFY age INT NOT NULL;
ALTER TABLE users ADD COLUMN...
```

You should delete empty script files and keep patches clean and tight
as possible.

#### Applying a Patch

To apply the forementioned patch you can run the following command and optionaly
informing the target environments

    php vendor/bin/datapatch apply 201912231531 --env staging

The `apply` command know how to apply a patch by reading the
`datapatch.config.php` file.

#### Appling all non-applied Patches

Just just like a regular `migrate` command on others database migrations tools,
you can also apply all the non-applied patches with the command:

    php vendor/bin/datapatch apply-all

It will try to apply all the patches in alphabetical order.

#### Patch naming

You can adopt a naming scheme of your choose but it should be a cannonical date
time string (ex: 201912122520) or a project management tool task number
(ex: TASK-21445, BUG-231).

## Errors and Unfinished Patches

If a script inside a patch raise an error or had his execution interrupted and
was left unfinished, you will not be able to resume the patch application until
you inform *datapatch* that you fix any wrong database state left by unfinished/errored script.

After you fix the script code and the database, you must mark it as executed
in the database that was left dangling:

    php vendor/bin/datapatch mark-executed TASK-1232/change_schema1.sql -d {errored_database}

Only then you will be able to continue the execution of the patch.

## Transactions

It is your job to put (or don't) transactions inside your scripts.

The *dapatch* will not wrap your scripts inside a transaction.

> Remember that MySQL transactions doesn't rollback DDL changes

## Bundles

Bundles are YAML files listing a group of patches that should be applied
together in a particular order.

Bundles are very usefull when you want to bundle up a group of patches for a
particular version release.

#### Create a Bundle

To create a bundle you should run:

    php vendor/bin/datapatch gen:bundle v1.7.23

Then the bundle `v1.7.23` will be created at the bundles directories:

```
project-dir/
  |- db/
     |- bundles/
        +- v1.6.0.yml
        +- v1.7.7.yml
        +- v1.7.23.yml
```

Then is up to you to list all the patches inside the bundle file:

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

#### Apply (deploy) a Bundle

To deploy a bundle at the environment of your choose, you must run:

    php vendor/bin/datapatch deploy v1.7.23 --env production

## Rollback

The `datapatch` does not support rollback/down migrations by design.

It is up to you to manually carefully undo a patch.

## More

To list all the available commands run

    php vendor/bin/datapatch list

## License

This project is licensed under the MIT License - see the
[LICENSE](LICENSE) file for details
