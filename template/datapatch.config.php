<?php return [

  // The current runtime enviroment. You can also override this by
  // the command line option
  'env' => env('ENV', 'development'),

  // Databases servers you wish to apply patches, bundles and execute
  // excute sql scripts
  'database_servers' => [
    'appserver0' => [
      'driver'   => 'mysql', # Maybe PostgreSQL in the future
      'socket'   => env('MYSQL56_SOCKET', ''),
      'host'     => env('MYSQL56_HOST', 'localhost'),
      'port'     => env('MYSQL56_PORT', 3306),

      'user'     => env('MYSQL56_USER', 'root'),
      'password' => env('MYSQL56_PASSWORD', 'root'),

      // You can override the parameters based upon the chosen environment
      'production' => [
        'host'     => env('MYSQL_PRODUCTION_HOST', ''),
        'user'     => env('MYSQL_PRODUCTION_USER', ''),
        'password' => env('MYSQL_PRODUCTION_PASSWORD', '')
      ]

      // You could also setup others environments
      // 'staging' => [
      //   'host' => '...'
      // ]
    ],
  ],

  // Scripts running configurations
  'scripts' => [
    // Script change.sql to be executed in all "erp_*" schemas
    // inside "appserver0" servers
    'change' => [
      'databases' => 'erp_*',
      'servers' => 'appserver0'
    ],
  ]

];
