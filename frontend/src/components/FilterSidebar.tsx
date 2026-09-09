import FilterListIcon from '@mui/icons-material/FilterList';
import Badge from '@mui/material/Badge';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Drawer from '@mui/material/Drawer';
import Typography from '@mui/material/Typography';
import { useMediaQuery, useTheme } from '@mui/material';
import { useState } from 'react';
import { VehicleFilterBar } from './VehicleFilterBar';
import type { VehicleFacets, VehicleFilterParams } from '../lib/vehicles';

interface FilterSidebarProps {
    value: VehicleFilterParams;
    facets?: VehicleFacets;
    onChange: (value: VehicleFilterParams) => void;
}

function activeFilterCount(value: VehicleFilterParams): number {
    return Object.entries(value).filter(([key, v]) => key !== 'sort' && v !== undefined && v !== '').length;
}

/** Sidebar fixa no desktop, drawer no mobile -- mesmo `VehicleFilterBar` nos dois, só muda o shell. */
export function FilterSidebar({ value, facets, onChange }: FilterSidebarProps) {
    const theme = useTheme();
    const isDesktop = useMediaQuery(theme.breakpoints.up('md'));
    const [drawerOpen, setDrawerOpen] = useState(false);
    const count = activeFilterCount(value);

    const content = (
        <Box sx={{ width: isDesktop ? 280 : 320, p: isDesktop ? 0 : 2 }}>
            {!isDesktop && (
                <Typography variant="h6" component="h2" sx={{ mb: 2 }}>
                    Filtros
                </Typography>
            )}
            <VehicleFilterBar value={value} facets={facets} onChange={onChange} />
            {/* Drawer é modal (some o resultado atrás) -- sem um jeito explícito de fechar, o filtro
                aplicado fica invisível até o usuário adivinhar que precisa fechar o drawer sozinho. */}
            {!isDesktop && (
                <Button fullWidth variant="contained" sx={{ mt: 2 }} onClick={() => setDrawerOpen(false)}>
                    Ver resultados
                </Button>
            )}
        </Box>
    );

    if (isDesktop) {
        return (
            <Box component="aside" aria-label="Filtros" sx={{ flexShrink: 0 }}>
                {content}
            </Box>
        );
    }

    return (
        <>
            {/* `sticky` logo abaixo do AppBar (também sticky) -- mesmo critério, pra não sumir rolando uma lista longa. */}
            <Box
                sx={{
                    position: 'sticky',
                    top: { xs: 56, sm: 64 },
                    zIndex: (theme) => theme.zIndex.appBar - 1,
                    bgcolor: 'grey.50',
                    py: 1,
                    width: '100%',
                }}
            >
                <Badge badgeContent={count} color="primary">
                    {/* `primary.dark` em vez do padrão -- o azul default do MUI (#1976d2) fica em 4.4:1
                        contra o `grey.50` do fundo, abaixo do mínimo 4.5:1 de WCAG 2.1 AA. */}
                    <Button
                        variant="outlined"
                        startIcon={<FilterListIcon />}
                        onClick={() => setDrawerOpen(true)}
                        aria-haspopup="dialog"
                        sx={{ color: 'primary.dark', borderColor: 'primary.dark' }}
                    >
                        Filtros
                    </Button>
                </Badge>
            </Box>
            <Drawer anchor="left" open={drawerOpen} onClose={() => setDrawerOpen(false)}>
                {content}
            </Drawer>
        </>
    );
}
