import Autocomplete from '@mui/material/Autocomplete';
import Dialog from '@mui/material/Dialog';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogTitle from '@mui/material/DialogTitle';
import Grid from '@mui/material/Grid';
import InputAdornment from '@mui/material/InputAdornment';
import { useQuery } from '@tanstack/react-query';
import { type FormEvent, useState } from 'react';
import { FormError } from './FormError';
import { FormTextField } from './FormTextField';
import { SubmitButton } from './SubmitButton';
import { ApiError } from '../lib/apiClient';
import { listDealerships, type Dealership } from '../lib/dealerships';
import type { Vehicle, VehicleInput } from '../lib/vehicles';

const emptyForm: VehicleInput = {
    dealership_id: '',
    brand: '',
    model: '',
    version: '',
    year: undefined,
    price: '',
    description: '',
};

function formFromVehicle(vehicle?: Vehicle | null): VehicleInput {
    if (!vehicle) {
        return emptyForm;
    }

    return {
        dealership_id: vehicle.dealership_id,
        brand: vehicle.brand,
        model: vehicle.model,
        version: vehicle.version ?? '',
        year: vehicle.year ?? undefined,
        price: vehicle.price,
        description: vehicle.description ?? '',
    };
}

interface VehicleFormDialogProps {
    open: boolean;
    /** Presente = editando esse veículo; ausente = criando um novo. */
    vehicle?: Vehicle | null;
    submitting: boolean;
    error: ApiError | null;
    onSubmit: (input: VehicleInput) => void;
    onClose: () => void;
}

/**
 * Mesmo formulário serve criação e edição, e mandar `dealership_id` diferente do atual move o
 * veículo -- o backend só aceita se quem chama alcançar a concessionária de destino.
 */
export function VehicleFormDialog({ open, vehicle, submitting, error, onSubmit, onClose }: VehicleFormDialogProps) {
    const [form, setForm] = useState<VehicleInput>(() => formFromVehicle(vehicle));

    const dealerships = useQuery({
        queryKey: ['dealerships', 'for-vehicle-form'],
        queryFn: () => listDealerships(1, 100),
    });

    function set<K extends keyof VehicleInput>(field: K, value: VehicleInput[K]) {
        setForm((current) => ({ ...current, [field]: value }));
    }

    function handleSubmit(event: FormEvent) {
        event.preventDefault();
        onSubmit(form);
    }

    const options = dealerships.data?.data ?? [];
    const selectedDealership = options.find((dealership) => dealership.id === form.dealership_id) ?? null;

    return (
        <Dialog open={open} onClose={onClose} maxWidth="sm" fullWidth>
            <DialogTitle>{vehicle ? 'Editar veículo' : 'Novo veículo'}</DialogTitle>
            <form onSubmit={handleSubmit}>
                <DialogContent>
                    <Grid container spacing={1}>
                        <Grid size={12}>
                            <Autocomplete<Dealership>
                                options={options}
                                loading={dealerships.isPending}
                                getOptionLabel={(dealership) => dealership.name}
                                value={selectedDealership}
                                onChange={(_, dealership) => set('dealership_id', dealership?.id ?? '')}
                                isOptionEqualToValue={(option, selected) => option.id === selected.id}
                                renderInput={(params) => (
                                    <FormTextField
                                        {...params}
                                        label="Concessionária"
                                        error={error?.errors?.dealership_id}
                                        required
                                    />
                                )}
                            />
                        </Grid>
                        <Grid size={6}>
                            <FormTextField
                                label="Marca"
                                value={form.brand}
                                onChange={(event) => set('brand', event.target.value)}
                                error={error?.errors?.brand}
                                required
                            />
                        </Grid>
                        <Grid size={6}>
                            <FormTextField
                                label="Modelo"
                                value={form.model}
                                onChange={(event) => set('model', event.target.value)}
                                error={error?.errors?.model}
                                required
                            />
                        </Grid>
                        <Grid size={6}>
                            <FormTextField
                                label="Versão"
                                value={form.version}
                                onChange={(event) => set('version', event.target.value)}
                                error={error?.errors?.version}
                            />
                        </Grid>
                        <Grid size={3}>
                            <FormTextField
                                label="Ano"
                                type="number"
                                value={form.year ?? ''}
                                onChange={(event) =>
                                    set('year', event.target.value === '' ? undefined : Number(event.target.value))
                                }
                                error={error?.errors?.year}
                            />
                        </Grid>
                        <Grid size={3}>
                            <FormTextField
                                label="Preço"
                                value={form.price}
                                onChange={(event) => set('price', event.target.value)}
                                error={error?.errors?.price}
                                slotProps={{
                                    input: { startAdornment: <InputAdornment position="start">R$</InputAdornment> },
                                }}
                                required
                            />
                        </Grid>
                        <Grid size={12}>
                            <FormTextField
                                label="Descrição"
                                value={form.description}
                                onChange={(event) => set('description', event.target.value)}
                                error={error?.errors?.description}
                                multiline
                                minRows={3}
                            />
                        </Grid>
                    </Grid>
                    <FormError message={error?.message} />
                </DialogContent>
                <DialogActions sx={{ px: 3, pb: 2 }}>
                    <SubmitButton loading={submitting} sx={{ width: 'auto' }}>
                        {vehicle ? 'Salvar' : 'Criar'}
                    </SubmitButton>
                </DialogActions>
            </form>
        </Dialog>
    );
}
