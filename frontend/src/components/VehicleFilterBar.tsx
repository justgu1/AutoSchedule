import Autocomplete from '@mui/material/Autocomplete';
import MenuItem from '@mui/material/MenuItem';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import { useEffect, useState } from 'react';
import { NumericFormat } from 'react-number-format';
import type { VehicleFacets, VehicleFilterParams } from '../lib/vehicles';

interface VehicleFilterBarProps {
    value: VehicleFilterParams;
    facets?: VehicleFacets;
    onChange: (value: VehicleFilterParams) => void;
}

const TRANSMISSION_OPTIONS = [
    { value: 'manual', label: 'Manual' },
    { value: 'automatic', label: 'Automático' },
    { value: 'automated', label: 'Automatizado' },
    { value: 'cvt', label: 'CVT' },
] as const;

const BODY_TYPE_OPTIONS = [
    { value: 'hatch', label: 'Hatch' },
    { value: 'sedan', label: 'Sedã' },
    { value: 'suv', label: 'SUV' },
    { value: 'pickup', label: 'Picape' },
    { value: 'coupe', label: 'Cupê' },
    { value: 'convertible', label: 'Conversível' },
    { value: 'minivan', label: 'Minivan' },
    { value: 'wagon', label: 'Perua' },
] as const;

const FUEL_TYPE_OPTIONS = [
    { value: 'flex', label: 'Flex' },
    { value: 'gasoline', label: 'Gasolina' },
    { value: 'ethanol', label: 'Etanol' },
    { value: 'diesel', label: 'Diesel' },
    { value: 'electric', label: 'Elétrico' },
    { value: 'hybrid', label: 'Híbrido' },
] as const;

/**
 * Empilhados e combináveis -- todo campo aqui é opcional e independente dos outros.
 * Debounce único pro grupo inteiro: digitar em qualquer campo adia a busca real em
 * 400ms, então trocar de campo no meio não dispara uma consulta por tecla.
 */
export function VehicleFilterBar({ value, facets, onChange }: VehicleFilterBarProps) {
    const [draft, setDraft] = useState(value);
    const nextYear = new Date().getFullYear() + 1;

    useEffect(() => {
        const timeout = setTimeout(() => onChange(draft), 400);

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [draft]);

    function set<K extends keyof VehicleFilterParams>(field: K, fieldValue: VehicleFilterParams[K]) {
        setDraft((current) => ({ ...current, [field]: fieldValue }));
    }

    function setNumber(field: 'year_min' | 'year_max' | 'mileage_km_max', raw: string) {
        set(field, raw === '' ? undefined : Number(raw));
    }

    return (
        <Stack spacing={2}>
            <TextField
                label="Buscar"
                fullWidth
                size="small"
                value={draft.q ?? ''}
                onChange={(event) => set('q', event.target.value || undefined)}
                placeholder="marca, modelo, versão..."
            />
            <Autocomplete
                freeSolo
                options={facets?.brands ?? []}
                inputValue={draft.brand ?? ''}
                onInputChange={(_, inputValue) => set('brand', inputValue || undefined)}
                renderInput={(params) => <TextField {...params} label="Marca" size="small" />}
            />
            <Autocomplete
                freeSolo
                options={facets?.models ?? []}
                inputValue={draft.model ?? ''}
                onInputChange={(_, inputValue) => set('model', inputValue || undefined)}
                renderInput={(params) => <TextField {...params} label="Modelo" size="small" />}
            />
            <Stack direction="row" spacing={1}>
                <TextField
                    label="Ano de"
                    type="number"
                    fullWidth
                    size="small"
                    value={draft.year_min ?? ''}
                    onChange={(event) => setNumber('year_min', event.target.value)}
                    slotProps={{ htmlInput: { max: nextYear } }}
                />
                <TextField
                    label="Ano até"
                    type="number"
                    fullWidth
                    size="small"
                    value={draft.year_max ?? ''}
                    onChange={(event) => {
                        const numeric =
                            event.target.value === '' ? undefined : Math.min(Number(event.target.value), nextYear);
                        set('year_max', numeric);
                    }}
                    slotProps={{ htmlInput: { max: nextYear } }}
                />
            </Stack>
            <Stack direction="row" spacing={1}>
                <NumericFormat
                    customInput={TextField}
                    label="Preço de"
                    fullWidth
                    size="small"
                    value={draft.price_min ?? ''}
                    onValueChange={(values) => set('price_min', values.value || undefined)}
                    thousandSeparator="."
                    decimalSeparator=","
                    decimalScale={2}
                    prefix="R$ "
                    valueIsNumericString
                />
                <NumericFormat
                    customInput={TextField}
                    label="Preço até"
                    fullWidth
                    size="small"
                    value={draft.price_max ?? ''}
                    onValueChange={(values) => set('price_max', values.value || undefined)}
                    thousandSeparator="."
                    decimalSeparator=","
                    decimalScale={2}
                    prefix="R$ "
                    valueIsNumericString
                />
            </Stack>
            <TextField
                select
                label="Câmbio"
                size="small"
                fullWidth
                value={draft.transmission ?? ''}
                onChange={(event) =>
                    set('transmission', (event.target.value || undefined) as VehicleFilterParams['transmission'])
                }
            >
                <MenuItem value="">
                    <em>Qualquer</em>
                </MenuItem>
                {TRANSMISSION_OPTIONS.map((option) => (
                    <MenuItem key={option.value} value={option.value}>
                        {option.label}
                    </MenuItem>
                ))}
            </TextField>
            <TextField
                select
                label="Carroceria"
                size="small"
                fullWidth
                value={draft.body_type ?? ''}
                onChange={(event) =>
                    set('body_type', (event.target.value || undefined) as VehicleFilterParams['body_type'])
                }
            >
                <MenuItem value="">
                    <em>Qualquer</em>
                </MenuItem>
                {BODY_TYPE_OPTIONS.map((option) => (
                    <MenuItem key={option.value} value={option.value}>
                        {option.label}
                    </MenuItem>
                ))}
            </TextField>
            <TextField
                select
                label="Combustível"
                size="small"
                fullWidth
                value={draft.fuel_type ?? ''}
                onChange={(event) =>
                    set('fuel_type', (event.target.value || undefined) as VehicleFilterParams['fuel_type'])
                }
            >
                <MenuItem value="">
                    <em>Qualquer</em>
                </MenuItem>
                {FUEL_TYPE_OPTIONS.map((option) => (
                    <MenuItem key={option.value} value={option.value}>
                        {option.label}
                    </MenuItem>
                ))}
            </TextField>
            <TextField
                label="KM até"
                type="number"
                fullWidth
                size="small"
                value={draft.mileage_km_max ?? ''}
                onChange={(event) => setNumber('mileage_km_max', event.target.value)}
            />
        </Stack>
    );
}
