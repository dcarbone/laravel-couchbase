<?php

return [
    'connections' => [
        'couchbase' => [
            'name'       => 'couchbase',
            'driver'     => 'couchbase',
            'port'       => 8091,
            'host'       => '127.0.0.1',
            'bucket'     => 'testing',
            'password' => 'password',
            'n1ql_hosts' => ['http://127.0.0.1:8093']
        ],
    ],

];
