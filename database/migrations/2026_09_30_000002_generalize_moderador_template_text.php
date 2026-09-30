<?php

use App\Models\CertificateTemplate;
use App\Models\ParticipationType;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * La constancia de moderador ya no es exclusiva de conferencias: cualquier
     * taller, ponencia o conferencia moderada la habilita. Se generaliza el
     * texto de la plantilla por defecto (solo si no fue personalizada por un admin).
     */
    public function up(): void
    {
        $type = ParticipationType::where('key', 'moderador')->first();

        if ($type !== null && $type->label === 'Moderador de conferencias') {
            $type->update(['label' => 'Moderador']);
        }

        if ($type === null) {
            return;
        }

        $texts = [
            'Por su destacada participación como moderador de conferencias en el' => 'Por su destacada participación como moderador en el',
            'Conferencias moderadas:' => 'Actividades moderadas:',
        ];

        CertificateTemplate::where('participation_type_id', $type->id)
            ->where('kind', 'certificate')
            ->get()
            ->each(function (CertificateTemplate $template) use ($texts) {
                $template->elements()
                    ->whereIn('content', array_keys($texts))
                    ->get()
                    ->each(fn ($element) => $element->update([
                        'content' => $texts[$element->content],
                    ]));
            });
    }

    public function down(): void
    {
        $type = ParticipationType::where('key', 'moderador')->first();

        if ($type === null) {
            return;
        }

        if ($type->label === 'Moderador') {
            $type->update(['label' => 'Moderador de conferencias']);
        }

        $texts = [
            'Por su destacada participación como moderador en el' => 'Por su destacada participación como moderador de conferencias en el',
            'Actividades moderadas:' => 'Conferencias moderadas:',
        ];

        CertificateTemplate::where('participation_type_id', $type->id)
            ->where('kind', 'certificate')
            ->get()
            ->each(function (CertificateTemplate $template) use ($texts) {
                $template->elements()
                    ->whereIn('content', array_keys($texts))
                    ->get()
                    ->each(fn ($element) => $element->update([
                        'content' => $texts[$element->content],
                    ]));
            });
    }
};
