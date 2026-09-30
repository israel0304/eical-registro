<?php

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\CertificateTemplateElement;
use App\Models\ParticipationType;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $type = ParticipationType::updateOrCreate(
            ['key' => 'comite'],
            [
                'label' => 'Miembro del comité',
                'event_kind' => 'comite',
                'kind' => null,
                'role' => null,
                'is_active' => true,
                'manual_generable' => true,
            ]
        );

        if (CertificateTemplate::where('participation_type_id', $type->id)->where('kind', 'certificate')->exists()) {
            return;
        }

        $template = CertificateTemplate::create([
            'name' => 'Constancia de Miembro del Comité',
            'description' => 'Plantilla por defecto para la constancia de miembros del comité del EICAL',
            'kind' => 'certificate',
            'participation_type_id' => $type->id,
            'is_default' => true,
            'width' => 1800,
            'height' => 1200,
        ]);

        $elements = [
            ['type' => 'text', 'content' => 'CONSTANCIA DE MIEMBRO DEL COMITÉ', 'variable' => null, 'x' => 150, 'y' => 170, 'width' => 1500, 'height' => 70, 'font_size' => 50, 'font_weight' => 'bold', 'font_family' => 'Georgia, serif', 'color' => '#4338ca', 'text_align' => 'center', 'z_index' => 1],
            ['type' => 'text', 'content' => 'Se otorga a', 'variable' => null, 'x' => 200, 'y' => 330, 'width' => 1400, 'height' => 40, 'font_size' => 26, 'font_weight' => 'normal', 'font_family' => 'Arial', 'color' => '#555555', 'text_align' => 'center', 'z_index' => 2],
            ['type' => 'text', 'content' => '{nombre}', 'variable' => 'nombre', 'x' => 150, 'y' => 400, 'width' => 1500, 'height' => 100, 'font_size' => 60, 'font_weight' => 'bold', 'font_family' => 'Georgia, serif', 'color' => '#1a1a2e', 'text_align' => 'center', 'z_index' => 3],
            ['type' => 'text', 'content' => 'Por su destacada participación como miembro del comité en el', 'variable' => null, 'x' => 200, 'y' => 560, 'width' => 1400, 'height' => 50, 'font_size' => 30, 'font_weight' => 'normal', 'font_family' => 'Arial', 'color' => '#333333', 'text_align' => 'center', 'z_index' => 4],
            ['type' => 'text', 'content' => '{evento}', 'variable' => 'evento', 'x' => 200, 'y' => 620, 'width' => 1400, 'height' => 50, 'font_size' => 34, 'font_weight' => 'bold', 'font_family' => 'Arial', 'color' => '#16213e', 'text_align' => 'center', 'z_index' => 5],
            ['type' => 'text', 'content' => '{fecha_evento}', 'variable' => 'fecha_evento', 'x' => 200, 'y' => 690, 'width' => 1400, 'height' => 40, 'font_size' => 24, 'font_weight' => 'normal', 'font_family' => 'Arial', 'color' => '#555555', 'text_align' => 'center', 'z_index' => 6],
            ['type' => 'text', 'content' => 'Folio: {folio}', 'variable' => 'folio', 'x' => 150, 'y' => 925, 'width' => 1500, 'height' => 30, 'font_size' => 20, 'font_weight' => 'normal', 'font_family' => 'Arial', 'color' => '#888888', 'text_align' => 'center', 'z_index' => 7],
            ['type' => 'qr', 'content' => null, 'variable' => 'qr', 'x' => 820, 'y' => 970, 'width' => 160, 'height' => 160, 'font_size' => null, 'font_weight' => null, 'font_family' => null, 'color' => null, 'text_align' => 'center', 'z_index' => 8],
        ];

        foreach ($elements as $el) {
            CertificateTemplateElement::create(array_merge(['template_id' => $template->id], $el));
        }
    }

    public function down(): void
    {
        $type = ParticipationType::where('key', 'comite')->first();

        if ($type === null) {
            return;
        }

        Certificate::where('participation_type_id', $type->id)->delete();
        CertificateTemplate::where('participation_type_id', $type->id)->get()->each->forceDelete();
        $type->delete();
    }
};
