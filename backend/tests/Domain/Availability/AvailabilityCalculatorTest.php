<?php

declare(strict_types=1);

namespace Tests\Domain\Availability;

use App\Domain\Availability\AvailabilityCalculator;
use App\Domain\Availability\AvailabilityException;
use App\Domain\Availability\WeeklyWindow;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AvailabilityCalculatorTest extends TestCase
{
    // Terça-feira, weekday=2.
    private const string TUESDAY = '2026-09-08';

    private AvailabilityCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new AvailabilityCalculator();
    }

    #[Test]
    public function exemplo_da_documentacao_concessionaria_09_18_veiculo_10_15_gera_dez_a_quatorze(): void
    {
        $slots = $this->calculator->slotsFor(
            date: $this->date(self::TUESDAY),
            dealershipWindows: [$this->window(2, '09:00', '18:00')],
            vehicleWindows: [$this->window(2, '10:00', '15:00')],
            exceptions: [],
            occupiedStarts: [],
            durationMinutes: 60,
        );

        $this->assertSame(['10:00', '11:00', '12:00', '13:00', '14:00'], $this->times($slots));
    }

    #[Test]
    public function sem_intersecao_entre_concessionaria_e_veiculo_nao_ha_slot(): void
    {
        $slots = $this->calculator->slotsFor(
            date: $this->date(self::TUESDAY),
            dealershipWindows: [$this->window(2, '08:00', '09:00')],
            vehicleWindows: [$this->window(2, '10:00', '15:00')],
            exceptions: [],
            occupiedStarts: [],
            durationMinutes: 60,
        );

        $this->assertSame([], $slots);
    }

    #[Test]
    public function excecao_sem_horario_bloqueia_o_dia_inteiro(): void
    {
        $slots = $this->calculator->slotsFor(
            date: $this->date(self::TUESDAY),
            dealershipWindows: [$this->window(2, '09:00', '18:00')],
            vehicleWindows: [$this->window(2, '10:00', '15:00')],
            exceptions: [$this->exception(startTime: null, endTime: null, isAvailable: false, vehicleId: 'v1')],
            occupiedStarts: [],
            durationMinutes: 60,
        );

        $this->assertSame([], $slots);
    }

    #[Test]
    public function excecao_com_horario_subtrai_so_aquele_intervalo(): void
    {
        $slots = $this->calculator->slotsFor(
            date: $this->date(self::TUESDAY),
            dealershipWindows: [$this->window(2, '09:00', '18:00')],
            vehicleWindows: [$this->window(2, '10:00', '15:00')],
            exceptions: [$this->exception(startTime: '12:00', endTime: '13:00', isAvailable: false, vehicleId: 'v1')],
            occupiedStarts: [],
            durationMinutes: 60,
        );

        $this->assertSame(['10:00', '11:00', '13:00', '14:00'], $this->times($slots));
    }

    #[Test]
    public function excecao_is_available_substitui_a_janela_do_dia_inteiro(): void
    {
        // Concessionária normalmente fecharia terça, mas abre em horário especial das 20h às 22h;
        // o veículo cobre o dia inteiro, então quem decide a janela final é a exceção.
        $slots = $this->calculator->slotsFor(
            date: $this->date(self::TUESDAY),
            dealershipWindows: [],
            vehicleWindows: [$this->window(2, '00:00', '23:59')],
            exceptions: [$this->exception(startTime: '20:00', endTime: '22:00', isAvailable: true, dealershipId: 'd1')],
            occupiedStarts: [],
            durationMinutes: 60,
        );

        $this->assertSame(['20:00', '21:00'], $this->times($slots));
    }

    #[Test]
    public function agendamento_existente_remove_o_slot_ocupado(): void
    {
        $slots = $this->calculator->slotsFor(
            date: $this->date(self::TUESDAY),
            dealershipWindows: [$this->window(2, '09:00', '18:00')],
            vehicleWindows: [$this->window(2, '10:00', '15:00')],
            exceptions: [],
            occupiedStarts: [$this->dateTime(self::TUESDAY, '11:00')],
            durationMinutes: 60,
        );

        $this->assertSame(['10:00', '12:00', '13:00', '14:00'], $this->times($slots));
    }

    #[Test]
    public function multiplas_janelas_no_mesmo_weekday_se_somam(): void
    {
        $slots = $this->calculator->slotsFor(
            date: $this->date(self::TUESDAY),
            dealershipWindows: [$this->window(2, '09:00', '12:00'), $this->window(2, '14:00', '16:00')],
            vehicleWindows: [$this->window(2, '08:00', '20:00')],
            exceptions: [],
            occupiedStarts: [],
            durationMinutes: 60,
        );

        $this->assertSame(['09:00', '10:00', '11:00', '14:00', '15:00'], $this->times($slots));
    }

    #[Test]
    public function borda_do_start_end_o_slot_que_termina_exatamente_no_fim_da_janela_e_valido(): void
    {
        $slots = $this->calculator->slotsFor(
            date: $this->date(self::TUESDAY),
            dealershipWindows: [$this->window(2, '09:00', '18:00')],
            vehicleWindows: [$this->window(2, '14:00', '15:00')],
            exceptions: [],
            occupiedStarts: [],
            durationMinutes: 60,
        );

        $this->assertSame(['14:00'], $this->times($slots));
    }

    #[Test]
    public function janela_recorrente_de_outro_weekday_nao_conta(): void
    {
        $slots = $this->calculator->slotsFor(
            date: $this->date(self::TUESDAY),
            dealershipWindows: [$this->window(3, '09:00', '18:00')], // quarta, não terça
            vehicleWindows: [$this->window(2, '10:00', '15:00')],
            exceptions: [],
            occupiedStarts: [],
            durationMinutes: 60,
        );

        $this->assertSame([], $slots);
    }

    #[Test]
    public function not_before_remove_slot_que_ja_passou(): void
    {
        $slots = $this->calculator->slotsFor(
            date: $this->date(self::TUESDAY),
            dealershipWindows: [$this->window(2, '09:00', '18:00')],
            vehicleWindows: [$this->window(2, '10:00', '15:00')],
            exceptions: [],
            occupiedStarts: [],
            durationMinutes: 60,
            notBefore: $this->dateTime(self::TUESDAY, '12:00'),
        );

        $this->assertSame(['12:00', '13:00', '14:00'], $this->times($slots));
    }

    #[Test]
    public function sem_not_before_slot_do_passado_continua_valido(): void
    {
        $slots = $this->calculator->slotsFor(
            date: $this->date(self::TUESDAY),
            dealershipWindows: [$this->window(2, '09:00', '18:00')],
            vehicleWindows: [$this->window(2, '10:00', '15:00')],
            exceptions: [],
            occupiedStarts: [],
            durationMinutes: 60,
        );

        $this->assertSame(['10:00', '11:00', '12:00', '13:00', '14:00'], $this->times($slots));
    }

    #[Test]
    public function veiculo_sem_regra_e_sem_excecao_nao_restringe_usa_so_a_janela_da_concessionaria(): void
    {
        $slots = $this->calculator->slotsFor(
            date: $this->date(self::TUESDAY),
            dealershipWindows: [$this->window(2, '09:00', '12:00')],
            vehicleWindows: [],
            exceptions: [],
            occupiedStarts: [],
            durationMinutes: 60,
        );

        $this->assertSame(['09:00', '10:00', '11:00'], $this->times($slots));
    }

    #[Test]
    public function concessionaria_sem_regra_e_sem_excecao_usa_default_seg_sex_9_18(): void
    {
        $slots = $this->calculator->slotsFor(
            date: $this->date(self::TUESDAY),
            dealershipWindows: [],
            vehicleWindows: [$this->window(2, '08:00', '10:00')],
            exceptions: [],
            occupiedStarts: [],
            durationMinutes: 60,
        );

        $this->assertSame(['09:00'], $this->times($slots));
    }

    #[Test]
    public function veiculo_sem_regra_recorrente_mas_com_excecao_pontual_respeita_a_excecao(): void
    {
        $slots = $this->calculator->slotsFor(
            date: $this->date(self::TUESDAY),
            dealershipWindows: [$this->window(2, '09:00', '18:00')],
            vehicleWindows: [],
            exceptions: [$this->exception(startTime: null, endTime: null, isAvailable: false, vehicleId: 'v1')],
            occupiedStarts: [],
            durationMinutes: 60,
        );

        $this->assertSame([], $slots);
    }

    private function window(int $weekday, string $start, string $end): WeeklyWindow
    {
        return new WeeklyWindow($weekday, $this->time($start), $this->time($end));
    }

    private function exception(?string $startTime, ?string $endTime, bool $isAvailable, ?string $dealershipId = null, ?string $vehicleId = null): AvailabilityException
    {
        return AvailabilityException::register(
            dealershipId: $dealershipId,
            vehicleId: $vehicleId,
            date: $this->date(self::TUESDAY),
            startTime: $startTime === null ? null : $this->time($startTime),
            endTime: $endTime === null ? null : $this->time($endTime),
            isAvailable: $isAvailable,
            reason: null,
        );
    }

    private function time(string $value): \DateTimeImmutable
    {
        $time = \DateTimeImmutable::createFromFormat('!H:i', $value);
        \assert($time instanceof \DateTimeImmutable);

        return $time;
    }

    private function date(string $value): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        \assert($date instanceof \DateTimeImmutable);

        return $date;
    }

    private function dateTime(string $date, string $time): \DateTimeImmutable
    {
        $dateTime = \DateTimeImmutable::createFromFormat('Y-m-d H:i', "{$date} {$time}");
        \assert($dateTime instanceof \DateTimeImmutable);

        return $dateTime;
    }

    /**
     * @param list<\DateTimeImmutable> $slots
     * @return list<string>
     */
    private function times(array $slots): array
    {
        return array_map(static fn (\DateTimeImmutable $s): string => $s->format('H:i'), $slots);
    }
}
