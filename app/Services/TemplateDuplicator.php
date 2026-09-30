<?php

namespace App\Services;

use App\Models\CertificateTemplate;
use App\Models\CertificateTemplateElement;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Duplica plantillas de constancias, gafetes, cartas y programa copiando
 * también sus elementos y el archivo de fondo.
 */
class TemplateDuplicator
{
    public function duplicate(CertificateTemplate $source, array $overrides = []): CertificateTemplate
    {
        $copy = CertificateTemplate::create(array_merge([
            'name' => $this->uniqueCopyName($source),
            'description' => $source->description,
            'kind' => $source->kind,
            'participation_type_id' => $source->participation_type_id,
            'role_id' => $source->role_id,
            'is_default' => false,
            'is_active' => (bool) $source->is_active,
            'width' => $source->width,
            'height' => $source->height,
            'print_width_mm' => $source->print_width_mm,
            'print_height_mm' => $source->print_height_mm,
            'background_path' => $this->copyBackground($source->background_path),
        ], $overrides));

        $this->copyElements($source, $copy);

        return $copy;
    }

    /**
     * Genera un nombre libre del tipo "Nombre - copia", "Nombre - copia 2", ...
     * dentro del mismo tipo de plantilla.
     */
    public function uniqueCopyName(CertificateTemplate $source): string
    {
        $base = $source->name.' - copia';
        $name = $base;
        $suffix = 2;

        while (CertificateTemplate::query()
            ->where('kind', $source->kind)
            ->where('name', $name)
            ->exists()) {
            $name = $base.' '.$suffix;
            $suffix++;
        }

        return $name;
    }

    private function copyElements(CertificateTemplate $source, CertificateTemplate $copy): void
    {
        $source->loadMissing('elements');

        $rows = [];

        foreach ($source->elements as $index => $element) {
            $rows[] = [
                'template_id' => $copy->id,
                'type' => $element->type,
                'content' => $element->content,
                'variable' => $element->variable,
                'x' => $element->x,
                'y' => $element->y,
                'width' => $element->width,
                'height' => $element->height,
                'font_size' => $element->font_size,
                'auto_fit' => $element->auto_fit,
                'word_wrap' => $element->word_wrap,
                'font_weight' => $element->font_weight,
                'font_family' => $element->font_family,
                'color' => $element->color,
                'text_align' => $element->text_align,
                'z_index' => $element->z_index ?? $index,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows !== []) {
            CertificateTemplateElement::insert($rows);
        }
    }

    /**
     * El fondo se copia a un archivo nuevo: si se compartiera la ruta, al
     * cambiar el fondo de la copia se borraría el archivo del original.
     */
    private function copyBackground(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return null;
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'png';
        $newPath = dirname($path).'/'.Str::uuid().'.'.$extension;
        $disk->copy($path, $newPath);

        return $newPath;
    }
}
