<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class UnitDepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            'Unidad Zacatenco (CDMX)' => [
                'Biología Celular', 'Biomedicina Molecular', 'Bioquímica', 'Biotecnología y Bioingeniería',
                'Computación', 'Control Automático', 'Farmacología', 'Física', 'Física Aplicada',
                'Genética y Biología Molecular', 'Infectómica y Patogénesis Molecular', 'Ingeniería Eléctrica',
                'Investigaciones Educativas (DIE)', 'Matemática Educativa', 'Matemáticas', 'Química', 'Toxicología',
            ],
            'Sede Sur (CDMX)' => [
                'Farmacobiología', 'Investigaciones Educativas (DIE)', 'Envejecimiento (CIE)',
            ],
            'Unidad Tlaxcala' => [
                'Biología de la Reproducción',
            ],
            'Unidad Irapuato' => [
                'Biotecnología y Bioquímica', 'Ingeniería Genética',
            ],
            'Unidad de Genómica Avanzada (Langebio - Irapuato)' => [
                'Genómica Avanzada',
            ],
            'Unidad Guadalajara' => [
                'Ingeniería Eléctrica (Computación, Control, Diseño Electrónico, Potencia y Telecomunicaciones)',
            ],
            'Unidad Mérida' => [
                'Ecología Humana', 'Física Aplicada', 'Recursos del Mar',
            ],
            'Unidad Querétaro' => [
                'Ciencia e Ingeniería de Materiales',
            ],
            'Unidad Saltillo' => [
                'Ingeniería Cerámica', 'Ingeniería Metalúrgica', 'Robótica y Manufactura Avanzada', 'Sustentabilidad de los Recursos Naturales y Energía',
            ],
            'Unidad Monterrey' => [
                'Biología Celular y Salud',
            ],
            'Unidad Tamaulipas' => [
                'Tecnologías de la Información',
            ],
        ];

        foreach ($data as $unitName => $departments) {
            $unit = \App\Models\Unit::create(['name' => $unitName]);
            foreach ($departments as $deptName) {
                \App\Models\Department::create([
                    'unit_id' => $unit->id,
                    'name' => $deptName,
                ]);
            }
        }
    }
}
