import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import CircularProgress from '@mui/material/CircularProgress';
import Grid from '@mui/material/Grid';
import MenuItem from '@mui/material/MenuItem';
import Pagination from '@mui/material/Pagination';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { FilterSidebar } from '../components/FilterSidebar';
import { VehicleCard } from '../components/VehicleCard';
import {
    getPublicVehicleFacets,
    listPublicVehicles,
    type VehicleFilterParams,
    type VehicleSort,
} from '../lib/vehicles';

const PER_PAGE = 12;

const SORT_OPTIONS: { value: VehicleSort; label: string }[] = [
    { value: 'price_desc', label: 'Maior preço' },
    { value: 'price_asc', label: 'Menor preço' },
    { value: 'year_desc', label: 'Mais novos' },
    { value: 'created_desc', label: 'Mais recentes' },
    { value: 'created_asc', label: 'Mais antigos' },
];

/** Catálogo público -- mesmo estoque pra todo mundo, dono logado ou não (`GET /vehicles` sem `scope`). */
export function HomePage() {
    const [page, setPage] = useState(1);
    const [filters, setFilters] = useState<VehicleFilterParams>({});

    const vehicles = useQuery({
        queryKey: ['public-vehicles', filters, page],
        queryFn: () => listPublicVehicles(filters, page, PER_PAGE),
    });
    const facets = useQuery({ queryKey: ['public-vehicles', 'filters'], queryFn: () => getPublicVehicleFacets() });

    function updateFilters(value: VehicleFilterParams) {
        setFilters(value);
        setPage(1);
    }

    return (
        <Stack direction={{ xs: 'column', md: 'row' }} spacing={3} sx={{ alignItems: 'flex-start' }}>
            <FilterSidebar value={filters} facets={facets.data} onChange={updateFilters} />

            <Stack spacing={2} sx={{ flex: 1, minWidth: 0, width: '100%' }}>
                <Stack
                    direction={{ xs: 'column', sm: 'row' }}
                    spacing={2}
                    sx={{ alignItems: { sm: 'center' }, justifyContent: 'space-between' }}
                >
                    <Typography variant="h6" component="h1" color="text.secondary">
                        {vehicles.data ? `${vehicles.data.meta.total} veículos` : 'Veículos'}
                    </Typography>
                    <TextField
                        select
                        label="Ordenar por"
                        size="small"
                        sx={{ minWidth: 200 }}
                        value={filters.sort ?? ''}
                        onChange={(event) =>
                            updateFilters({
                                ...filters,
                                sort: (event.target.value || undefined) as VehicleSort | undefined,
                            })
                        }
                    >
                        <MenuItem value="">
                            <em>Relevância</em>
                        </MenuItem>
                        {SORT_OPTIONS.map((option) => (
                            <MenuItem key={option.value} value={option.value}>
                                {option.label}
                            </MenuItem>
                        ))}
                    </TextField>
                </Stack>

                {vehicles.isPending && (
                    <Box sx={{ display: 'flex', justifyContent: 'center', py: 4 }}>
                        <CircularProgress aria-label="Carregando" />
                    </Box>
                )}

                {vehicles.isError && <Alert severity="error">Não foi possível carregar o catálogo.</Alert>}

                {vehicles.data?.data.length === 0 && (
                    <Alert severity="info">Nenhum veículo encontrado -- ajuste os filtros e tente de novo.</Alert>
                )}

                {vehicles.data && vehicles.data.data.length > 0 && (
                    <Grid container spacing={2}>
                        {vehicles.data.data.map((vehicle) => (
                            <Grid key={vehicle.id} size={{ xs: 12, sm: 6, md: 4, lg: 3 }}>
                                <VehicleCard vehicle={vehicle} />
                            </Grid>
                        ))}
                    </Grid>
                )}

                {vehicles.data && vehicles.data.meta.last_page > 1 && (
                    <Stack sx={{ alignItems: 'center' }}>
                        <Pagination
                            page={page}
                            count={vehicles.data.meta.last_page}
                            onChange={(_, value) => setPage(value)}
                        />
                    </Stack>
                )}
            </Stack>
        </Stack>
    );
}
