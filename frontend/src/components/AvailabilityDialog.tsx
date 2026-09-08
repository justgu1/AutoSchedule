import DeleteIcon from '@mui/icons-material/Delete';
import Alert from '@mui/material/Alert';
import Button from '@mui/material/Button';
import Dialog from '@mui/material/Dialog';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogTitle from '@mui/material/DialogTitle';
import Divider from '@mui/material/Divider';
import FormControlLabel from '@mui/material/FormControlLabel';
import IconButton from '@mui/material/IconButton';
import Stack from '@mui/material/Stack';
import Switch from '@mui/material/Switch';
import TextField from '@mui/material/TextField';
import ToggleButton from '@mui/material/ToggleButton';
import ToggleButtonGroup from '@mui/material/ToggleButtonGroup';
import Typography from '@mui/material/Typography';
import { AdapterDayjs } from '@mui/x-date-pickers/AdapterDayjs';
import { DatePicker } from '@mui/x-date-pickers/DatePicker';
import { LocalizationProvider } from '@mui/x-date-pickers/LocalizationProvider';
import { TimePicker } from '@mui/x-date-pickers/TimePicker';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import dayjs, { type Dayjs } from 'dayjs';
import 'dayjs/locale/pt-br';
import { useState } from 'react';
import {
    createAvailabilityException,
    createDealershipAvailabilityRule,
    createVehicleAvailabilityRule,
    deleteAvailabilityException,
    deleteDealershipAvailabilityRule,
    deleteVehicleAvailabilityRule,
    listAvailabilityExceptions,
    listDealershipAvailabilityRules,
    listVehicleAvailabilityRules,
    type AvailabilityRuleInput,
} from '../lib/availability';

const WEEKDAYS = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
const WEEKDAY_SHORT = ['DOM', 'SEG', 'TER', 'QUA', 'QUI', 'SEX', 'SÁB'];
const WEEKDAYS_MON_FRI = [1, 2, 3, 4, 5];
const WEEKDAYS_MON_SAT = [1, 2, 3, 4, 5, 6];
const TIME_FORMAT = 'HH:mm';

/** Comum às duas tabelas -- o componente nunca precisa saber de qual dono a regra é. */
interface AvailabilityRuleRow extends AvailabilityRuleInput {
    id: string;
}

export type AvailabilityScope = { type: 'dealership'; id: string } | { type: 'vehicle'; id: string };

interface AvailabilityDialogProps {
    open: boolean;
    scope: AvailabilityScope | null;
    onClose: () => void;
}

/** Regras recorrentes + exceções, pra concessionária ou pra veículo -- mesmo componente, o CRUD que muda por baixo. */
export function AvailabilityDialog({ open, scope, onClose }: AvailabilityDialogProps) {
    const queryClient = useQueryClient();
    const [weekdays, setWeekdays] = useState<number[]>([]);
    const [startTime, setStartTime] = useState<Dayjs | null>(dayjs('2000-01-01T09:00'));
    const [endTime, setEndTime] = useState<Dayjs | null>(dayjs('2000-01-01T18:00'));
    const [exceptionDate, setExceptionDate] = useState<Dayjs | null>(null);
    const [exceptionIsAvailable, setExceptionIsAvailable] = useState(false);
    const [exceptionStart, setExceptionStart] = useState<Dayjs | null>(null);
    const [exceptionEnd, setExceptionEnd] = useState<Dayjs | null>(null);
    const [exceptionReason, setExceptionReason] = useState('');

    const rulesKey = ['availability-rules', scope?.type, scope?.id];
    const exceptionsKey = ['availability-exceptions', scope?.type, scope?.id];

    const rules = useQuery({
        queryKey: rulesKey,
        queryFn: async (): Promise<AvailabilityRuleRow[]> =>
            scope?.type === 'dealership'
                ? listDealershipAvailabilityRules(scope.id)
                : listVehicleAvailabilityRules(scope!.id),
        enabled: scope !== null,
    });

    const exceptions = useQuery({
        queryKey: exceptionsKey,
        queryFn: () =>
            listAvailabilityExceptions(
                scope?.type === 'dealership' ? { dealership_id: scope.id } : { vehicle_id: scope!.id },
            ),
        enabled: scope !== null,
    });

    /** Um dia por chamada -- o endpoint já existente só aceita um weekday por vez, sem rota nova. */
    const createRuleMutation = useMutation({
        mutationFn: async (input: Omit<AvailabilityRuleInput, 'weekday'>): Promise<void> => {
            for (const weekday of weekdays) {
                await (scope?.type === 'dealership'
                    ? createDealershipAvailabilityRule(scope.id, { ...input, weekday })
                    : createVehicleAvailabilityRule(scope!.id, { ...input, weekday }));
            }
        },
        onSuccess: () => void queryClient.invalidateQueries({ queryKey: rulesKey }),
    });

    const deleteRuleMutation = useMutation({
        mutationFn: (ruleId: string) =>
            scope?.type === 'dealership'
                ? deleteDealershipAvailabilityRule(ruleId)
                : deleteVehicleAvailabilityRule(ruleId),
        onSuccess: () => void queryClient.invalidateQueries({ queryKey: rulesKey }),
    });

    const createExceptionMutation = useMutation({
        mutationFn: createAvailabilityException,
        onSuccess: () => {
            setExceptionDate(null);
            setExceptionStart(null);
            setExceptionEnd(null);
            setExceptionReason('');
            void queryClient.invalidateQueries({ queryKey: exceptionsKey });
        },
    });

    const deleteExceptionMutation = useMutation({
        mutationFn: deleteAvailabilityException,
        onSuccess: () => void queryClient.invalidateQueries({ queryKey: exceptionsKey }),
    });

    function handleAddRule() {
        if (weekdays.length === 0 || !startTime || !endTime) {
            return;
        }

        createRuleMutation.mutate({ start_time: startTime.format(TIME_FORMAT), end_time: endTime.format(TIME_FORMAT) });
    }

    function handleAddException() {
        if (!scope || !exceptionDate) {
            return;
        }

        createExceptionMutation.mutate({
            dealership_id: scope.type === 'dealership' ? scope.id : undefined,
            vehicle_id: scope.type === 'vehicle' ? scope.id : undefined,
            date: exceptionDate.format('YYYY-MM-DD'),
            start_time: exceptionStart ? exceptionStart.format(TIME_FORMAT) : undefined,
            end_time: exceptionEnd ? exceptionEnd.format(TIME_FORMAT) : undefined,
            is_available: exceptionIsAvailable,
            reason: exceptionReason === '' ? undefined : exceptionReason,
        });
    }

    return (
        <Dialog open={open} onClose={onClose} maxWidth="sm" fullWidth>
            <LocalizationProvider dateAdapter={AdapterDayjs} adapterLocale="pt-br">
                <DialogTitle>Disponibilidade</DialogTitle>
                <DialogContent>
                    <Stack spacing={3} sx={{ mt: 1 }}>
                        <Stack spacing={1.5}>
                            <Typography variant="subtitle1">Horários recorrentes</Typography>

                            {rules.data?.map((rule) => (
                                <Stack key={rule.id} direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                                    <Typography sx={{ flex: 1 }}>
                                        {WEEKDAYS[rule.weekday]}, {rule.start_time} às {rule.end_time}
                                    </Typography>
                                    <IconButton
                                        size="small"
                                        aria-label="Remover horário"
                                        onClick={() => deleteRuleMutation.mutate(rule.id)}
                                    >
                                        <DeleteIcon fontSize="small" />
                                    </IconButton>
                                </Stack>
                            ))}

                            <Stack spacing={1}>
                                <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap' }}>
                                    <Button size="small" onClick={() => setWeekdays(WEEKDAYS_MON_FRI)}>
                                        Seg a sex
                                    </Button>
                                    <Button size="small" onClick={() => setWeekdays(WEEKDAYS_MON_SAT)}>
                                        Seg a sáb
                                    </Button>
                                </Stack>
                                <ToggleButtonGroup
                                    value={weekdays}
                                    onChange={(_, value: number[]) => setWeekdays(value)}
                                    aria-label="Dias da semana"
                                    size="small"
                                >
                                    {WEEKDAY_SHORT.map((label, index) => (
                                        <ToggleButton key={label} value={index} aria-label={WEEKDAYS[index]}>
                                            {label}
                                        </ToggleButton>
                                    ))}
                                </ToggleButtonGroup>
                                <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap' }}>
                                    <TimePicker
                                        label="Início"
                                        value={startTime}
                                        onChange={setStartTime}
                                        slotProps={{ textField: { size: 'small' } }}
                                    />
                                    <TimePicker
                                        label="Fim"
                                        value={endTime}
                                        onChange={setEndTime}
                                        slotProps={{ textField: { size: 'small' } }}
                                    />
                                    <Button
                                        variant="outlined"
                                        disabled={weekdays.length === 0 || createRuleMutation.isPending}
                                        onClick={handleAddRule}
                                    >
                                        Adicionar
                                    </Button>
                                </Stack>
                            </Stack>
                            {createRuleMutation.isError && <Alert severity="error">Não foi possível adicionar.</Alert>}
                        </Stack>

                        <Divider />

                        <Stack spacing={1.5}>
                            <Typography variant="subtitle1">Exceções</Typography>

                            {exceptions.data?.map((exception) => (
                                <Stack key={exception.id} direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                                    <Typography sx={{ flex: 1 }}>
                                        {exception.date} --{' '}
                                        {exception.is_available
                                            ? `aberto ${exception.start_time}-${exception.end_time}`
                                            : exception.start_time
                                              ? `bloqueado ${exception.start_time}-${exception.end_time}`
                                              : 'bloqueado o dia inteiro'}
                                        {exception.reason && ` (${exception.reason})`}
                                    </Typography>
                                    <IconButton
                                        size="small"
                                        aria-label="Remover exceção"
                                        onClick={() => deleteExceptionMutation.mutate(exception.id)}
                                    >
                                        <DeleteIcon fontSize="small" />
                                    </IconButton>
                                </Stack>
                            ))}

                            <Stack spacing={1}>
                                <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap', alignItems: 'center' }}>
                                    <DatePicker
                                        label="Data da exceção"
                                        value={exceptionDate}
                                        onChange={setExceptionDate}
                                        slotProps={{ textField: { size: 'small' } }}
                                    />
                                    <FormControlLabel
                                        control={
                                            <Switch
                                                checked={exceptionIsAvailable}
                                                onChange={(event) => setExceptionIsAvailable(event.target.checked)}
                                            />
                                        }
                                        label="Horário especial (abre em vez de bloquear)"
                                    />
                                </Stack>
                                <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap' }}>
                                    <TimePicker
                                        label="Início da exceção"
                                        value={exceptionStart}
                                        onChange={setExceptionStart}
                                        slotProps={{
                                            textField: { size: 'small', helperText: 'Vazio bloqueia o dia inteiro' },
                                        }}
                                    />
                                    <TimePicker
                                        label="Fim da exceção"
                                        value={exceptionEnd}
                                        onChange={setExceptionEnd}
                                        slotProps={{ textField: { size: 'small' } }}
                                    />
                                    <TextField
                                        label="Motivo"
                                        size="small"
                                        value={exceptionReason}
                                        onChange={(event) => setExceptionReason(event.target.value)}
                                        sx={{ flex: 1, minWidth: 160 }}
                                    />
                                </Stack>
                                <Button
                                    variant="outlined"
                                    disabled={exceptionDate === null || createExceptionMutation.isPending}
                                    onClick={handleAddException}
                                >
                                    Adicionar exceção
                                </Button>
                            </Stack>
                            {createExceptionMutation.isError && (
                                <Alert severity="error">Não foi possível adicionar a exceção.</Alert>
                            )}
                        </Stack>
                    </Stack>
                </DialogContent>
            </LocalizationProvider>
            <DialogActions>
                <Button onClick={onClose}>Fechar</Button>
            </DialogActions>
        </Dialog>
    );
}
