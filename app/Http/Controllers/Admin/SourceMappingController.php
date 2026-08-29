<?php

namespace App\Http\Controllers\Admin;

/**
 * Contrôleur de configuration des mappings de colonnes par source.
 *
 * Permet aux utilisateurs de définir, pour chaque Source, quelle colonne
 * du fichier correspond à quel champ métier, et quelles transformations
 * appliquer (trim, zero-pad, parsing de date, conversion millimes, etc.).
 */
use App\Enums\MappingTargetField;
use App\Enums\TransformType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSourceMappingRequest;
use App\Models\Import;
use App\Models\Source;
use App\Models\SourceColumnMapping;
use App\Services\Import\Readers\ImportRowReaderFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class SourceMappingController extends Controller
{
    public function edit(Source $source, Request $request, ImportRowReaderFactory $readers): View
    {
        $this->authorize('update', $source);

        $importId = $request->query('import');
        $referenceImport = $importId
            ? Import::query()->where('source_id', $source->id)->find($importId)
            : Import::query()->where('source_id', $source->id)->latest()->first();

        $detectedHeaders = [];
        if ($referenceImport) {
            try {
                $reader = $readers->make($source);
                $detectedHeaders = $reader->headers(Storage::path($referenceImport->stored_path), $source->config ?? []);
            } catch (Throwable) {
                $detectedHeaders = [];
            }
        }

        $mappings = SourceColumnMapping::query()
            ->where('source_id', $source->id)
            ->get()
            ->keyBy('target_field');

        return view('admin.sources.mappings.edit', [
            'source' => $source,
            'detectedHeaders' => $detectedHeaders,
            'mappings' => $mappings,
            'targetFields' => MappingTargetField::cases(),
            'transformTypes' => TransformType::cases(),
            'importId' => $referenceImport?->id,
        ]);
    }

    public function update(UpdateSourceMappingRequest $request, Source $source): RedirectResponse
    {
        $this->authorize('update', $source);

        $submitted = $request->validated('mappings', []);

        foreach (MappingTargetField::cases() as $index => $field) {
            $data = $submitted[$field->value] ?? null;
            $sourceColumn = trim((string) ($data['source_column'] ?? ''));

            if ($sourceColumn === '') {
                SourceColumnMapping::query()
                    ->where('source_id', $source->id)
                    ->where('target_field', $field->value)
                    ->delete();

                continue;
            }

            SourceColumnMapping::query()->updateOrCreate(
                ['source_id' => $source->id, 'target_field' => $field->value],
                [
                    'source_column' => $sourceColumn,
                    'transform' => $this->buildTransformSteps($field, $data),
                    'is_required' => ! empty($data['is_required']),
                    'sort_order' => $index,
                ]
            );
        }

        $importId = $request->input('import_id');

        if ($importId) {
            return redirect()->route('admin.imports.show', $importId)
                ->with('status', __('Mapping mis à jour. Vous pouvez maintenant relancer cet import.'));
        }

        return redirect()->route('admin.sources.edit', $source)
            ->with('status', __('Mapping mis à jour avec succès.'));
    }

    /** @return array<int,array<string,mixed>> */
    private function buildTransformSteps(MappingTargetField $field, ?array $data): array
    {
        $steps = [['key' => TransformType::Trim->value]];
        $transformType = $data['transform_type'] ?? '';

        switch ($transformType) {
            case TransformType::StripPrefixChars->value:
                $chars = array_filter(array_map('trim', explode(',', $data['chars'] ?? '')));
                $steps[] = ['key' => TransformType::StripPrefixChars->value, 'config' => ['chars' => array_values($chars)]];
                break;

            case TransformType::SubstringAfterNthDelimiter->value:
                $steps[] = ['key' => TransformType::SubstringAfterNthDelimiter->value, 'config' => array_filter([
                    'delimiter' => $data['delimiter'] ?? ',',
                    'n' => (int) ($data['n'] ?? 1),
                    'length' => isset($data['length']) && $data['length'] !== '' ? (int) $data['length'] : null,
                ], fn ($value) => $value !== null)];
                break;

            case TransformType::FixedWidthMillimes->value:
                $steps[] = ['key' => TransformType::FixedWidthMillimes->value];
                break;

            case TransformType::DecimalStringToMillimes->value:
                $steps[] = ['key' => TransformType::DecimalStringToMillimes->value, 'config' => [
                    'decimals' => (int) ($data['decimals'] ?? 3),
                ]];
                break;

            case TransformType::DateParse->value:
                $steps[] = ['key' => TransformType::DateParse->value, 'config' => [
                    'format' => $data['date_format'] ?? '',
                    'output' => $field === MappingTargetField::Datetime ? 'datetime' : 'date',
                ]];
                break;
        }

        return $steps;
    }
}
