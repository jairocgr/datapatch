#!/usr/bin/env bash

# Exit immediately if something returns a non-zero status
set -o errexit

# If set, the return value of a pipeline is the value of the last (rightmost)
# command to exit with a non-zero status, or zero if all commands in the
# pipeline exit successfully. This option is disabled by default.
set -o pipefail

# Exit your script if you try to use an uninitialised variable
set -o nounset

SCRIPT_PATH="$(realpath ${BASH_SOURCE[0]})"
PROJECT_ROOT="$(realpath $(dirname $SCRIPT_PATH)/../)"

function load_env {
  if ! [[ -f .env ]]; then
    cp .env.example .env
  fi

  set -a
  . .env
  set +a
}

function hide_passwd_warn {
  grep -v "Using a password on the command line interface can be insecure" || true
}

function env {
  echo -n "${!1}"
}

function createdb {
  local mysql_version=$1
  local dbname=$2
  local charset=$3
  local collation=$4

  local host="$(env MYSQL${mysql_version}_HOST)"
  local port="$(env MYSQL${mysql_version}_PORT)"
  local user="$(env MYSQL${mysql_version}_USER)"
  local passwd="$(env MYSQL${mysql_version}_PASSWORD)"

  echo "Creating $dbname at mysql$mysql_version..."

  echo "DROP DATABASE IF EXISTS $dbname" \
    | mysql -h $host -P $port -u $user -p$passwd 2>&1 \
    | hide_passwd_warn

  echo "CREATE DATABASE $dbname CHARSET $charset COLLATE $collation" \
    | mysql -h $host -P $port -u $user -p$passwd 2>&1 \
    | hide_passwd_warn

  cat script/schema.sql \
    | mysql -h $host -P $port -u $user -p$passwd $dbname 2>&1 \
    | hide_passwd_warn
}

function dropdb {
  local mysql_version=$1
  local dbname=$2

  local host="$(env MYSQL${mysql_version}_HOST)"
  local port="$(env MYSQL${mysql_version}_PORT)"
  local user="$(env MYSQL${mysql_version}_USER)"
  local passwd="$(env MYSQL${mysql_version}_PASSWORD)"

  echo "Droping $dbname at mysql$mysql_version..."

  echo "DROP DATABASE IF EXISTS $dbname" \
    | mysql -h $host -P $port -u $user -p$passwd 2>&1 \
    | hide_passwd_warn
}

function createdbs {
  createdb 56 zun utf8 utf8_general_ci
  createdb 56 zun_rs utf8 utf8_general_ci
  createdb 56 zun_pr utf8 utf8_general_ci
  createdb 56 reports latin1 latin1_swedish_ci

  createdb 57 zun_ms latin1 latin1_swedish_ci
  createdb 57 zun_mt latin1 latin1_swedish_ci
  createdb 57 zun_mg latin1 latin1_swedish_ci
  createdb 57 zun_sp latin1 latin1_swedish_ci
  createdb 57 zun_go latin1 latin1_swedish_ci

  createdb 57 logs utf8 utf8_general_ci
  createdb 57 forms utf8 utf8_general_ci

  createdb _LOCAL zun_ma utf8 utf8_general_ci
  createdb _LOCAL zun_mm utf8 utf8_general_ci
  createdb _LOCAL zun_pa utf8 utf8_general_ci
  createdb _LOCAL zun_ro utf8 utf8_general_ci
  createdb _LOCAL zun_rr utf8 utf8_general_ci

  # Simulate another environment
  createdb _PRODUCTION zun utf8 utf8_general_ci
  createdb _PRODUCTION zun_rs utf8 utf8_general_ci
  createdb _PRODUCTION zun_pr utf8 utf8_general_ci
  createdb _PRODUCTION reports latin1 latin1_swedish_ci

  createdb _PRODUCTION zun_ms latin1 latin1_swedish_ci
  createdb _PRODUCTION zun_mt latin1 latin1_swedish_ci
  createdb _PRODUCTION zun_mg latin1 latin1_swedish_ci
  createdb _PRODUCTION zun_sp latin1 latin1_swedish_ci
  createdb _PRODUCTION zun_go latin1 latin1_swedish_ci

  createdb _PRODUCTION logs utf8 utf8_general_ci
  createdb _PRODUCTION forms utf8 utf8_general_ci

  createdb _PRODUCTION zun_ma utf8 utf8_general_ci
  createdb _PRODUCTION zun_mm utf8 utf8_general_ci
  createdb _PRODUCTION zun_pa utf8 utf8_general_ci
  createdb _PRODUCTION zun_ro utf8 utf8_general_ci
  createdb _PRODUCTION zun_rr utf8 utf8_general_ci
}

function run_containers {
  docker-compose up -d || true
}

function main {
  cd $PROJECT_ROOT

  load_env
  run_containers
  createdbs
}

main
