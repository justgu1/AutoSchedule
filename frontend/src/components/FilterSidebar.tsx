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
            <Badge badgeContent={count} color="primary">
                <Button
                    variant="outlined"
                    startIcon={<FilterListIcon />}
                    onClick={() => setDrawerOpen(true)}
                    aria-haspopup="dialog"
                >
                    Filtros
                </Button>
            </Badge>
            <Drawer anchor="left" open={drawerOpen} onClose={() => setDrawerOpen(false)}>
                {content}
            </Drawer>
        </>
    );
}
