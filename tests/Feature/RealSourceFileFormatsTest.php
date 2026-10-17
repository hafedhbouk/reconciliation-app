<?php

use App\Jobs\ProcessImportJob;
use App\Models\Import;
use App\Models\NormalizedTransaction;
use App\Models\Source;
use App\Models\User;
use App\Services\Import\MappingEngine;
use App\Services\Import\Readers\ImportRowReaderFactory;
use App\Services\Import\TransactionNormalizer;
use Database\Seeders\BankSeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\SourceColumnMappingSeeder;
use Database\Seeders\SourceSeeder;
use Illuminate\Support\Facades\Storage;

/**
 * Regression coverage for a mapping mix-up that shipped twice: WEB and SMT's
 * real file structures (and CSV delimiters) got swapped. These tests run the
 * actual seeders -- not hand-rolled mappings -- against byte-for-byte real
 * sample rows, so a future edit to SourceColumnMappingSeeder/SourceSeeder
 * that reintroduces the mix-up fails here instead of in a client's import.
 */
function seedRealSources(): void
{
    (new CurrencySeeder)->run();
    (new BankSeeder)->run();
    (new SourceSeeder)->run();
    (new SourceColumnMappingSeeder)->run();
}

function runImport(Source $source, string $filename, string $csv): Import
{
    Storage::disk('local')->put("imports/{$filename}", $csv);

    $import = Import::query()->create([
        'source_id' => $source->id,
        'original_filename' => $filename,
        'stored_path' => "imports/{$filename}",
        'file_hash' => hash('sha256', $csv),
        'mime_type' => 'text/csv',
        'size_bytes' => strlen($csv),
        'status' => 'pending',
        'imported_by' => User::factory()->create()->id,
    ]);

    app(ProcessImportJob::class, ['importId' => $import->id])->handle(
        app(ImportRowReaderFactory::class),
        app(MappingEngine::class),
        app(TransactionNormalizer::class),
    );

    return $import->refresh();
}

test('WEB source file (comma-delimited, session/reference/recu_paie) imports cleanly', function () {
    Storage::fake('local');
    seedRealSources();

    $source = Source::query()->where('code', 'WEB')->firstOrFail();

    // Updated format (2026-08): session and reference are now separate
    // columns instead of the old fused "session,reference" column.
    // The legacy DATE_FORMAT column still contains a comma in its name,
    // so it must remain quoted to avoid being split into 2 headers.
    $csv = "session,reference,\"DATE_FORMAT(`date_au`, '%d%m%Y')\",montant,date_paiement,recu_paie,valid_oper\n"
        ."00001047258018969545,104725801,22012026,000000016000,\"2026-02-01 00:02:29\",b416779,1\n";

    $import = runImport($source, 'web_sample.csv', $csv);

    expect($import->status->value)->toBe('completed');
    expect($import->success_rows)->toBe(1);
    expect($import->error_rows)->toBe(0);

    $normalized = NormalizedTransaction::query()->sole();
    expect($normalized->normalized_reference)->toBe('104725801');
    expect($normalized->normalized_amount_millimes)->toBe(16000);
    expect($normalized->normalized_date->format('Y-m-d'))->toBe('2026-02-01');
});

test('SMT source file (semicolon-delimited payment-gateway export) imports cleanly', function () {
    Storage::fake('local');
    seedRealSources();

    $source = Source::query()->where('code', 'SMT')->firstOrFail();

    $header = "Order type;Nom de l'acqu?reur;Date;IP;Identification de la commande;Canal de l'appareil;"
        .'Etat du paiement;Delayed Clearing Status;Nom du marchand;Connexion du commer?ant;Paiement initial;'
        ."Description;New Payment date;New Deposit date;Date d'annulation;Montant;Certificate amount;"
        .'Total amount;Devise;Montant approuv?;Montant d?pos?;Refunded amount;Fee amount;Poids de la fraude;'
        .'R?gles de fraude;Zone de fraude;Code de r?ponse SVFM;Date de remboursement;Mode de paiement;'
        .'Nom du titulaire de la carte;Num?ro de carte;Syst?me de paiement;Produit;Banque ?mettrice;'
        ."Pays de la banque ?mettrice;Pays du payeur;Code de l'action;Description du code d'action;"
        ."Identifiant de la r?ponse d'autorisation;Num?ro de r?f?rence;Identifiant du terminal;"
        .'Identification du traitement;3DSec/SSL;Protocole de s?curit?;ECI;ID du client;Email;'
        ."Identification du n?ud;Mois de versement;Param?tres suppl?mentaires\n";

    $row = 'Purchase;BNA Acquirer;2026.02.28 23:57:41;197.17.12.77;f918789f-1a52-4a01-9b66-82e905eb26d5;BROWSER;'
        .'Approved;;STEG ECOM;STEG-ECOM;;;2026.02.28 23:58:19;2026.02.28 23:58:20;;458.000;;;TND;458.000;'
        .'458.000;0.000;;0;;;;;Card;AHMED OUERFELLI;414629**6394;Visa;;;788;Tunisia;0;'
        .'0: Request processed successfully;573878;1618143201;89471517;332854710;3DS_enr;3DS2;5;;'
        ."ahmed.ouerfelli@ouerfelli.tn;EPG_CORE_NODE_342;;[disablePhone:true]\n";

    $import = runImport($source, 'smt_sample.csv', $header.$row);

    expect($import->status->value)->toBe('completed');
    expect($import->success_rows)->toBe(1);
    expect($import->error_rows)->toBe(0);

    $normalized = NormalizedTransaction::query()->sole();
    // Per client spec, SMT keeps ONLY date + amount; the normalized
    // reference is the composite date|amount key generated by the
    // TransactionNormalizer (no native reference column exists).
    expect($normalized->normalized_reference)->toBe('2026-02-28|458000');
    expect($normalized->normalized_amount_millimes)->toBe(458000);
    expect($normalized->normalized_date->format('Y-m-d'))->toBe('2026-02-28');
    // SMT has no authorization column: secondary reference must stay null.
    expect($normalized->normalized_secondary_reference)->toBeNull();
});

test('BNA source file maps N° autorisation into normalized_reference and leaves secondary_reference null', function () {
    Storage::fake('local');
    seedRealSources();

    $source = Source::query()->where('code', 'BNA')->firstOrFail();
    // The real file is XLSX; a CSV with the same headers exercises the same mappings.
    $source->update(['file_type' => 'csv', 'config' => ['csv_delimiter' => ';']]);

    $csv = "Date;Terminal;N° compte;Montant (TND);N° autorisation;Libellé opération;Numéro du terminal;Type de la transaction\n"
        .'31/07/2026;STEG ECOM TND;1200115006381;103.000;331533;WEB8233i310726;0389471517;Vente Web';

    $import = runImport($source, 'bna_sample.csv', $csv);

    expect($import->status->value)->toBe('completed');
    expect($import->success_rows)->toBe(1);
    expect($import->error_rows)->toBe(0);

    $normalized = NormalizedTransaction::query()->sole();
    // BNA has no reference column: the N° autorisation must become the
    // primary normalized_reference (not the old date|amount fallback).
    expect($normalized->normalized_reference)->toBe('331533');
    expect($normalized->normalized_secondary_reference)->toBeNull();
    expect($normalized->normalized_amount_millimes)->toBe(103000);
    expect($normalized->normalized_date->format('Y-m-d'))->toBe('2026-07-31');

    // The import page must not repeat the authorization as a secondary reference.
    $shown = $import->rows()->sole()->normalized_data;
    expect($shown['normalized_reference'])->toBe('331533')
        ->and($shown)->not->toHaveKey('normalized_secondary_reference');
});

test('ALPHA source file keeps reference as primary and NUM_AUTO as normalized_secondary_reference', function () {
    Storage::fake('local');
    seedRealSources();

    $source = Source::query()->where('code', 'ALPHA')->firstOrFail();
    // The real file is XLSX; a CSV with the same headers exercises the same mappings.
    $source->update(['file_type' => 'csv', 'config' => ['csv_delimiter' => ';']]);

    $csv = "CANAL;DAT_ENC;NUM_AUTO;REFERENCE;MONTANT_ENCAISS\n"
        .'WEB;01/05/2026;b697464;185113460;000000989000';

    $import = runImport($source, 'alpha_sample.csv', $csv);

    expect($import->status->value)->toBe('completed');
    expect($import->success_rows)->toBe(1);
    expect($import->error_rows)->toBe(0);

    $normalized = NormalizedTransaction::query()->sole();
    // ALPHA exposes both REFERENCE and NUM_AUTO: REFERENCE stays the primary
    // normalized_reference, NUM_AUTO is exposed as normalized_secondary_reference.
    expect($normalized->normalized_reference)->toBe('185113460');
    expect($normalized->normalized_secondary_reference)->toBe('697464');
    expect($normalized->normalized_amount_millimes)->toBe(989000);
    expect($normalized->normalized_date->format('Y-m-d'))->toBe('2026-05-01');

    $shown = $import->rows()->sole()->normalized_data;
    expect($shown['normalized_reference'])->toBe('185113460')
        ->and($shown['normalized_secondary_reference'])->toBe('697464');
});
