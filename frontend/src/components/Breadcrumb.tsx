import Breadcrumbs from '@mui/material/Breadcrumbs';
import Typography from '@mui/material/Typography';
import { Link as RouterLink } from 'react-router-dom';

export interface BreadcrumbItem {
    label: string;
    to?: string;
}

/** Home > ... > página atual (sem link). Um item só (a própria home) não desenha nada. */
export function Breadcrumb({ items }: { items: BreadcrumbItem[] }) {
    const trail: BreadcrumbItem[] = [{ label: 'Home', to: '/' }, ...items];

    if (trail.length < 2) {
        return null;
    }

    return (
        <Breadcrumbs aria-label="Trilha de navegação" sx={{ mb: 2 }}>
            {trail.map((item, index) =>
                item.to && index < trail.length - 1 ? (
                    <Typography
                        key={item.label}
                        component={RouterLink}
                        to={item.to}
                        variant="body2"
                        sx={{
                            color: 'text.secondary',
                            textDecoration: 'none',
                            '&:hover': { textDecoration: 'underline' },
                        }}
                    >
                        {item.label}
                    </Typography>
                ) : (
                    <Typography key={item.label} variant="body2" color="text.primary" aria-current="page">
                        {item.label}
                    </Typography>
                ),
            )}
        </Breadcrumbs>
    );
}
