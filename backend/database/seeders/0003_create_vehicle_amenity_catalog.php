<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Schema\Seeder;

return new class () implements Seeder {
    public function run(\PDO $pdo): void
    {
        $statement = $pdo->prepare(<<<'SQL'
            INSERT INTO vehicle_amenity_catalog (code, label, position)
            VALUES (:code, :label, :position)
            ON CONFLICT (code) DO NOTHING
            SQL);

        $items = [
            ['alarm', 'Alarme'],
            ['onboard_computer', 'Computador de bordo'],
            ['traction_control', 'Controle de tração'],
            ['air_conditioning', 'Ar condicionado'],
            ['abs_brakes', 'Freio ABS'],
            ['cruise_control', 'Controle automático de velocidade'],
            ['power_locks', 'Travas elétricas'],
            ['power_windows', 'Vidros elétricos'],
            ['tilt_steering_wheel', 'Volante com regulagem de altura'],
            ['power_steering_adjustment', 'Direção com Ajuste'],
            ['remote_alarm', 'Alarme com acionamento a distância'],
            ['abs_ebd_brakes', 'Freios ABS com EBD'],
            ['stability_control', 'Controle de estabilidade'],
            ['multimedia_screen', 'Tela Multimídia'],
            ['smartphone_mirroring', 'Espelhamento com Smartphone'],
            ['usb', 'USB'],
            ['bluetooth', 'Bluetooth'],
        ];

        foreach ($items as $position => [$code, $label]) {
            $statement->execute(['code' => $code, 'label' => $label, 'position' => $position]);
        }
    }
};
