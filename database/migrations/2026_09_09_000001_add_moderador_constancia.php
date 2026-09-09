<?php

use App\Models\CertificateTemplate;
use App\Models\CertificateTemplateElement;
use App\Models\ParticipationType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moderador_constancias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('activated')->default(false);
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });

        // Los tipos por-kind dejan de emitirse; el moderador tiene una sola
        // constancia global definida por el tipo 'moderador'.
        ParticipationType::query()
            ->whereIn('key', ['moderador_mesa', 'moderador_simposio'])
            ->update(['is_active' => false]);

        $type = ParticipationType::updateOrCreate(
            ['key' => 'moderador'],
            [
                'label' => 'Moderador de conferencias',
                'event_kind' => 'conference',
                'kind' => null,
                'role' => 'moderator',
                'is_active' => true,
                'manual_generable' => false,
            ]
        );

        if (! CertificateTemplate::where('participation_type_id', $type->id)->where('kind', 'certificate')->exists()) {
            $template = CertificateTemplate::create([
                'name' => 'Constancia de Moderador',
                'description' => 'Plantilla por defecto para la constancia única de moderador',
                'kind' => 'certificate',
                'participation_type_id' => $type->id,
                'is_default' => true,
                'width' => 1800,
                'height' => 1200,
            ]);

            $elements = [
                ['type' => 'text', 'content' => 'CONSTANCIA DE MODERADOR', 'variable' => null, 'x' => 150, 'y' => 150, 'width' => 1500, 'height' => 70, 'font_size' => 52, 'font_weight' => 'bold', 'font_family' => 'Georgia, serif', 'color' => '#4338ca', 'text_align' => 'center', 'z_index' => 1],
                ['type' => 'text', 'content' => '{nombre_completo}', 'variable' => 'nombre_completo', 'x' => 150, 'y' => 430, 'width' => 1500, 'height' => 100, 'font_size' => 62, 'font_weight' => 'bold', 'font_family' => 'Georgia, serif', 'color' => '#1a1a2e', 'text_align' => 'center', 'z_index' => 2],
                ['type' => 'text', 'content' => 'Por su destacada participación como moderador de conferencias en el', 'variable' => null, 'x' => 200, 'y' => 580, 'width' => 1400, 'height' => 50, 'font_size' => 32, 'font_weight' => 'normal', 'font_family' => 'Arial', 'color' => '#333333', 'text_align' => 'center', 'z_index' => 3],
                ['type' => 'text', 'content' => '{evento}', 'variable' => 'evento', 'x' => 200, 'y' => 640, 'width' => 1400, 'height' => 50, 'font_size' => 34, 'font_weight' => 'bold', 'font_family' => 'Arial', 'color' => '#16213e', 'text_align' => 'center', 'z_index' => 4],
                ['type' => 'text', 'content' => 'Conferencias moderadas:', 'variable' => null, 'x' => 200, 'y' => 720, 'width' => 1400, 'height' => 40, 'font_size' => 26, 'font_weight' => 'normal', 'font_family' => 'Arial', 'color' => '#555555', 'text_align' => 'center', 'z_index' => 5],
                ['type' => 'text', 'content' => '{titulo_actividad}', 'variable' => 'titulo_actividad', 'x' => 250, 'y' => 770, 'width' => 1300, 'height' => 140, 'font_size' => 26, 'font_weight' => 'normal', 'font_family' => 'Arial', 'color' => '#333333', 'text_align' => 'center', 'z_index' => 6, 'word_wrap' => true],
                ['type' => 'text', 'content' => 'Folio: {folio}', 'variable' => 'folio', 'x' => 150, 'y' => 925, 'width' => 1500, 'height' => 30, 'font_size' => 20, 'font_weight' => 'normal', 'font_family' => 'Arial', 'color' => '#888888', 'text_align' => 'center', 'z_index' => 7],
                ['type' => 'qr', 'content' => null, 'variable' => 'qr', 'x' => 820, 'y' => 970, 'width' => 160, 'height' => 160, 'font_size' => null, 'font_weight' => null, 'font_family' => null, 'color' => null, 'text_align' => 'center', 'z_index' => 8],
            ];

            foreach ($elements as $el) {
                CertificateTemplateElement::create(array_merge(['template_id' => $template->id], $el));
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('moderador_constancias');

        ParticipationType::query()
            ->whereIn('key', ['moderador_mesa', 'moderador_simposio'])
            ->update(['is_active' => true]);

        $type = ParticipationType::where('key', 'moderador')->first();

        if ($type !== null) {
            CertificateTemplate::where('participation_type_id', $type->id)->delete();
            $type->delete();
        }
    }
};
