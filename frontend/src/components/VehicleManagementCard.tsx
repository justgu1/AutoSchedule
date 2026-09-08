import CalendarTodayIcon from '@mui/icons-material/CalendarToday';
import SpeedIcon from '@mui/icons-material/Speed';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardActionArea from '@mui/material/CardActionArea';
import CardContent from '@mui/material/CardContent';
import CardMedia from '@mui/material/CardMedia';
import Chip from '@mui/material/Chip';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import type { Vehicle, VehicleStatus } from '../lib/vehicles';

const IMAGE_HEIGHT = 176;

const STATUS_LABEL: Record<VehicleStatus, string> = {
    active: 'Ativo',
    trashed: 'Na lixeira',
    deleted: 'Removido',
};

interface VehicleManagementCardProps {
    vehicle: Vehicle;
    onEdit: () => void;
    onGallery: () => void;
    onAvailability: () => void;
    onTrash: () => void;
    onRestore: () => void;
    onPurge: () => void;
    restoring: boolean;
}

/** Mesmo layout visual do card público (`VehicleCard`) -- clicar abre edição em vez de navegar, com as ações de gestão embaixo. */
export function VehicleManagementCard({
    vehicle,
    onEdit,
    onGallery,
    onAvailability,
    onTrash,
    onRestore,
    onPurge,
    restoring,
}: VehicleManagementCardProps) {
    const price = Number(vehicle.price);

    return (
        <Card
            role="group"
            aria-label={`${vehicle.brand} ${vehicle.model}`}
            sx={{ height: '100%', display: 'flex', flexDirection: 'column' }}
        >
            <CardActionArea
                onClick={onEdit}
                aria-label={`Editar ${vehicle.brand} ${vehicle.model}`}
                sx={{ display: 'flex', flexDirection: 'column', alignItems: 'stretch' }}
            >
                {vehicle.photo_url ? (
                    <CardMedia
                        component="img"
                        image={vehicle.photo_url}
                        alt=""
                        sx={{ height: IMAGE_HEIGHT, objectFit: 'cover' }}
                    />
                ) : (
                    <Box
                        sx={{
                            height: IMAGE_HEIGHT,
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            bgcolor: 'grey.200',
                        }}
                    >
                        <Typography color="text.secondary">Sem foto</Typography>
                    </Box>
                )}
                <CardContent sx={{ width: '100%' }}>
                    <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'flex-start' }}>
                        <Typography variant="subtitle1" component="h2" noWrap>
                            {vehicle.brand} {vehicle.model}
                        </Typography>
                        <Chip
                            size="small"
                            label={STATUS_LABEL[vehicle.status]}
                            color={vehicle.status === 'active' ? 'success' : 'default'}
                        />
                    </Stack>
                    <Typography variant="body2" color="text.secondary" noWrap sx={{ minHeight: '1.25em' }}>
                        {vehicle.version ?? ' '}
                    </Typography>

                    <Stack direction="row" spacing={2} sx={{ mt: 1, mb: 1, color: 'text.secondary' }}>
                        <Stack direction="row" spacing={0.5} sx={{ alignItems: 'center' }}>
                            <CalendarTodayIcon sx={{ fontSize: 16 }} />
                            <Typography variant="body2">{vehicle.model_year ?? '--'}</Typography>
                        </Stack>
                        <Stack direction="row" spacing={0.5} sx={{ alignItems: 'center' }}>
                            <SpeedIcon sx={{ fontSize: 16 }} />
                            <Typography variant="body2">
                                {vehicle.mileage_km === null
                                    ? '--'
                                    : `${vehicle.mileage_km.toLocaleString('pt-BR')} km`}
                            </Typography>
                        </Stack>
                    </Stack>

                    <Typography variant="h6">
                        {price.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}
                    </Typography>
                </CardContent>
            </CardActionArea>
            <Stack direction="row" spacing={1} sx={{ p: 1.5, pt: 0, flexWrap: 'wrap' }}>
                <Button size="small" onClick={onGallery}>
                    Galeria
                </Button>
                <Button size="small" onClick={onAvailability}>
                    Disponibilidade
                </Button>
                {vehicle.status === 'active' && (
                    <Button size="small" color="error" onClick={onTrash}>
                        Mover pra lixeira
                    </Button>
                )}
                {vehicle.status === 'trashed' && (
                    <>
                        <Button size="small" disabled={restoring} onClick={onRestore}>
                            Restaurar
                        </Button>
                        <Button size="small" color="error" onClick={onPurge}>
                            Excluir agora
                        </Button>
                    </>
                )}
            </Stack>
        </Card>
    );
}
