<?php

use App\Models\CertificateTemplateElement;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->apply(fn (string $foo) => str_replace(
            'titulado "{evento}", llevado a cabo',
            'titulado "{evento}", con una duración total de {horas_totales} horas, llevado a cabo',
            $foo,
        ));
    }

    public function down(): void
    {
        $this->apply(fn (string $foo) => str_replace(
            'titulado "{evento}", con una duración total de {horas_totales} horas, llevado a cabo',
            'titulado "{evento}", llevado a cabo',
            $foo,
        ));
    }

    private function apply(callable $transform): void
    {
        $search = 'titulado "{evento}", llevado a cabo';

        CertificateTemplateElement::query()
            ->where('type', 'text')
            ->where('content', 'like', '%'.$search.'%')
            ->cursor()
            ->each(fn (CertificateTemplateElement $element) => $element->update([
                'content' => $transform($element->content),
            ]));
    }
};
