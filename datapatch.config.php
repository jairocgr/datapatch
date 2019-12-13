<?php return [

  // The current runtime enviroment. You can also override this by
  // the command line option
  'env' => env('ENV', 'development'),

  // List all allowed environments and their properties
  'environments' => [
    'development' => [
      // Env color can be one of the default ASCII terminal colors:
      // red, green, blue, yellow, magenta, cyan or white
      'color' => 'blue'
    ],

    'staging' => [
      'color' => 'yellow',
      // If protected, you will have to confirm before run a
      // datapatch command
      'protected' => TRUE
    ],

    'production' => [
      'color' => 'red',
      // Datapatch will set production as protected no matter what
      // this value says
      'protected' => TRUE
    ]
  ],

  // Databases servers you wish to apply patches, bundles and execute
  // excute sql scripts
  'database_servers' => [
    'mysql56' => [
      'driver'   => 'mysql', # Maybe PostgreSQL in the future
      'socket'   => env('MYSQL56_SOCKET', ''),
      'host'     => env('MYSQL56_HOST', 'localhost'),
      'port'     => env('MYSQL56_PORT', 3306),

      'user'     => env('MYSQL56_USER', 'root'),
      'password' => env('MYSQL56_PASSWORD', 'root'),

      // You can override the parameters based upon the chosen environment
      'production' => [
        'host'     => env('MYSQL_PRODUCTION_HOST', ''),
        'port'     => env('MYSQL_PRODUCTION_PORT', 3306),
        'user'     => env('MYSQL_PRODUCTION_USER', ''),
        'password' => env('MYSQL_PRODUCTION_PASSWORD', '')
      ]

      // You could also setup others environments
      // 'stating' => [
      //   'host' => '...'
      // ]
    ],

    'mysql57' => [
      'driver'   => 'mysql',
      'socket'   => env('MYSQL57_SOCKET', ''),
      'host'     => env('MYSQL57_HOST', 'localhost'),
      'port'     => env('MYSQL57_PORT', 3306),

      'user'     => env('MYSQL57_USER', 'root'),
      'password' => env('MYSQL57_PASSWORD', 'root'),

      'production' => [
        'host'     => env('MYSQL_PRODUCTION_HOST', ''),
        'port'     => env('MYSQL_PRODUCTION_PORT', 3306),
        'user'     => env('MYSQL_PRODUCTION_USER', ''),
        'password' => env('MYSQL_PRODUCTION_PASSWORD', '')
      ]
    ],

    'local' => [
      'driver'   => 'mysql',
      'socket'   => env('MYSQL_LOCAL_SOCKET', ''),
      'host'     => env('MYSQL_LOCAL_HOST', 'localhost'),
      'port'     => env('MYSQL_LOCAL_PORT', 3306),

      'user'     => env('MYSQL_LOCAL_USER', 'root'),
      'password' => env('MYSQL_LOCAL_PASSWORD', 'root'),

      'production' => [
        'socket'   => env('MYSQL_PRODUCTION_SOCKET', ''),
        'host'     => env('MYSQL_PRODUCTION_HOST', ''),
        'port'     => env('MYSQL_PRODUCTION_PORT', 3306),
        'user'     => env('MYSQL_PRODUCTION_USER', ''),
        'password' => env('MYSQL_PRODUCTION_PASSWORD', '')
      ]
    ],
  ],

  // Scripts running configurations
  'scripts' => [
    // zun.sql file to be execute in "zun" database at "mysql56" server
    'zun'     => 'mysql56',
    'reports' => 'mysql56',

    // pap.sql to be executed in all "zun_*" database inside all three servers
    'pap' => [
      'databases' => 'zun_*',
      'servers' => [ 'mysql5*', 'local' ],
      'after' => [ 'zun', 'reports', 'log' ]
    ],

    'log' => [
      // log.sql file at "logs" database
      'databases' => 'logs',
      'servers' => 'mysql57'
    ],

    'north' => [
      'databases' => [ 'zun_ma', 'zun_ro', 'zun_rr' ],
      'servers' => 'local',

      // Run north.sql only after pap.sql
      'after' => 'pap',

      // Generator command won't generate north.sql
      'generate_script' => FALSE
    ],

    'policy' => [
      'databases' => 'zun_m*',
      'servers' => 'local',

      // After pap.sql and north.sql
      'after' => [ 'pap', 'north' ],

      // Generator command won't generate policy.sql
      'generate_script' => FALSE
    ],

    'telemetry' => [
      'databases' => 'zun',
      'servers' => 'mysql56',
      'generate_script' => FALSE,

      // if production use another schema and server
      'production' => [
        'databases' => 'logs',
        'servers' => 'mysql57',
      ]
    ]
  ]

];
