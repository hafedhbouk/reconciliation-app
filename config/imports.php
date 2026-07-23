<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Import chunk size
    |--------------------------------------------------------------------------
    |
    | Number of rows ProcessImportJob batches per DB transaction (bulk insert
    | into import_rows/transactions/normalized_transactions, one status update
    | to imports per chunk instead of per row).
    |
    */
    'chunk_size' => env('IMPORT_CHUNK_SIZE', 500),
];
