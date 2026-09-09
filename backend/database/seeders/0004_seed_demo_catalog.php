<?php

declare(strict_types=1);

use App\Application\File\UploadFile;
use App\Bootstrap\CliKernel;
use App\Domain\Shared\Slugger;
use App\Infrastructure\Persistence\Schema\Seeder;

/**
 * Catálogo de demonstração: 10 concessionárias com agenda/veículos/fotos/agendamentos próprios.
 * Idempotente pelo slug da concessionária; foto real por modelo via Wikimedia, cai pro placeholder de GD sem rede.
 */
return new class () implements Seeder {
    // Endereço/CEP/bairro de cada linha conferido contra o ViaCEP real.
    private const array DEALERSHIPS = [
        ['Auto Estrela São Paulo', 'Avenida Paulista', '2000', 'Bela Vista', 'São Paulo', 'SP', '01310-200'],
        ['Rio Motors Copacabana', 'Avenida Nossa Senhora de Copacabana', '500', 'Copacabana', 'Rio de Janeiro', 'RJ', '22020-001'],
        ['Minas Veículos Savassi', 'Avenida Getúlio Vargas', '900', 'Savassi', 'Belo Horizonte', 'MG', '30112-021'],
        ['Curitiba Auto Center', 'Rua XV de Novembro', '700', 'Centro', 'Curitiba', 'PR', '80020-310'],
        ['Porto Alegre Veículos', 'Avenida Ipiranga', '5000', 'Jardim Botânico', 'Porto Alegre', 'RS', '90610-000'],
        ['Salvador Auto Shopping', 'Avenida Tancredo Neves', '1200', 'Caminho das Árvores', 'Salvador', 'BA', '41820-020'],
        ['Fortaleza Veículos Aldeota', 'Avenida Santos Dumont', '1800', 'Aldeota', 'Fortaleza', 'CE', '60150-161'],
        ['Recife Auto Boa Vista', 'Avenida Conde da Boa Vista', '800', 'Boa Vista', 'Recife', 'PE', '50060-004'],
        ['Brasília Veículos Asa Sul', 'SCS Quadra 5', '100', 'Asa Sul', 'Brasília', 'DF', '70305-000'],
        ['Campinas Auto Center', 'Avenida Coronel Silva Teles', '300', 'Cambuí', 'Campinas', 'SP', '13024-000'],
    ];

    /** brand, model, version, body_type, fuel_type, transmission */
    private const array MODELS = [
        ['Chevrolet', 'Onix', 'LT', 'hatch', 'flex', 'manual'],
        ['Chevrolet', 'Onix Plus', 'LTZ', 'sedan', 'flex', 'automatic'],
        ['Chevrolet', 'Tracker', 'Premier', 'suv', 'flex', 'automatic'],
        ['Chevrolet', 'S10', 'LTZ', 'pickup', 'diesel', 'automatic'],
        ['Fiat', 'Argo', 'Drive', 'hatch', 'flex', 'manual'],
        ['Fiat', 'Pulse', 'Audace', 'suv', 'flex', 'automatic'],
        ['Fiat', 'Strada', 'Volcano', 'pickup', 'flex', 'automatic'],
        ['Fiat', 'Toro', 'Ranch', 'pickup', 'diesel', 'automatic'],
        ['Volkswagen', 'Gol', 'MSI', 'hatch', 'flex', 'manual'],
        ['Volkswagen', 'Polo', 'Highline', 'hatch', 'flex', 'automatic'],
        ['Volkswagen', 'Virtus', 'Comfortline', 'sedan', 'flex', 'automatic'],
        ['Volkswagen', 'T-Cross', 'Highline', 'suv', 'flex', 'automatic'],
        ['Volkswagen', 'Nivus', 'Highline', 'suv', 'flex', 'automatic'],
        ['Toyota', 'Corolla', 'XEi', 'sedan', 'flex', 'automatic'],
        ['Toyota', 'Corolla Cross', 'XRE', 'suv', 'hybrid', 'automatic'],
        ['Toyota', 'Hilux', 'SRX', 'pickup', 'diesel', 'automatic'],
        ['Toyota', 'Yaris', 'XLS', 'hatch', 'flex', 'automatic'],
        ['Honda', 'Civic', 'Touring', 'sedan', 'flex', 'automatic'],
        ['Honda', 'HR-V', 'EXL', 'suv', 'flex', 'automatic'],
        ['Honda', 'City', 'Touring', 'sedan', 'flex', 'automatic'],
        ['Hyundai', 'HB20', 'Comfort', 'hatch', 'flex', 'manual'],
        ['Hyundai', 'HB20S', 'Platinum', 'sedan', 'flex', 'automatic'],
        ['Hyundai', 'Creta', 'Ultimate', 'suv', 'flex', 'automatic'],
        ['Jeep', 'Renegade', 'Longitude', 'suv', 'flex', 'automatic'],
        ['Jeep', 'Compass', 'Limited', 'suv', 'diesel', 'automatic'],
        ['Nissan', 'Kicks', 'Advance', 'suv', 'flex', 'automatic'],
        ['Nissan', 'Versa', 'Sense', 'sedan', 'flex', 'manual'],
        ['Renault', 'Kwid', 'Zen', 'hatch', 'flex', 'manual'],
        ['Renault', 'Duster', 'Iconic', 'suv', 'flex', 'automatic'],
        ['Peugeot', '208', 'Griffe', 'hatch', 'flex', 'automatic'],
        ['Citroën', 'C4 Cactus', 'Shine', 'suv', 'flex', 'automatic'],
        ['Ford', 'Ka', 'SE', 'hatch', 'flex', 'manual'],
    ];

    private const array COLORS = ['Branco', 'Prata', 'Preto', 'Cinza', 'Vermelho', 'Azul', 'Marrom'];

    private const int VEHICLES_PER_DEALERSHIP = 10;

    /**
     * Uma agenda semanal diferente por concessionária -- cada linha é [weekday, start, end],
     * `weekday` no formato de `DateTimeImmutable::format('w')` (0 domingo .. 6 sábado).
     */
    private const array SCHEDULES = [
        [[1, '08:00', '18:00'], [2, '08:00', '18:00'], [3, '08:00', '18:00'], [4, '08:00', '18:00'], [5, '08:00', '18:00'], [6, '09:00', '13:00']],
        [[1, '09:00', '19:00'], [2, '09:00', '19:00'], [3, '09:00', '19:00'], [4, '09:00', '19:00'], [5, '09:00', '19:00'], [6, '09:00', '19:00']],
        [[2, '10:00', '19:00'], [3, '10:00', '19:00'], [4, '10:00', '19:00'], [5, '10:00', '19:00'], [6, '10:00', '19:00']],
        [[1, '08:30', '17:30'], [2, '08:30', '17:30'], [3, '08:30', '17:30'], [4, '08:30', '17:30'], [5, '08:30', '17:30']],
        [[1, '09:00', '18:00'], [2, '09:00', '18:00'], [3, '09:00', '18:00'], [4, '09:00', '18:00'], [5, '09:00', '18:00'], [6, '09:00', '12:00']],
        [[1, '08:00', '20:00'], [2, '08:00', '20:00'], [3, '08:00', '20:00'], [4, '08:00', '20:00'], [5, '08:00', '20:00'], [6, '08:00', '20:00']],
        [[1, '10:00', '19:00'], [2, '10:00', '19:00'], [3, '10:00', '19:00'], [4, '10:00', '19:00'], [5, '10:00', '19:00']],
        [[1, '08:00', '17:00'], [2, '08:00', '17:00'], [3, '08:00', '17:00'], [4, '08:00', '17:00'], [5, '08:00', '17:00'], [6, '08:00', '12:00']],
        [[1, '09:00', '18:30'], [2, '09:00', '18:30'], [3, '09:00', '18:30'], [4, '09:00', '18:30'], [5, '09:00', '18:30']],
        [[2, '09:00', '18:00'], [3, '09:00', '18:00'], [4, '09:00', '18:00'], [5, '09:00', '18:00'], [6, '09:00', '18:00'], [0, '10:00', '16:00']],
    ];

    private const array CUSTOMERS = [
        ['Ana Beatriz Souza', '11988880001'],
        ['Bruno Costa Lima', '11988880002'],
        ['Carla Fernandes', '11988880003'],
        ['Diego Martins', '11988880004'],
        ['Eduarda Ribeiro', '11988880005'],
        ['Felipe Nogueira', '11988880006'],
        ['Gabriela Alves', '11988880007'],
        ['Henrique Pires', '11988880008'],
    ];

    /** Testes esperam a tabela "vazia" fora do que a própria suíte insere; E2E monta a própria carga por teste. */
    private const array SKIP_ON_DATABASES = ['autoschedule_test', 'autoschedule_e2e'];

    /** @var array<string, list<string>> "brand|model" -> file ids, uma busca de rede só por modelo, não por veículo. */
    private array $modelImageCache = [];

    public function run(\PDO $pdo): void
    {
        // Só pro banco de dev/demo -- nem teste, nem E2E precisam (ou querem) do catálogo sintético inteiro.
        $statement = $pdo->query('SELECT current_database()');

        if ($statement !== false && in_array($statement->fetchColumn(), self::SKIP_ON_DATABASES, true)) {
            return;
        }

        // RLS de `appointments` só libera INSERT em contexto de serviço (o mesmo caminho do booking
        // público) -- `current_user_role = 'admin'` sozinho (setado pelo `SeederRunner`) não basta aqui.
        $pdo->exec("SET LOCAL app.is_service_context = 'true'");

        $kernel = CliKernel::boot();
        $uploadFile = $kernel->container->get(UploadFile::class);

        $amenityIds = $this->fetchAmenityIds($pdo);
        $customerIds = $this->seedCustomers($pdo);

        foreach (self::DEALERSHIPS as $index => $dealershipData) {
            $dealershipId = $this->seedDealership($pdo, $uploadFile, $index, $dealershipData);

            if ($dealershipId === null) {
                continue; // já seedado numa rodada anterior -- idempotente, não duplica o estoque.
            }

            $this->seedAvailability($pdo, $dealershipId, $index);

            $vehicleIds = [];

            for ($slot = 0; $slot < self::VEHICLES_PER_DEALERSHIP; ++$slot) {
                $vehicleIds[] = $this->seedVehicle($pdo, $uploadFile, $dealershipId, $index, $slot, $amenityIds);
            }

            $this->seedAppointments($pdo, $vehicleIds, $customerIds, $index);
        }
    }

    /** @return list<string> */
    private function seedCustomers(\PDO $pdo): array
    {
        $statement = $pdo->prepare(<<<'SQL'
            INSERT INTO users (name, email, phone, password, role)
            VALUES (:name, :email, :phone, :password, 'customer')
            ON CONFLICT (email) DO UPDATE SET email = EXCLUDED.email
            RETURNING id
            SQL);

        $ids = [];

        foreach (self::CUSTOMERS as $index => [$name, $phone]) {
            $statement->execute([
                'name' => $name,
                'email' => sprintf('customer%d@demo.autoschedule.local', $index + 1),
                'phone' => $phone,
                'password' => password_hash('password', PASSWORD_ARGON2ID),
            ]);
            $ids[] = (string) $statement->fetchColumn();
        }

        return $ids;
    }

    /** @return list<string> */
    private function fetchAmenityIds(\PDO $pdo): array
    {
        $statement = $pdo->query('SELECT id FROM vehicle_amenity_catalog');
        \assert($statement !== false);

        return array_map(static fn (mixed $row): string => (string) $row['id'], $statement->fetchAll());
    }

    /** @param array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6: string} $data */
    private function seedDealership(\PDO $pdo, UploadFile $uploadFile, int $index, array $data): ?string
    {
        [$name, $street, $number, $neighborhood, $city, $state, $zipCode] = $data;
        $slug = Slugger::slugify($name);
        $sellerNumber = $index + 1;
        $email = sprintf('seller%d@demo.autoschedule.local', $sellerNumber);

        $existing = $pdo->prepare('SELECT id FROM dealerships WHERE slug = ?');
        $existing->execute([$slug]);
        $existingId = $existing->fetchColumn();

        if ($existingId !== false) {
            return null;
        }

        $seller = $pdo->prepare(<<<'SQL'
            INSERT INTO users (name, email, phone, password, role)
            VALUES (:name, :email, :phone, :password, 'seller')
            ON CONFLICT (email) DO UPDATE SET email = EXCLUDED.email
            RETURNING id
            SQL);
        $seller->execute([
            'name' => sprintf('Vendedor Demo %d', $sellerNumber),
            'email' => $email,
            'phone' => sprintf('119%07d', 10000000 + $sellerNumber),
            'password' => password_hash('password', PASSWORD_ARGON2ID),
        ]);
        $ownerUserId = (string) $seller->fetchColumn();

        $photoFileId = $this->uploadPlaceholderImage($uploadFile, $name, '2E7D32');

        $dealership = $pdo->prepare(<<<'SQL'
            INSERT INTO dealerships (
                owner_user_id, name, slug, zip_code, address, number, neighborhood, city, state,
                phone, email, photo_file_id
            ) VALUES (
                :owner_user_id, :name, :slug, :zip_code, :address, :number, :neighborhood, :city, :state,
                :phone, :email, :photo_file_id
            )
            RETURNING id
            SQL);
        $dealership->execute([
            'owner_user_id' => $ownerUserId,
            'name' => $name,
            'slug' => $slug,
            'zip_code' => $zipCode,
            'address' => $street,
            'number' => $number,
            'neighborhood' => $neighborhood,
            'city' => $city,
            'state' => $state,
            'phone' => sprintf('11%08d', 30000000 + $index),
            'email' => sprintf('contato@%s.demo.autoschedule.local', $slug),
            'photo_file_id' => $photoFileId,
        ]);

        return (string) $dealership->fetchColumn();
    }

    /** Cada concessionária tem a própria agenda semanal (`SCHEDULES`) -- nada de horário único repetido nas 10. */
    private function seedAvailability(\PDO $pdo, string $dealershipId, int $index): void
    {
        $statement = $pdo->prepare(<<<'SQL'
            INSERT INTO dealership_availability_rules (dealership_id, weekday, start_time, end_time)
            VALUES (:dealership_id, :weekday, :start_time, :end_time)
            SQL);

        foreach (self::SCHEDULES[$index] as [$weekday, $startTime, $endTime]) {
            $statement->execute([
                'dealership_id' => $dealershipId,
                'weekday' => $weekday,
                'start_time' => $startTime,
                'end_time' => $endTime,
            ]);
        }
    }

    /** @param list<string> $amenityIds */
    private function seedVehicle(\PDO $pdo, UploadFile $uploadFile, string $dealershipId, int $dealershipIndex, int $slot, array $amenityIds): string
    {
        $model = self::MODELS[($dealershipIndex * self::VEHICLES_PER_DEALERSHIP + $slot) % count(self::MODELS)];
        [$brand, $modelName, $version, $bodyType, $fuelType, $transmission] = $model;

        $seed = $dealershipIndex * self::VEHICLES_PER_DEALERSHIP + $slot;
        $modelYear = 2021 + ($seed % 5);
        $manufactureYear = $modelYear - ($seed % 2);
        $mileageKm = max(0, (2026 - $modelYear) * 12000 + ($seed % 7) * 1000);
        $price = $this->priceFor($bodyType, $fuelType, $modelYear);
        $color = self::COLORS[$seed % count(self::COLORS)];

        // 1 a 10 fotos, determinístico por veículo -- dá pra testar o carrossel da galeria com quantidades variadas.
        $imageCount = 1 + ($seed * 7 + 3) % 10;
        $photoFileIds = $this->resolveVehicleImages($uploadFile, $brand, $modelName, $imageCount);

        $vehicle = $pdo->prepare(<<<'SQL'
            INSERT INTO vehicles (
                dealership_id, brand, model, version, manufacture_year, model_year, price, description,
                mileage_km, transmission, body_type, fuel_type, color, plate_end_digit,
                accepts_trade, ipva_paid, licensed, trashed_by_dealership_trash
            ) VALUES (
                :dealership_id, :brand, :model, :version, :manufacture_year, :model_year, :price, :description,
                :mileage_km, :transmission, :body_type, :fuel_type, :color, :plate_end_digit,
                :accepts_trade, :ipva_paid, :licensed, false
            )
            RETURNING id
            SQL);
        $vehicle->execute([
            'dealership_id' => $dealershipId,
            'brand' => $brand,
            'model' => $modelName,
            'version' => $version,
            'manufacture_year' => $manufactureYear,
            'model_year' => $modelYear,
            'price' => $price,
            'description' => "{$brand} {$modelName} {$version} {$modelYear}, único dono, revisado, pronto pra transferência.",
            'mileage_km' => $mileageKm,
            'transmission' => $transmission,
            'body_type' => $bodyType,
            'fuel_type' => $fuelType,
            'color' => $color,
            'plate_end_digit' => $seed % 10,
            // Raw PDO não faz o bind consciente de tipo de `Statement::execute()` -- `false` vira '' e a coluna rejeita.
            'accepts_trade' => $this->pgBool($seed % 3 !== 0),
            'ipva_paid' => $this->pgBool($seed % 4 !== 0),
            'licensed' => $this->pgBool(true),
        ]);
        $vehicleId = (string) $vehicle->fetchColumn();

        $imageStatement = $pdo->prepare('INSERT INTO vehicle_images (vehicle_id, file_id, position) VALUES (?, ?, ?)');

        foreach ($photoFileIds as $position => $fileId) {
            $imageStatement->execute([$vehicleId, $fileId, $position]);
        }

        $this->linkAmenities($pdo, $vehicleId, $amenityIds, $seed);

        return $vehicleId;
    }

    /** @param list<string> $amenityIds */
    private function linkAmenities(\PDO $pdo, string $vehicleId, array $amenityIds, int $seed): void
    {
        if ($amenityIds === []) {
            return;
        }

        // Determinístico (sem random real) -- rodar o seeder de novo escolhe sempre os mesmos itens pro mesmo veículo.
        $count = 5 + ($seed % 5);
        $offset = $seed % count($amenityIds);
        $selected = [];

        for ($i = 0; $i < min($count, count($amenityIds)); ++$i) {
            $selected[] = $amenityIds[($offset + $i) % count($amenityIds)];
        }

        $statement = $pdo->prepare('INSERT INTO vehicle_amenity_links (vehicle_id, amenity_id) VALUES (?, ?)');

        foreach (array_unique($selected) as $amenityId) {
            $statement->execute([$vehicleId, $amenityId]);
        }
    }

    /**
     * 5 agendamentos por concessionária cobrindo pending/confirmed/completed/cancelled/no_show --
     * idempotente junto com o resto, só roda quando a concessionária é criada pela primeira vez.
     *
     * @param list<string> $vehicleIds
     * @param list<string> $customerIds
     */
    private function seedAppointments(\PDO $pdo, array $vehicleIds, array $customerIds, int $dealershipIndex): void
    {
        [$weekday, $startTime] = self::SCHEDULES[$dealershipIndex][0];
        [$startHour, $startMinute] = array_map(intval(...), explode(':', $startTime));

        // weeksOffset negativo = já aconteceu (completed/cancelled/no_show), positivo = ainda vai acontecer.
        $plans = [
            ['status' => 'pending', 'weeksOffset' => 2],
            ['status' => 'confirmed', 'weeksOffset' => 1],
            ['status' => 'completed', 'weeksOffset' => -2],
            ['status' => 'cancelled', 'weeksOffset' => -1],
            ['status' => 'no_show', 'weeksOffset' => -3],
        ];

        $statement = $pdo->prepare(<<<'SQL'
            INSERT INTO appointments (
                vehicle_id, user_id, scheduled_at, customer_name, customer_email, customer_phone,
                status, confirmation_token_hash, confirmation_email_sent_at, expires_at, picked_up_at, released_at
            ) VALUES (
                :vehicle_id, :user_id, :scheduled_at, :customer_name, :customer_email, :customer_phone,
                :status, :confirmation_token_hash, :confirmation_email_sent_at, :expires_at, :picked_up_at, :released_at
            )
            SQL);

        foreach ($plans as $planIndex => $plan) {
            $vehicleId = $vehicleIds[$planIndex % count($vehicleIds)];
            $customerIndex = ($dealershipIndex * count($plans) + $planIndex) % count($customerIds);
            [$customerName, $customerPhone] = self::CUSTOMERS[$customerIndex];

            // Sempre dentro do próprio horário de funcionamento (weekday/hora de abertura da concessionária).
            $scheduledAt = $this->nextWeekdayAt($weekday, $startHour, $startMinute, $plan['weeksOffset'])->modify('+2 hours');

            [$confirmationHash, $confirmationSentAt, $expiresAt, $pickedUpAt, $releasedAt] = $this->timestampsFor($plan['status'], $scheduledAt, $dealershipIndex, $planIndex);

            $statement->execute([
                'vehicle_id' => $vehicleId,
                'user_id' => $customerIds[$customerIndex],
                'scheduled_at' => $scheduledAt->format(DATE_ATOM),
                'customer_name' => $customerName,
                'customer_email' => sprintf('customer%d@demo.autoschedule.local', $customerIndex + 1),
                'customer_phone' => $customerPhone,
                'status' => $plan['status'],
                'confirmation_token_hash' => $confirmationHash,
                'confirmation_email_sent_at' => $confirmationSentAt,
                'expires_at' => $expiresAt,
                'picked_up_at' => $pickedUpAt,
                'released_at' => $releasedAt,
            ]);
        }
    }

    /** @return array{0: ?string, 1: ?string, 2: ?string, 3: ?string, 4: ?string} */
    private function timestampsFor(string $status, \DateTimeImmutable $scheduledAt, int $dealershipIndex, int $planIndex): array
    {
        // `pending`/`cancelled` nunca tiveram e-mail de confirmação enviado -- `cancel()` só existe a partir de `pending`.
        if ($status === 'pending' || $status === 'cancelled') {
            return [null, null, null, null, null];
        }

        $confirmationHash = hash('sha256', sprintf('seed-%d-%d', $dealershipIndex, $planIndex));
        $confirmationSentAt = $scheduledAt->modify('-1 day')->format(DATE_ATOM);
        $expiresAt = $scheduledAt->modify('-1 day +1 hour')->format(DATE_ATOM);

        return match ($status) {
            'confirmed' => [$confirmationHash, $confirmationSentAt, $expiresAt, null, null],
            'no_show' => [$confirmationHash, $confirmationSentAt, $expiresAt, null, null],
            'completed' => [$confirmationHash, $confirmationSentAt, $expiresAt, $scheduledAt->modify('+10 minutes')->format(DATE_ATOM), $scheduledAt->modify('+50 minutes')->format(DATE_ATOM)],
            default => [null, null, null, null, null],
        };
    }

    /** Sempre o próximo (ou último) dia com esse weekday a partir de agora -- nunca cai num dia fechado. */
    private function nextWeekdayAt(int $weekday, int $hour, int $minute, int $weeksOffset): \DateTimeImmutable
    {
        $now = new \DateTimeImmutable('now');
        $daysUntil = ($weekday - (int) $now->format('w') + 7) % 7;

        return $now->modify("+{$daysUntil} days")->modify(sprintf('%+d weeks', $weeksOffset))->setTime($hour, $minute);
    }

    private function pgBool(bool $value): string
    {
        return $value ? 'true' : 'false';
    }

    private function priceFor(string $bodyType, string $fuelType, int $modelYear): string
    {
        $base = match ($bodyType) {
            'hatch' => 75000,
            'sedan' => 105000,
            'suv' => 135000,
            'pickup' => 190000,
            default => 90000,
        };

        $ageDiscount = (2026 - $modelYear) * 6000;
        $fuelPremium = $fuelType === 'hybrid' ? 25000 : ($fuelType === 'diesel' ? 15000 : 0);

        return number_format(max(45000, $base + $fuelPremium - $ageDiscount), 2, '.', '');
    }

    private function colorHexFor(int $seed): string
    {
        $palette = ['1565C0', 'C62828', '2E7D32', '6A1B9A', 'EF6C00', '37474F', '00838F'];

        return $palette[$seed % count($palette)];
    }

    /** @return list<string> file ids do modelo com pelo menos $count posições (repete se tiver menos fotos reais). */
    private function resolveVehicleImages(UploadFile $uploadFile, string $brand, string $modelName, int $count): array
    {
        $key = "{$brand}|{$modelName}";

        if (!isset($this->modelImageCache[$key])) {
            $fileIds = $this->fetchRealCarImages($uploadFile, $brand, $modelName);

            $this->modelImageCache[$key] = $fileIds !== []
                ? $fileIds
                : [$this->uploadPlaceholderImage($uploadFile, "{$brand} {$modelName}", $this->colorHexFor(crc32($key)))];
        }

        $available = $this->modelImageCache[$key];

        return array_map(static fn (int $position): string => $available[$position % count($available)], range(0, $count - 1));
    }

    /**
     * Até 5 fotos reais por modelo via Wikimedia Commons (licença livre) -- lista vazia se a rede
     * falhar, quem chama cai pro placeholder.
     *
     * @return list<string>
     */
    private function fetchRealCarImages(UploadFile $uploadFile, string $brand, string $modelName): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 6,
                'header' => "User-Agent: AutoScheduleDemoSeeder/1.0 (contact: dev@autoschedule.local)\r\n",
                'ignore_errors' => true,
            ],
        ]);

        $query = rawurlencode("{$brand} {$modelName} car");
        $searchUrl = 'https://commons.wikimedia.org/w/api.php?action=query&generator=search'
            . "&gsrsearch={$query}&gsrnamespace=6&gsrlimit=5&prop=imageinfo&iiprop=url|mime&iiurlwidth=800&format=json";

        $raw = @file_get_contents($searchUrl, false, $context);

        if (!is_string($raw) || $raw === '') {
            return [];
        }

        try {
            /** @var array{query?: array{pages?: array<int|string, array{imageinfo?: list<array{thumburl?: string, url?: string, mime?: string}>}>}} $decoded */
            $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        $fileIds = [];

        foreach ($decoded['query']['pages'] ?? [] as $index => $page) {
            $fileId = $this->downloadCommonsImage($uploadFile, $page['imageinfo'][0] ?? null, $context, "{$brand} {$modelName} " . ($index + 1));

            if ($fileId !== null) {
                $fileIds[] = $fileId;
            }
        }

        return $fileIds;
    }

    /** @param array{thumburl?: string, url?: string, mime?: string}|null $imageInfo */
    private function downloadCommonsImage(UploadFile $uploadFile, ?array $imageInfo, mixed $context, string $label): ?string
    {
        $imageUrl = $imageInfo['thumburl'] ?? $imageInfo['url'] ?? null;
        $mime = $imageInfo['mime'] ?? null;

        if (!is_string($imageUrl) || !is_string($mime) || !str_starts_with($mime, 'image/')) {
            return null;
        }

        $bytes = @file_get_contents($imageUrl, false, $context);

        if (!is_string($bytes) || $bytes === '') {
            return null;
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'seed-real-img-');
        \assert($tmpPath !== false);
        file_put_contents($tmpPath, $bytes);
        $extension = str_contains($mime, 'png') ? 'png' : 'jpg';

        try {
            return $uploadFile->uploadImage($tmpPath, Slugger::slugify($label) . '.' . $extension, null)->id;
        } catch (\Throwable) {
            // Resultado de busca às vezes não é uma foto decodificável (SVG, PDF de ficha técnica) -- ignora essa e segue.
            return null;
        } finally {
            if (file_exists($tmpPath)) {
                unlink($tmpPath);
            }
        }
    }

    /** Placeholder gerado por GD (sem internet) -- retângulo colorido com o texto, convertido a WebP pelo mesmo pipeline real de upload. */
    private function uploadPlaceholderImage(UploadFile $uploadFile, string $label, string $hexColor): string
    {
        $width = 640;
        $height = 480;
        $image = imagecreatetruecolor($width, $height);

        [$r, $g, $b] = sscanf($hexColor, '%02x%02x%02x');
        $background = imagecolorallocate($image, $r, $g, $b);
        imagefilledrectangle($image, 0, 0, $width, $height, $background);

        $white = imagecolorallocate($image, 255, 255, 255);
        $font = 5;
        $lines = explode(' ', $label, 2);

        foreach ($lines as $lineIndex => $line) {
            $textWidth = imagefontwidth($font) * strlen($line);
            $x = (int) (($width - $textWidth) / 2);
            $y = $height / 2 - 20 + ($lineIndex * 20);
            imagestring($image, $font, max(0, $x), $y, $line, $white);
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'seed-img-');
        \assert($tmpPath !== false);
        imagepng($image, $tmpPath);

        $file = $uploadFile->uploadImage($tmpPath, Slugger::slugify($label) . '.png', null);

        if (file_exists($tmpPath)) {
            unlink($tmpPath);
        }

        return $file->id;
    }
};
