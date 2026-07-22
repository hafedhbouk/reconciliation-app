<?php

namespace App\Enums;

enum FileType: string
{
    case Csv = 'csv';
    case Xls = 'xls';
    case Xlsx = 'xlsx';
}
