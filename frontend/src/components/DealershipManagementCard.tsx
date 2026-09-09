import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardActionArea from '@mui/material/CardActionArea';
import CardContent from '@mui/material/CardContent';
import CardMedia from '@mui/material/CardMedia';
import Chip from '@mui/material/Chip';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import type { Dealership, DealershipStatus } from '../lib/dealerships';

const IMAGE_HEIGHT = 140;

const STATUS_LABEL: Record<DealershipStatus, string> = {
    active: 'Ativa',
    trashed: 'Na lixeira',
    deleted: 'Removida',
};

interface DealershipManagementCardProps {
    dealership: Dealership;
    onEdit: () => void;
    onPhoto: () => void;
    onAvailability: () => void;
    onTrash: () => void;
    onRestore: () => void;
    onPurge: () => void;
    restoring: boolean;
}

/** Mesmo molde do `VehicleManagementCard` -- grid de cards no lugar de tabela, mais fácil de operar no mobile. */
export function DealershipManagementCard({
    dealership,
    onEdit,
    onPhoto,
    onAvailability,
    onTrash,
    onRestore,
    onPurge,
    restoring,
}: DealershipManagementCardProps) {
    return (
        <Card
            role="group"
            aria-label={dealership.name}
            sx={{ height: '100%', display: 'flex', flexDirection: 'column' }}
        >
            <CardActionArea
                onClick={onEdit}
                aria-label={`Editar ${dealership.name}`}
                sx={{ display: 'flex', flexDirection: 'column', alignItems: 'stretch' }}
            >
                {dealership.photo_url ? (
                    <CardMedia
                        component="img"
                        image={dealership.photo_url}
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
                            {dealership.name}
                        </Typography>
                        <Chip
                            size="small"
                            label={STATUS_LABEL[dealership.status]}
                            color={dealership.status === 'active' ? 'success' : 'default'}
                        />
                    </Stack>
                    <Typography variant="body2" color="text.secondary" noWrap>
                        {dealership.city}/{dealership.state}
                    </Typography>
                </CardContent>
            </CardActionArea>
            <Stack direction="row" spacing={1} sx={{ p: 1.5, pt: 0, flexWrap: 'wrap' }}>
                <Button size="small" onClick={onPhoto}>
                    Foto
                </Button>
                <Button size="small" onClick={onAvailability}>
                    Disponibilidade
                </Button>
                {dealership.status === 'active' && (
                    <Button
                        size="small"
                        component="a"
                        href={`/concessionarias/${dealership.slug}`}
                        target="_blank"
                        rel="noopener"
                    >
                        Ver página pública
                    </Button>
                )}
                {dealership.status === 'active' && (
                    <Button size="small" color="error" onClick={onTrash}>
                        Mover pra lixeira
                    </Button>
                )}
                {dealership.status === 'trashed' && (
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
