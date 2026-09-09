import Autocomplete from '@mui/material/Autocomplete';
import Checkbox from '@mui/material/Checkbox';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogTitle from '@mui/material/DialogTitle';
import FormControlLabel from '@mui/material/FormControlLabel';
import Grid from '@mui/material/Grid';
import InputAdornment from '@mui/material/InputAdornment';
import MenuItem from '@mui/material/MenuItem';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useQuery } from '@tanstack/react-query';
import { type FormEvent, useState } from 'react';
import { ResponsiveDialog as Dialog } from './ResponsiveDialog';
import { FormError } from './FormError';
import { FormTextField } from './FormTextField';
import { SubmitButton } from './SubmitButton';
import { ApiError } from '../lib/apiClient';
import { listDealerships, type Dealership } from '../lib/dealerships';
import { listVehicleAmenityCatalog, type Vehicle, type VehicleAmenity, type VehicleInput } from '../lib/vehicles';

const TRANSMISSIONS = [
    { value: 'manual', label: 'Manual' },
    { value: 'automatic', label: 'Automático' },
    { value: 'automated', label: 'Automatizado' },
    { value: 'cvt', label: 'CVT' },
] as const;

const BODY_TYPES = [
    { value: 'hatch', label: 'Hatch' },
    { value: 'sedan', label: 'Sedã' },
    { value: 'suv', label: 'SUV' },
    { value: 'pickup', label: 'Picape' },
    { value: 'coupe', label: 'Cupê' },
    { value: 'convertible', label: 'Conversível' },
    { value: 'minivan', label: 'Minivan' },
    { value: 'wagon', label: 'Perua' },
] as const;

const FUEL_TYPES = [
    { value: 'flex', label: 'Flex' },
    { value: 'gasoline', label: 'Gasolina' },
    { value: 'ethanol', label: 'Etanol' },
    { value: 'diesel', label: 'Diesel' },
    { value: 'electric', label: 'Elétrico' },
    { value: 'hybrid', label: 'Híbrido' },
] as const;

// Mesma lista usada pelo seeder de demo -- cobre as cores mais comuns do mercado brasileiro.
const COLORS = ['Branco', 'Prata', 'Preto', 'Cinza', 'Vermelho', 'Azul', 'Marrom', 'Verde', 'Amarelo', 'Bege'];

const emptyForm: VehicleInput = {
    dealership_id: '',
    brand: '',
    model: '',
    version: '',
    manufacture_year: undefined,
    model_year: undefined,
    price: '',
    description: '',
    mileage_km: undefined,
    transmission: undefined,
    body_type: undefined,
    fuel_type: undefined,
    color: '',
    plate_end_digit: undefined,
    accepts_trade: false,
    ipva_paid: false,
    licensed: false,
    amenity_ids: [],
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
        manufacture_year: vehicle.manufacture_year ?? undefined,
        model_year: vehicle.model_year ?? undefined,
        price: vehicle.price,
        description: vehicle.description ?? '',
        mileage_km: vehicle.mileage_km ?? undefined,
        transmission: vehicle.transmission ?? undefined,
        body_type: vehicle.body_type ?? undefined,
        fuel_type: vehicle.fuel_type ?? undefined,
        color: vehicle.color ?? '',
        plate_end_digit: vehicle.plate_end_digit ?? undefined,
        accepts_trade: vehicle.accepts_trade,
        ipva_paid: vehicle.ipva_paid,
        licensed: vehicle.licensed,
        amenity_ids: vehicle.amenities.map((amenity) => amenity.id),
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

    // Catálogo global e estático -- não muda entre uma abertura e outra do formulário.
    const amenityCatalog = useQuery({
        queryKey: ['vehicle-amenity-catalog'],
        queryFn: listVehicleAmenityCatalog,
        staleTime: Infinity,
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
    const amenityOptions = amenityCatalog.data ?? [];
    const selectedAmenities = amenityOptions.filter((amenity) => form.amenity_ids?.includes(amenity.id));

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
                                label="Ano de fabricação"
                                type="number"
                                value={form.manufacture_year ?? ''}
                                onChange={(event) =>
                                    set(
                                        'manufacture_year',
                                        event.target.value === '' ? undefined : Number(event.target.value),
                                    )
                                }
                                error={error?.errors?.manufacture_year}
                            />
                        </Grid>
                        <Grid size={3}>
                            <FormTextField
                                label="Ano modelo"
                                type="number"
                                value={form.model_year ?? ''}
                                onChange={(event) =>
                                    set(
                                        'model_year',
                                        event.target.value === '' ? undefined : Number(event.target.value),
                                    )
                                }
                                error={error?.errors?.model_year}
                            />
                        </Grid>
                        <Grid size={6}>
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
                        <Grid size={4}>
                            <FormTextField
                                label="Quilometragem"
                                type="number"
                                value={form.mileage_km ?? ''}
                                onChange={(event) =>
                                    set(
                                        'mileage_km',
                                        event.target.value === '' ? undefined : Number(event.target.value),
                                    )
                                }
                                error={error?.errors?.mileage_km}
                            />
                        </Grid>
                        <Grid size={4}>
                            <FormTextField
                                select
                                label="Cor"
                                value={form.color}
                                onChange={(event) => set('color', event.target.value)}
                                error={error?.errors?.color}
                            >
                                <MenuItem value="">
                                    <em>Não informado</em>
                                </MenuItem>
                                {COLORS.map((color) => (
                                    <MenuItem key={color} value={color}>
                                        {color}
                                    </MenuItem>
                                ))}
                            </FormTextField>
                        </Grid>
                        <Grid size={4}>
                            <FormTextField
                                label="Final de placa"
                                type="number"
                                value={form.plate_end_digit ?? ''}
                                onChange={(event) =>
                                    set(
                                        'plate_end_digit',
                                        event.target.value === '' ? undefined : Number(event.target.value),
                                    )
                                }
                                error={error?.errors?.plate_end_digit}
                            />
                        </Grid>
                        <Grid size={4}>
                            <FormTextField
                                select
                                label="Câmbio"
                                value={form.transmission ?? ''}
                                onChange={(event) =>
                                    set(
                                        'transmission',
                                        (event.target.value || undefined) as VehicleInput['transmission'],
                                    )
                                }
                                error={error?.errors?.transmission}
                            >
                                <MenuItem value="">
                                    <em>Não informado</em>
                                </MenuItem>
                                {TRANSMISSIONS.map((option) => (
                                    <MenuItem key={option.value} value={option.value}>
                                        {option.label}
                                    </MenuItem>
                                ))}
                            </FormTextField>
                        </Grid>
                        <Grid size={4}>
                            <FormTextField
                                select
                                label="Carroceria"
                                value={form.body_type ?? ''}
                                onChange={(event) =>
                                    set('body_type', (event.target.value || undefined) as VehicleInput['body_type'])
                                }
                                error={error?.errors?.body_type}
                            >
                                <MenuItem value="">
                                    <em>Não informado</em>
                                </MenuItem>
                                {BODY_TYPES.map((option) => (
                                    <MenuItem key={option.value} value={option.value}>
                                        {option.label}
                                    </MenuItem>
                                ))}
                            </FormTextField>
                        </Grid>
                        <Grid size={4}>
                            <FormTextField
                                select
                                label="Combustível"
                                value={form.fuel_type ?? ''}
                                onChange={(event) =>
                                    set('fuel_type', (event.target.value || undefined) as VehicleInput['fuel_type'])
                                }
                                error={error?.errors?.fuel_type}
                            >
                                <MenuItem value="">
                                    <em>Não informado</em>
                                </MenuItem>
                                {FUEL_TYPES.map((option) => (
                                    <MenuItem key={option.value} value={option.value}>
                                        {option.label}
                                    </MenuItem>
                                ))}
                            </FormTextField>
                        </Grid>
                        <Grid size={12}>
                            <Stack direction="row" spacing={2} sx={{ flexWrap: 'wrap' }}>
                                <FormControlLabel
                                    control={
                                        <Checkbox
                                            checked={form.accepts_trade ?? false}
                                            onChange={(event) => set('accepts_trade', event.target.checked)}
                                        />
                                    }
                                    label="Aceita troca"
                                />
                                <FormControlLabel
                                    control={
                                        <Checkbox
                                            checked={form.ipva_paid ?? false}
                                            onChange={(event) => set('ipva_paid', event.target.checked)}
                                        />
                                    }
                                    label="IPVA pago"
                                />
                                <FormControlLabel
                                    control={
                                        <Checkbox
                                            checked={form.licensed ?? false}
                                            onChange={(event) => set('licensed', event.target.checked)}
                                        />
                                    }
                                    label="Licenciado"
                                />
                            </Stack>
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
                        <Grid size={12}>
                            <Typography variant="subtitle2" sx={{ mt: 1 }}>
                                Itens de veículo
                            </Typography>
                            <Autocomplete<VehicleAmenity, true>
                                multiple
                                options={amenityOptions}
                                loading={amenityCatalog.isPending}
                                getOptionLabel={(amenity) => amenity.label}
                                value={selectedAmenities}
                                onChange={(_, amenities) =>
                                    set(
                                        'amenity_ids',
                                        amenities.map((amenity) => amenity.id),
                                    )
                                }
                                isOptionEqualToValue={(option, selected) => option.id === selected.id}
                                renderInput={(params) => (
                                    <FormTextField {...params} label="Selecionar itens" margin="dense" />
                                )}
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
