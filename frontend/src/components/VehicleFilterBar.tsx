import Autocomplete from '@mui/material/Autocomplete';
import Grid from '@mui/material/Grid';
import TextField from '@mui/material/TextField';
import { useEffect, useState } from 'react';
import type { VehicleFacets, VehicleFilterParams } from '../lib/vehicles';

interface VehicleFilterBarProps {
    value: VehicleFilterParams;
    facets?: VehicleFacets;
    onChange: (value: VehicleFilterParams) => void;
}

/**
 * Empilhados e combináveis -- todo campo aqui é opcional e independente dos outros.
 * Debounce único pro grupo inteiro: digitar em qualquer campo adia a busca real em
 * 400ms, então trocar de campo no meio não dispara uma consulta por tecla.
 */
export function VehicleFilterBar({ value, facets, onChange }: VehicleFilterBarProps) {
    const [draft, setDraft] = useState(value);

    useEffect(() => {
        const timeout = setTimeout(() => onChange(draft), 400);

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [draft]);

    function set<K extends keyof VehicleFilterParams>(field: K, fieldValue: VehicleFilterParams[K]) {
        setDraft((current) => ({ ...current, [field]: fieldValue }));
    }

    function setNumber(field: 'year_min' | 'year_max', raw: string) {
        set(field, raw === '' ? undefined : Number(raw));
    }

    return (
        <Grid container spacing={1} sx={{ mb: 2 }}>
            <Grid size={{ xs: 12, sm: 4 }}>
                <TextField
                    label="Buscar"
                    fullWidth
                    size="small"
                    value={draft.q ?? ''}
                    onChange={(event) => set('q', event.target.value || undefined)}
                    placeholder="marca, modelo, versão..."
                />
            </Grid>
            <Grid size={{ xs: 6, sm: 2 }}>
                <Autocomplete
                    freeSolo
                    options={facets?.brands ?? []}
                    inputValue={draft.brand ?? ''}
                    onInputChange={(_, inputValue) => set('brand', inputValue || undefined)}
                    renderInput={(params) => <TextField {...params} label="Marca" size="small" />}
                />
            </Grid>
            <Grid size={{ xs: 6, sm: 2 }}>
                <Autocomplete
                    freeSolo
                    options={facets?.models ?? []}
                    inputValue={draft.model ?? ''}
                    onInputChange={(_, inputValue) => set('model', inputValue || undefined)}
                    renderInput={(params) => <TextField {...params} label="Modelo" size="small" />}
                />
            </Grid>
            <Grid size={{ xs: 6, sm: 2 }}>
                <TextField
                    label="Ano de"
                    type="number"
                    fullWidth
                    size="small"
                    value={draft.year_min ?? ''}
                    onChange={(event) => setNumber('year_min', event.target.value)}
                />
            </Grid>
            <Grid size={{ xs: 6, sm: 2 }}>
                <TextField
                    label="Ano até"
                    type="number"
                    fullWidth
                    size="small"
                    value={draft.year_max ?? ''}
                    onChange={(event) => setNumber('year_max', event.target.value)}
                />
            </Grid>
            <Grid size={{ xs: 6, sm: 2 }}>
                <TextField
                    label="Preço de"
                    fullWidth
                    size="small"
                    value={draft.price_min ?? ''}
                    onChange={(event) => set('price_min', event.target.value || undefined)}
                    slotProps={{ htmlInput: { inputMode: 'decimal' } }}
                />
            </Grid>
            <Grid size={{ xs: 6, sm: 2 }}>
                <TextField
                    label="Preço até"
                    fullWidth
                    size="small"
                    value={draft.price_max ?? ''}
                    onChange={(event) => set('price_max', event.target.value || undefined)}
                    slotProps={{ htmlInput: { inputMode: 'decimal' } }}
                />
            </Grid>
        </Grid>
    );
}
