<?php

namespace App\Console\Commands;

use App\Models\Certificate;
use App\Services\CertificateRenderer;
use Illuminate\Console\Command;

class RefreshCertificateMetadata extends Command
{
    protected $signature = 'constancias:refrescar-metadata
                            {--dry-run : Muestra los cambios sin escribirlos}
                            {--user= : Limita el refresco a un usuario}';

    protected $description = 'Recalcula la metadata de las constancias emitidas para que reflejen el nombre, afiliación y títulos actuales';

    public function handle(CertificateRenderer $renderer): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $userId = $this->option('user');

        if ($userId !== null && ! is_numeric($userId)) {
            $this->error('El valor de --user debe ser un id numérico.');

            return self::FAILURE;
        }

        $this->info($dryRun
            ? 'Simulación: no se escribirá nada en la base de datos.'
            : 'Refrescando la metadata de las constancias...');
        $this->newLine();

        $updated = 0;
        $unchanged = 0;
        $skipped = [];
        $changes = [];

        // Se cargan todas las columnas del usuario: los builders usan
        // created_at, affiliation y country, no sólo el nombre.
        $query = Certificate::query()->with(['user', 'participationType', 'role']);

        if ($userId !== null) {
            $query->where('user_id', (int) $userId);
        }

        $query->orderBy('id')->chunkById(200, function ($certificates) use ($renderer, $dryRun, &$updated, &$unchanged, &$skipped, &$changes) {
            foreach ($certificates as $certificate) {
                try {
                    $metadata = $renderer->resolveMetadata($certificate);
                } catch (\Throwable $e) {
                    // Una fila con datos inconsistentes no debe abortar la
                    // reparación de las demás.
                    $skipped[] = $this->label($certificate).' — no se pudo reconstruir: '.$e->getMessage();

                    continue;
                }

                if ($metadata === null) {
                    $skipped[] = $this->skipReason($certificate);

                    continue;
                }

                $current = $certificate->metadata ?? [];

                if ($certificate->folio !== null) {
                    $metadata['folio'] = $certificate->folio;
                }

                // Se compara contra lo que realmente se escribiría: las claves
                // antiguas que el builder ya no produce se conservan, así que
                // por sí solas no son un cambio.
                $next = array_merge($current, $metadata);

                if ($this->normalize($next) === $this->normalize($current)) {
                    $unchanged++;

                    continue;
                }

                $updated++;
                $changes[] = $this->describeChange($certificate, $current, $next);

                if (! $dryRun) {
                    $certificate->update(['metadata' => $next]);
                }
            }
        });

        $this->line('  Constancias revisadas: '.($updated + $unchanged + count($skipped)));
        $this->line("  Metadata actualizada:  {$updated}".($dryRun ? ' (simulación)' : ''));
        $this->line("  Sin cambios:           {$unchanged}");
        $this->line('  Omitidas:              '.count($skipped));

        if ($changes !== []) {
            $this->newLine();
            $this->comment('  Cambios detectados:');

            foreach ($changes as $change) {
                $this->line('    '.$change);
            }
        }

        if ($skipped !== []) {
            $this->newLine();
            $this->comment('  Omitidas (no se reconstruyen):');

            foreach ($skipped as $line) {
                $this->warn('    '.$line);
            }
        }

        if ($dryRun && $updated > 0) {
            $this->newLine();
            $this->info('  Corre sin --dry-run para aplicar los cambios.');
        }

        return self::SUCCESS;
    }

    /**
     * Los valores se comparan como texto: null y "" son equivalentes y un 5
     * numérico no debe contar como cambio frente al "5" ya guardado.
     *
     * @param  array<string, mixed>  $metadata
     * @return array<string, string>
     */
    private function normalize(array $metadata): array
    {
        $normalized = [];

        foreach ($metadata as $key => $value) {
            $normalized[(string) $key] = $this->scalar($value);
        }

        ksort($normalized);

        return $normalized;
    }

    private function label(Certificate $certificate): string
    {
        return ($certificate->folio ?? "#{$certificate->id}")
            .' · '.($certificate->user?->name ?? "usuario {$certificate->user_id}");
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $next
     */
    private function describeChange(Certificate $certificate, array $current, array $next): string
    {
        $changed = [];
        $added = [];
        $removed = [];

        foreach (array_unique(array_merge(array_keys($current), array_keys($next))) as $key) {
            $hasBefore = array_key_exists($key, $current);
            $hasAfter = array_key_exists($key, $next);

            if ($hasBefore && ! $hasAfter) {
                $removed[] = (string) $key;
            } elseif (! $hasBefore && $hasAfter) {
                $added[] = (string) $key;
            } elseif ($this->scalar($current[$key]) !== $this->scalar($next[$key])) {
                $changed[] = (string) $key;
            }
        }

        $parts = [];

        if ($changed !== []) {
            $parts[] = implode(', ', $changed);
        }

        if ($added !== []) {
            $parts[] = 'agrega '.implode(', ', $added);
        }

        if ($removed !== []) {
            $parts[] = 'quita '.implode(', ', $removed);
        }

        return $this->label($certificate).' · '.$certificate->event_type
            .' · '.implode('; ', $parts);
    }

    private function skipReason(Certificate $certificate): string
    {
        $label = $this->label($certificate);

        if ($certificate->user === null) {
            return "{$label} — el usuario {$certificate->user_id} ya no existe.";
        }

        if ($certificate->event_type === 'workshop' || $certificate->event_type === 'workshop-group') {
            return "{$label} — el taller #{$certificate->event_id} fue eliminado.";
        }

        if ($certificate->event_type === 'presentation' || $certificate->event_type === 'carta-presentation') {
            return "{$label} — la ponencia #{$certificate->event_id} fue eliminada.";
        }

        if ($certificate->event_type === 'conference' || $certificate->event_type === 'carta-conference') {
            return $certificate->event_id > 0
                ? "{$label} — la conferencia #{$certificate->event_id} fue eliminada."
                : "{$label} — falta el tipo de participación del moderador.";
        }

        if ($certificate->participationType === null) {
            return "{$label} — falta el tipo de participación o el rol.";
        }

        return "{$label} — tipo \"{$certificate->event_type}\" no se puede reconstruir.";
    }

    private function scalar(mixed $value): string
    {
        if (is_array($value)) {
            return implode(', ', array_map(fn ($item) => (string) $item, $value));
        }

        return (string) ($value ?? '');
    }
}
