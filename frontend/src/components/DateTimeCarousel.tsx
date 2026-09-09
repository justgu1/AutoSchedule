import Box from '@mui/material/Box';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { AdapterDayjs } from '@mui/x-date-pickers/AdapterDayjs';
import { DateCalendar } from '@mui/x-date-pickers/DateCalendar';
import { LocalizationProvider } from '@mui/x-date-pickers/LocalizationProvider';
import { keepPreviousData, useQuery } from '@tanstack/react-query';
import dayjs, { type Dayjs } from 'dayjs';
import 'dayjs/locale/pt-br';
import { useState } from 'react';
import { listAvailableDates, listAvailableSlots } from '../lib/availability';

interface DateTimeCarouselProps {
    vehicleId: string;
    date: string | null;
    onDateChange: (date: string) => void;
    time: string | null;
    onTimeChange: (time: string) => void;
}

/**
 * Calendário aberto pra escolher o dia (grade normal, sem scroll) + linha horizontal só pros
 * horários -- o calendário fica com `placeholderData: keepPreviousData` pra não "piscar" (e
 * voltar pro mês atual sozinho) toda vez que troca de mês e a lista de dias disponíveis recarrega.
 */
export function DateTimeCarousel({ vehicleId, date, onDateChange, time, onTimeChange }: DateTimeCarouselProps) {
    const [month, setMonth] = useState(() => dayjs());

    const availableDates = useQuery({
        queryKey: ['available-dates', vehicleId, month.format('YYYY-MM')],
        queryFn: () => listAvailableDates(vehicleId, month.format('YYYY-MM')),
        placeholderData: keepPreviousData,
    });

    const availableSlots = useQuery({
        queryKey: ['available-slots', vehicleId, date],
        queryFn: () => listAvailableSlots(vehicleId, date!),
        enabled: date !== null,
    });

    const availableDatesSet = new Set(availableDates.data ?? []);

    function handleDateChange(value: Dayjs | null) {
        if (!value) {
            return;
        }

        const newDate = value.format('YYYY-MM-DD');
        onDateChange(newDate);

        if (newDate !== date) {
            onTimeChange('');
        }
    }

    return (
        <Stack spacing={2}>
            <LocalizationProvider dateAdapter={AdapterDayjs} adapterLocale="pt-br">
                <DateCalendar
                    value={date ? dayjs(date) : null}
                    onChange={handleDateChange}
                    onMonthChange={setMonth}
                    minDate={dayjs()}
                    shouldDisableDate={(value) => !availableDatesSet.has(value.format('YYYY-MM-DD'))}
                />
            </LocalizationProvider>

            {date !== null && (
                <Stack spacing={1}>
                    <Typography variant="subtitle2">Horários livres</Typography>
                    {availableSlots.isPending && <Typography color="text.secondary">Carregando horários...</Typography>}
                    {availableSlots.data?.length === 0 && (
                        <Typography color="text.secondary">Nenhum horário livre nesse dia.</Typography>
                    )}
                    <Box
                        role="listbox"
                        aria-label="Selecione um horário"
                        sx={{ display: 'flex', gap: 1, overflowX: 'auto', pb: 0.5 }}
                    >
                        {availableSlots.data?.map((slot) => {
                            const selected = slot === time;

                            return (
                                <Box
                                    key={slot}
                                    component="button"
                                    type="button"
                                    role="option"
                                    aria-selected={selected}
                                    onClick={() => onTimeChange(slot)}
                                    sx={{
                                        flexShrink: 0,
                                        py: 1,
                                        px: 2,
                                        borderRadius: 2,
                                        border: '1px solid',
                                        borderColor: selected ? 'success.main' : 'divider',
                                        bgcolor: selected ? 'success.main' : 'background.paper',
                                        color: selected ? 'success.contrastText' : 'text.primary',
                                        cursor: 'pointer',
                                        font: 'inherit',
                                        fontWeight: 600,
                                    }}
                                >
                                    {slot}
                                </Box>
                            );
                        })}
                    </Box>
                </Stack>
            )}
        </Stack>
    );
}
