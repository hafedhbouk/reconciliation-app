<?php

namespace App\Services\Matching;

use App\Models\Source;
use InvalidArgumentException;

/** Shared field definitions for matching and displaying differences between files. */
class FileComparisonRules
{
    public function criteria(Source $a, Source $b): array
    {
        $codes = array_map(fn ($source) => strtoupper($source->code) === 'STEG' ? 'WEB' : strtoupper($source->code), [$a, $b]);
        if ($codes[0] === $codes[1] || array_diff($codes, ['ALPHA', 'BNA', 'WEB', 'SMT']) !== []) {
            throw new InvalidArgumentException('Combinaison de sources non prise en charge pour la comparaison de fichiers.');
        }

        $verify = ['amount', 'date'];
        if (in_array('SMT', $codes, true)) {
            $primary = ['a' => 'date|amount', 'b' => 'date|amount'];
        } elseif (in_array('ALPHA', $codes, true) && in_array('WEB', $codes, true)) {
            $primary = ['a' => 'reference', 'b' => 'reference'];
            $verify[] = [
                'a' => $codes[0] === 'ALPHA' ? 'num_autorisation' : 'secondary_reference',
                'b' => $codes[1] === 'ALPHA' ? 'num_autorisation' : 'secondary_reference',
            ];
        } else {
            // BNA has no equivalent of ALPHA's REFERENCE.
            $primary = [
                'a' => $codes[0] === 'WEB' ? 'secondary_reference' : 'num_autorisation',
                'b' => $codes[1] === 'WEB' ? 'secondary_reference' : 'num_autorisation',
            ];
        }

        return [
            'file_comparison' => true,
            'tolerance_amount_millimes' => 0,
            'tolerance_days' => 0,
            'excluded_status_raw' => ['a' => [], 'b' => []],
            'primary_key' => $primary,
            'verify_fields' => $verify,
        ];
    }
}
